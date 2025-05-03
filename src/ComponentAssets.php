<?php
namespace Doubleedesign\Comet\WordPress;

class ComponentAssets {

	function __construct() {
		if(!function_exists('register_block_type')) {
			// Block editor is not available.
			return;
		}

		add_action('wp_enqueue_scripts', [$this, 'enqueue_comet_combined_component_css'], 10);
		add_action('wp_enqueue_scripts', [$this, 'enqueue_comet_combined_component_js'], 10);
		add_filter('script_loader_tag', [$this, 'script_type_module'], 10, 3);
		add_filter('script_loader_tag', [$this, 'script_base_path'], 10, 3);

		if(is_admin()) {
			add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_css'], 10);
			add_action('enqueue_block_assets', [$this, 'enqueue_block_editor_css'], 10);
			add_filter('block_editor_settings_all', [$this, 'remove_gutenberg_inline_styles']);
		}
	}

	/**
	 * Combined stylesheet for all components, to be used both for the front-end and the back-end editor
	 * @return void
	 */
	function enqueue_comet_combined_component_css(): void {
		$libraryDir = COMET_COMPOSER_VENDOR_URL . '/doubleedesign/comet-components-core';
		wp_enqueue_style('comet-components', "$libraryDir/dist/dist.css", array(), COMET_VERSION, 'all');
	}

	/**
	 * Bundled JS for all components for the front-end
	 * @return void
	 */
	function enqueue_comet_combined_component_js(): void {
		$libraryDir = COMET_COMPOSER_VENDOR_URL . '/doubleedesign/comet-components-core';
		wp_enqueue_script('comet-components-js', "$libraryDir/dist/dist.js", array(), COMET_VERSION, true);
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
			$libraryDir = COMET_COMPOSER_VENDOR_URL . '/doubleedesign/comet-components-core';
			$libraryDirShort = str_replace(get_site_url(), '', $libraryDir);
			$tag = '<script type="module" src="' . esc_url($src) . '" id="' . $handle . '" data-base-path="' . $libraryDirShort . '" ></script>';
		}

		return $tag;
	}

	/**
	 * Block editor overrides
	 * @return void
	 */
	function enqueue_block_editor_css(): void {
		$currentDir = plugin_dir_url(__FILE__);
		$pluginDir = dirname($currentDir, 1);

		$global_css_path = COMET_COMPOSER_VENDOR_URL . '/doubleedesign/comet-components-core/src/components/global.css';
		$block_css_path = $pluginDir . '/src/editor.css';

		wp_enqueue_style('comet-global-styles', $global_css_path, array('wp-edit-blocks'), COMET_VERSION);
		wp_enqueue_style('comet-block-styles', $block_css_path, array('wp-edit-blocks'), COMET_VERSION);
	}


	/**
	 * Admin CSS overrides for things outside the block editor
	 * @return void
	 */
	function enqueue_admin_css(): void {
		$currentDir = plugin_dir_url(__FILE__);
		$pluginDir = dirname($currentDir, 1);

		$admin_css_path = $pluginDir . '/src/admin.css';
		wp_enqueue_style('comet-admin-styles', $admin_css_path, array(), COMET_VERSION);
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
