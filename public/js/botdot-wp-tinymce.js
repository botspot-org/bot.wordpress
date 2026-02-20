/**
 * TinyMCE plugin for BotDot Appendix shortcode
 *
 * @package    BotDot_WP
 * @subpackage BotDot_WP/public/js
 * @since      0.2.0
 */

(function () {
  tinymce.PluginManager.add("botdot_appendix", function (editor, url) {
    // Add button to toolbar
    editor.addButton("botdot_appendix", {
      title: "Insert BotDot Appendix",
      icon: "icon dashicons-info",
      onclick: function () {
        editor.insertContent("[botdot_appendix]");
      },
    });

    // Add menu item
    editor.addMenuItem("botdot_appendix", {
      text: "BotDot Appendix",
      icon: "icon dashicons-info",
      context: "insert",
      onclick: function () {
        editor.insertContent("[botdot_appendix]");
      },
    });
  });
})();
