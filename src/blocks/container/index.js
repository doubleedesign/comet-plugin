/* global wp */

wp.domReady(() => {
	const { registerBlockType, createBlock } = wp.blocks;
	const { InspectorControls, useBlockProps, InnerBlocks } = wp.blockEditor;
	const { createElement } = wp.element;
	const { PanelBody, SelectControl } = wp.components;

	registerBlockType('comet/container', {
		transforms: {
			from: [
				{
					type: 'block',
					blocks: ['core/group'],
					transform: (blockAttributes, innerBlocks) => {
						return createBlock(
							'comet/container',
							{ size: 'default' },
							innerBlocks
						);
					},
				},
			],
			to: [
				{
					type: 'block',
					blocks: ['core/group'],
					transform: (blockAttributes, innerBlocks) => {
						return createBlock(
							'core/group',
							{},
							innerBlocks
						);
					},
				},
			],
		},
		edit: ({ attributes, setAttributes, ...props }) => {
			const containerSizeControl = createElement(
				PanelBody,
				{ title: 'Layout' },
				createElement(
					SelectControl,
					{
						label: 'Container size',
						value: attributes.size,
						options: [
							{ label: 'Default', value: 'default' },
							{ label: 'Narrow', value: 'narrow' },
							{ label: 'Wide', value: 'wide' },
							{ label: 'Full-width', value: 'fullwidth' }
						],
						onChange: (newValue) => setAttributes({ size: newValue }),
						// eslint-disable-next-line max-len
						help: 'Note: Some page templates (such as those with a sidebar) have their own hard-coded container(s) and may ignore containers. The Group block may be more suitable for these cases.',
						__next40pxDefaultSize: true
					}
				));


			const blockProps = useBlockProps();
			const template = [
				['core/paragraph']
			];

			return createElement(
				'div',
				blockProps,
				// Block editor sidebar
				createElement(
					InspectorControls,
					null,
					containerSizeControl,
				),
				// Block preview
				createElement('section',
					blockProps,
					createElement(
						'div',
						{ className: 'container' },
						createElement(
							'div',
							{ 'data-size': attributes.size },
							createElement(InnerBlocks, {
								template: template
							})
						)
					)
				)
			);
		},
		save: () => {
			return createElement('section',
				useBlockProps.save(),
				createElement(InnerBlocks.Content)
			);
		}
	});
});
