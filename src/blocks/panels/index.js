/* global wp */

wp.domReady(() => {
	const { registerBlockType } = wp.blocks;
	const { useBlockProps, InnerBlocks } = wp.blockEditor;
	const { createElement } = wp.element;

	registerBlockType('comet/panels', {
		edit: ({ attributes }) => {
			const themeColors = wp.data.select('core/block-editor').getSettings().colors;
			const colorThemeHex = attributes?.style?.elements?.theme?.color?.background ?? '#ffffff';
			const colorThemeName = themeColors.find((color) => color.color === colorThemeHex)?.slug;
			const variant = attributes.variant === 'tab' ? 'tabs' : attributes.variant;

			const blockProps = useBlockProps({
				className: variant,
				'data-color-theme': colorThemeName ?? 'primary',
				'data-orientation': attributes?.layout?.orientation,
			});

			const template = [
				['comet/panel'],
				['comet/panel'],
				['comet/panel']
			];

			return createElement(
				'div',
				{ 'data-vue-component': variant },
				createElement('div',
					blockProps,
					createElement(InnerBlocks, {
						allowedBlocks: ['comet/panel'],
						template: template
					})
				));
		},
		save: () => {
			return createElement('div',
				useBlockProps.save(),
				createElement(InnerBlocks.Content)
			);
		}
	});
});
