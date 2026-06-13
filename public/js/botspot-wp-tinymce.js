/**
 * TinyMCE plugin for BotSpot Appendix shortcode
 *
 * @package    BotSpot_WP
 * @subpackage BotSpot_WP/public/js
 * @since      0.2.0
 */

(function () {
  tinymce.PluginManager.add("botspot_appendix", function (editor, url) {
    // Add button to toolbar
    editor.addButton("botspot_appendix", {
      title: "Insert BotSpot Appendix",
      icon: "icon dashicons-info",
      onclick: function () {
        editor.insertContent("[botspot_appendix]");
      },
    });

    // Add menu item
    editor.addMenuItem("botspot_appendix", {
      text: "BotSpot Appendix",
      icon: "icon dashicons-info",
      context: "insert",
      onclick: function () {
        editor.insertContent("[botspot_appendix]");
      },
    });
  });
})();
