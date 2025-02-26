/* global wp */

wp.domReady(() => {
	const { registerBlockType } = wp.blocks;
	const { useBlockProps, InnerBlocks } = wp.blockEditor;
	const { createElement } = wp.element;

	registerBlockType('comet/step', {
		edit: ({ attributes }) => {
			const themeColors = wp.data.select('core/block-editor').getSettings().colors;
			const colorThemeHex = attributes?.style?.elements?.theme?.color?.background ?? '#ffffff';
			const colorThemeName = themeColors.find((color) => color.color === colorThemeHex)?.slug;

			const blockProps = useBlockProps({
				className: 'steps__step'
			});

			const template = [
				['core/heading', { level: 3, placeholder: 'Step heading' }],
				['core/paragraph', { placeholder: 'Step description' }],
			];

			return createElement('li',
				blockProps,
				createElement(
					'div',
					{ className: colorThemeName ? `steps__step__inner bg-${colorThemeName}` : 'steps__step__inner' },
					createElement(InnerBlocks, {
						allowedBlocks: ['core/heading', 'core/paragraph', 'core/list', 'core/image', 'core/buttons'],
						template: template
					})
				)
			);
		},
		save: () => {
			return createElement('li',
				useBlockProps.save(),
				createElement(InnerBlocks.Content)
			);
		}
	});
});
