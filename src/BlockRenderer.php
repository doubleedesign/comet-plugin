<?php
namespace Doubleedesign\Comet\WordPress;
use Doubleedesign\Comet\Core\{Callout, Paragraph, Renderable, Utils, NotImplemented};
use DOMDocument;
use HTMLPurifier;
use HTMLPurifier_Config;
use ReflectionClass, ReflectionProperty, Closure, ReflectionException, RuntimeException;
use WP_Block_Type_Registry, WP_Block;

class BlockRenderer {
	private array $theme_json;

	public function __construct() {
		$this->load_merged_theme_json();
		add_filter('wp_theme_json_data_default', [$this, 'filter_default_theme_json'], 10, 1);
		add_action('init', [$this, 'override_core_block_rendering'], 20);
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

		$this->theme_json = $final_theme_json;
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
	 * @return void
	 */
	function override_core_block_rendering(): void {
		$blocks = $this->get_allowed_blocks();
		$core_blocks = array_filter($blocks, fn($block) => str_starts_with($block, 'core/'));

		// Check rendering prerequisites and bail early if one is not met
		// Otherwise, do nothing - the original WP block render function will be used
		foreach($core_blocks as $core_block_name) {
			// core/block is for reusable blocks (synced patterns), and does not have a Comet component
			// so we don't want to override it like we do with other core blocks
			if($core_block_name === 'core/block') continue;

			// WP block type exists
			$block_type = WP_Block_Type_Registry::get_instance()->get_registered($core_block_name);
			if(!$block_type) continue;

			// Corresponding Comet Component class exists
			$ComponentClass = self::get_comet_component_class($core_block_name); // returns the namespaced class name
			if(!$ComponentClass) continue;

			//...and the render method has been implemented
			$ready_to_render = $this->can_render_comet_component($ComponentClass);
			if(!$ready_to_render) continue;

			//...and one or more of the expected content fields is present
			$content_types = $this->get_comet_component_content_type($ComponentClass); // so we know what to pass to it
			if(!$content_types) continue;

			// If all of those conditions were met, override the block's render callback
			// Unregister the original block
			unregister_block_type($core_block_name);

			// Re-register the block with the original settings merged with new settings and new render callback
			register_block_type($core_block_name,
				array_merge(
					get_object_vars($block_type), // Convert object to array
					[
						// Custom front-end rendering using Comet
						'render_callback' => self::render_block_callback($core_block_name),
					]
				)
			);
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
			if($block_instance->block_type->supports['anchor']) {
				$tag = trim($attributes['tagName'] ?? 'div');
				// Create a simple DOM parser to process the $content and find the first instance of $tag, and extract the ID if it has one
				// Note: In PHP 8.4+ you will be able to use Dom\HTMLDocument::createFromString and presumably remove the ext-dom and ext-libxml Composer dependencies
				$dom = new DOMDocument();
				@$dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
				$element = $dom->getElementsByTagName($tag)->item(0);
				if($element && $element->getAttribute('id')) {
					$attributes['id'] = $element->getAttribute('id');
					$block_instance->attributes['id'] = $element->getAttribute('id');
				}
			}

			return (new BlockRenderer())->render_block($block_name, $attributes, $content, $block_instance);
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
	public function render_block(string $block_name, array $attributes, string $content, WP_Block|bool $block_instance): string {
		// Handle blocks that hit this function due to being an inner block,
		// but shouldn't be passed directly to Comet components to render
		if(
			(!str_starts_with($block_name, 'core/') && !str_starts_with($block_name, 'comet/'))
			|| (gettype($block_instance) !== 'object')
			|| in_array($block_name, ['comet/file-group', 'comet/link-group'])
		) {
			try {
				if($block_instance->block_type->render_callback) {
					$rendered = call_user_func($block_instance->block_type->render_callback, $attributes, $content, $block_instance);
					return $rendered;
				}
				else {
					throw new RuntimeException("Problem rendering $block_name in BlockRenderer->render_block()");
				}
			}
			catch(RuntimeException $e) {
				return self::handle_error($e);
			}
		}

		try {
			ob_start();
			$component = $this->block_to_comet_component_object($block_instance);
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
	private function block_to_comet_component_object(WP_Block &$block_instance): ?object {
		$block_name = $block_instance->name;
		$block_name_trimmed = array_reverse(explode('/', $block_name))[0];
		$content = $block_instance->parsed_block['innerHTML'] ?? '';
		$innerComponents = $block_instance->inner_blocks ? $this->process_innerblocks($block_instance) : [];

		// Block-specific handling of attributes and content
		if($block_name === 'core/button') {
			$this->process_button_block($block_instance);
			$content = $block_instance->attributes['content'];
		}
		if($block_name === 'core/image') {
			$this->process_image_block($block_instance);
		}
		if($block_name === 'core/pullquote') {
			$quoteContent = $block_instance->parsed_block['innerHTML'];
			// Create a simple DOM parser and find the quote and citation elements
			// Note: In PHP 8.4+ you will be able to use Dom\HTMLDocument::createFromString and presumably remove the ext-dom and ext-libxml Composer dependencies
			$dom = new DOMDocument();
			@$dom->loadHTML($quoteContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
			$quote = $dom->getElementsByTagName('p')->item(0);
			$citation = $dom->getElementsByTagName('cite')->item(0)->textContent;
			$content = $quote->textContent;
			$block_instance->attributes['citation'] = $citation;
		}

		// Process custom attributes added in BlockRegistry.php
		// Note: We do not expect blocks to have both a "colour theme" and a text colour attribute
		if(isset($block_instance->attributes['style']['elements']['theme'])) {
			$color = $block_instance->attributes['style']['elements']['theme']['color']['background'];
			$block_instance->attributes['colorTheme'] = $this->hex_to_theme_color_name($color) ?? null;
			unset($block_instance->attributes['style']);
		}
		if(isset($block_instance->attributes['style']['elements']['inline'])) {
			$color = $block_instance->attributes['style']['elements']['inline']['color']['text'];
			$block_instance->attributes['textColor'] = $this->hex_to_theme_color_name($color) ?? null;
			unset($block_instance->attributes['style']);
		}

		// Figure out the component class to use:
		// This is a block variant at the top level, such as an Accordion (variant of Panels)
		if(isset($block_instance->attributes['variant'])) {
			// use the namespaced class name matching the variant name
			if($block_instance->attributes['variant'] === 'tab') {
				$ComponentClass = self::get_comet_component_class('comet/tabs');
			}
			else {
				$ComponentClass = self::get_comet_component_class($block_instance->attributes['variant']);
			}
		}
		// This is a block within a variant that is providing its namespaced name via the providesContext property
		else if(isset($block_instance->context['comet/variant'])) {
			// use the namespaced class name matching the variant name + the block name (e.g. Accordion variant + Panel block = AccordionPanel)
			$variant = $block_instance->context['comet/variant'];
			$transformed_name = Utils::pascal_case("$variant-$block_name_trimmed");
			$ComponentClass = self::get_comet_component_class($transformed_name);
		}
		// For the core group block, detect variation based on layout attributes and use that class instead
		else if($block_name_trimmed === 'group') {
			$layout = $block_instance->attributes['layout'];
			$variation = match ($layout['type']) {
				'flex' => isset($layout['orientation']) && $layout['orientation'] === 'vertical' ? 'stack' : 'row',
				'grid' => 'grid',
				default => 'group'
			};
			$ComponentClass = self::get_comet_component_class($variation);
		}
		// This is a regular block that is not a Group or variation of a Group
		else {
			$ComponentClass = self::get_comet_component_class($block_name); // returns the namespaced class name matching the block name
		}

		if(!isset($ComponentClass)) {
			throw new RuntimeException("No component class found to render $block_name");
		}

		// Check what type of content to pass to it - an array, a string, etc
		$content_type = $this->get_comet_component_content_type($ComponentClass);

		// Create the component object
		// Self-closing tag components, e.g. <img>, only have attributes
		if($content_type[0] === 'is-self-closing') {
			$component = new $ComponentClass($block_instance->attributes);
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
	private function process_innerblocks(WP_Block $block_instance): array {
		// Handle nested reusable blocks (synced patterns)
		if($block_instance->name === 'core/block') {
			return $this->reusable_block_content_to_comet_component_objects($block_instance);
		}

		$innerBlocks = $block_instance->inner_blocks ?? null;
		if($innerBlocks) {
			$transformed = array_map(function($block) {
				// Handle reusable blocks/synced patterns
				// TODO: handle these not being Comet Components blocks
				if($block->name === 'core/block') {
					return $this->reusable_block_content_to_comet_component_objects($block);
				}

				// Handle known ACF blocks that we want to use its render template for
				if(in_array($block->name, ['comet/file-group', 'comet/link-group'])) {
					$html = $this->render_block($block->name, $block->attributes, $block->innerHTML || '', $block);
					return new PreprocessedHTML($block->attributes, $html);
				}

				try {
					return $this->block_to_comet_component_object($block);
				}
				catch(RuntimeException $e) {
					// If the block did not have a matching Comet component (at least not directly), render it as HTML
					// and then wrap it in a component that handles that
					// this should pick up client blocks, which are usually ACF blocks and this is how we want to handle those
					try {
						$html = $this->render_block($block->name, $block->attributes, $block->innerHTML || '', $block);
						return new PreprocessedHTML($block->attributes, $html);
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

	private function reusable_block_content_to_comet_component_objects(WP_Block $block): array {
		try {
			$postId = $block->parsed_block['attrs']['ref'];
			$serializedBlock = get_the_content(null, false, $postId);
			$blockObjects = parse_blocks($serializedBlock);
			// No idea why we sometimes get some empty ones here sometimes, but let's just filter them out
			// and reset the indexes using array_values
			$blockObjects = array_values(array_filter($blockObjects, fn($block) => !empty($block['blockName'])));

			// Convert to Comet component objects and return those
			$components = array_map(function($block) {
				try {
					$block_instance = new WP_Block($block);
					return $this->block_to_comet_component_object($block_instance);
				}
				catch(RuntimeException $e) {
					self::handle_error($e);
					return null;
				}
			}, $blockObjects);

			return $components;
		}
		catch(RuntimeException $e) {
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
	private function get_comet_component_content_type(string $className): ?array {
		if(!$className || !class_exists($className)) return null;

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
	protected function process_image_block(WP_Block &$block_instance): void {
		if(!isset($block_instance->attributes['id'])) return;

		$size = $block_instance->attributes['sizeSlug'] ?? 'full';
		$id = $block_instance->attributes['id'];
		$block_instance->attributes['src'] = wp_get_attachment_image_url($id, $size);
		$block_instance->attributes['caption'] = wp_get_attachment_caption($id) ?? null;
		// If the alt or title are set on the block, use those; otherwise use the image's alt/title from the media library
		$blockAlt = $block_instance->attributes['alt'] ?? null;
		$blockTitle = $block_instance->attributes['title'] ?? null;
		$block_instance->attributes['alt'] = $blockAlt ?? get_post_meta($id, '_wp_attachment_image_alt', true) ?? '';
		$block_instance->attributes['title'] = $blockTitle ?? get_the_title($id) ?? null;

		$block_content = $block_instance->parsed_block['innerHTML'];
		preg_match('/href="([^"]+)"/', $block_content, $matches);
		$block_instance->attributes['href'] = $matches[1] ?? null;
	}

	/**
	 * Process the button block's HTML and turn it into Comet-compatible attributes format
	 * @param WP_Block $block_instance
	 * @return void
	 */
	protected function process_button_block(WP_Block $block_instance): void {
		$attributes = $block_instance->attributes;
		$raw_content = $block_instance->parsed_block['innerHTML'];
		$content = '';

		// Process custom attributes
		if(isset($attributes['style'])) {
			$attributes['colorTheme'] = $this->hex_to_theme_color_name($attributes['style']['elements']['theme']['color']['background']) ?? null;
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
		// Note: In PHP 8.4+ you will be able to use Dom\HTMLDocument::createFromString and presumably remove the ext-dom and ext-libxml Composer dependencies
		$dom = new DOMDocument();
		$dom->loadHTML($clean_html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
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

	/**
	 * Loop through an array of inner blocks and prepend the given variant name to the block name,
	 * and add an attribute to pass down the variant context in the way Comet expects
	 * e.g. comet/panel => comet/accordion-panel (where accordion is the variant name)
	 * $blocks are passed by reference and are modified in-place rather than returning a new array
	 * @param $variant_name
	 * @param $blocks
	 * @return void
	 */
	protected function apply_variant_context($variant_name, &$blocks): void {
		foreach($blocks as &$block) {
			if(!str_starts_with($block['blockName'], 'comet/')) return; // Apply only to Comet component blocks

			$short_name = explode('/', $block['blockName'])[1];
			$block['blockName'] = "comet/$variant_name-$short_name";
			$block['attrs']['context'] = $variant_name;

			// Recurse into inner blocks
			if(isset($block['innerBlocks'])) {
				$this->apply_variant_context($variant_name, $block['innerBlocks']);
			}
		}
	}

	/**
	 * The custom colour attributes are stored as hex values, but we want them as the theme colour names
	 * @param $hex
	 * @return string | null
	 */
	private function hex_to_theme_color_name($hex): ?string {
		$theme = $this->theme_json['settings']['color']['palette'];

		return array_reduce($theme, function($carry, $item) use ($hex) {
			return strtoupper($item['color']) === strtoupper($hex) ? $item['slug'] : $carry;
		}, null);
	}

	/**
	 * Utility function to get all allowed blocks after filtering functions have run
	 * @return array
	 */
	public function get_allowed_blocks(): array {
		$all_blocks = WP_Block_Type_Registry::get_instance()->get_all_registered();
		$allowed_blocks = apply_filters('allowed_block_types_all', $all_blocks);

		return array_values($allowed_blocks);
	}

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
