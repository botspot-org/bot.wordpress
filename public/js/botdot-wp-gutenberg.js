/**
 * Gutenberg block for BotDot Appendix
 *
 * @package    BotDot_WP
 * @subpackage BotDot_WP/public/js
 * @since      0.2.0
 */

(function(wp) {
    var registerBlockType = wp.blocks.registerBlockType;
    var createElement = wp.element.createElement;
    var InspectorControls = wp.editor.InspectorControls;
    var PanelBody = wp.components.PanelBody;
    var TextControl = wp.components.TextControl;
    var ToggleControl = wp.components.ToggleControl;

    registerBlockType('botdot-wp/appendix', {
        title: 'BotDot Appendix',
        description: 'Insert AI-discoverable appendix content',
        icon: 'info',
        category: 'common',
        attributes: {
            title: {
                type: 'string',
                default: window.botdotWP ? window.botdotWP.defaultTitle : 'AI Appendix'
            },
            open: {
                type: 'boolean',
                default: window.botdotWP ? window.botdotWP.defaultOpen : false
            }
        },

        edit: function(props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;

            return [
                createElement(
                    InspectorControls,
                    {},
                    createElement(
                        PanelBody,
                        {title: 'Appendix Settings'},
                        createElement(TextControl, {
                            label: 'Appendix Title',
                            value: attributes.title,
                            onChange: function(value) {
                                setAttributes({title: value});
                            }
                        }),
                        createElement(ToggleControl, {
                            label: 'Open by Default',
                            checked: attributes.open,
                            onChange: function(value) {
                                setAttributes({open: value});
                            }
                        })
                    )
                ),
                createElement(
                    'div',
                    {
                        className: 'botdot-wp-appendix-placeholder',
                        style: {
                            padding: '20px',
                            backgroundColor: '#f0f0f1',
                            border: '1px dashed #8c8f94',
                            borderRadius: '2px',
                            textAlign: 'center'
                        }
                    },
                    createElement('p', {
                        style: {
                            margin: 0,
                            fontSize: '14px',
                            color: '#1e1e1e'
                        }
                    }, '📎 BotDot Appendix: ' + attributes.title)
                )
            ];
        },

        save: function() {
            // Server-side rendering, so return null
            return null;
        }
    });
})(window.wp);
