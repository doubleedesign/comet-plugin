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

		add_action('init', [$this, 'register_blocks'], 10, 2);
		add_action('acf/include_fields', [$this, 'register_block_fields'], 10, 2);
		add_filter('allowed_block_types_all', [$this, 'set_allowed_blocks'], 10, 2);
		add_action('init', [$this, 'register_core_block_styles'], 10);
		add_action('block_type_metadata', [$this, 'register_core_block_variations'], 10);
		add_action('block_type_metadata', [$this, 'update_some_core_block_descriptions'], 10);
		add_action('init', [$this, 'register_custom_attributes'], 5);
		add_filter('block_type_metadata', [$this, 'customise_core_block_options'], 15, 1);
		add_filter('block_type_metadata', [$this, 'control_block_parents'], 15, 1);
	}

	/**
	 * Get the names of all custom blocks defined in this plugin via JSON files in the blocks folder
	 * @return array
	 */
	function get_custom_block_names(): array {
		$folder = dirname(__DIR__, 1) . '/src/blocks/';
		$block_folders = scandir($folder);
		$blocks = array_map(fn($block) => 'comet/' . $block, $block_folders);

		return $blocks;
	}

	/**
	 * Register custom blocks
	 * @return void
	 */
	function register_blocks(): void {
		$block_folders = scandir(dirname(__DIR__, 1) . '/src/blocks');

		foreach($block_folders as $block_name) {
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

				$shortName = $block_name; // folder name in this case
				$pascalCaseName = Utils::pascal_case($shortName); // should match the class name without the namespace
				$cssPath = dirname(__DIR__, 1) . "/vendor/doubleedesign/comet-components-core/src/components/$pascalCaseName/$shortName.css";
				if(file_exists($cssPath)) {
					$handle = Utils::kebab_case($block_name) . '-style';
					$pluginFilePath = dirname(plugin_dir_url(__FILE__)) . "/vendor/doubleedesign/comet-components-core/src/components/$pascalCaseName/$shortName.css";
					wp_register_style(
						$handle,
						$pluginFilePath,
						[],
						COMET_VERSION
					);

					wp_enqueue_style($handle);
				}
			}
			// Block has variations that align to a component name, without the overarching block name being used for a rendering class
			else if(isset($block_json->variations)) {
				// TODO: Actually check for matching component classes
				register_block_type($folder, [
					'render_callback' => BlockRenderer::render_block_callback("comet/$block_name")
				]);

				foreach($block_json->variations as $variation) {
					$shortName = Utils::pascal_case(array_reverse(explode('/', $variation->name))[0]);
					$shortNameLower = strtolower($shortName);
					$filePath = dirname(__DIR__, 1) . "/vendor/doubleedesign/comet-components-core/src/components/$shortName/$shortNameLower.css";

					if(file_exists($filePath)) {
						$handle = Utils::kebab_case($variation->name) . '-style';
						$pluginFilePath = dirname(plugin_dir_url(__FILE__)) . "/vendor/doubleedesign/comet-components-core/src/components/$shortName/$shortNameLower.css";
						wp_register_style(
							$handle,
							$pluginFilePath,
							[],
							COMET_VERSION
						);

						wp_enqueue_style($handle);
					}
				}

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
		$all_block_types = WP_Block_Type_Registry::get_instance()->get_all_registered();
		// Read core block list from JSON file
		$core = array_keys($this->block_support_json['core']['supported']);
		// Custom blocks in this plugin
		$custom = $this->get_custom_block_names();
		// Third-party plugin block types
		$plugin = array_filter($all_block_types, fn($block_type) => str_starts_with($block_type->name, 'ninja-forms/'));
		// Block types for the current site - based on theme textdomain matching block prefix
		$theme = wp_get_theme()->get('TextDomain');
		$current_site = array_filter($all_block_types, fn($block_type) => str_starts_with($block_type->name, "$theme/"));

		$result = array_merge(
			$core,
			$custom,
			array_keys($current_site),
			array_column($plugin, 'name')
		// add core or plugin blocks here if:
		// 1. They are to be allowed at the top level
		// 2. They Are allowed to be inserted as child blocks of a core block (note: set custom parents for core blocks in addCoreBlockParents() in blocks.js if not allowing them at the top level)
		// No need to include them here if they are only being used in one or more of the below contexts:
		// 1. As direct $allowed_blocks within custom ACF-driven blocks and/or
		// 2. In a page/post type template defined programmatically and locked there (so users can't delete something that can't be re-inserted)
		// TODO: When adding post support, there's some post-specific blocks that may be useful
		);

		return $result;
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
	 * Register some additional attribute options
	 * Notes: Requires the block-supports-extended plugin, which is installed as a dependency via Composer
	 *        To see the styles in the editor, you must override the JavaScript edit function for the block (see block-registry.js)
	 * @return void
	 */
	function register_custom_attributes(): void {
		Block_Supports_Extended\register('color', 'theme', [
			'label'    => __('Colour theme'),
			'property' => 'background',
			// TODO: Support for comet/panels
			'selector' => '.%1$s wp-block-button__link wp-block-callout wp-block-file-group wp-block-steps wp-block-pullquote',
			'blocks'   => ['core/button', 'comet/callout', 'comet/file-group', 'comet/steps', 'core/pullquote'],
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
			'selector' => '.%1$s wp-block-heading wp-block-paragraph wp-block-pullquote',
			'blocks'   => ['core/heading', 'core/paragraph', 'core/pullquote'],
		]);

		// Note: Remove the thing the custom attribute is replacing, if applicable, using block_type_metadata filter
	}

	/**
	 * Override core block.json configuration
	 * Note: $metadata['allowed_blocks'] also exists and is an array of block names,
	 * so presumably allowed blocks can be added and removed here too
	 * @param $metadata
	 * @return array
	 */
	function customise_core_block_options($metadata): array {
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
		))[0]['blocks'] ?? null;

		// Remove support for some things from all blocks
		if(isset($metadata['supports'])) {
			$metadata['supports'] = array_diff_key(
				$metadata['supports'],
				array_flip(['spacing', 'typography', 'shadow', 'dimensions'])
			);
		}

		// All layout blocks
		if(in_array($name, $layout_blocks)) {
			$metadata['supports']['color']['background'] = true;
			$metadata['supports']['color']['gradients'] = false;
			$metadata['supports']['color']['text'] = false;
			$metadata['supports']['color']['__experimentalDefaultControls'] = [
				'background' => true,
				'text'       => false,
			];
		}

		// All typography blocks
		if(in_array($name, $typography_blocks)) {
			if($name !== 'comet/call-to-action') {
				$metadata['supports']['color']['background'] = false;
				$metadata['supports']['color']['gradients'] = false;
				$metadata['supports']['color']['__experimentalDefaultControls'] = [
					'text'       => false, // replaced with custom attribute because it wasn't working
					'background' => false
				];
			}
			$metadata['supports']['__experimentalBorder'] = false;
			$metadata['supports']['border'] = false;
		}

		if($name === 'core/buttons') {
			$metadata['supports']['layout'] = array_merge(
				$metadata['supports']['layout'],
				[
					'allowEditing'           => true, // allow selection of the enabled layout options
					'allowSwitching'         => false,
					'allowOrientation'       => true,
					'allowJustification'     => true,
					'allowVerticalAlignment' => false
				]
			);
		}
		if($name === 'core/button') {
			$metadata['attributes'] = array_diff_key(
				$metadata['attributes'],
				array_flip(['textAlign', 'textColor', 'width'])
			);

			$metadata['supports']['color']['text'] = false;
			$metadata['supports']['color']['gradients'] = false;
			$metadata['supports']['color']['background'] = false;
			$metadata['supports']['__experimentalBorder'] = false;
			$metadata['supports']['color']['__experimentalDefaultControls'] = [
				'background' => false,
				'theme'      => true
			];
		}

		// Group block
		// Remember: Row, Stack, and Grid are variations of Group so any settings here will affect all of those if you enable those variations
		if($name === 'core/group') {
			$metadata['supports'] = array_diff_key(
				$metadata['supports'],
				array_flip(['__experimentalSettings', 'align', 'position'])
			);

			$metadata['supports']['layout'] = array_merge(
				$metadata['supports']['layout'],
				[
					'allowEditing' => false, // allow selection of the enabled layout options
//					'allowSwitching'         => false, // disables selection of flow/flex/constrained/grid because we're deciding that with CSS
//					'allowOrientation'       => false, // disable vertical stacking option
//					'allowJustification'     => true,
//					'allowVerticalAlignment' => true,
				]
			);
		}

		// Columns
		if($name === 'core/columns') {
			$metadata['attributes']['tagName'] = [
				'type'    => 'string',
				'default' => 'div'
			];
		}
		if($name === 'core/columns' && isset($metadata['supports']['layout'])) {
			$metadata['supports']['layout'] = array_merge(
				$metadata['supports']['layout'],
				[
					'allowEditing'           => true,  // allow selection of any enabled layout options
					'allowSwitching'         => false, // selection of flow/flex/constrained/grid - false because we're deciding that with CSS
					'allowOrientation'       => false, // vertical stacking option
					'allowJustification'     => false, // selection of horizontal alignment
					'allowVerticalAlignment' => false, // prevent double-up - this one adds a class, but the attribute is an attribute which is preferred for my programmatic handling
				]
			);
		}
		if($name === 'core/column') {
			if(!is_array($metadata['supports']['layout'])) {
				$metadata['supports']['layout'] = [];
			}
			$metadata['supports']['layout'] = array_merge(
				$metadata['supports']['layout'],
				[
					'allowEditing'           => false,
					'allowInheriting'        => false,
					'allowSwitching'         => false,
					'allowJustification'     => false,
					'allowVerticalAlignment' => false // also use the attribute here, don't add a class name
				]
			);
		}

		// Gallery
		if($name === 'core/gallery') {
			$metadata['supports']['align'] = false;
			$metadata['supports']['color']['background'] = false;
			$metadata['supports']['color']['gradients'] = false;

			// This removes some attributes from the definition but doesn't remove them from the editor; that's done in JS
			unset($metadata['attributes']['linkTarget']);
			unset($metadata['attributes']['randomOrder']);
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
			$supported = ['comet/container', 'core/group', 'comet/panel-content'];
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
			$supported = ['comet/container', 'core/column', 'core/group', 'core/details', 'comet/panel-content'];

			if(isset($metadata['parent'])) {
				$metadata['parent'] = array_merge($metadata['parent'], $supported);
			}
			else {
				$metadata['parent'] = $supported;
			}
		}
		if($name === 'core/freeform') {
			$metadata['parent'] = ['comet/container', 'comet/group', 'comet/column', 'comet/panel-content'];
		}

		return $metadata;
	}
}
