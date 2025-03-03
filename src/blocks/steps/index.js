/* global wp */

wp.domReady(() => {
	const { registerBlockType } = wp.blocks;
	const { useBlockProps, InnerBlocks, InspectorControls } = wp.blockEditor;
	const { createElement } = wp.element;
	const { RangeControl } = wp.components;

	registerBlockType('comet/steps', {
		edit: ({ attributes, setAttributes }) => {
			const { maxPerRow } = attributes;
			const themeColors = wp.data.select('core/block-editor').getSettings().colors;
			const colorThemeHex = attributes?.style?.elements?.theme?.color?.background ?? '#ffffff';
			const colorThemeName = themeColors.find((color) => color.color === colorThemeHex)?.slug;

			const blockProps = useBlockProps({
				'className': 'steps',
				'data-color-theme': colorThemeName,
				'data-orientation': attributes?.layout?.orientation ?? 'vertical',
				'data-max-per-row': attributes?.layout?.orientation === 'horizontal' ? maxPerRow : null
			});

			const template = [
				['comet/step'],
				['comet/step'],
				['comet/step'],
			];

			return createElement(
				wp.element.Fragment,
				null,
				createElement(
					InspectorControls,
					null,
					createElement('div', { style: { paddingInline: '16px', marginTop: '-0.5rem' } },
						createElement(
							RangeControl,
							{
								label: 'Maximum steps per row (horizontal layout only)',
								value: maxPerRow,
								onChange: (value) => setAttributes({ maxPerRow: value }),
								min: 2,
								max: 5
							}
						)
					)
				),
				createElement('ol',
					blockProps,
					createElement(InnerBlocks, {
						allowedBlocks: ['comet/step'],
						template: template
					})
				)
			);
		},
		save: ({ attributes }) => {
			const { maxPerRow } = attributes;
			const saveProps = useBlockProps.save({
				'data-max-per-row': maxPerRow
			});

			return createElement('ol',
				saveProps,
				createElement(InnerBlocks.Content)
			);
		}
	});
});
