<?php
/**
 * Plugin name: Comet Components for the Block Editor
 * Description: Double-E Design's foundational components and customisations for the WordPress block editor.
 *
 * Dev note: The dependency on Gutenberg is because at the time of development, justification controls incorrectly show up for the Panels block.
 *             This was fixed in version 19.8.0 which had not yet been merged into WordPress core.
 *             See https://github.com/WordPress/gutenberg/pull/67059
 *
 * Author:              Double-E Design
 * Author URI:          https://www.doubleedesign.com.au
 * Version:             0.2.0
 * Requires at least:   6.7.0
 * Requires PHP:        8.2.23
 * Requires plugins:    advanced-custom-fields-pro, block-supports-extended, gutenberg
 * Text Domain:         comet
 *
 * @package Comet
 */
if (!defined('COMET_COMPOSER_VENDOR_URL')) {
    define('COMET_COMPOSER_VENDOR_URL', get_site_url() . '/wp-content/plugins/comet-plugin/vendor');
}
require_once __DIR__ . '/vendor/autoload.php';

add_action('plugins_loaded', function() {
	if(!class_exists('Doubleedesign\Comet\Core\Config')) {
		wp_die('<p>Comet Components Core Config class not found in Comet Components Blocks plugin. Perhaps you need to install or update Composer dependencies.</p><p>If you are working locally with symlinked packages, you might want <code>$env:COMPOSER = "composer.local.json"; composer update</code>.</p>');
	}
	// Ensure global config is initialized
	Doubleedesign\Comet\Core\Config::getInstance();
});

use Doubleedesign\Comet\WordPress\{
    BlockRegistry,
    BlockRenderer,
    BlockEditorConfig,
    ComponentAssets,
    BlockPatternHandler,
    TinyMceConfig
};


new BlockRegistry();
new BlockRenderer();
new BlockEditorConfig();
new ComponentAssets();
new BlockPatternHandler();
new TinyMceConfig();

// Hackily disable (well, hide) the Style Book (Appearance > Design > Styles)
// because it's not accurate and really possible to customise
add_action('admin_head', function() { ?>
	<style>
		#stylebook-navigation-item {
			display: none !important;
			pointer-events: none !important;
		}

		.edit-site-style-book__iframe {
			display: none !important;
		}
	</style>
	<?php
});
