<?php
namespace Doubleedesign\Comet\WordPress;

use Doubleedesign\Comet\Core\Utils;
use WP_Theme_JSON_Data;

class BlockEditorConfig extends JavaScriptImplementation {
    private array $final_theme_json = [];
    private array $block_category_map = [];
    private array $block_support_json = [];

    public function __construct() {
        parent::__construct();
        $this->block_support_json = json_decode(file_get_contents(plugin_dir_path(__FILE__) . 'block-support.json'), true);

        // Set up block category map so it's not run every time assign_blocks_to_categories is called
        foreach ($this->block_support_json['categories'] as $category) {
            foreach ($category['blocks'] as $block_name) {
                $this->block_category_map[$block_name] = $category['slug'];
            }
        }

        remove_action('enqueue_block_editor_assets', 'wp_enqueue_editor_block_directory_assets');
        remove_action('enqueue_block_editor_assets', 'gutenberg_enqueue_block_editor_assets_block_directory');

        add_action('enqueue_block_assets', [$this, 'enqueue_global_css'], 50); // for pattern editor

        add_action('init', [$this, 'load_merged_theme_json'], 5, 1);
        add_action('init', [$this, 'register_page_template'], 15, 2);
        add_filter('allowed_block_types_all', [$this, 'post_type_allowed_block_types'], 11, 2);

        add_filter('block_categories_all', [$this, 'customise_block_categories'], 5, 1);
        add_filter('register_block_type_args', [$this, 'assign_blocks_to_categories'], 11, 2);

        add_filter('block_editor_settings_all', [$this, 'block_inspector_single_panel'], 10, 2);
        add_filter('use_block_editor_for_post_type', [$this, 'selective_gutenberg'], 10, 2);
        add_action('after_setup_theme', [$this, 'disable_block_template_editor']);
        add_filter('block_editor_settings_all', [$this, 'disable_block_code_editor'], 10, 2);

        if (is_admin()) {
            add_action('admin_enqueue_scripts', [$this, 'admin_css']);
            add_filter('admin_body_class', [$this, 'block_editor_body_class'], 10, 1);
        }
    }

    public function enqueue_global_css(): void {
        $libraryDir = COMET_COMPOSER_VENDOR_URL . '/doubleedesign/comet-components-core';
        wp_enqueue_style('comet-global-styles', "$libraryDir/src/components/global.css", array(), COMET_VERSION);
    }

    /**
     * Load theme.json file from this plugin to set defaults of what's supported in the editor
     * and combine it with the theme's theme.json for actual theme stuff like colours
     *
     * @return void
     */
    public function load_merged_theme_json(): void {
        delete_option('wp_theme_json_data'); // clear cache

        add_filter('wp_theme_json_data_theme', function($theme_json) {
            $plugin_theme_json_path = plugin_dir_path(__FILE__) . 'theme.json';
            $plugin_theme_json_data = json_decode(file_get_contents($plugin_theme_json_path), true);
            if (is_array($plugin_theme_json_data)) {
                return new WP_Theme_JSON_Data(Utils::array_merge_deep($plugin_theme_json_data, $theme_json->get_data()));
            }

            return $theme_json;
        });
    }

    /**
     * Default blocks for a new page
     *
     * @return void
     */
    public function register_page_template(): void {
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
     * Limit block types allowed on specific post types
     *
     * @param  $allowed_blocks
     * @param  \WP_Block_Editor_Context|null  $context
     *
     * @return array
     */
    public function post_type_allowed_block_types($allowed_blocks, ?\WP_Block_Editor_Context $context = null): array {
        if ($context === null) {
            return $allowed_blocks;
        }

        $post = $context->post;

        // If all blocks are allowed ($allowed_blocks = true), we need to get all registered blocks first
        if ($allowed_blocks === true) {
            $all_blocks = \WP_Block_Type_Registry::get_instance()->get_all_registered();
            $allowed_blocks = array_keys($all_blocks);
        }

        if (!isset($post->post_type)) {
            return $allowed_blocks;
        }
        // For some reason filtering for page templates doesn't work from within the theme where it really belongs :(
        // Events are filtered within the Comet Calendar plugin, where that belongs
        switch ($post->post_type) {
            case 'post':
                $filtered = array_filter($allowed_blocks, function($block) {
                    return !in_array($block, ['comet/container', 'comet/banner']);
                }, ARRAY_FILTER_USE_BOTH);

                return array_values($filtered);
            case 'page':
                $page_template = get_post_meta($post->ID, '_wp_page_template', true);

                // Disallow adding Containers at the top level of the with-nav-sidebar template
                // Note: This works in conjunction with some JavaScript that transforms Containers to Groups when switching to this template
                // TODO: This requires a page refresh to take effect, so could be improved to ensure the correct block is always used
                //       Would it be easier to refresh the block list on template change, or change Groups to Containers and vice versa on save?
                if ($page_template === 'template-page-with-nav-sidebar.php') {
                    $filtered = array_filter($allowed_blocks, function($block) {
                        return $block !== 'comet/container';
                    }, ARRAY_FILTER_USE_BOTH);

                    return array_values($filtered);
                }

                // Other templates, do nothing
                return $allowed_blocks;
            default:
                return $allowed_blocks;
        }
    }

    /**
     * Register custom block categories and customise some existing ones
     *
     * @param  $categories
     *
     * @return array
     */
    public function customise_block_categories($categories): array {
        $custom_categories = $this->block_support_json['categories'];
        $new_categories = [];

        foreach ($custom_categories as $cat) {
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
     *
     * @param  array  $settings  - settings of the block being registered
     * @param  string  $name  - name of the block being registered
     *                        Note: This doesn't work for some blocks, such as Ninja Forms. Such cases can be adjusted using a filter added via JavaScript
     *                        See block-editor-config.js
     *
     * @return array
     */
    public function assign_blocks_to_categories(array $settings, string $name): array {
        if (isset($this->block_category_map[$name])) {
            $settings['category'] = $this->block_category_map[$name];
        }

        return $settings;
    }

    /**
     * Display all block settings in one panel in the block inspector sidebar
     *
     * @param  $settings
     *
     * @return mixed
     */
    public function block_inspector_single_panel($settings): mixed {
        $settings['blockInspectorTabs'] = array('default' => false);

        return $settings;
    }

    /**
     * Only use the block editor for certain content types
     * (This can be overridden by plugins depending on the priority of the filter)
     *
     * @param  $current_status
     * @param  $post_type
     *
     * @return bool
     */
    public function selective_gutenberg($current_status, $post_type): bool {
        if (in_array($post_type, ['page', 'post', 'event'])) {
            return true;
        }

        return false;
    }

    /**
     * Disable block template editor option
     *
     * @return void
     */
    public function disable_block_template_editor(): void {
        remove_theme_support('block-templates');
    }

    /**
     * Disable access to the block code editor
     */
    public function disable_block_code_editor($settings, $context) {
        $settings['codeEditingEnabled'] = false;

        return $settings;
    }

    /**
     * Scripts to hackily hide stuff (e.g., the disabled code editor button)
     * and other CSS adjustments to the editor itself (not blocks) for simplicity
     *
     * @return void
     */
    public function admin_css(): void {
        $currentDir = plugin_dir_url(__FILE__);
        $pluginDir = dirname($currentDir, 1);

        wp_enqueue_style('comet-block-editor-hacks', "$pluginDir/src/block-editor-config.css", array(), COMET_VERSION);
    }

    /**
     * Add some utility classes to the body tag for certain page types
     *
     * @param  $classes
     *
     * @return string
     */
    public function block_editor_body_class($classes): string {
        $id = get_the_id();
        // Note: loose comparison == because
        if ($id == get_option('page_on_front')) {
            $classes .= ' is-homepage';
        }
        if ($id == get_option('page_for_posts')) {
            $classes .= ' is-page-for-posts';
        }

        return $classes;
    }
}
