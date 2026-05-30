(function (blocks, element, blockEditor, components, i18n) {
    'use strict';

    var el               = element.createElement;
    var useBlockProps    = blockEditor.useBlockProps;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody        = components.PanelBody;
    var CheckboxControl  = components.CheckboxControl;
    var __               = i18n.__;

    var audiences = mawiblahSubscriptionBlock.audiences || [];

    blocks.registerBlockType('mawiblah/subscription-form', {
        title: __('Mawiblah Subscription Form', 'mawiblah'),
        icon: 'email-alt2',
        category: 'widgets',
        attributes: {
            audienceHashes: {
                type: 'array',
                default: [],
            },
        },

        edit: function (props) {
            var audienceHashes = props.attributes.audienceHashes;

            function toggleAudience(hash, checked) {
                var next = checked
                    ? audienceHashes.concat([hash])
                    : audienceHashes.filter(function (h) { return h !== hash; });
                props.setAttributes({ audienceHashes: next });
            }

            var blockProps = useBlockProps();

            return el(
                'div',
                blockProps,
                el(
                    InspectorControls,
                    null,
                    el(
                        PanelBody,
                        { title: __('Audiences', 'mawiblah'), initialOpen: true },
                        audiences.length === 0
                            ? el('p', null, __('No audiences found. Create one first.', 'mawiblah'))
                            : audiences.map(function (audience) {
                                return el(CheckboxControl, {
                                    key: audience.hash,
                                    label: audience.name,
                                    checked: audienceHashes.indexOf(audience.hash) !== -1,
                                    onChange: function (checked) {
                                        toggleAudience(audience.hash, checked);
                                    },
                                });
                            })
                    )
                ),
                el(
                    'div',
                    { className: 'mawiblah-subscribe-form-preview' },
                    el('p', null, __('Mawiblah Subscription Form', 'mawiblah')),
                    audienceHashes.length > 0
                        ? el('small', null, __('Audiences: ', 'mawiblah') + audienceHashes.join(', '))
                        : el('small', null, __('No audiences selected', 'mawiblah'))
                )
            );
        },

        save: function () {
            // Server-side rendered — return null
            return null;
        },
    });

}(
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor,
    window.wp.components,
    window.wp.i18n
));
