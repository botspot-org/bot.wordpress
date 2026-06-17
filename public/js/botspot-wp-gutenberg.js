/**
 * Gutenberg block for BotSpot Appendix
 *
 * @package    BotSpot_WP
 * @subpackage BotSpot_WP/public/js
 * @since      0.2.0
 */

(function (wp) {
  var registerBlockType = wp.blocks.registerBlockType;
  var createElement = wp.element.createElement;

  var blockConfig = {
    title: "BotSpot Appendix",
    description: "Insert AI-discoverable appendix content from bot.spot",
    icon: createElement(
      "svg",
      { viewBox: "0 0 24 24", fill: "none", xmlns: "http://www.w3.org/2000/svg" },
      createElement("path", {
        d: "M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 .88-.36 1.68-.93 2.25z",
        fill: "currentColor"
      })
    ),
    category: "widgets",
    keywords: ["botspot", "appendix", "ai", "faq", "content"],
    attributes: {},

    edit: function () {
      return createElement(
        "div",
        {
          className: "botspot-wp-appendix-block",
          style: {
            padding: "24px",
            background: "linear-gradient(135deg, #1a1a2e 0%, #16213e 100%)",
            border: "1px solid rgba(255, 255, 255, 0.1)",
            borderRadius: "8px",
            textAlign: "center",
          },
        },
        createElement(
          "div",
          {
            style: {
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              gap: "8px",
              marginBottom: "12px",
            },
          },
          createElement(
            "span",
            {
              style: {
                fontSize: "11px",
                fontWeight: "600",
                letterSpacing: "0.1em",
                textTransform: "uppercase",
                color: "#a0aec0",
              },
            },
            "BOT.SPOT"
          )
        ),
        createElement(
          "p",
          {
            style: {
              margin: "0 0 8px 0",
              fontSize: "16px",
              fontWeight: "500",
              color: "#ffffff",
            },
          },
          "BotSpot Appendix"
        ),
        createElement(
          "p",
          {
            style: {
              margin: 0,
              fontSize: "13px",
              color: "#718096",
            },
          },
          "AI-generated FAQ content will appear here"
        )
      );
    },

    save: function () {
      return null;
    },
  };

  // Register under primary name (WordPress.org compliant prefix)
  registerBlockType("bspt/appendix", blockConfig);
  // Register under legacy name (backwards compatibility)
  registerBlockType("botspot-wp/appendix", blockConfig);
})(window.wp);
