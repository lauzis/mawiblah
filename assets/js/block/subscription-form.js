(function (blocks, element, blockEditor, components) {
    'use strict';

    var el          = element.createElement;
    var useBlockProps = blockEditor.useBlockProps;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody   = components.PanelBody;
    var CheckboxControl = components.CheckboxControl;

    var audiences = mawiblahSubscriptionBlock.audiences || [];

    blocks.registerBlockType('mawiblah/subscription-form', {
        title: 'Mawiblah Subscription Form',
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
                        { title: 'Audiences', initialOpen: true },
                        audiences.length === 0
                            ? el('p', null, 'No audiences found. Create one first.')
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
                    el('p', null, 'Mawiblah Subscription Form'),
                    audienceHashes.length > 0
                        ? el('small', null, 'Audiences: ' + audienceHashes.join(', '))
                        : el('small', null, 'No audiences selected')
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
    window.wp.components
));
