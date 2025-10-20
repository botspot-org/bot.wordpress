/**
 * TinyMCE plugin for BotDot Appendix shortcode
 *
 * @package    BotDot_WP
 * @subpackage BotDot_WP/public/js
 * @since      0.2.0
 */

(function() {
    tinymce.PluginManager.add('botdot_appendix', function(editor, url) {
        // Add button to toolbar
        editor.addButton('botdot_appendix', {
            title: 'Insert BotDot Appendix',
            icon: 'icon dashicons-info',
            onclick: function() {
                // Open dialog
                editor.windowManager.open({
                    title: 'Insert BotDot Appendix',
                    body: [
                        {
                            type: 'textbox',
                            name: 'title',
                            label: 'Appendix Title',
                            value: 'AI Appendix'
                        },
                        {
                            type: 'listbox',
                            name: 'open',
                            label: 'Open by Default',
                            values: [
                                {text: 'No', value: 'false'},
                                {text: 'Yes', value: 'true'}
                            ],
                            value: 'false'
                        }
                    ],
                    onsubmit: function(e) {
                        // Build shortcode
                        var shortcode = '[botdot_appendix';

                        if (e.data.title && e.data.title !== 'AI Appendix') {
                            shortcode += ' title="' + e.data.title + '"';
                        }

                        if (e.data.open === 'true') {
                            shortcode += ' open="true"';
                        }

                        shortcode += ']';

                        // Insert shortcode at cursor
                        editor.insertContent(shortcode);
                    }
                });
            }
        });

        // Add menu item
        editor.addMenuItem('botdot_appendix', {
            text: 'BotDot Appendix',
            icon: 'icon dashicons-info',
            context: 'insert',
            onclick: function() {
                editor.execCommand('mcebot_appendix');
            }
        });
    });
})();
