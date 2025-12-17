# Theme Integration Guide

## Inheriting Your Theme's FAQ/Accordion Styles

BotSpot WP automatically detects popular themes and inherits their FAQ/accordion classes. If you're using a custom theme or want to customize the styling, here are your options:

### Option 1: Automatic Detection (Built-in)

The plugin automatically detects these themes and applies their classes:
- **Divi** - Uses `et_pb_toggle` classes
- **Avada** - Uses `fusion-accordian` classes
- **Astra** - Uses `ast-accordion` classes
- **GeneratePress** - Uses `accordion-container` classes
- **OceanWP** - Uses `oceanwp-accordion` classes

### Option 2: Custom Theme Filter

Add this to your theme's `functions.php`:

```php
add_filter('botdot_wp_theme_classes', function($classes, $theme_name, $theme_template) {
    // Replace with your theme's FAQ/accordion classes
    return array(
        'wrapper' => 'my-faq-wrapper',
        'details' => 'my-faq-item',
        'summary' => 'my-faq-header',
        'title' => 'my-faq-title',
        'content' => 'my-faq-body',
    );
}, 10, 3);
```

### Option 3: Find Your Theme's Classes

1. Go to a page on your site that has an FAQ or accordion
2. Right-click the FAQ element and select "Inspect"
3. Look for the class names in the HTML
4. Use those classes in the filter above

### Option 4: Disable Theme Classes

If you prefer the default BotSpot styling:

```php
add_filter('botdot_wp_appendix_args', function($args) {
    $args['use_theme_classes'] = false;
    return $args;
});
```

## CSS Customization

The appendix uses CSS custom properties that inherit from WordPress theme.json:

```css
/* Override in your theme's custom CSS */
.botdot-appendix-details {
    --wp--preset--color--primary: #your-color;
    --wp--preset--color--border: #border-color;
}
```

## Example: Complete Custom Styling

```php
// functions.php
add_filter('botdot_wp_appendix_html', function($html, $data, $args) {
    // Completely replace the HTML structure
    ob_start();
    ?>
    <div class="my-custom-accordion">
        <button class="my-accordion-toggle"><?php echo esc_html($args['title']); ?></button>
        <div class="my-accordion-content" style="display: none;">
            <?php
            // Your custom rendering logic
            print_r($data);
            ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}, 10, 3);
```

## Elementor Integration

For Elementor users, add this widget:

```php
add_action('elementor/widgets/widgets_registered', function($widgets_manager) {
    // Register custom Elementor widget
    // See Elementor docs for full implementation
});
```

## Testing Theme Inheritance

1. Enable Debug Mode in BotSpot WP settings
2. View page source and look for `<!-- BotSpot WP Appendix Start -->`
3. Check the classes applied to the appendix elements
4. Compare with your theme's FAQ classes

## Common Issues

**Q: The appendix still looks different from my theme's FAQs**
- Check if your theme uses JavaScript for accordion behavior
- You may need to enqueue your theme's FAQ scripts

**Q: How do I match my theme's colors?**
- Use browser DevTools to inspect your theme's FAQ colors
- Override using the CSS custom properties above

**Q: The accordion doesn't animate like my theme**
- Add theme-specific JavaScript or use the filter to inject custom behavior

## Support

For theme-specific integration help:
1. Check your theme's documentation for FAQ/accordion structure
2. Contact theme support for CSS class names
3. Use browser DevTools to inspect existing FAQ elements
