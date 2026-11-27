<?php
/**
 * WordPress stubs and fakes for the SiteSync*Test files.
 *
 * PHPUnit loads every test file into one process, in name order, before it
 * runs a test, so the first definition of a function wins. Each stub here is
 * guarded, and a stub that another test file also defines behaves the same
 * once a test sets its globals.
 */

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response
    {
        private $data;
        private $status;

        public function __construct($data = null, $status = 200)
        {
            $this->data = $data;
            $this->status = $status;
        }

        public function get_data()
        {
            return $this->data;
        }

        public function get_status()
        {
            return $this->status;
        }
    }
}

class Bspt_Test_Rest_Request
{
    private $headers;
    private $body;

    public function __construct(array $headers, $body)
    {
        $this->headers = $headers;
        $this->body = $body;
    }

    public function get_header($name)
    {
        return isset($this->headers[$name]) ? $this->headers[$name] : null;
    }

    public function get_body()
    {
        return $this->body;
    }
}

/**
 * A request signed as core signs it, stamped with the current time.
 */
function bspt_test_signed_request($body, $secret = 'whsec_test')
{
    $timestamp = gmdate('Y-m-d\TH:i:s\Z');
    return new Bspt_Test_Rest_Request([
        'X-Webhook-Timestamp' => $timestamp,
        'X-Webhook-Signature-V2' => 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret),
    ], $body);
}

function bspt_test_fixture($name, $raw = false)
{
    $contents = file_get_contents(__DIR__ . '/fixtures/site_sync/wordpress/' . $name . '.json');
    return $raw ? $contents : json_decode($contents, true);
}

class Bspt_Test_Wpdb
{
    public $posts = 'wp_posts';
    public $prepared = [];
    public $ids = [];

    public function prepare($query, $args)
    {
        $this->prepared[] = [$query, $args];
        return $query;
    }

    public function get_col($query)
    {
        return $this->ids;
    }
}

class Bspt_Test_Yoast_Meta_Surface
{
    public function for_post($post_id)
    {
        if (!isset($GLOBALS['bspt_test_yoast_schema'][$post_id])) {
            return false;
        }
        return (object) ['schema' => $GLOBALS['bspt_test_yoast_schema'][$post_id]];
    }
}

if (!function_exists('YoastSEO')) {
    function YoastSEO()
    {
        return (object) ['meta' => new Bspt_Test_Yoast_Meta_Surface()];
    }
}

// Same body as WordPressRegistrationTest.php.
if (!function_exists('rest_url')) {
    function rest_url($path = '')
    {
        return 'https://example.com/wp-json/' . ltrim($path, '/');
    }
}

// SettingsSiteScopeTest.php and WordPressRegistrationTest.php read the same global.
if (!function_exists('home_url')) {
    function home_url($path = '')
    {
        $base = isset($GLOBALS['bspt_test_home_url'])
            ? $GLOBALS['bspt_test_home_url']
            : 'https://example.com';
        return $base . $path;
    }
}

// Same body as SettingsSiteScopeTest.php and SyncEligibilityTest.php.
if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url, $component = -1)
    {
        return $component === -1 ? parse_url($url) : parse_url($url, $component);
    }
}

// Same bodies as ContentSourceTelemetryTest.php.
if (!function_exists('has_filter')) {
    function has_filter($tag, $callback = false)
    {
        return false;
    }
}
if (!function_exists('get_locale')) {
    function get_locale()
    {
        return 'en_US';
    }
}

if (!function_exists('register_rest_route')) {
    function register_rest_route($namespace, $route, $args = [])
    {
        $GLOBALS['bspt_test_routes'][] = [$namespace, $route, $args];
        return true;
    }
}
if (!function_exists('is_post_type_viewable')) {
    function is_post_type_viewable($post_type)
    {
        $viewable = isset($GLOBALS['bspt_test_viewable_types'])
            ? $GLOBALS['bspt_test_viewable_types']
            : ['post', 'page', 'product'];
        return in_array($post_type, $viewable, true);
    }
}
if (!function_exists('url_to_postid')) {
    function url_to_postid($url)
    {
        return isset($GLOBALS['bspt_test_url_ids'][$url]) ? $GLOBALS['bspt_test_url_ids'][$url] : 0;
    }
}
if (!function_exists('get_post_thumbnail_id')) {
    function get_post_thumbnail_id($post = null)
    {
        $id = is_object($post) ? $post->ID : (int) $post;
        return isset($GLOBALS['bspt_test_thumbnails'][$id]) ? $GLOBALS['bspt_test_thumbnails'][$id] : 0;
    }
}
if (!function_exists('wp_get_attachment_image_src')) {
    function wp_get_attachment_image_src($attachment_id, $size = 'thumbnail')
    {
        return isset($GLOBALS['bspt_test_images'][$attachment_id]) ? $GLOBALS['bspt_test_images'][$attachment_id] : false;
    }
}
if (!function_exists('get_post_mime_type')) {
    function get_post_mime_type($post = null)
    {
        return isset($GLOBALS['bspt_test_mime_types'][$post]) ? $GLOBALS['bspt_test_mime_types'][$post] : false;
    }
}
if (!function_exists('get_the_terms')) {
    function get_the_terms($post, $taxonomy)
    {
        $id = is_object($post) ? $post->ID : (int) $post;
        $key = $id . ':' . $taxonomy;
        if (empty($GLOBALS['bspt_test_terms'][$key])) {
            return false;
        }
        return array_map(function ($name) {
            return (object) ['name' => $name];
        }, $GLOBALS['bspt_test_terms'][$key]);
    }
}
if (!function_exists('get_userdata')) {
    function get_userdata($user_id)
    {
        if (!isset($GLOBALS['bspt_test_users'][$user_id])) {
            return false;
        }
        return (object) ['display_name' => $GLOBALS['bspt_test_users'][$user_id]];
    }
}

// Polylang. With the globals unset they answer as a site without Polylang.
if (!function_exists('pll_languages_list')) {
    function pll_languages_list($args = [])
    {
        return array_keys(isset($GLOBALS['bspt_test_pll_homes']) ? $GLOBALS['bspt_test_pll_homes'] : []);
    }
}
if (!function_exists('pll_home_url')) {
    function pll_home_url($lang = '')
    {
        return isset($GLOBALS['bspt_test_pll_homes'][$lang]) ? $GLOBALS['bspt_test_pll_homes'][$lang] : home_url('/');
    }
}
if (!function_exists('pll_get_post_language')) {
    function pll_get_post_language($post_id, $field = 'slug')
    {
        return isset($GLOBALS['bspt_test_pll_post_languages'][$post_id])
            ? $GLOBALS['bspt_test_pll_post_languages'][$post_id]
            : false;
    }
}
