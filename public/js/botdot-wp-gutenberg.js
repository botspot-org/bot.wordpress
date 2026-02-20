/**
 * Gutenberg block for BotDot Appendix
 *
 * @package    BotDot_WP
 * @subpackage BotDot_WP/public/js
 * @since      0.2.0
 */

(function (wp) {
  var registerBlockType = wp.blocks.registerBlockType;
  var createElement = wp.element.createElement;

  registerBlockType("botdot-wp/appendix", {
    title: "BotDot Appendix",
    description: "Insert AI-discoverable appendix content",
    icon: "info",
    category: "common",
    attributes: {},

    edit: function (props) {
      return createElement(
        "div",
        {
          className: "botdot-wp-appendix-placeholder",
          style: {
            padding: "20px",
            backgroundColor: "#f0f0f1",
            border: "1px dashed #8c8f94",
            borderRadius: "2px",
            textAlign: "center",
          },
        },
        createElement(
          "p",
          {
            style: {
              margin: 0,
              fontSize: "14px",
              color: "#1e1e1e",
            },
          },
          "BotDot Appendix",
        ),
      );
    },

    save: function () {
      // Server-side rendering, so return null
      return null;
    },
  });
})(window.wp);
