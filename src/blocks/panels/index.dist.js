/* global wp */
const { registerBlockType } = wp.blocks;
const { useBlockProps,
	InnerBlocks } = wp.blockEditor;
const { useSelect } = wp.data;
wp.domReady(() => {
	registerBlockType('comet/panels', {
		edit: ({ attributes,
			clientId,
			isSelected }) => {
			const variant = attributes.variant === 'tab' ? 'Tabs' : attributes.variant;
			const themeColors = useSelect(select => select('core/block-editor').getSettings().colors);
			const colorThemeHex = attributes?.style?.elements?.theme?.color?.background ?? '#ffffff';
			const colorThemeName = themeColors.find(color => color.color === colorThemeHex)?.slug;
			const template = [['comet/panel'], ['comet/panel'], ['comet/panel']];
			const blockProps = useBlockProps({
				className: `${kebabCase(variant)}`,
				'data-color-theme': colorThemeName ?? 'primary',
				'data-orientation': attributes?.layout?.orientation
			});

			return /*#__PURE__*/React.createElement('div', blockProps, /*#__PURE__*/React.createElement(InnerBlocks, {
				allowedBlocks: ['comet/panel'],
				template: template,
				templateLock: 'all'
			}));
		},
		save: () => /*#__PURE__*/React.createElement('div', useBlockProps.save(), /*#__PURE__*/React.createElement(InnerBlocks.Content, null))
	});
});
function kebabCase(str) {
	return str.replace(/([a-z])([A-Z])/g, '$1-$2').toLowerCase();
}
//# sourceMappingURL=index.dist.js.map
