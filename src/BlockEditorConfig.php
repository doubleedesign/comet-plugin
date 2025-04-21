<?php
namespace Doubleedesign\Comet\WordPress;

use Doubleedesign\Comet\Core\Utils;
use WP_Theme_JSON_Data;

class BlockEditorConfig extends JavaScriptImplementation {
	private array $final_theme_json = [];
	private array $block_category_map = [];
	private array $block_support_json = [];

	function __construct() {
		parent::__construct();
		$this->block_support_json = json_decode(file_get_contents(plugin_dir_path(__FILE__) . 'block-support.json'), true);

		// Set up block category map so it's not run every time assign_blocks_to_categories is called
		foreach($this->block_support_json['categories'] as $category) {
			foreach($category['blocks'] as $block_name) {
				$this->block_category_map[$block_name] = $category['slug'];
			}
		}

		remove_action('enqueue_block_editor_assets', 'wp_enqueue_editor_block_directory_assets');
		remove_action('enqueue_block_editor_assets', 'gutenberg_enqueue_block_editor_assets_block_directory');

		add_action('init', [$this, 'load_merged_theme_json'], 5, 1);
		add_action('init', [$this, 'register_page_template'], 15, 2);

		add_filter('block_categories_all', [$this, 'customise_block_categories'], 5, 1);
		add_filter('register_block_type_args', [$this, 'assign_blocks_to_categories'], 11, 2);

		add_filter('block_editor_settings_all', [$this, 'block_inspector_single_panel'], 10, 2);
		add_filter('use_block_editor_for_post_type', [$this, 'selective_gutenberg'], 10, 2);
		add_action('after_setup_theme', [$this, 'disable_block_template_editor']);
		add_filter('block_editor_settings_all', [$this, 'disable_block_code_editor'], 10, 2);

		add_action('admin_enqueue_scripts', [$this, 'admin_css']);
	}


	/**
	 * Load theme.json file from this plugin to set defaults of what's supported in the editor
	 * and combine it with the theme's theme.json for actual theme stuff like colours
	 * @return void
	 */
	function load_merged_theme_json(): void {
		delete_option('wp_theme_json_data'); // clear cache

		add_filter('wp_theme_json_data_theme', function($theme_json) {
			$plugin_theme_json_path = plugin_dir_path(__FILE__) . 'theme.json';
			$plugin_theme_json_data = json_decode(file_get_contents($plugin_theme_json_path), true);
			if(is_array($plugin_theme_json_data)) {
				return new WP_Theme_JSON_Data(Utils::array_merge_deep($plugin_theme_json_data, $theme_json->get_data()));
			}

			return $theme_json;
		});
	}


	/**
	 * Default blocks for a new page
	 * @return void
	 */
	function register_page_template(): void {
		$template = [
			[
				'comet/container',
				[]
			],
		];
		$post_type_object = get_post_type_object('page');
		$post_type_object->template = $template;
		$post_type_object->template_lock = false;
	}


	/**
	 * Register custom block categories and customise some existing ones
	 * @param $categories
	 * @return array
	 */
	function customise_block_categories($categories): array {
		$custom_categories = $this->block_support_json['categories'];
		$new_categories = [];

		foreach($custom_categories as $cat) {
			$new_categories[] = [
				'slug'  => $cat['slug'],
				'title' => $cat['name'],
				'icon'  => $cat['icon']
			];
		}

		$preferred_order = array('structure', 'ui', 'text', 'dynamic-content', 'media', 'forms');
		usort($new_categories, function($a, $b) use ($preferred_order) {
			return array_search($a['slug'], $preferred_order) <=> array_search($b['slug'], $preferred_order);
		});

		return $new_categories;
	}

	/**
	 * Modify block registration to set correct categories
	 * @param array $settings - settings of the block being registered
	 * @param string $name - name of the block being registered
	 * Note: This doesn't work for some blocks, such as Ninja Forms. Such cases can be adjusted using a filter added via JavaScript
	 *       See block-editor-config.js
	 *
	 * @return array
	 */
	function assign_blocks_to_categories(array $settings, string $name): array {
		if(isset($this->block_category_map[$name])) {
			$settings['category'] = $this->block_category_map[$name];
		}

		return $settings;
	}


	/**
	 * Display all block settings in one panel in the block inspector sidebar
	 * @param $settings
	 * @return mixed
	 */
	function block_inspector_single_panel($settings): mixed {
		$settings['blockInspectorTabs'] = array('default' => false);

		return $settings;
	}


	/**
	 * Only use the block editor for certain content types
	 * (This can be overridden by plugins depending on the priority of the filter)
	 * @param $current_status
	 * @param $post_type
	 *
	 * @return bool
	 */
	function selective_gutenberg($current_status, $post_type): bool {
		if(in_array($post_type, ['page', 'post', 'event'])) {
			return true;
		}

		return false;
	}

	/**
	 * Disable block template editor option
	 * @return void
	 */
	function disable_block_template_editor(): void {
		remove_theme_support('block-templates');
	}

	/**
	 * Disable access to the block code editor
	 */
	function disable_block_code_editor($settings, $context) {
		$settings['codeEditingEnabled'] = false;

		return $settings;
	}


	/**
	 * Scripts to hackily hide stuff (e.g., the disabled code editor button)
	 * and other CSS adjustments for simplicity
	 * @return void
	 */
	function admin_css(): void {
		$currentDir = plugin_dir_url(__FILE__);
		$pluginDir = dirname($currentDir, 1);

		wp_enqueue_style('comet-block-editor-hacks', "$pluginDir/src/block-editor-config.css", array(), COMET_VERSION);
	}
}
