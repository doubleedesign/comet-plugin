<?php
namespace Doubleedesign\Comet\WordPress;

/**
 * This class handles loading of CSS and JS assets in the block editor
 */
class BlockEditorAdminAssets {

	function __construct() {
		if (!function_exists('register_block_type')) {
			// Block editor is not available.
			return;
		}

		// Front-end CSS
		add_action('wp_enqueue_scripts', [$this, 'enqueue_comet_global_css'], 10);
		add_action('wp_enqueue_scripts', [$this, 'enqueue_comet_combined_component_css'], 10);

		// Editor CSS
		if(is_admin()) {
			add_action('enqueue_block_assets', [$this, 'enqueue_comet_global_css'], 10);
			add_action('enqueue_block_assets', [$this, 'enqueue_wp_block_css'], 10);
			add_filter('block_editor_settings_all', [$this, 'remove_gutenberg_inline_styles']);
		}

		// General admin JS
		add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);
	}

	/**
	 * Global stylesheet for basics like typography, colors, etc.
	 * To be used both for the front-end and the back-end editor.
	 * @return void
	 */
	function enqueue_comet_global_css(): void {
		$currentDir = plugin_dir_url(__FILE__);
		$pluginDir = dirname($currentDir, 1);
		$global_css_path = $pluginDir . '/vendor/doubleedesign/comet-components-core/src/components/global.css';
		wp_enqueue_style('comet-global-styles', $global_css_path, array(), COMET_VERSION);
	}

	/**
	 * Combined stylesheet for all blocks for the front-end
	 * @return void
	 */
	function enqueue_comet_combined_component_css(): void {
		$currentDir = plugin_dir_url(__FILE__);
		$pluginDir = dirname($currentDir, 1);
		$block_css_path = $pluginDir . '/src/blocks.css';
		wp_enqueue_style('comet-component-styles', $block_css_path, array(), COMET_VERSION);
	}

	/**
	 * Combined stylesheet for all blocks for the editor
	 * @return void
	 */
	function enqueue_wp_block_css(): void {
		$currentDir = plugin_dir_url(__FILE__);
		$pluginDir = dirname($currentDir, 1);
		$block_css_path = $pluginDir . '/src/editor.css';
		wp_enqueue_style('comet-block-styles', $block_css_path, array('wp-edit-blocks'), COMET_VERSION);
	}

	/**
	 * Remove default inline styles from the block editor
	 * @param $editor_settings
	 * @return array
	 */
	function remove_gutenberg_inline_styles($editor_settings): array {
		if (!empty($editor_settings['styles'])) {
			$editor_settings['styles'] = [];
		}

		return $editor_settings;
	}

	/**
	 * Scripts to hackily remove/hide menu items (e.g., the disabled code editor button) for simplicity,
	 * open list view by default, and other editor UX things like that
	 * @return void
	 */
	function admin_scripts(): void {
		$currentDir = plugin_dir_url(__FILE__);
		$pluginDir = dirname($currentDir, 1);

		//wp_enqueue_script('comet-block-editor-hacks', './block-editor-hacks.js', array('wp-edit-post', 'wp-data', 'wp-dom-ready'), COMET_VERSION, true);
		wp_enqueue_style('comet-block-editor-hacks', "$pluginDir/src/block-editor-hacks.css", array(), COMET_VERSION);
	}
}
