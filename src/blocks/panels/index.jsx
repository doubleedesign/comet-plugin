/* global wp */
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';

wp.domReady(() => {
	registerBlockType('comet/panels', {
		edit: ({ attributes, clientId, isSelected }) => {
			const variant = attributes.variant === 'tab' ? 'Tabs' : attributes.variant;

			const themeColors = useSelect(select =>
				select('core/block-editor').getSettings().colors
			);
			const colorThemeHex = attributes?.style?.elements?.theme?.color?.background ?? '#ffffff';
			const colorThemeName = themeColors.find((color) => color.color === colorThemeHex)?.slug;

			const template = [
				['comet/panel'],
				['comet/panel'],
				['comet/panel']
			];

			const blockProps = useBlockProps({
				className: `${kebabCase(variant)}`,
				'data-color-theme': colorThemeName ?? 'primary',
				'data-orientation': attributes?.layout?.orientation,
			});

			return (
				<div {...blockProps}>
					<InnerBlocks
						allowedBlocks={['comet/panel']}
						template={template}
						templateLock="all"
					/>
				</div>
			);
		},
		save: () => (
			<div {...useBlockProps.save()}>
				<InnerBlocks.Content />
			</div>
		)
	});
});


function kebabCase(str) {
	return str
		.replace(/([a-z])([A-Z])/g, '$1-$2')
		.toLowerCase();
}
