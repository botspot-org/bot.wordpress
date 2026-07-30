<?php
/**
 * Tests for Bspt_Sync::extract_main_content().
 *
 * @since 3.5.15
 */

use PHPUnit\Framework\TestCase;

class MainContentExtractionTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../includes/class-bspt-sync.php';
    }

    /**
     * Invoke the private static extractor.
     *
     * @param string $html
     * @return string
     */
    private function extract($html)
    {
        $ref = new ReflectionMethod('Bspt_Sync', 'extract_main_content');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        return $ref->invoke(null, $html);
    }

    /**
     * A realistic theme page: site chrome outside the container, an
     * entry-header holding the H1 inside it, and several sibling blocks after
     * the first closing tag.
     *
     * @return string
     */
    private function theme_page()
    {
        // Deliberately NO <main> and NO <article>. Those two selectors capture
        // up to </main> / </article>, and since a page has exactly one of each
        // the lazy regex happens to land correctly. The truncation bug lives in
        // the id= and class= selectors, whose capture terminates at the first
        // closing tag of ANY kind — which is the shape most classic themes emit.
        //
        // The outer wrapper is class="entry-content", not id="content": #content
        // and .site-content are page-level wrappers that can also contain the
        // widget sidebar (see test_sidebar_aside_inside_page_level_wrapper_is_removed),
        // so <aside> is stripped there. .entry-content bounds the article itself,
        // so the in-content pull quote below is expected to survive here.
        return '<!DOCTYPE html><html><head><title>Roasting</title>'
            . '<style>.x{color:red}</style></head><body>'
            . '<header class="site-header"><nav><a href="/">Home</a> Site navigation chrome</nav></header>'
            . '<div class="entry-content">'
            . '<div class="post">'
            . '<header class="entry-header"><h1>How We Roast Our Coffee</h1></header>'
            . '<p>First paragraph about the drum roaster and its thermal mass.</p>'
            . '<div class="wp-block-group">'
            . '<h2>Second stage</h2>'
            . '<p>Second paragraph about development time after first crack.</p>'
            . '</div>'
            . '<aside class="pullquote">A pull quote that belongs to the article body.</aside>'
            . '<!-- wp-rocket cache footprint marker -->'
            . '<p>Third paragraph about resting the beans before grinding.</p>'
            . '</div>'
            . '</div>'
            . '<footer class="site-footer">Copyright and footer navigation goes here.</footer>'
            . '<script>window.analytics = 1;</script>'
            . '</body></html>';
    }

    /**
     * The defect: the lazy regex capture stopped at the first closing tag, so
     * everything after the first paragraph was silently dropped.
     */
    public function test_content_after_the_first_closing_tag_survives()
    {
        $result = $this->extract($this->theme_page());

        $this->assertStringContainsString('First paragraph about the drum roaster', $result);
        $this->assertStringContainsString('Second paragraph about development time', $result);
        $this->assertStringContainsString('Third paragraph about resting the beans', $result);
    }

    /**
     * The in-content <header class="entry-header"> holds the H1. Blanket
     * <header> stripping deleted it.
     */
    public function test_in_content_h1_survives()
    {
        $result = $this->extract($this->theme_page());

        $this->assertStringContainsString('How We Roast Our Coffee', $result);
    }

    /**
     * <aside> inside the content container is a pull quote or callout, not site
     * chrome. Only a page-level <aside> is boilerplate, and by then we have
     * already selected a container.
     */
    public function test_in_content_aside_survives()
    {
        $result = $this->extract($this->theme_page());

        $this->assertStringContainsString('A pull quote that belongs to the article body', $result);
    }

    /**
     * The regex implementation stripped comments before matching. DOM traversal
     * serialises DOMComment children verbatim unless they are removed, so
     * caching-plugin footprints and minifier banners would reach the embedder.
     */
    public function test_html_comments_are_removed()
    {
        $result = $this->extract($this->theme_page());

        $this->assertStringNotContainsString('wp-rocket cache footprint', $result);
        $this->assertStringNotContainsString('<!--', $result);
    }

    public function test_site_chrome_is_excluded()
    {
        $result = $this->extract($this->theme_page());

        $this->assertStringNotContainsString('Site navigation chrome', $result);
        $this->assertStringNotContainsString('footer navigation goes here', $result);
        $this->assertStringNotContainsString('window.analytics', $result);
    }

    /**
     * A nav rendered inside the content container (breadcrumbs, related-post
     * lists) is still boilerplate and must go, even though the container was
     * selected.
     */
    public function test_nav_inside_the_container_is_removed()
    {
        $html = '<html><body><main>'
            . '<nav class="breadcrumbs">You are here: Home / Blog / Roasting</nav>'
            . '<p>The body copy of the article continues for several sentences here.</p>'
            . '</main></body></html>';

        $result = $this->extract($html);

        $this->assertStringNotContainsString('You are here', $result);
        $this->assertStringContainsString('The body copy of the article', $result);
    }

    /**
     * No recognised container: fall back to the body with the full strip,
     * page-level header included.
     */
    public function test_falls_back_to_body_when_no_container_matches()
    {
        $html = '<html><body>'
            . '<header>Site header chrome that should not be ingested</header>'
            . '<div class="mystery-theme-wrapper">'
            . '<p>The only real prose on this page lives in an unrecognised wrapper element.</p>'
            . '</div></body></html>';

        $result = $this->extract($html);

        $this->assertStringContainsString('unrecognised wrapper element', $result);
        $this->assertStringNotContainsString('Site header chrome', $result);
    }

    /**
     * A container that matches the selector but holds almost nothing must not
     * win over a later selector that has the real content.
     */
    public function test_empty_container_does_not_shadow_a_real_one()
    {
        $html = '<html><body>'
            . '<main></main>'
            . '<div class="entry-content"><p>' . str_repeat('Real prose. ', 20) . '</p></div>'
            . '</body></html>';

        $result = $this->extract($html);

        $this->assertStringContainsString('Real prose.', $result);
    }

    /**
     * UTF-8 must survive the DOM round-trip. loadHTML() assumes Latin-1 unless
     * told otherwise, which mangles any non-ASCII page.
     */
    public function test_utf8_is_preserved()
    {
        $html = '<html><head><meta charset="utf-8"></head><body><main>'
            . '<p>Café crème, jalapeño, and a piñata — em dash included.</p>'
            . '</main></body></html>';

        $result = $this->extract($html);

        $this->assertStringContainsString('Café crème', $result);
        $this->assertStringContainsString('jalapeño', $result);
        $this->assertStringContainsString('—', $result);
    }

    /**
     * Malformed markup is the norm in the wild. It must not fatal or emit
     * warnings (libxml errors must be suppressed and cleared).
     */
    public function test_malformed_html_does_not_error()
    {
        $html = '<html><body><main><p>Unclosed paragraph with prose in it'
            . '<div><span>and a stray nesting error</main></body>';

        $result = $this->extract($html);

        $this->assertStringContainsString('Unclosed paragraph', $result);
    }

    /**
     * A classic theme with no <main>/<article> wraps the article AND the
     * widget sidebar in the same <div id="content"> page-level container.
     * <aside> there is the sidebar, not a pull quote, and must be stripped —
     * the pre-DOM regex code stripped every <aside> document-wide, so this is
     * a regression guard for the #content candidate specifically.
     */
    public function test_sidebar_aside_inside_page_level_wrapper_is_removed()
    {
        $html = '<html><body>'
            . '<div id="content">'
            . '<p>The article body copy continues for several sentences about roasting.</p>'
            . '<aside id="secondary">Recent posts, tag cloud, and a newsletter signup form.</aside>'
            . '</div>'
            . '</body></html>';

        $result = $this->extract($html);

        $this->assertStringContainsString('The article body copy continues', $result);
        $this->assertStringNotContainsString('Recent posts, tag cloud', $result);
    }

    /**
     * Same page-level-wrapper shape, .site-content flavour.
     */
    public function test_sidebar_aside_inside_site_content_wrapper_is_removed()
    {
        $html = '<html><body>'
            . '<div class="site-content">'
            . '<p>The article body copy continues for several sentences about roasting.</p>'
            . '<aside class="widget-area">Recent posts, tag cloud, and a newsletter signup form.</aside>'
            . '</div>'
            . '</body></html>';

        $result = $this->extract($html);

        $this->assertStringContainsString('The article body copy continues', $result);
        $this->assertStringNotContainsString('Recent posts, tag cloud', $result);
    }

    /**
     * An <aside> inside .entry-content is still a pull quote, not a sidebar.
     * Regression guard so the #content/.site-content narrowing does not
     * spread to the entry-content-class candidate.
     */
    public function test_aside_inside_entry_content_class_still_survives()
    {
        $html = '<html><body>'
            . '<div class="entry-content">'
            . '<p>The article body copy continues for several sentences about roasting.</p>'
            . '<aside class="pullquote">A pull quote that belongs to the article body.</aside>'
            . '</div>'
            . '</body></html>';

        $result = $this->extract($html);

        $this->assertStringContainsString('A pull quote that belongs to the article body', $result);
    }

    /**
     * DOMDocument always exists under PHPUnit, so no test above can reach
     * extract_main_content_fallback(). Call it directly via reflection to
     * cover the path that runs on PHP builds without ext-dom.
     */
    public function test_fallback_strips_boilerplate_and_returns_body()
    {
        $html = '<html><body>'
            . '<header>Site header chrome</header>'
            . '<p>The only real prose on this fallback-parsed page.</p>'
            . '<aside>Sidebar widget text</aside>'
            . '<footer>Footer chrome</footer>'
            . '</body></html>';

        $ref = new ReflectionMethod('Bspt_Sync', 'extract_main_content_fallback');
        if (PHP_VERSION_ID < 80100) {
            $ref->setAccessible(true);
        }
        $result = $ref->invoke(null, $html);

        $this->assertStringContainsString('The only real prose on this fallback-parsed page', $result);
        $this->assertStringNotContainsString('Site header chrome', $result);
        $this->assertStringNotContainsString('Sidebar widget text', $result);
        $this->assertStringNotContainsString('Footer chrome', $result);
    }
}
