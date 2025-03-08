/* global wp */

wp.domReady(() => {
	const { registerBlockType } = wp.blocks;
	const { useBlockProps, InnerBlocks } = wp.blockEditor;
	const { createElement } = wp.element;

	registerBlockType('comet/callout', {
		edit: ({ attributes }) => {
			const themeColors = wp.data.select('core/block-editor').getSettings().colors;
			const colorThemeHex = attributes?.style?.elements?.theme?.color?.background ?? '#ffffff';
			const colorThemeName = themeColors.find((color) => color.color === colorThemeHex)?.slug;
			const blockProps = useBlockProps({
				className: ['callout', `callout--${colorThemeName}`].join(' ')
			});
			const template = [
				['core/paragraph']
			];

			return createElement('div',
				blockProps,
				createElement(InnerBlocks, {
					allowedBlocks: ['core/heading', 'comet/paragraph', 'core/list', 'core/buttons'],
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
