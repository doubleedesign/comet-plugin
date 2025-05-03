/* global wp */

wp.domReady(() => {
	const { registerBlockType, createBlock } = wp.blocks;
	const { InspectorControls, useBlockProps, InnerBlocks } = wp.blockEditor;
	const { createElement, Fragment } = wp.element;
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
						help: 'Note: Some page templates have their own hard-coded container(s) and may ignore containers. The Group block may be more suitable for these cases.',
						__next40pxDefaultSize: true
					}
				));

			const blockProps = useBlockProps({
				className: 'page-section layout-block',
				// could probably add the proper background attribute here but don't because we have to style for the default classes
				// for core blocks anyway, may as well just share that
			});
			const template = [
				['core/paragraph']
			];

			return createElement(
				Fragment,
				null,
				// Block editor sidebar
				createElement(
					InspectorControls,
					null,
					containerSizeControl,
				),
				createElement('section',
					blockProps,
					// Block preview
					createElement(
						'div',
						{
							className: 'container',
							'data-size': attributes.size,
						},
						createElement(InnerBlocks, {
							template: template
						})
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
