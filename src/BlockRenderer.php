<?php /** @noinspection PhpMultipleClassDeclarationsInspection */
namespace Doubleedesign\Comet\WordPress;
use Doubleedesign\Comet\Core\{Callout, Paragraph, Renderable, Settings, Utils, NotImplemented};
use DOMDocument, ReflectionClass, ReflectionProperty, Closure, ReflectionException, RuntimeException, Exception;
use HTMLPurifier, HTMLPurifier_Config;
use WP_Block_Type_Registry, WP_Block;

class BlockRenderer {
	private static array $theme_json;
	private static array $block_support_json;

	public function __construct() {
		$this->load_merged_theme_json();

		add_filter('wp_theme_json_data_default', [$this, 'filter_default_theme_json'], 10, 1);

		if(is_admin()) {
			self::$block_support_json = json_decode(file_get_contents(__DIR__ . '/block-support.json'), true);
			add_action('init', [$this, 'register_core_block_stylesheets_for_editor'], 99, 2);
		}

		// Core block rendering override needs to run pretty late to ensure all blocks are registered and available in the registry instance first
		//add_action('init', [$this, 'override_core_block_rendering'], 100);
		add_filter('render_block', [$this, 'override_core_block_rendering'], 15, 3);
	}

	protected function load_merged_theme_json(): void {
		$plugin_theme_json_path = plugin_dir_path(__FILE__) . 'theme.json';
		$plugin_theme_json_data = json_decode(file_get_contents($plugin_theme_json_path), true);
		$final_theme_json = $plugin_theme_json_data;

		$theme_json_file = get_stylesheet_directory() . '/theme.json';
		if(file_exists($theme_json_file)) {
			$theme_json_data = json_decode(file_get_contents($theme_json_file), true);
			$final_theme_json = Utils::array_merge_deep($plugin_theme_json_data, $theme_json_data);
		}

		self::$theme_json = $final_theme_json;
	}

	/**
	 * Filter the default theme.json data to remove some of the WP defaults
	 * that add unwanted CSS variables
	 * @param $theme_json
	 * @return object (WP_Theme_JSON_Data, or WP_Theme_JSON_Data_Gutenberg if the Gutenberg plugin is installed to get latest features/fixes)
	 */
	function filter_default_theme_json($theme_json): object {
		$data = $theme_json->get_data();
		// Remove unused WP defaults that become the --wp--preset-* CSS variables and clog up the CSS
		$data['settings']['color']['palette']['default'] = [];
		$data['settings']['color']['duotone']['default'] = [];
		$data['settings']['color']['gradients']['default'] = [];
		$data['settings']['shadow']['presets']['default'] = [];
		$data['settings']['typography']['fontSizes']['default'] = [];
		$data['settings']['spacing']['spacingSizes']['default'] = [];

		// Remove some only on the front-end, because they're needed in the editor
		if(!is_admin()) {
			$data['settings']['dimensions']['aspectRatios'] = [];
		}

		$theme_json->update_with($data);
		return $theme_json;
	}


	/**
	 * Override core block render function and use Comet instead
	 * @param $content
	 * @param $parsed_block
	 * @param $block_instance
	 * @return string
	 * @noinspection t
	 */
	function override_core_block_rendering($content, $parsed_block, $block_instance): string {
		$blocks = $this->get_allowed_blocks();
		$core_blocks = array_values(array_filter($blocks, fn($block) => str_starts_with($block, 'core/')));
		$core_block_name = $parsed_block['blockName'];

		if($core_block_name === 'core/block') {
			return $content; // handled in a separate filter function in BlockPatternHandler
		}

		// Check rendering prerequisites and bail early if one is not met
		// Otherwise, return the original content
		if(!in_array($core_block_name, $core_blocks)) return $content;

		// WP block type exists
		$block_type = WP_Block_Type_Registry::get_instance()->get_registered($core_block_name);
		if(!$block_type) return $content;

		// Corresponding Comet Component class exists
		$ComponentClass = self::get_comet_component_class($core_block_name); // returns the namespaced class name
		if(!$ComponentClass) return $content;

		//...and the render method has been implemented
		$ready_to_render = $this->can_render_comet_component($ComponentClass);
		if(!$ready_to_render) return $content;

		//...and one or more of the expected content fields is present
		$content_types = $this->get_comet_component_content_type($ComponentClass); // so we know what to pass to it
		if(!$content_types) return $content;

		// If all of those conditions were met, override the block's render callback
		return self::render_block_callback($core_block_name)($block_instance->attributes, $content, $block_instance);
	}

	/**
	 * Register component stylesheets for the editor, to override/supplement core and third-party block styles
	 * The styles are added in the block registration override function (override_core_block_rendering) and are equivalent to what we'd put in editorStyle in a custom block.json
	 * @return void
	 */
	function register_core_block_stylesheets_for_editor(): void {
		if(!isset($this->block_support_json)) return;

		$supported_core_blocks = array_keys($this->block_support_json['core']['supported']);
		$deps = array('wp-edit-blocks');
		foreach($supported_core_blocks as $block_name) {
			$shortName = array_reverse(explode('/', $block_name))[0];
			$className = Utils::pascal_case($shortName);
			$stylesheet = COMET_COMPOSER_VENDOR_URL . "/doubleedesign/comet-components-core/src/components/$className/$shortName.css";
			if($shortName === 'column') {
				$stylesheet = COMET_COMPOSER_VENDOR_URL . "/doubleedesign/comet-components-core/src/components/Columns/Column/column.css";
			}
			if($shortName === 'buttons') {
				$stylesheet = COMET_COMPOSER_VENDOR_URL . "/doubleedesign/comet-components-core/src/components/ButtonGroup/button-group.css";
			}

			if(file_exists($stylesheet)) {
				wp_register_style("comet-$shortName-style", $stylesheet, $deps, COMET_VERSION);
			}
		}
	}

	/**
	 * Inner function for the override, to render a core block using a custom template
	 * @param string $block_name
	 *
	 * @return Closure
	 */
	public static function render_block_callback(string $block_name): Closure {
		return function($attributes, $content, $block_instance) use ($block_name) {
			if(isset($block_instance->block_type->supports['anchor']) && $block_instance->block_type->supports['anchor']) {
				$tag = trim($attributes['tagName'] ?? 'div');
				// Create a simple DOM parser to process the $content and find the first instance of $tag, and extract the ID if it has one
				// Note: In PHP 8.4+ you will be able to use Dom\HTMLDocument::createFromString
				$dom = new DOMDocument();
				@$dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOWARNING);
				$element = $dom->getElementsByTagName($tag)->item(0);
				if($element && $element->getAttribute('id')) {
					$attributes['id'] = $element->getAttribute('id');
					$block_instance->attributes['id'] = $element->getAttribute('id');
				}
			}

			return self::render_block($block_name, $attributes, $content, $block_instance);
		};
	}

	/**
	 * The function called inside render_block_callback
	 * to render blocks using Comet Components.
	 *
	 * Note: If another block is rendered inside a Comet Components block,
	 *       it will hit this function as well
	 *
	 * This exists separately from render_block_callback for better debugging - this way we see render_block() in Xdebug stack traces,
	 * whereas if this returned the closure directly, it would show up as an anonymous function
	 * @param string $block_name
	 * @param array $attributes
	 * @param string $content
	 * @param WP_Block|bool $block_instance
	 *
	 * @return string
	 */
	public static function render_block(string $block_name, array $attributes, string $content, WP_Block|bool $block_instance): string {
		// Handle blocks that hit this function due to being an inner block,
		// but shouldn't be passed directly to Comet components to render
		if(
			(!str_starts_with($block_name, 'core/') && !str_starts_with($block_name, 'comet/'))
			|| (gettype($block_instance) !== 'object')
			|| in_array($block_name, ['comet/file-group', 'comet/link-group', 'comet/upcoming-events'])
		) {
			try {
				if($block_instance->block_type->render_callback) {
					return call_user_func($block_instance->block_type->render_callback, $attributes, $content, $block_instance);
				}
				else {
					throw new RuntimeException("Problem rendering $block_name in BlockRenderer::render_block()");
				}
			}
			catch(RuntimeException $e) {
				return self::handle_error($e);
			}
		}

		try {
			ob_start();
			$component = self::block_to_comet_component_object($block_instance);
			$component->render();
			return ob_get_clean();
		}
		catch(RuntimeException $e) {
			return self::handle_error($e);
		}
	}

	/**
	 * Get the Comet class name from a block name and see if that class exists
	 * @param string $blockName
	 * @return string|null
	 */
	public static function get_comet_component_class(string $blockName): ?string {
		$blockNameTrimmed = array_reverse(explode('/', $blockName))[0];
		$className = Utils::get_class_name($blockNameTrimmed);
		if(class_exists($className)) {
			return $className;
		}

		return null;
	}

	/**
	 * Convert a WP_Block instance to a Comet component object
	 * @param WP_Block $block_instance
	 * @return object|null
	 * @throws RuntimeException
	 */
	private static function block_to_comet_component_object(WP_Block &$block_instance): ?object {
		$block_name = $block_instance->name;
		$block_name_trimmed = array_reverse(explode('/', $block_name))[0];
		$content = $block_instance->parsed_block['innerHTML'] ?? '';
		// Ignore blocks that exist just for the editing experience and skip down to their inner blocks and attributes
		if($block_name === 'comet/image-and-text') {
			$rawInner = iterator_to_array($block_instance->inner_blocks);
			$transformedAttrs = [];
			foreach($rawInner as $index => $block) {
				if($block->name === 'comet/image-and-text-image-wrapper') {
					$transformedAttrs['imageMaxWidth'] = $block->attributes['maxWidth'] ?? null;
					$transformedAttrs['imageAlign'] = $block->attributes['layout']['justifyContent'] ?? null;
					$transformedAttrs['imageFirst'] = $index === 0;
				}
				if($block->name === 'comet/image-and-text-content') {
					$transformedAttrs['contentMaxWidth'] = $block->attributes['maxWidth'] ?? null;
					$transformedAttrs['contentAlign'] = $block->attributes['layout']['justifyContent'] ?? null;
					$transformedAttrs['overlayAmount'] = $block->attributes['overlayAmount'] ?? null;
				}
			}

			$innerComponents = array_map(function($child) {
				return $child->inner_blocks ? self::process_innerblocks($child) : [];
			}, iterator_to_array($block_instance->inner_blocks));
			$innerComponents = Utils::array_flat($innerComponents);

			$block_instance->attributes = array_merge($block_instance->attributes, $transformedAttrs);
		}
		// Otherwise, process inner blocks as-is
		else {
			$innerComponents = $block_instance->inner_blocks ? self::process_innerblocks($block_instance) : [];
		}

		// Block-specific handling of attributes and content
		if($block_name === 'comet/banner') {
			self::process_banner_block($block_instance);
		}
		if($block_name === 'comet/table') {
			self::process_table_block($block_instance);
		}
		if($block_name === 'core/button') {
			self::process_button_block($block_instance);
			$content = $block_instance->attributes['content'];
		}
		if($block_name === 'core/image') {
			self::process_image_block($block_instance);
		}
		if($block_name === 'core/gallery') {
			self::process_gallery_block($block_instance);
		}
		if($block_name === 'core/pullquote') {
			$quoteContent = $block_instance->parsed_block['innerHTML'];
			// Create a simple DOM parser and find the quote and citation elements
			// Note: In PHP 8.4+ you will be able to use Dom\HTMLDocument::createFromString
			$dom = new DOMDocument();
			@$dom->loadHTML($quoteContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOWARNING);
			$quote = $dom->getElementsByTagName('p')->item(0);
			$citation = $dom->getElementsByTagName('cite')->item(0)->textContent;
			$content = $quote->textContent;
			$block_instance->attributes['citation'] = $citation;
		}

		// Process custom attributes added in BlockRegistry.php
		// Note: We do not expect blocks to have both a "colour theme" and a text colour attribute
		if(isset($block_instance->attributes['style']['elements']['theme'])) {
			$color = $block_instance->attributes['style']['elements']['theme']['color']['background'];
			$block_instance->attributes['colorTheme'] = self::hex_to_theme_color_name($color) ?? null;
			unset($block_instance->attributes['style']);
		}
		if(isset($block_instance->attributes['style']['elements']['overlay'])) {
			$color = $block_instance->attributes['style']['elements']['overlay']['color']['background'];
			$block_instance->attributes['overlayColor'] = self::hex_to_theme_color_name($color) ?? null;
			unset($block_instance->attributes['style']);
		}
		if(isset($block_instance->attributes['style']['elements']['inline']['color']['text'])) {
			$color = $block_instance->attributes['style']['elements']['inline']['color']['text'];
			$block_instance->attributes['textColor'] = self::hex_to_theme_color_name($color) ?? null;
			unset($block_instance->attributes['style']);
		}

		// Figure out the component class to use:
		// This is a block variant at the top level, such as an Accordion (variant of Panels)
		if(isset($block_instance->attributes['variant'])) {
			// use the namespaced class name matching the variant name
			if($block_instance->attributes['variant'] === 'tab') {
				$ComponentClass = self::get_comet_component_class('comet/tabs');
			}
			else if($block_name === 'comet/panels' && $block_instance->attributes['variant'] === 'responsive') {
				$ComponentClass = self::get_comet_component_class('responsive-panels');
			}
			else {
				$ComponentClass = self::get_comet_component_class($block_instance->attributes['variant']);
			}
		}
		// This is a block within a variant that is providing its namespaced name via the providesContext property
		else if(isset($block_instance->context['comet/variant'])) {
			// use the namespaced class name matching the variant name + the block name (e.g. Accordion variant + Panel block = AccordionPanel)
			$variant = $block_instance->context['comet/variant'];
			$transformed_name = "$variant-$block_name_trimmed";
			$ComponentClass = self::get_comet_component_class($transformed_name);
		}
		// This is a regular block that is not a variant, something with variant context, a Group, or variation of a Group
		else {
			$ComponentClass = self::get_comet_component_class($block_name); // returns the namespaced class name matching the block name
		}

		if(!isset($ComponentClass)) {
			throw new RuntimeException("No component class found to render $block_name");
		}

		// Check what type of content to pass to it - an array, a string, etc
		$content_type = self::get_comet_component_content_type($ComponentClass);

		// Create the component object
		// Self-closing tag components, e.g. <img>, only have attributes
		if($content_type[0] === 'is-self-closing') {
			$component = new $ComponentClass($block_instance->attributes);
		}
		else if($block_name === 'comet/table') {
			// Table HTML is converted into an array and put into inner_blocks by transform_table_block even though they aren't complete blocks
			// TODO: They could probably be made into complete blocks by having a Comet object for a Row, Cell, etc - but that might be over-abstracting it
			$component = new $ComponentClass($block_instance->attributes, $block_instance->inner_blocks);
		}
		// Most components will have string content or an array of inner components
		else if(count($content_type) === 1) {
			$component = $content_type[0] === 'array'
				? new $ComponentClass($block_instance->attributes, $innerComponents)
				: new $ComponentClass($block_instance->attributes, $content);
		}
		// Some can have both, e.g. list items can have text content and nested lists
		else if(count($content_type) === 2) {
			$component = new $ComponentClass($block_instance->attributes, $content, $innerComponents);
		}

		return $component ?? null;
	}

	/**
	 * Convert an innerBlocks array to an array of Comet component objects
	 * @param WP_Block $block_instance
	 * @return array<Renderable>
	 */
	private static function process_innerblocks(WP_Block $block_instance): array {
		// Handle nested reusable blocks (synced patterns)
		if($block_instance->name === 'core/block') {
			return self::reusable_block_content_to_comet_component_objects($block_instance);
		}

		$innerBlocks = $block_instance->inner_blocks ?? null;
		if($innerBlocks) {
			$transformed = array_map(function($block) {
				// Handle reusable blocks/synced patterns
				// TODO: Support third-party blocks in synced patterns
				if($block->name === 'core/block') {
					return self::reusable_block_content_to_comet_component_objects($block);
				}

				// Handle known ACF blocks that we want to use its render template for
				if(in_array($block->name, ['comet/file-group', 'comet/link-group'])) {
					$html = self::render_block($block->name, $block->attributes, $block->innerHTML || '', $block);
					return [new PreprocessedHTML($block->attributes, $html)];
				}

				try {
					return [self::block_to_comet_component_object($block)];
				}
				catch(RuntimeException $e) {
					// If the block did not have a matching Comet component (at least not directly), render it as HTML
					// and then wrap it in a component that handles that
					// this should pick up client blocks, which are usually ACF blocks and this is how we want to handle those
					try {
						$html = self::render_block($block->name, $block->attributes, $block->innerHTML || '', $block);
						return [new PreprocessedHTML($block->attributes, $html)];
					}
					catch(RuntimeException $e) {
						self::handle_error($e);
						return null;
					}
				}
			}, iterator_to_array($innerBlocks));

			// Ensure arrays of arrays (common with reusable blocks) get flattened to a single array
			return Utils::array_flat($transformed);
		}

		return [];
	}

	public static function reusable_block_content_to_comet_component_objects(WP_Block $block): array {
		try {
			$postId = $block->parsed_block['attrs']['ref']; // reusable blocks are posts
			$serializedBlock = get_the_content(null, false, $postId);
			$blockObjects = parse_blocks($serializedBlock);
			// No idea why we sometimes get some empty ones here sometimes, but let's just filter them out
			// and reset the indexes using array_values
			$blockObjects = array_values(array_filter($blockObjects, fn($block) => !empty($block['blockName'])));

			// Convert to Comet component objects and return those
			return array_map(function($block) {
				try {
					$block_instance = new WP_Block($block);
					return self::block_to_comet_component_object($block_instance);
				}
				catch(RuntimeException $e) {
					self::handle_error($e);
					return null;
				}
			}, $blockObjects);
		}
		catch(Exception $e) {
			self::handle_error($e);
			return [];
		}
	}

	/**
	 * Check whether the Comet Component's render method has been implemented
	 * Helpful for development/progressive adoption of components by falling back to default WP rendering if the custom one isn't ready yet
	 * @param string $className
	 * @return bool
	 */
	public static function can_render_comet_component(string $className): bool {
		try {
			$reflectionClass = new ReflectionClass($className);
			$method = $reflectionClass->getMethod('render');
			$attribute = $method->getAttributes(NotImplemented::class);
			return empty($attribute);
		}
		catch(ReflectionException $e) {
			return false;
		}
	}

	/**
	 * Check if the class is expecting string $content, an array of $innerComponents, both, or neither
	 * This is used in the block render function to determine what to pass from WordPress to the Comet component
	 * because Comet has constructors like new Thing($attributes, $content) or new Thing($attributes, $innerComponents)
	 * @param string $className
	 * @return array<string>|null
	 */
	private static function get_comet_component_content_type(string $className): ?array {
		if(!$className || !class_exists($className)) return null;

		if($className === 'Doubleedesign\Comet\Core\Table') {
			return ['array'];
		}

		$fields = [];
		$reflectionClass = new ReflectionClass($className);
		$properties = $reflectionClass->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED);

		foreach($properties as $property) {
			$fields[$property->getName()] = $property->getType()->getName();
		}

		$stringContent = isset($fields['content']) && $fields['content'] === 'string';
		$innerComponents = isset($fields['innerComponents']) && $fields['innerComponents'] === 'array';

		if($stringContent && $innerComponents) {
			return ['string', 'array'];
		}
		else if($stringContent) {
			return ['string'];
		}
		else if($innerComponents) {
			return ['array'];
		}
		return ['is-self-closing']; // Assuming if we get this far, it's something like the Image block
	}

	/**
	 * Process all image blocks and add the relevant attributes as Comet expects
	 * @param WP_Block $block_instance
	 * @return void
	 */
	protected static function process_image_block(WP_Block &$block_instance): void {
		if(!isset($block_instance->attributes['id'])) return;

		$size = $block_instance->attributes['sizeSlug'] ?? 'full';
		$id = $block_instance->attributes['id'];
		$block_instance->attributes['src'] = wp_get_attachment_image_url($id, $size);
		$block_instance->attributes['caption'] = wp_get_attachment_caption($id) ?? null;

		// If the alt/title/caption are set on the block, use those; otherwise use the image's alt/title/caption from the media library
		$blockAlt = $block_instance->attributes['alt'] ?? null;
		$block_instance->attributes['alt'] = $blockAlt ?? get_post_meta($id, '_wp_attachment_image_alt', true) ?? '';
		$blockTitle = $block_instance->attributes['title'] ?? null;
		$block_instance->attributes['title'] = $blockTitle ?? get_the_title($id) ?? null;
		$block_content = $block_instance->parsed_block['innerHTML'];
		$dom = new DOMDocument();
		@$dom->loadHTML($block_content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOWARNING);
		$blockCaption = $dom->getElementsByTagName('figcaption')->item(0);
		$block_instance->attributes['caption'] = $blockCaption
			? Utils::sanitise_content($blockCaption->textContent, Settings::INLINE_PHRASING_ELEMENTS)
			: get_post_meta($id, '_wp_attachment_image_caption', true) ?? '';

		// Similarly extract the link
		preg_match('/href="([^"]+)"/', $block_content, $matches);
		$block_instance->attributes['href'] = $matches[1] ?? null;
	}

	/**
	 * Process gallery blocks to add the relevant attributes as Comet expects
	 * @param WP_Block $block_instance
	 * @return void
	 */
	protected static function process_gallery_block(WP_Block &$block_instance): void {
		// If the gallery itself has a caption, it's probably the last element in the block's inner content array
		$maybe_caption = end($block_instance->inner_content);
		if(!empty($maybe_caption) && gettype($maybe_caption) === 'string') {
			// Strip the <figcaption> tag because Comet will add its own
			$maybe_caption = Utils::sanitise_content($maybe_caption, Settings::INLINE_PHRASING_ELEMENTS);
			// Add it as an attribute
			$block_instance->attributes['caption'] = $maybe_caption;
		}
	}

	/**
	 * Process the button block's HTML and turn it into Comet-compatible attributes format
	 * @param WP_Block $block_instance
	 * @return void
	 */
	protected static function process_button_block(WP_Block $block_instance): void {
		$attributes = $block_instance->attributes;
		$raw_content = $block_instance->parsed_block['innerHTML'];
		$content = '';

		// Process custom attributes
		if(isset($attributes['style'])) {
			$attributes['colorTheme'] = self::hex_to_theme_color_name($attributes['style']['elements']['theme']['color']['background']) ?? null;
			unset($attributes['style']);
		}

		// Turn style classes into attributes
		if(isset($attributes['className'])) {
			$classes = explode(' ', $attributes['className']);
			if(in_array('is-style-outline', $classes)) {
				$attributes['isOutline'] = true;
			}
		}

		// Use HTMLPurifier to do the initial stripping of unwanted tags and attributes for the inner content
		$config = HTMLPurifier_Config::createDefault();
		$config->set('HTML.Allowed', 'a[href|target|title|rel],span,i,b,strong,em');
		$config->set('Attr.AllowedFrameTargets', ['_blank', '_self', '_parent', '_top']);
		$purifier = new HTMLPurifier($config);
		$clean_html = $purifier->purify($raw_content);

		// Create a simple DOM parser and find the anchor tag and attributes
		// Note: In PHP 8.4+ you will be able to use Dom\HTMLDocument::createFromString
		$dom = new DOMDocument();
		$dom->loadHTML($clean_html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOWARNING);
		$links = $dom->getElementsByTagName('a');
		$link = $links->item(0);
		foreach($link->attributes as $attr) {
			$attributes[$attr->name] = $attr->value;
		}

		// Remove unwanted attributes
		unset($attributes['type']);

		// Get inner HTML with any nested tags
		foreach($link->childNodes as $child) {
			$content .= $dom->saveHTML($child);
		}

		$block_instance->attributes = $attributes;
		$block_instance->attributes['content'] = $content;
	}


	protected static function process_banner_block(WP_Block $block_instance): void {
		$attributes = $block_instance->attributes;
		$id = $attributes['imageId'] ?? null;
		$block_instance->attributes['imageAlt'] = get_post_meta($id, '_wp_attachment_image_alt', true) ?? '';
	}

	protected static function process_table_block(WP_Block $block_instance): void {
		$filteredAttributes = array_filter($block_instance->attributes, function($key) {
			return !in_array($key, ['head', 'body', 'foot']);
		}, ARRAY_FILTER_USE_KEY);

		// Create a simple DOM parser to process the WP-saved HTML
		// Note: In PHP 8.4+ you will be able to use Dom\HTMLDocument::createFromString
		$dom = new DOMDocument();
		@$dom->loadHTML($block_instance->inner_html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOWARNING);

		// Extract the caption and store it as an attribute
		$caption = $dom->getElementsByTagName('caption')->item(0);
		if($caption) {
			$filteredAttributes['tableCaption'] = array(
				'content'    => Utils::sanitise_content(self::domdocument_node_to_html($caption, $dom), Settings::INLINE_PHRASING_ELEMENTS),
				'attributes' => array(
					'textAlign' => $filteredAttributes['captionStyles']['textAlign'] ?? null,
					'position'  => $filteredAttributes['captionStyles']['captionSide'] ?? null,
				)
			);
		}
		unset($filteredAttributes['captionStyles']);
		$block_instance->attributes = $filteredAttributes;

		// Process the table HTML and transform it into an array of rows, which are each an array of cells
		$table = $dom->getElementsByTagName('table')->item(0);
		$thead = $table->getElementsByTagName('thead')->item(0);
		$tbody = $table->getElementsByTagName('tbody')->item(0);
		$tfoot = $table->getElementsByTagName('tfoot')->item(0);
		$headRows = $thead !== null ? $thead->getElementsByTagName('tr') : [];
		$bodyRows = $tbody !== null ? $tbody->getElementsByTagName('tr') : [];
		$footRows = $tfoot !== null ? $tfoot->getElementsByTagName('tr') : [];

		$transformed = array(
			'thead' => array_map(fn($row) => self::get_table_row_cells($row, $dom), iterator_to_array($headRows)),
			'tbody' => array_map(fn($row) => self::get_table_row_cells($row, $dom), iterator_to_array($bodyRows)),
			'tfoot' => array_map(fn($row) => self::get_table_row_cells($row, $dom), iterator_to_array($footRows)),
		);

		$block_instance->inner_blocks = $transformed;
	}

	private static function get_table_row_cells($row, $dom): array {
		$cells = $row->childNodes;

		// Build an array of cells with their own attributes and content
		return array_map(function($cell) use ($dom) {
			// Find what attributes are available and their values
//			if($cell->hasAttributes()) {
//				echo '<pre>';
//				echo print_r(Utils::pick_object_properties($cell->attributes, ['nodeName', 'nodeValue'], false), true);
//				echo '</pre>';
//			}

			$inlineStyles = $cell->getAttribute('style') ? Utils::array_flat(array_map(function($style) {
				$split = explode(':', $style);
				return [$split[0] => $split[1]];
			}, explode(";", $cell->getAttribute('style')))) : [];

			$attributes = [
				'tagName'         => $cell->tagName,
				'id'              => $cell->id ?? null,
				'classes'         => !empty($cell->className) ? explode(' ', $cell->className) : null,
				'scope'           => $cell->getAttribute('scope'),
				'headers'         => $cell->getAttribute('headers'),
				'colspan'         => $cell->getAttribute('colspan'),
				'width'           => $inlineStyles['width'] ?? null,
				'backgroundColor' => isset($inlineStyles['background-color']) ? self::hex_to_theme_color_name($inlineStyles['background-color']) : null,
				'textAlign'       => $inlineStyles['text-align'] ?? null,
				'verticalAlign'   => $inlineStyles['vertical-align'] ?? null,
			];

			return [
				'attributes' => array_filter($attributes), // filter out empty values
				'content'    => self::domdocument_node_to_html($cell, $dom)
			];
		}, iterator_to_array($cells));
	}

	private static function domdocument_node_to_html($node, $dom): string {
		$content = $node->textContent;
		// If the node has child nodes, get the HTML content as-is (this allows for inline tags like strong, em, images, etc)
		if($node->childNodes) {
			$content = $dom->saveHTML($node);
		}

		return $content;
	}


	/**
	 * The custom colour attributes are stored as hex values, but we want them as the theme colour names
	 * @param $hex
	 * @return string | null
	 */
	private static function hex_to_theme_color_name($hex): ?string {
		$theme = self::$theme_json['settings']['color']['palette'];

		return array_reduce($theme, function($carry, $item) use ($hex) {
			if(isset($item['slug']) && isset($item['color'])) {
				return strtoupper($item['color']) === strtoupper($hex) ? $item['slug'] : $carry;
			}
			return $carry;
		}, null);
	}


	/**
	 * Utility function to get all allowed blocks including the result of filtering functions
	 * @return array
	 */
	private function get_allowed_blocks(): array {
		$all_blocks = WP_Block_Type_Registry::get_instance()->get_all_registered();
		$allowed_blocks = apply_filters('allowed_block_types_all', array_keys($all_blocks));

		return array_values($allowed_blocks);
	}


	/**
	 * Show an error message to logged-in users who can edit posts if there is a problem rendering the block
	 * @param $error
	 * @return string
	 */
	private static function handle_error($error): string {
		if(current_user_can('edit_posts')) {
			$adminMessage = new Callout(
				['colorTheme' => 'error'],
				[
					new Paragraph(['className' => 'is-style-lead'], $error->getMessage()),
					new Paragraph([], "This message is shown only to logged-in site editors. For support, please <a href=\"https://www.doubleedesign.com.au\">contact Double-E Design.</a>")
				]
			);

			ob_start();
			$adminMessage->render();
			return ob_get_clean();
		}
		else {
			error_log($error);
			return '';
		}
	}

}
