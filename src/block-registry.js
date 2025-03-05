/* global wp */

/**
 * Customisations for blocks themselves
 * Companion for what is done in BlockRegistry.php
 */
wp.domReady(() => {
	removeSomeCoreStylesAndVariations();
	addCustomAttributesToCoreBlockHtml();
});

/**
 * Unregister unwanted core block styles and variations
 * Note: This is only accounts for blocks that are explicitly allowed by the allowed_block_types_all filter
 * At the time of writing, this can't be done in PHP, otherwise I would have.
 */
function removeSomeCoreStylesAndVariations() {
	setTimeout(() => {
		wp.blocks.unregisterBlockVariation('core/group', 'group-grid');
		wp.blocks.unregisterBlockVariation('core/group', 'group-stack');
		wp.blocks.unregisterBlockVariation('core/group', 'group-row');

		wp.blocks.unregisterBlockStyle('core/separator', 'wide');

		// TODO: Can this be an explicit allow list rather than filtering out?
		// eslint-disable-next-line max-len
		(['wordpress', 'issuu', 'spotify', 'soundcloud', 'flickr', 'animoto', 'cloudup', 'crowdsignal', 'dailymotion', 'imgur', 'kickstarter', 'mixcloud', 'pocket-casts', 'reddit', 'reverbnation', 'screencast', 'scribd', 'smugmug', 'speaker-deck', 'ted', 'tumblr', 'videopress', 'amazon-kindle', 'wolfram-cloud', 'pinterest', 'wordpress-tv']).forEach((embed) => {
			wp.blocks.unregisterBlockVariation('core/embed', embed);
		});
	}, 200);
}

/**
 * Add custom attributes (registered in PHP using Block Supports Extended plugin) as classes to core block HTML in the editor
 * Note: This is for the editor only, the PHP render function customisations take care of the front-end
 */
function addCustomAttributesToCoreBlockHtml() {
	const { addFilter } = wp.hooks;
	const { select } = wp.data;
	const { createElement } = wp.element;

	addFilter(
		'blocks.registerBlockType',
		'comet/modify-button-block',
		(settings, name) => {
			if (name !== 'core/button') {
				return settings;
			}

			const originalEdit = settings.edit;

			settings.edit = (props) => {
				const themeColors = React.useMemo(() => {
					return select('core/block-editor').getSettings().colors;
				}, []);

				const colorThemeHex = React.useMemo(() => {
					return props?.attributes?.style?.elements?.theme?.color?.background;
				}, [props?.attributes?.style?.elements?.theme?.color?.background]);

				const colorThemeName = React.useMemo(() => {
					return themeColors?.find((color) => color.color === colorThemeHex)?.slug ?? 'primary';
				}, [themeColors, colorThemeHex]);

				const buttonClass = React.useMemo(() => {
					if (props.attributes?.className?.includes('is-style-outline')) {
						return `button button--${colorThemeName}--outline`;
					}

					return `button button--${colorThemeName}`;
				}, [colorThemeName, props.attributes?.className]);

				// Wrap the original edit component with our custom classes
				return createElement('div',
					{ className: buttonClass },
					originalEdit(props)
				);
			};

			return settings;
		}
	);

	addFilter(
		'blocks.registerBlockType',
		'comet/modify-text-blocks',
		(settings, name) => {
			if (name !== 'core/heading' && name !== 'core/paragraph') {
				return settings;
			}

			const originalEdit = settings.edit;

			settings.edit = (props) => {
				const themeColors = React.useMemo(() => {
					return select('core/block-editor').getSettings().colors;
				}, []);

				const colorHex = React.useMemo(() => {
					return props?.attributes?.style?.elements?.inline?.color?.text;
				}, [props?.attributes?.style?.elements?.inline?.color?.text]);

				const colorThemeName = React.useMemo(() => {
					return themeColors?.find((color) => color.color === colorHex)?.slug ?? '';
				}, [themeColors, colorHex]);

				const styleClass = React.useMemo(() => {
					return `color-${colorThemeName}`;
				}, [colorThemeName, props.attributes?.className]);

				// Wrap the original edit component with our custom classes
				return createElement('div',
					{ className: styleClass },
					originalEdit(props)
				);
			};

			return settings;
		}
	);
}
