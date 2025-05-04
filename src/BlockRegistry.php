<?php
namespace Doubleedesign\Comet\WordPress;
use Doubleedesign\Comet\Core\{Utils};
use WP_Block_Type_Registry;
use Block_Supports_Extended;

class BlockRegistry extends JavaScriptImplementation {
	private array $block_support_json;
	private WP_Block_Type_Registry $wpInstance;

	function __construct() {
		parent::__construct();

		$this->block_support_json = json_decode(file_get_contents(__DIR__ . '/block-support.json'), true);
		$this->wpInstance = WP_Block_Type_Registry::get_instance();

		// Comet block registration
		add_action('init', [$this, 'register_blocks'], 10, 2);
		add_action('acf/include_fields', [$this, 'register_block_fields'], 10, 2);

		// Limit editor to supported blocks
		add_action('init', [$this, 'unregister_unsupported_blocks'], 50);
		add_filter('allowed_block_types_all', [$this, 'set_allowed_blocks'], 15, 2);

		// Register common custom attributes
		add_action('init', [$this, 'register_custom_attributes'], 5);

		// Styles for core blocks
		add_action('init', [$this, 'register_core_block_styles'], 10);

		// Override some block.json configuration for core and plugin blocks
		add_filter('block_type_metadata', [$this, 'update_some_core_block_descriptions'], 10);
		add_filter('block_type_metadata', [$this, 'customise_core_block_options'], 15, 1);

		// Block relationship limitations
		add_filter('block_type_metadata', [$this, 'control_block_parents'], 20, 1);
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
			}
			// Block has variations that align to a component name, without the overarching block name being used for a rendering class
			else if(isset($block_json->variations)) {
				// TODO: Actually check for matching component classes
				register_block_type($folder, [
					'render_callback' => BlockRenderer::render_block_callback("comet/$block_name")
				]);
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
	 * Limit available blocks for simplicity by completely unregistering unsupported core blocks
	 * This works around issues with filtering with allowed_block_types all in multiple places
	 * NOTE: May need to add more here because previously some blocks used in specific contexts still showed in those contexts
	 * @return void
	 */
	function unregister_unsupported_blocks(): void {
		$core = array_keys($this->block_support_json['core']['supported']);
		$all_block_types = array_keys($this->wpInstance->get_all_registered());

		// Unregister core blocks not in the list of supported blocks
		foreach($all_block_types as $name) {
			if(str_starts_with($name, 'core/') && !in_array($name, $core)) {
				$this->wpInstance->unregister($name);
			}
		}

		// Disallow specific blocks
		if(in_array($name, ['ninja-forms/submissions-table'])) {
			$this->wpInstance->unregister($name);
		}
	}


	/**
	 * Ensure the first time that allowed_block_types_all is run, it gets the filtered list
	 * IMPORTANT: Run this earlier than any theme or plugin's use of the filter
	 * @param $allowed_blocks
	 * @return array
	 */
	function set_allowed_blocks($allowed_blocks): array {
		if($allowed_blocks === true) {
			$registered = array_keys($this->wpInstance->get_all_registered());

			// Where unregistering doesn't work
			return array_filter($registered, function($block) {
				return !in_array($block, ['ninja-forms/submissions-table']);
			});
		}

		return $allowed_blocks;
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
	}

	/**
	 * Control where blocks can be placed by requiring them to be inside certain other blocks
	 * This is for core and third-party plugin blocks because - custom blocks should have the parent set in block.json.
	 * @param $metadata
	 * @return array
	 */
	function control_block_parents($metadata): array {
		delete_transient('wp_blocks_data'); // clear cache
		// Note: Ninja Forms has to be handled in JS because it doesn't hit this function

		// Skip Comet blocks because their parents should be handled in their block.json
		if(str_starts_with($metadata['name'], 'comet/')) return $metadata;

		$commonLayout = ['comet/container', 'comet/panel', 'core/group', 'core/column', 'comet/image-and-text'];

		$name = $metadata['name'];
		switch($name) {
			case 'core/columns':
				$metadata['parent'] = ['comet/container', 'comet/panel', 'core/group'];
				return $metadata;
			case 'core/buttons':
				$metadata['parent'] = [...$commonLayout, 'comet/call-to-action', 'comet/step'];
				return $metadata;
			case 'core/button':
				$metadata['parent'] = ['core/buttons', ...$commonLayout, 'comet/step'];
				return $metadata;
			case 'core/gallery':
			case 'core/details':
			case 'core/separator':
			case 'core/pullquote':
			case 'core/freeform':
				$metadata['parent'] = $commonLayout;
				return $metadata;
			case 'core/heading':
			case 'core/paragraph':
			case 'core/list':
			case 'core/image':
			case 'core/quote':
				$metadata['parent'] = [...$commonLayout, 'comet/step', 'comet/banner'];
				return $metadata;
			default:
				return $metadata;
		}
	}
}
