<?php
namespace Doubleedesign\Comet\WordPress;

class TinyMceConfig {

	public function __construct() {
		add_action('after_setup_theme', [$this, 'editor_css']);
		add_filter('tiny_mce_before_init', [$this, 'init_settings']);
		add_filter('tiny_mce_before_init', [$this, 'editor_css_acf']);
		add_filter('tiny_mce_before_init', [$this, 'add_theme_colours']);
		add_filter('tiny_mce_plugins', [$this, 'remove_custom_colours']);
		add_filter('mce_buttons', [$this, 'remove_buttons']);
		add_filter('mce_buttons', [$this, 'add_styleselect']);
		add_filter('tiny_mce_before_init', [$this, 'populate_styleselect']);
	}


	/**
	 * Utility function to get theme colour palette from theme.json
	 * @return array
	 */
	static function get_theme(): array {
		$theme = get_stylesheet_directory() . '/theme.json';
		$parent = get_template_directory() . '/theme.json';
		$plugin = __DIR__ . '/theme.json';
		$json = null;

		if(file_exists($theme)) {
			$json = file_get_contents($theme);
		}
		else if(file_exists($parent)) {
			$json = file_get_contents($parent);
		}
		else if(file_exists($plugin)) {
			$json = file_get_contents($plugin);
		}

		$colours = $json ? json_decode($json, true)['settings']['color']['palette'] : [];

		return array_reduce($colours, function($acc, $item) {
			$acc[$item['slug']] = $item['color'];
			return $acc;
		}, []);
	}


	/**
	 * Load CSS in default TinyMCE
	 * @return void
	 */
	function editor_css(): void {
		$comet_global_styles = COMET_COMPOSER_VENDOR_URL . '/doubleedesign/comet-plugin/src/components/global.css';
		$theme_editor_css = get_stylesheet_directory() . '/editor.css';
		$css = [
			$comet_global_styles,
			$theme_editor_css
		];

		foreach($css as $file) {
			if(file_exists($file)) {
				add_editor_style($file);
			}
		}
	}


	/**
	 * Some default TinyMCE settings
	 * @param $settings
	 *
	 * @return array
	 */
	function init_settings($settings): array {
		$settings['paste_as_text'] = true; // default to "Paste as text"
		$settings['wordpress_adv_hidden'] = false; // keep the "kitchen sink" open

		return $settings;
	}


	/**
	 * Load CSS in ACF WYSIWYG fields
	 * Ref: https://pagegwood.com/web-development/custom-editor-stylesheets-advanced-custom-fields-wysiwyg/
	 * @param $mce_init
	 *
	 * @return array
	 */
	function editor_css_acf($mce_init): array {
		$content_css = '/styles-editor.css';

		if(file_exists(get_stylesheet_directory() . $content_css)) {
			$version = filemtime(get_stylesheet_directory() . $content_css);
			$content_css = get_stylesheet_directory_uri() . $content_css . '?v=' . $version; // it caches hard, use this to force a refresh
			if(isset($mce_init['content_css'])) {
				$content_css_new = $mce_init['content_css'] . ',' . $content_css;
				$mce_init['content_css'] = $content_css_new;
			}
		}

		return $mce_init;
	}


	/**
	 * Add predefined colours to TinyMCE
	 * @param $settings
	 *
	 * @return array
	 */
	function add_theme_colours($settings): array {
		$colours = $this->get_theme();

		if(!empty($colours)) {
			$map = array();
			foreach($colours as $slug => $value) {
				$map[] = '"' . $value . '","' . $slug . '"';
			}

			$settings['textcolor_map'] = '[' . implode(',', $map) . ']';
		}

		return $settings;
	}


	/**
	 * Remove the Color Picker plugin from TinyMCE
	 * so only the theme colours specified in starterkit_tinymce_add_custom_colours can be selected
	 * @param array $plugins An array of default TinyMCE plugins.
	 */
	function remove_custom_colours(array $plugins): array {
		foreach($plugins as $key => $plugin_name) {
			if('colorpicker' === $plugin_name) {
				unset($plugins[$key]);

				return $plugins;
			}
		}

		return $plugins;
	}


	/**
	 * Remove unwanted buttons from TinyMCE
	 * @param array $buttons
	 *
	 * @return array
	 */
	function remove_buttons(array $buttons): array {
		$to_remove = array(
			'wp_more',
			// Ability to add a "read more" tag
			'wp_adv'
			// Toggle for the "kitchen sink" i.e. second toolbar row, which is set to stay open in starterkit_tinymce_init_settings
		);

		foreach($buttons as $index => $button) {
			if(in_array($button, $to_remove)) {
				unset($buttons[$index]);
			}
		}

		return $buttons;
	}


	/**
	 * Add custom formats menu to TinyMCE
	 * @param $buttons
	 *
	 * @return array
	 */
	function add_styleselect($buttons): array {
		// Insert as the second item by splitting the existing array and then recombining with the new button
		return array_merge(
			array_slice($buttons, 0, 1),
			array('styleselect'),
			array_slice($buttons, 1)
		);
	}


	/**
	 * Populate custom formats menu in TinyMCE
	 * Notes: - 'selector' for block-level element that format is applied to; 'inline' to add wrapping tag e.g.'span'
	 *        - Using 'attributes' to apply the classes instead of 'class' ensures previous classes are replaced rather than added to
	 *        - 'styles' are inline styles that are applied to the items in the menu, not the output; options are pretty limited but enough to add things like colours
	 *          (further styling customisation to the menu may be done in the admin stylesheet)
	 * @param $settings
	 *
	 * @return array
	 */
	function populate_styleselect($settings): array {
		$style_formats = array(
			array(
				'title'   => 'Lead paragraph',
				'block'   => 'p',
				'classes' => 'is-style-lead'
			),
			array(
				'title' => 'Small style heading',
				'items' => array(
					array(
						'title'   => 'H2',
						'block'   => 'h2',
						'classes' => 'is-style-small'
					),
					array(
						'title'   => 'H3',
						'block'   => 'h3',
						'classes' => 'is-style-small'
					),
					array(
						'title'   => 'H4',
						'block'   => 'h4',
						'classes' => 'is-style-small'
					),
					array(
						'title'   => 'H5',
						'block'   => 'h5',
						'classes' => 'is-style-small'
					),
					array(
						'title'   => 'H6',
						'block'   => 'h6',
						'classes' => 'is-style-small'
					)
				)
			),
			array(
				'title' => 'Accent style heading',
				'items' => array(
					array(
						'title'   => 'H2',
						'block'   => 'h2',
						'classes' => 'is-style-accent'
					),
					array(
						'title'   => 'H3',
						'block'   => 'h3',
						'classes' => 'is-style-accent'
					),
					array(
						'title'   => 'H4',
						'block'   => 'h4',
						'classes' => 'is-style-accent'
					),
					array(
						'title'   => 'H5',
						'block'   => 'h5',
						'classes' => 'is-style-accent'
					),
					array(
						'title'   => 'H6',
						'block'   => 'h6',
						'classes' => 'is-style-accent'
					)
				)
			)
		);

		$settings['style_formats'] = json_encode($style_formats);
		unset($settings['preview_styles']);

		return $settings;
	}
}
