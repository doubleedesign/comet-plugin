/* global wp */

wp.domReady(() => {
	const { registerBlockType } = wp.blocks;
	const { createElement, Fragment } = wp.element;

	const {
		InspectorControls,
		MediaUpload,
		MediaUploadCheck,
		useBlockProps,
		InnerBlocks,
		BlockVerticalAlignmentControl
	} = wp.blockEditor;
	const {
		PanelBody,
		Button,
		RangeControl,
		ToggleControl,
		BaseControl,
		TextControl,
		SelectControl
	} = wp.components;

	registerBlockType('comet/banner', {
		edit: ({ attributes, setAttributes }) => {
			const themeColors = wp.data.select('core/block-editor').getSettings().colors;
			const overlayHex = attributes?.style?.elements?.overlay.color?.background ?? '';
			const overlayColorName = themeColors.find((color) => color.color === overlayHex)?.slug;

			const {
				// This block has its own verticalAlignment attribute (instead of using the one in the layout attribute)
				// because at the time of writing, vertical alignment layout options only show in the toolbar
				// not the block inspector sidebar which is quite stupid given the horizontal justification options are there.
				// This allows us to add a control for it below.
				verticalAlignment = 'center',
				layout,
				containerSize,
				contentMaxWidth = 50,
				imageId,
				imageUrl,
				overlayOpacity,
				isParallax,
				minHeight,
				maxHeight,
				backgroundColor
			} = attributes;

			const verticalAlignmentControl = createElement(
				BaseControl,
				{
					label: 'Vertical alignment',
					children: createElement(
						BlockVerticalAlignmentControl,
						{
							value: verticalAlignment,
							onChange: (alignment) => setAttributes({ verticalAlignment: alignment }),
							isCollapsed: false
						}
					)
				},
			);

			const containerSizeControl = createElement(
				'div',
				{ style: { padding: '0 1rem 1rem 1rem' } },
				createElement(
					SelectControl,
					{
						label: 'Container size',
						value: containerSize,
						options: [
							{ label: 'Default', value: 'default' },
							{ label: 'Narrow', value: 'narrow' },
							{ label: 'Wide', value: 'wide' },
							{ label: 'Full-width', value: 'fullwidth' }
						],
						onChange: (newValue) => setAttributes({ containerSize: newValue }),
						__next40pxDefaultSize: true
					}
				));


			const contentMaxWidthControl = createElement(
				'div',
				{ style: { padding: '0 1rem 1rem 1rem' } },
				createElement(
					TextControl,
					{
						label: 'Content max width (%)',
						value: contentMaxWidth,
						onChange: (newValue) => setAttributes({ contentMaxWidth: parseInt(newValue, 10) || 0 }),
						type: 'number',
						min: 20,
						max: 100,
						help: '(May be overridden to take up full width in small containers/viewports)'
					}
				)
			);

			const imageUploader = createElement(
				MediaUploadCheck,
				null,
				createElement(
					MediaUpload,
					{
						onSelect: (media) => setAttributes({ imageId: media.id, imageUrl: media.url }),
						allowedTypes: ['image'],
						value: imageId,
						render: ({ open }) => createElement(
							Button,
							{
								onClick: open,
								isPrimary: true,
								style: { marginBlockEnd: '1rem' }
							},
							!imageId ? 'Choose an image' : 'Replace image'
						)
					}
				)
			);

			const fixedBackgroundToggle = createElement(
				ToggleControl,
				{
					label: 'Fixed background (parallax effect)',
					checked: isParallax,
					onChange: () => setAttributes({ isParallax: !isParallax })
				}
			);

			const minHeightSetting = createElement(
				TextControl,
				{
					label: 'Minimum height (px)',
					value: minHeight,
					onChange: (newValue) => setAttributes({ minHeight: parseInt(newValue, 10) || 0 }),
					type: 'number',
					min: 400,
					max: 1280
				}
			);

			const maxHeightSetting = createElement(
				TextControl,
				{
					label: 'Maximum height (vh)',
					value: maxHeight,
					onChange: (newValue) => setAttributes({ maxHeight: parseInt(newValue, 10) || 0 }),
					type: 'number',
					min: 20,
					max: 100,
					help: '% of viewport height'
				}
			);

			const overlayOpacityControl = createElement(
				RangeControl,
				{
					label: 'Overlay opacity',
					value: overlayOpacity,
					onChange: (value) => setAttributes({ overlayOpacity: value }),
					min: 0,
					max: 100,
					step: 1
				}
			);

			const blockProps = useBlockProps({});

			const overlayStyles = {
				backgroundColor: 'rgba(0, 0, 0, ' + (overlayOpacity / 100) + ')',
			};

			return createElement(
				Fragment,
				null,
				// The block inspector sidebar
				createElement(
					InspectorControls,
					null,
					createElement('div', { className: 'comet-custom-layout-controls' },
						verticalAlignmentControl,
						containerSizeControl,
						contentMaxWidthControl
					),
					createElement(
						PanelBody,
						{ title: 'Image settings' },
						createElement(
							'div',
							null,
							imageUploader,
							fixedBackgroundToggle
						),
					),
					createElement(
						PanelBody,
						{ title: 'Dimensions' },
						createElement(
							'div',
							{ style: { display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: '0.5rem' } },
							minHeightSetting,
							maxHeightSetting
						)
					),
					createElement(
						PanelBody,
						{ title: 'Overlay settings' },
						overlayOpacityControl
					)
				),
				// The block preview in the editor
				createElement(
					'section',
					{
						...blockProps,
						style: {
							minHeight: minHeight + 'px',
							maxHeight: maxHeight + 'vh'
						},
						'data-size': containerSize,
					},
					createElement(
						'div',
						{ className: 'banner__image', 'data-behaviour': 'cover' },
						imageUrl && createElement(
							'img',
							{
								src: imageUrl,
								alt: '',
							}
						)
					),
					createElement(
						'div',
						{
							className: 'banner__content',
						},
						createElement(
							'div',
							{
								className: 'banner__container layout-block container',
								'data-width': containerSize,
								'data-valign': verticalAlignment,
								'data-halign': layout?.justifyContent,
							},
							createElement(
								'div',
								{
									className: 'banner__content__inner',
									style: { maxWidth: contentMaxWidth + '%' },
									'data-background': backgroundColor
								},
								createElement(
									InnerBlocks,
									{
										allowedBlocks: ['core/heading', 'core/paragraph', 'core/buttons'],
										template: [
											['core/heading', { placeholder: 'Add a title...' }],
											['core/paragraph', { placeholder: 'Write content...' }],
											['core/buttons', {}]
										]
									}
								)
							),
						)
					),
					createElement(
						'div',
						{ className: 'banner__overlay', style: overlayStyles },
					),
				)
			);
		},

		save: () => {
			return createElement(
				'section',
				useBlockProps.save(),
				createElement(InnerBlocks.Content)
			);
		}
	});
});
