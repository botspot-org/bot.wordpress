<?php
/**
 * Bot user-agent classifier.
 *
 * Two-stage classification:
 *   1. Explicit regex → canonical class name map (authoritative source for
 *      counting specific named AI crawlers, e.g. GPTBot, ClaudeBot).
 *   2. Fallback to jaybizzle/crawler-detect — if the library thinks it's
 *      any bot but we have no named match, classify as "unknown_bot".
 *   3. Otherwise: "human".
 *
 * The canonical class names are duplicated in Python at
 * app/services/analytics/constants.py (locus-core). A CI check
 * compares the two lists for drift.
 *
 * @package BotDot_WP
 */

if (!defined('ABSPATH')) {
    exit;
}

class BotDot_WP_Bot_Classifier
{
    /**
     * Canonical class names. Must match BOT_CLASS_NAMES in locus-core.
     * Kept as a class constant to make drift checks trivial.
     */
    const CANONICAL_CLASSES = [
        // OpenAI
        'gptbot',
        'chatgpt_user',
        'oai_searchbot',
        // Anthropic
        'claudebot',
        'claude_web',
        'claude_user',
        'anthropic_ai',
        // Perplexity
        'perplexitybot',
        'perplexity_user',
        // Google
        'googlebot',
        'google_extended',
        // Microsoft
        'bingbot',
        // Others
        'ccbot',
        'bytespider',
        'amazonbot',
        'applebot',
        'meta_externalagent',
        // Fallback / sentinel
        'unknown_bot',
        // Non-bot
        'human',
    ];

    /**
     * Classify a user agent string.
     *
     * @param string $user_agent Raw HTTP_USER_AGENT value.
     * @return string One of CANONICAL_CLASSES.
     */
    public static function classify($user_agent)
    {
        if (empty($user_agent) || !is_string($user_agent)) {
            return 'unknown_bot';
        }

        $named = self::match_named($user_agent);
        if ($named !== null) {
            return $named;
        }

        if (self::is_generic_bot($user_agent)) {
            return 'unknown_bot';
        }

        return 'human';
    }

    /**
     * Return the explicit regex → canonical name map.
     *
     * Ordering matters: more specific patterns must come before more general
     * ones (e.g. `OAI-SearchBot` before `GPTBot`).
     *
     * @return array<string, string> Regex (without delimiters) → canonical name.
     */
    public static function get_pattern_map()
    {
        return [
            // OpenAI
            'OAI-SearchBot'           => 'oai_searchbot',
            'ChatGPT-User'            => 'chatgpt_user',
            'GPTBot'                  => 'gptbot',
            // Anthropic
            'anthropic-ai'            => 'anthropic_ai',
            'Claude-Web'              => 'claude_web',
            'Claude-User'             => 'claude_user',
            'ClaudeBot'               => 'claudebot',
            // Perplexity
            'Perplexity-User'         => 'perplexity_user',
            'PerplexityBot'           => 'perplexitybot',
            // Google
            'Googlebot'               => 'googlebot',
            // Microsoft
            'bingbot'                 => 'bingbot',
            // Others
            'CCBot'                   => 'ccbot',
            'Bytespider'              => 'bytespider',
            'Amazonbot'               => 'amazonbot',
            'Applebot'                => 'applebot',
            'meta-externalagent'      => 'meta_externalagent',
        ];
    }

    /**
     * Return the first canonical name whose regex matches, or null.
     *
     * @param string $user_agent
     * @return string|null
     */
    private static function match_named($user_agent)
    {
        foreach (self::get_pattern_map() as $pattern => $class_name) {
            if (stripos($user_agent, $pattern) !== false) {
                return $class_name;
            }
        }
        return null;
    }

    /**
     * Fallback: ask jaybizzle/crawler-detect whether this is any kind of bot.
     *
     * @param string $user_agent
     * @return bool
     */
    private static function is_generic_bot($user_agent)
    {
        $class = '\\BotSpot\\Vendor\\Jaybizzle\\CrawlerDetect\\CrawlerDetect';
        if (!class_exists($class)) {
            // Library missing — conservative default: not a bot.
            return false;
        }
        $detector = new $class();
        return (bool) $detector->isCrawler($user_agent);
    }
}
