/* global wp */

wp.domReady(() => {
	const { registerBlockType } = wp.blocks;
	const { useBlockProps, InnerBlocks, InspectorControls } = wp.blockEditor;
	const { createElement } = wp.element;
	const { PanelBody, TextControl } = wp.components;

	// Custom wrapper for the image to allow finer-grained width and placement control
	registerBlockType('comet/image-and-text-image-wrapper', {
		title: 'Image wrapper',
		category: 'featured-text',
		icon: 'image-crop',
		attributes: {
			maxWidth: {
				type: 'number',
				default: 50
			}
		},
		supports: {
			layout: {
				allowEditing: true,
				allowSwitching: false,
				allowJustification: true,
				allowOrientation: false,
				default: {
					type: 'flex'
				}
			}
		},
		edit: (props) => {
			const { attributes, setAttributes } = props;
			const { maxWidth } = attributes;

			const blockProps = useBlockProps({
				className: 'image-and-text__image',
				style: maxWidth ? { maxWidth: maxWidth + '%' } : {}
			});

			return createElement(
				wp.element.Fragment,
				null,
				createElement(
					InspectorControls,
					null,
					createElement(
						PanelBody,
						{ title: 'Block size', initialOpen: true },
						createElement(
							TextControl,
							{
								label: 'Maximum width (% of available space)',
								value: maxWidth,
								onChange: (newMaxWidth) => setAttributes({ maxWidth: parseInt(newMaxWidth, 10) || 0 }),
								type: 'number',
								help: '(When the space is very narrow, this may be ignored and the block will take up all available space.)'
							}
						)
					)
				),
				createElement(
					'div',
					blockProps,
					createElement(InnerBlocks, {
						allowedBlocks: ['core/image'],
						template: [['core/image']],
						templateLock: false
					})
				)
			);
		},
		save: (props) => {
			const { maxWidth } = props.attributes;
			const blockProps = useBlockProps.save({
				className: 'image-and-text__image',
				style: maxWidth ? { maxWidth: maxWidth + '%' } : {}
			});

			return createElement(
				'div',
				blockProps,
				createElement(InnerBlocks.Content)
			);
		}
	});

	// Custom wrapper for the content to restrict which blocks can be used here
	registerBlockType('comet/image-and-text-content', {
		title: 'Content',
		category: 'featured-text',
		icon: 'text',
		attributes: {
			maxWidth: {
				type: 'number',
				default: 50
			},
			overlayAmount: {
				type: 'number',
				default: 0
			}
		},
		supports: {
			layout: {
				allowEditing: true,
				allowSwitching: false,
				allowJustification: true,
				allowOrientation: false,
				default: {
					type: 'flex'
				}
			}
		},
		edit: (props) => {
			const { attributes, setAttributes } = props;
			const { maxWidth, overlayAmount } = attributes;

			const blockProps = useBlockProps({
				className: 'image-and-text__content',
				style: maxWidth ? { maxWidth: maxWidth + '%' } : {}
			});

			return createElement(
				wp.element.Fragment,
				null,
				createElement(
					InspectorControls,
					null,
					createElement(
						PanelBody,
						{ title: 'Block size', initialOpen: true },
						createElement(
							TextControl,
							{
								label: 'Maximum width (% of available space)',
								value: maxWidth,
								onChange: (newMaxWidth) => setAttributes({ maxWidth: parseInt(newMaxWidth, 10) || 0 }),
								type: 'number',
								help: '(When the space is very narrow, this may be ignored and the block will take up all available space.)'
							}
						),
						createElement(
							TextControl,
							{
								label: 'Overlay amount (px)',
								value: overlayAmount,
								onChange: (newOverlayAmount) => setAttributes({ overlayAmount: parseInt(newOverlayAmount, 10) || 0 }),
								type: 'number'
							}
						)
					)
				),
				createElement(
					'div',
					blockProps,
					createElement(InnerBlocks, {
						allowedBlocks: ['comet/call-to-action', 'core/pullquote', 'core/block'],
						template: [['comet/call-to-action']],
						templateLock: false
					})
				)
			);
		},
		save: (props) => {
			const { maxWidth } = props.attributes;
			const blockProps = useBlockProps.save({
				className: 'image-and-text__content',
				style: maxWidth ? { maxWidth: maxWidth + '%' } : {}
			});

			return createElement(
				'div',
				blockProps,
				createElement(InnerBlocks.Content)
			);
		}
	});

	// This actual block remains the same
	registerBlockType('comet/image-and-text', {
		edit: ({ attributes }) => {
			const blockProps = useBlockProps({
				className: 'image-and-text',
				'data-orientation': attributes?.layout?.orientation ?? 'vertical',
			});
			const template = [
				['comet/image-and-text-image-wrapper', { 'lock': { 'remove': true } }],
				['comet/image-and-text-content', { 'lock': { 'remove': true } }]
			];

			return createElement('div',
				blockProps,
				createElement(
					'div',
					{ className: 'image-and-text' },
					createElement(InnerBlocks, {
						template: template
					})
				),
			);
		},
		save: () => {
			return createElement('div',
				useBlockProps.save(),
				createElement(InnerBlocks.Content)
			);
		}
	});
});
