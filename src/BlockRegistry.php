<?php
namespace Doubleedesign\Comet\WordPress;
use Doubleedesign\Comet\Core\{Utils};
use WP_Block_Type_Registry;
use Block_Supports_Extended;

class BlockRegistry extends JavaScriptImplementation {
	private array $block_support_json;

	function __construct() {
		parent::__construct();

		$this->block_support_json = json_decode(file_get_contents(__DIR__ . '/block-support.json'), true);

		// Comet block registration
		add_action('init', [$this, 'register_blocks'], 10, 2);
		add_action('acf/include_fields', [$this, 'register_block_fields'], 10, 2);

		// Limit editor to supported blocks
		add_filter('allowed_block_types_all', [$this, 'set_allowed_blocks'], 10, 2);

		// Register common custom attributes
		add_action('init', [$this, 'register_custom_attributes'], 5);

		// Styles and variations of core blocks
		add_action('init', [$this, 'register_core_block_styles'], 10);
		add_action('block_type_metadata', [$this, 'register_core_block_variations'], 10);

		// Override some block.json configuration for core and plugin blocks
		add_action('block_type_metadata', [$this, 'update_some_core_block_descriptions'], 10);
		add_filter('block_type_metadata', [$this, 'customise_core_block_options'], 15, 1);

		// Block relationship limitations
		//add_filter('block_type_metadata', [$this, 'control_block_parents'], 15, 1);
	}


	/**
	 * Register custom blocks
	 * @return void
	 */
	function register_blocks(): void {
		$block_folders = scandir(dirname(__DIR__, 1) . '/src/blocks');

		foreach($block_folders as $block_name) {
			if($block_name === '.' || $block_name === '..') continue;

			$folder = dirname(__DIR__, 1) . '/src/blocks/' . $block_name;
			$className = BlockRenderer::get_comet_component_class($block_name);
			if(!file_exists("$folder/block.json")) continue;

			$block_json = json_decode(file_get_contents("$folder/block.json"));

			// This is an ACF block and we want to use a render template
			if(isset($block_json->acf->renderTemplate)) {
				register_block_type($folder);
			}
			// Block name -> direct translation to component name
			else if(isset($className) && BlockRenderer::can_render_comet_component($className)) {
				register_block_type($folder, [
					'render_callback' => BlockRenderer::render_block_callback("comet/$block_name")
				]);

				// This is how we would register block stylesheets individually
				// Not used because we currently compile them all into one
				// this doesn't differentiate when a block is shown or not anyway
//				$shortName = $block_name; // folder name in this case
//				$pascalCaseName = Utils::pascal_case($shortName); // should match the class name without the namespace
//				$cssPath = dirname(__DIR__, 1) . "/vendor/doubleedesign/comet-components-core/src/components/$pascalCaseName/$shortName.css";
//				if(file_exists($cssPath)) {
//					$handle = Utils::kebab_case($block_name) . '-style';
//					$pluginFilePath = dirname(plugin_dir_url(__FILE__)) . "/vendor/doubleedesign/comet-components-core/src/components/$pascalCaseName/$shortName.css";
//					wp_register_style(
//						$handle,
//						$pluginFilePath,
//						[],
//						COMET_VERSION
//					);
//
//					wp_enqueue_style($handle);
//				}
			}
			// Block has variations that align to a component name, without the overarching block name being used for a rendering class
			else if(isset($block_json->variations)) {
				// TODO: Actually check for matching component classes
				register_block_type($folder, [
					'render_callback' => BlockRenderer::render_block_callback("comet/$block_name")
				]);

				// This is how we would register block variation stylesheets individually
				// Not used because we currently compile them all into one
				// this doesn't differentiate when a block is shown or not anyway
//				foreach($block_json->variations as $variation) {
//					$shortName = Utils::pascal_case(array_reverse(explode('/', $variation->name))[0]);
//					$shortNameLower = strtolower($shortName);
//					$filePath = dirname(__DIR__, 1) . "/vendor/doubleedesign/comet-components-core/src/components/$shortName/$shortNameLower.css";
//
//					if(file_exists($filePath)) {
//						$handle = Utils::kebab_case($variation->name) . '-style';
//						$pluginFilePath = dirname(plugin_dir_url(__FILE__)) . "/vendor/doubleedesign/comet-components-core/src/components/$shortName/$shortNameLower.css";
//						wp_register_style(
//							$handle,
//							$pluginFilePath,
//							[],
//							COMET_VERSION
//						);
//
//						wp_enqueue_style($handle);
//					}
//				}
			}
			// Block is an inner component of a variation, and we want to use a Comet Component according to the variation
			else if(isset($block_json->parent)) {
				// TODO: Actually check for matching component classes
				register_block_type($folder, [
					'render_callback' => BlockRenderer::render_block_callback("comet/$block_name")
				]);
			}
			// Fallback = WP block rendering
			else {
				register_block_type($folder);
			}
		}
	}

	/**
	 * Register ACF fields for custom blocks if there is a fields.php file containing them in the block folder
	 * @return void
	 */
	function register_block_fields(): void {
		$block_folders = scandir(dirname(__DIR__, 1) . '/src/blocks');

		foreach($block_folders as $block_name) {
			$file = dirname(__DIR__, 1) . '/src/blocks/' . $block_name . '/fields.php';

			if(file_exists($file) && function_exists('acf_add_local_field_group')) {
				require_once $file;
			}
		}
	}

	/**
	 * Limit available blocks for simplicity
	 * NOTE: This is not the only place a block may be explicitly allowed.
	 * Most notably, ACF-driven custom blocks and page/post type templates may use/allow them directly.
	 * Some core blocks also have child blocks that already only show up in the right context.
	 *
	 * @param $allowed_blocks
	 *
	 * @return array
	 */
	function set_allowed_blocks($allowed_blocks): array {
		// Get all registered blocks
		$all_block_types = WP_Block_Type_Registry::get_instance()->get_all_registered();
		// Get supported core block names
		$core = array_keys($this->block_support_json['core']['supported']);
		$filtered = array_filter($all_block_types, function($block, $name) use ($core) {
			// Filter out core blocks not in the list
			if(str_starts_with($name, 'core/')) {
				return in_array($name, $core);
			}

			// Disallow specific blocks
			if(in_array($name, ['ninja-forms/submissions-table'])) {
				return false;
			}

			// Otherwise, allow it
			return true;
		}, ARRAY_FILTER_USE_BOTH);

		// Allow blocks here if:
		// 1. They are to be allowed at the top level
		// 2. They are allowed to be inserted as child blocks of a core block
		// No need to include them here if they are only being used in one or more of the below contexts:
		// 1. As direct $allowed_blocks within custom ACF-driven blocks and/or
		// 2. In a page/post type template defined programmatically and locked there (so users can't delete something that can't be re-inserted)
		// TODO: When adding post support, there's some post-specific blocks that may be useful

		return array_keys($filtered);
	}

	/**
	 * Register custom variations of core block
	 * @param $metadata - the existing block metadata used to register it
	 * @return array
	 */
	function register_core_block_variations($metadata): array {
		$supported_core_blocks = $this->block_support_json['core']['supported'];
		$blocks_with_variations = array_filter($supported_core_blocks, fn($block) => isset($block['variations']));

		foreach($blocks_with_variations as $block_name => $data) {
			if($metadata['name'] === $block_name) {
				$metadata['variations'] = array_merge(
					$metadata['variations'] ?? array(),
					$data['variations']
				);
			}
		}

		return $metadata;
	}


	/**
	 * Update some core block descriptions with those set in the block-support.json file
	 * @param $metadata
	 * @return array
	 */
	function update_some_core_block_descriptions($metadata): array {
		$blocks = $this->block_support_json['core']['supported'];
		$blocks_to_update = array_filter($blocks, fn($block) => isset($block['description']));

		foreach($blocks_to_update as $block_name => $data) {
			if($metadata['name'] === $block_name) {
				$metadata['description'] = $data['description'];
			}
		}

		return $metadata;
	}


	/**
	 * Register some additional attribute options
	 * Notes: Requires the block-supports-extended plugin, which is installed as a dependency via Composer
	 *        To see the styles in the editor, you must override the JavaScript edit function for the block (see block-registry.js)
	 * @return void
	 */
	function register_custom_attributes(): void {
		if(!function_exists('Block_Supports_Extended\register')) {
			error_log("Can't register custom attributes because Block Supports Extended is not available", true);
			return;
		}

		Block_Supports_Extended\register('color', 'theme', [
			'label'    => __('Colour theme'),
			'property' => 'background',
			'selector' => '.%1$s wp-block-button__link wp-block-callout wp-block-file-group wp-block-steps wp-block-pullquote wp-block-comet-panels wp-block-details',
			'blocks'   => ['core/button', 'comet/callout', 'comet/file-group', 'comet/steps', 'core/pullquote', 'comet/panels', 'core/details'],
		]);

		Block_Supports_Extended\register('color', 'overlay', [
			'label'    => __('Overlay'),
			'property' => 'background',
			'selector' => '.%1$s wp-block-banner',
			'blocks'   => ['comet/banner'],
		]);

		Block_Supports_Extended\register('color', 'inline', [
			'label'    => __('Text (override default)'),
			'property' => 'text',
			'selector' => '.%1$s wp-block-heading wp-block-paragraph wp-block-pullquote wp-block-list-item',
			'blocks'   => ['core/heading', 'core/paragraph', 'core/pullquote', 'core/list-item'],
		]);

		// Note: Remove the thing the custom attribute is replacing, if applicable, using block_type_metadata filter
	}


	/**
	 * Register additional styles for core blocks
	 * @return void
	 */
	function register_core_block_styles(): void {
		register_block_style('core/paragraph', [
			'name'  => 'lead',
			'label' => 'Lead',
		]);
		register_block_style('core/heading', [
			'name'  => 'accent',
			'label' => 'Accent font',
		]);
		register_block_style('core/heading', [
			'name'  => 'small',
			'label' => 'Small text',
		]);
	}


	/**
	 * Override core block.json configuration
	 * for attributes and supports that can't be modified in theme.json
	 * @param $metadata
	 * @return array
	 */
	function customise_core_block_options($metadata): array {
		delete_transient('wp_blocks_data'); // clear cache
		$name = $metadata['name'];
		// Comet blocks should use block.json, and other plugin blocks should be modified in separate functions to keep things tidy
		if(!str_starts_with($name, 'core/')) return $metadata;

		switch($name) {
			case 'core/button':
				unset($metadata['attributes']['width']);
				$metadata['supports']['__experimentalBorder'] = false;
				return $metadata;
			case 'core/pullquote':
				$metadata['supports']['__experimentalBorder'] = false;
				return $metadata;
			default:
				return $metadata;
		}

		return $metadata;
	}

	/**
	 * Control where blocks can be placed by requiring them to be inside certain other blocks
	 * This is mainly for core blocks because custom blocks should have the parent set in block.json,
	 * but because this uses block-support.json's categories then these settings can also apply to custom blocks that are listed there
	 * @param $metadata
	 * @return array
	 */
	function control_block_parents($metadata): array {
		delete_transient('wp_blocks_data'); // clear cache
		$name = $metadata['name'];

		$typography_blocks = array_merge(
			array_values(array_filter(
				$this->block_support_json['categories'],
				fn($category) => $category['slug'] === 'text'
			))[0]['blocks'] ?? [],
			array_values(array_filter(
				$this->block_support_json['categories'],
				fn($category) => $category['slug'] === 'featured-text'
			))[0]['blocks'] ?? []
		);

		$layout_blocks = array_values(array_filter(
			$this->block_support_json['categories'],
			fn($category) => $category['slug'] === 'design'
		))[0]['blocks'] ?? [];
		$layout_blocks = array_filter($layout_blocks, fn($block) => $block !== 'core/column');

		$media_blocks = array_values(array_filter(
			$this->block_support_json['categories'],
			fn($category) => $category['slug'] === 'media'
		))[0]['blocks'] ?? [];

		$content_blocks = array_values(array_filter(
			$this->block_support_json['categories'],
			fn($category) => $category['slug'] === 'content'
		))[0]['blocks'] ?? [];

		if(in_array($name, $layout_blocks)) {
			$supported = ['comet/container', 'core/group'];
			if(isset($metadata['parent'])) {
				$metadata['parent'] = array_merge($metadata['parent'], $supported);
			}
			else {
				$metadata['parent'] = $supported;
			}
		}
		if(in_array($name, array_merge($typography_blocks, $media_blocks))) {
			if($name !== 'comet/banner') { // let banner be used at the top level
				$supported = ['comet/container', 'core/column', 'core/group'];
				if(isset($metadata['parent'])) {
					$metadata['parent'] = array_merge($metadata['parent'], $supported);
				}
				else {
					$metadata['parent'] = $supported;
				}
			}
		}
		if(in_array($name, array_merge($content_blocks, ['core/embed']))) {
			$supported = ['comet/container', 'core/column', 'core/group', 'core/details'];

			if(isset($metadata['parent'])) {
				$metadata['parent'] = array_merge($metadata['parent'], $supported);
			}
			else {
				$metadata['parent'] = $supported;
			}
		}
		if($name === 'core/freeform') {
			$metadata['parent'] = ['comet/container', 'comet/group', 'comet/column'];
		}

		// Allow group at to be used at the top level because some page templates need it in place of container being the root parent
		if($name === 'core/group') {
			unset($metadata['parent']);
		}

		return $metadata;
	}
}
