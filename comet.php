<?php
/**
 * Plugin name: Comet Components
 * Description: Double-E Design's foundational components and customisations for the WordPress block editor.
 *
 * Author:              Double-E Design
 * Author URI:          https://www.doubleedesign.com.au
 * Version:             0.0.1
 * Requires at least:   6.7.0
 * Requires PHP:        8.2.23
 * Requires plugins:    advanced-custom-fields-pro
 * Text Domain:         comet
 *
 * @package Comet
 */

const COMET_VERSION = '0.0.1';
require_once __DIR__ . '/vendor/autoload.php';

use Doubleedesign\Comet\WordPress\{
	EmbeddedPlugins,
	BlockRegistry,
	BlockRenderer,
	BlockEditorConfig,
	BlockEditorAdminAssets,
	BlockPatternHandler,
	TinyMceConfig
};

new EmbeddedPlugins();
new BlockRegistry();
new BlockRenderer();
new BlockEditorConfig();
new BlockEditorAdminAssets();
new BlockPatternHandler();
new TinyMceConfig();
