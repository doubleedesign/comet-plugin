<?php
namespace Doubleedesign\Comet\WordPress;

/**
 * This class handles loading of CSS and JS assets in the block editor
 * and the front-end (so should probably be refactored or renamed)
 */
class ComponentAssets {

	function __construct() {
		if(!function_exists('register_block_type')) {
			// Block editor is not available.
			return;
		}

		// Front-end CSS
		add_action('wp_enqueue_scripts', [$this, 'enqueue_comet_global_css'], 10);
		add_action('wp_enqueue_scripts', [$this, 'enqueue_comet_combined_component_css'], 10);
		// Front-end JS
		add_action('wp_enqueue_scripts', [$this, 'enqueue_comet_combined_component_js'], 10);
		add_filter('script_loader_tag', [$this, 'script_type_module'], 10, 3);
		add_filter('script_loader_tag', [$this, 'script_base_path'], 10, 3);

		if(is_admin()) {
			// Editor CSS
			add_action('enqueue_block_assets', [$this, 'enqueue_comet_global_css'], 10);
			add_action('enqueue_block_assets', [$this, 'enqueue_wp_block_css'], 10);
			add_filter('block_editor_settings_all', [$this, 'remove_gutenberg_inline_styles']);
		}
	}

	/**
	 * - Global stylesheet for basics like typography, colors, etc.
	 * - Combined Comet styles for components corresponding to template parts like the header and footer
	 * To be used both for the front-end and the back-end editor.
	 * @return void
	 */
	function enqueue_comet_global_css(): void {
		$currentDir = plugin_dir_url(__FILE__);
		$pluginDir = dirname($currentDir, 1);

		$global_css_path = $pluginDir . '/vendor/doubleedesign/comet-components-core/src/components/global.css';
		wp_enqueue_style('comet-global-styles', $global_css_path, array(), COMET_VERSION);

		$template_css_path = $pluginDir . '/src/template-parts.css';
		wp_enqueue_style('comet-component-template-part-styles', $template_css_path, array(), COMET_VERSION);

	}

	/**
	 * Combined stylesheet for all blocks for the front-end
	 * @return void
	 */
	function enqueue_comet_combined_component_css(): void {
		$currentDir = plugin_dir_url(__FILE__);
		$pluginDir = dirname($currentDir, 1);
		$block_css_path = $pluginDir . '/src/blocks.css';
		wp_enqueue_style('comet-component-block-styles', $block_css_path, array(), COMET_VERSION);
	}

	/**
	 * Bundled JS for all components for the front-end
	 * @return void
	 */
	function enqueue_comet_combined_component_js(): void {
		$currentDir = plugin_dir_url(__FILE__);
		$pluginDir = dirname($currentDir, 1);
		$libraryDir = $pluginDir . '/vendor/doubleedesign/comet-components-core';
		wp_enqueue_script('comet-components-js', "$libraryDir/dist/dist.js", array(), COMET_VERSION, true);
		// Alternatively you can import individual components' JS like so:
		//wp_enqueue_script('comet-gallery', "$libraryDir/src/components/Gallery/gallery.js", array(), COMET_VERSION, true);
	}

	/**
	 * Add type=module to script tags
	 *
	 * @param $tag
	 * @param $handle
	 * @param $src
	 *
	 * @return mixed|string
	 */
	function script_type_module($tag, $handle, $src): mixed {
		if(str_starts_with($handle, 'comet-')) {
			$tag = '<script type="module" src="' . esc_url($src) . '" id="' . $handle . '" ></script>';
		}

		return $tag;
	}

	/**
	 * Add data-base-path attribute to Comet Components script tag
	 * so Vue SFC loader can find its templates
	 * @param $tag
	 * @param $handle
	 * @param $src
	 * @return mixed|string
	 */
	function script_base_path($tag, $handle, $src): mixed {
		if($handle === 'comet-components-js') {
			$currentDir = plugin_dir_url(__FILE__);
			$pluginDir = dirname($currentDir, 1);
			$libraryDir = $pluginDir . '/vendor/doubleedesign/comet-components-core';
			$libraryDirShort = str_replace(get_site_url(), '', $libraryDir);
			$tag = '<script type="module" src="' . esc_url($src) . '" id="' . $handle . '" data-base-path="' . $libraryDirShort . '" ></script>';
		}

		return $tag;
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
		if(!empty($editor_settings['styles'])) {
			$editor_settings['styles'] = [];
		}

		return $editor_settings;
	}
}
