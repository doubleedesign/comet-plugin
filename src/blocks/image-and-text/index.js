/* global wp */

wp.domReady(() => {
	const { registerBlockType } = wp.blocks;
	const { useBlockProps, InnerBlocks, InspectorControls } = wp.blockEditor;
	const { createElement } = wp.element;
	const { PanelBody, TextControl } = wp.components;

	// Custom wrapper for the image to allow finer-grained width and placement control
	registerBlockType('comet/image-and-text-image-wrapper', {
		title: 'Image wrapper',
		category: 'layout',
		icon: 'image-crop',
		attributes: {
			maxWidth: {
				type: 'number',
				default: 50
			}
		},
		supports: {
			className: false,
			customClassName: false,
			layout: {
				allowEditing: true,
				allowSwitching: false,
				allowJustification: true,
				allowOrientation: false,
				allowSizingOnChildren: false,
				allowCustomContentAndWideSize: false,
				allowInheriting: false,
				default: {
					type: 'constrained'
				}
			}
		},
		edit: (props) => {
			const { attributes, setAttributes } = props;
			const { maxWidth } = attributes;

			const blockProps = useBlockProps({
				className: 'image-and-text__image',
				'data-halign': attributes?.layout?.justifyContent ?? 'start'
			});

			return createElement(
				'div',
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
					createElement(
						'div',
						{ style: maxWidth ? { maxWidth: maxWidth + '%' } : {}, },
						createElement(InnerBlocks, {
							allowedBlocks: ['core/image'],
							template: [['core/image']],
							templateLock: false
						})
					)
				)
			);
		},
		save: (props) => {
			const { maxWidth } = props.attributes;
			const blockProps = useBlockProps.save({
				className: 'image-and-text__image',
			});

			return createElement(
				'div',
				blockProps,
				createElement(
					'div',
					{ style: maxWidth ? { maxWidth: maxWidth + '%' } : {}, },
					createElement(InnerBlocks.Content)
				)
			);
		}
	});

	// Custom wrapper for the content to restrict which blocks can be used here
	registerBlockType('comet/image-and-text-content', {
		title: 'Content',
		category: 'layout',
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
			className: false,
			customClassName: false,
			layout: {
				allowEditing: true,
				allowSwitching: false,
				allowJustification: true,
				allowOrientation: false,
				allowSizingOnChildren: false,
				allowCustomContentAndWideSize: false,
				allowInheriting: false,
				default: {
					type: 'constrained'
				}
			}
		},
		edit: (props) => {
			const { attributes, setAttributes } = props;
			const { maxWidth, overlayAmount } = attributes;

			const blockProps = useBlockProps({
				className: 'image-and-text__content',
				'data-halign': attributes?.layout?.justifyContent ?? 'start',
				style: {
					'--overlay-amount': `-${overlayAmount}px`
				}
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
					createElement(
						'div',
						{
							style: {
								maxWidth: maxWidth + '%',
							}
						},
						createElement(InnerBlocks, {
							allowedBlocks: ['comet/call-to-action', 'core/group', 'core/pullquote', 'core/block'],
							template: [['comet/call-to-action']],
							templateLock: false
						})
					)
				)
			);
		},
		save: (props) => {
			const { maxWidth, overlayAmount } = props.attributes;
			const blockProps = useBlockProps.save({
				className: 'image-and-text__content',
				style: {
					'--overlay-amount': `-${overlayAmount}px`
				}
			});

			return createElement(
				'div',
				blockProps,
				createElement(
					'div',
					{
						style: {
							maxWidth: maxWidth + '%',
						}
					},
					createElement(InnerBlocks.Content)
				),
			);
		}
	});

	// The overall block
	registerBlockType('comet/image-and-text', {
		edit: ({ attributes }) => {
			const blockProps = useBlockProps({
				className: 'image-and-text',
			});
			const template = [
				['comet/image-and-text-image-wrapper', { 'lock': { 'remove': true } }],
				['comet/image-and-text-content', { 'lock': { 'remove': true } }]
			];

			return createElement('div',
				blockProps,
				createElement(InnerBlocks, {
					template: template
				})
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
