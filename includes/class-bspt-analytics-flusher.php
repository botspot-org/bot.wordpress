<?php
/**
 * Analytics flusher — state machine + wp-cron handler.
 *
 * See docs/superpowers/specs/2026-04-10-wp-analytics-design.md
 * (sections "Three independent flows" and "Failure modes").
 *
 * Post meta keys used:
 *   _bspt_impressions_pending          (int, JSON blob with 'total' and 'by_class' and 'first_hit_at')
 *   _bspt_impressions_inflight         (same shape as pending, but owned by a specific batch_id)
 *   _bspt_impressions_inflight_batch   (string UUID — which batch owns the inflight)
 *   _bspt_impressions_inflight_at      (int — unix timestamp when inflight was created)
 *
 * Options used:
 *   bspt_last_flush_at  (int unix timestamp)
 *   bspt_last_flush_id  (string UUID)
 *
 * Transients used:
 *   bspt_flush_lock       (single-flight lock, 10-minute TTL)
 *
 * @package Bspt
 */

if (!defined('ABSPATH')) {
    exit;
}

class Bspt_Analytics_Flusher
{
    const MAX_ITEMS_PER_BATCH = 1000;
    const LOCK_TRANSIENT = 'bspt_flush_lock';
    const LOCK_TTL = 600;  // 10 minutes
    const ORPHAN_THRESHOLD = 7200;  // 2 hours — inflight older than this is considered orphaned
    const OPPORTUNISTIC_THRESHOLD = 7200;  // 2 hours — opportunistic flush threshold

    const META_PENDING = '_bspt_impressions_pending';
    const META_INFLIGHT = '_bspt_impressions_inflight';
    const META_INFLIGHT_BATCH = '_bspt_impressions_inflight_batch';
    const META_INFLIGHT_AT = '_bspt_impressions_inflight_at';

    const OPTION_LAST_FLUSH_AT = 'bspt_last_flush_at';
    const OPTION_LAST_FLUSH_ID = 'bspt_last_flush_id';

    /**
     * Increment the per-post pending counter. Called from the injector hot path.
     *
     * This is non-atomic read-modify-write — concurrent visitors can lose hits.
     * Known limitation, documented in the spec (Non-atomic counter increment).
     *
     * @param int    $post_id
     * @param string $bot_class  One of Bspt_Bot_Classifier::CANONICAL_CLASSES
     */
    public static function increment_post($post_id, $bot_class)
    {
        if (empty($post_id) || empty($bot_class)) {
            return;
        }

        $raw = get_post_meta($post_id, self::META_PENDING, true);
        $pending = is_array($raw) ? $raw : ['total' => 0, 'by_class' => [], 'first_hit_at' => 0];

        $pending['total'] = (int) ($pending['total'] ?? 0) + 1;
        $pending['by_class'][$bot_class] = (int) ($pending['by_class'][$bot_class] ?? 0) + 1;
        if (empty($pending['first_hit_at'])) {
            $pending['first_hit_at'] = time();
        }

        update_post_meta($post_id, self::META_PENDING, $pending);
    }

    /**
     * Should we opportunistically flush? Called on Analytics tab open.
     *
     * @return bool
     */
    public static function should_opportunistic_flush()
    {
        $last = (int) get_option(self::OPTION_LAST_FLUSH_AT, 0);
        return (time() - $last) > self::OPPORTUNISTIC_THRESHOLD;
    }

    /**
     * Main flush entry point — wp-cron handler + forced flush.
     *
     * @param bool $force  If true, skip the opportunistic-threshold check.
     * @return array       Status summary.
     */
    public static function flush($force = false)
    {
        // Single-flight lock
        if (false === get_transient(self::LOCK_TRANSIENT)) {
            set_transient(self::LOCK_TRANSIENT, 1, self::LOCK_TTL);
        } else {
            return ['status' => 'locked'];
        }

        try {
            return self::do_flush();
        } catch (Throwable $e) {
            Bspt_Logger::log_error('Analytics flush failed: ' . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        } finally {
            delete_transient(self::LOCK_TRANSIENT);
        }
    }

    /**
     * Main flush implementation. Orphan recovery → move pending to inflight →
     * POST batch → clear inflight (on 200) or merge back (on failure).
     */
    private static function do_flush()
    {
        self::recover_orphans();
        $batch_id = self::uuid_v4();
        $items = self::move_pending_to_inflight($batch_id);

        if (empty($items)) {
            return ['status' => 'empty', 'batch_id' => $batch_id];
        }

        $ok = self::send_batch($batch_id, $items);

        if ($ok) {
            self::clear_inflight($batch_id);
            update_option(self::OPTION_LAST_FLUSH_AT, time());
            update_option(self::OPTION_LAST_FLUSH_ID, $batch_id);
            return ['status' => 'ok', 'batch_id' => $batch_id, 'item_count' => count($items)];
        }

        self::merge_inflight_to_pending($batch_id);
        return ['status' => 'retry', 'batch_id' => $batch_id, 'item_count' => count($items)];
    }

    /**
     * Recover orphan inflight rows (inflight older than ORPHAN_THRESHOLD with
     * no corresponding batch_id success). These are from crashed flush runs.
     */
    private static function recover_orphans()
    {
        global $wpdb;
        $threshold = time() - self::ORPHAN_THRESHOLD;
        $sql = $wpdb->prepare(
            "SELECT post_id, meta_value FROM {$wpdb->postmeta}
             WHERE meta_key = %s
             AND CAST(meta_value AS UNSIGNED) < %d",
            self::META_INFLIGHT_AT,
            $threshold
        );
        $rows = $wpdb->get_results($sql);
        foreach ($rows as $row) {
            $post_id = (int) $row->post_id;
            // Rebuild orphan's batch_id and merge back into pending
            $batch_id = get_post_meta($post_id, self::META_INFLIGHT_BATCH, true);
            if (!empty($batch_id)) {
                self::merge_post_inflight_to_pending($post_id);
            }
        }
    }

    /**
     * Atomically move per-post pending counters into inflight. Returns the
     * batch payload items list.
     *
     * @param string $batch_id
     * @return array Items suitable for the POST body.
     */
    private static function move_pending_to_inflight($batch_id)
    {
        global $wpdb;

        // Find all posts with non-empty pending counters.
        $post_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s LIMIT %d",
            self::META_PENDING,
            self::MAX_ITEMS_PER_BATCH
        ));

        $items = [];
        $now = time();

        foreach ($post_ids as $post_id) {
            $post_id = (int) $post_id;
            $pending = get_post_meta($post_id, self::META_PENDING, true);
            if (!is_array($pending) || empty($pending['total'])) {
                continue;
            }

            $artifact_id = get_post_meta($post_id, '_bspt_artifact_id', true);
            if (empty($artifact_id)) {
                // Not synced yet — leave pending in place, skip for now.
                continue;
            }

            // Promote to inflight
            update_post_meta($post_id, self::META_INFLIGHT, $pending);
            update_post_meta($post_id, self::META_INFLIGHT_BATCH, $batch_id);
            update_post_meta($post_id, self::META_INFLIGHT_AT, $now);
            delete_post_meta($post_id, self::META_PENDING);

            $items[] = [
                'artifact_id'  => $artifact_id,
                'total'        => (int) $pending['total'],
                'by_bot_class' => (array) ($pending['by_class'] ?? []),
                'first_hit_at' => gmdate('c', (int) ($pending['first_hit_at'] ?? $now)),
            ];
        }

        return $items;
    }

    /**
     * POST the batch to locus-core. Returns true on 200 accepted/duplicate.
     *
     * @param string $batch_id
     * @param array  $items
     * @return bool
     */
    private static function send_batch($batch_id, $items)
    {
        $api_url = Bspt_Options::get('api_url', '');
        $api_key = Bspt_Options::get('api_key', '');
        if (empty($api_url) || empty($api_key)) {
            return false;
        }

        $url = rtrim($api_url, '/') . '/api/v1/analytics/impressions/batch';
        $body = [
            'batch_id'   => $batch_id,
            'flushed_at' => gmdate('c'),
            'items'      => $items,
        ];

        $response = wp_remote_post($url, [
            'method'  => 'POST',
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-Key'    => $api_key,
            ],
            'body'    => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            Bspt_Logger::log_error('Analytics batch POST failed: ' . $response->get_error_message());
            return false;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            Bspt_Logger::log_error("Analytics batch POST got HTTP $code");
            return false;
        }

        return true;
    }

    /**
     * Clear inflight rows for a successfully-posted batch.
     *
     * @param string $batch_id
     */
    private static function clear_inflight($batch_id)
    {
        global $wpdb;
        $post_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s",
            self::META_INFLIGHT_BATCH,
            $batch_id
        ));
        foreach ($post_ids as $post_id) {
            delete_post_meta((int) $post_id, self::META_INFLIGHT);
            delete_post_meta((int) $post_id, self::META_INFLIGHT_BATCH);
            delete_post_meta((int) $post_id, self::META_INFLIGHT_AT);
        }
    }

    /**
     * Merge inflight back into pending (batch POST failed or was orphaned).
     *
     * @param string $batch_id
     */
    private static function merge_inflight_to_pending($batch_id)
    {
        global $wpdb;
        $post_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s",
            self::META_INFLIGHT_BATCH,
            $batch_id
        ));
        foreach ($post_ids as $post_id) {
            self::merge_post_inflight_to_pending((int) $post_id);
        }
    }

    /**
     * Merge one post's inflight into its pending (or promote if no pending).
     *
     * @param int $post_id
     */
    private static function merge_post_inflight_to_pending($post_id)
    {
        $inflight = get_post_meta($post_id, self::META_INFLIGHT, true);
        if (!is_array($inflight)) {
            delete_post_meta($post_id, self::META_INFLIGHT);
            delete_post_meta($post_id, self::META_INFLIGHT_BATCH);
            delete_post_meta($post_id, self::META_INFLIGHT_AT);
            return;
        }

        $pending = get_post_meta($post_id, self::META_PENDING, true);
        $pending = is_array($pending) ? $pending : ['total' => 0, 'by_class' => [], 'first_hit_at' => 0];

        $pending['total'] = (int) ($pending['total'] ?? 0) + (int) ($inflight['total'] ?? 0);
        foreach ((array) ($inflight['by_class'] ?? []) as $cls => $n) {
            $pending['by_class'][$cls] = (int) ($pending['by_class'][$cls] ?? 0) + (int) $n;
        }
        if (empty($pending['first_hit_at']) || (!empty($inflight['first_hit_at']) && $inflight['first_hit_at'] < $pending['first_hit_at'])) {
            $pending['first_hit_at'] = (int) $inflight['first_hit_at'];
        }

        update_post_meta($post_id, self::META_PENDING, $pending);
        delete_post_meta($post_id, self::META_INFLIGHT);
        delete_post_meta($post_id, self::META_INFLIGHT_BATCH);
        delete_post_meta($post_id, self::META_INFLIGHT_AT);
    }

    /**
     * Generate a v4 UUID.
     *
     * @return string
     */
    private static function uuid_v4()
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
