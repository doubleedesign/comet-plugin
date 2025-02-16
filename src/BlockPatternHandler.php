<?php
namespace Doubleedesign\Comet\WordPress;

class BlockPatternHandler {

	function __construct() {
		add_filter('should_load_remote_block_patterns', '__return_false');
		add_filter('allowed_block_types_all', [$this, 'enable_block_patterns'], 20, 2);
		add_action('admin_menu', [$this, 'add_menu_item']);
		add_filter('block_editor_settings_all', [$this, 'remove_patterns_from_inserter']);
		add_action('init', [$this, 'allowed_block_patterns'], 10, 2);
	}

	/**
	 * Enable reusable blocks (synced patterns)
	 * NOTE: This must run AFTER the filtering in BlockRegistry.php or that will accidentally turn this off
	 * @param $allowed_block_types
	 * @return array
	 */
	function enable_block_patterns($allowed_block_types): array {
		$allowed_block_types[] = 'core/block';

		return $allowed_block_types;
	}


	/**
	 * Add a copy of the Patterns menu item to the top level of the admin menu, near Media/Pages/etc
	 * and call it Shared Blocks
	 * @return void
	 */
	function add_menu_item(): void {
		add_menu_page(
			'Shared blocks',
			'Shared blocks',
			'manage_options',
			'site-editor.php?path=/patterns',
			'',
			'dashicons-layout',
			11 // under Media by default
		);
	}


	/**
	 * Remove patterns from the inserter while allowing their creation/editing to stay available
	 * because the whole thing doesn't really work for clients -
	 * you can create patterns but then to be able to insert them you need to add code,
	 * the inserter UI is a bit shit IMO (because of how much it differs from blocks)
	 * and I'd rather keep everything in the one Blocks area
	 * @param $editor_settings
	 * @return array
	 */
	function remove_patterns_from_inserter($editor_settings): array {
		if (isset($editor_settings['__experimentalFeatures'])) {
			$editor_settings['__experimentalFeatures']['showPatternsList'] = false;
		}
		return $editor_settings;
	}


	/**
	 * Disable core Block Patterns for simplicity
	 * Note: Also ensure loading of remote patterns is disabled using add_filter('should_load_remote_block_patterns', '__return_false');
	 *
	 * @return void
	 */
	function allowed_block_patterns(): void {
		unregister_block_pattern('core/query-offset-posts');
		unregister_block_pattern('core/query-large-title-posts');
		unregister_block_pattern('core/query-grid-posts');
		unregister_block_pattern('core/query-standard-posts');
		unregister_block_pattern('core/query-medium-posts');
		unregister_block_pattern('core/query-small-posts');
	}
}
