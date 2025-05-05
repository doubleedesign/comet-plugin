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

		wp.blocks.registerBlockStyle('core/group', {
			name: 'breakout',
			label: 'Breakout (ignore sidebars)',
		});

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

	// See register_custom_attributes() in BlockRegistry.php
	const blocksWithCustomColorAttrs = [
		'core/heading',
		'core/paragraph',
		'core/list-item',
		'core/button',
		'core/pullquote',
		'core/details',
	];

	// Calculate the color attributes
	addFilter(
		'blocks.registerBlockType',
		'comet/handle-custom-colour-attributes',
		(settings, name) => {
			if(!blocksWithCustomColorAttrs.includes(name)) return settings;

			// Store the original edit function
			const originalEdit = settings.edit;

			// Replace the edit function with our enhanced version
			settings.edit = (props) => {
				const { attributes, setAttributes } = props;

				// Get theme colors
				const themeColors = React.useMemo(() => {
					return select('core/block-editor').getSettings().colors;
				}, []);

				// Get text color name from the hex value the editor applies
				const colorHex = React.useMemo(() => {
					return props?.attributes?.style?.elements?.inline?.color?.text;
				}, [props?.attributes?.style?.elements?.inline?.color?.text]);
				const colorName = React.useMemo(() => {
					return themeColors?.find((color) => color.color === colorHex)?.slug ?? '';
				}, [themeColors, colorHex]);

				// Get theme color name from the hex value the editor applies
				const themeHex = React.useMemo(() => {
					return props?.attributes?.style?.elements?.theme?.color?.background;
				}, [props?.attributes?.style?.elements?.theme?.color?.background]);
				const themeName = React.useMemo(() => {
					return themeColors?.find((color) => color.color === themeHex)?.slug ?? '';
				}, [themeColors, themeHex]);

				// Store color names in block attributes (for internal use)
				React.useEffect(() => {
					if (colorName || themeName) {
						setAttributes({
							textColorName: colorName,
							themeColorName: themeName
						});
					}
				}, [colorName, themeName, setAttributes]);

				// Return the original edit component with updated props
				return originalEdit(props);
			};

			// Add our custom attributes to the block's attribute definitions
			if (!settings.attributes) {
				settings.attributes = {};
			}

			settings.attributes.textColorName = {
				type: 'string',
				default: ''
			};

			settings.attributes.themeColorName = {
				type: 'string',
				default: '',
			};

			return settings;
		}
	);

	// Apply the attributes to the block editor DOM
	addFilter(
		'editor.BlockEdit',
		'comet/apply-data-attributes-in-editor',
		(BlockEdit) => {
			return function EnhancedBlockEdit(props) {
				const { attributes, name, clientId } = props;

				// Skip if this block type shouldn't have custom color attributes
				if (!blocksWithCustomColorAttrs.includes(name)) {
					return wp.element.createElement(BlockEdit, props);
				}

				React.useEffect(() => {
					// Wait a moment for the DOM to update
					setTimeout(() => {
						// Target the block by its client ID
						const blockElement = document.querySelector(`[data-block="${clientId}"]`);

						if (blockElement) {
							if (attributes.textColorName) {
								blockElement.setAttribute('data-text-color', attributes.textColorName);
							}
							if (attributes.themeColorName) {
								blockElement.setAttribute('data-color-theme', attributes.themeColorName);
							}
						}
					}, 0);
				}, [clientId, attributes.textColorName, attributes.themeColorName]);

				return wp.element.createElement(BlockEdit, props);
			};
		}
	);

	// Apply the attributes to the saved content
	addFilter(
		'blocks.getSaveContent.extraProps',
		'comet/apply-save-data-attributes',
		(extraProps, blockType, attributes) => {
			if (attributes.textColorName) {
				extraProps['data-text-color'] = attributes.textColorName;
			}

			if (attributes.themeColorName) {
				extraProps['data-color-theme'] = attributes.themeColorName;
			}

			return extraProps;
		}
	);
}
