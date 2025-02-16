<?php
namespace Doubleedesign\Comet\WordPress;

class EmbeddedPlugins {
	private array $embedded_plugins = [
		'/vendor/humanmade/block-supports-extended/block-supports-extended.php'
	];

	function __construct() {
		foreach ($this->embedded_plugins as $plugin) {
			$full_path = dirname(__DIR__, 1) . $plugin;
			if (file_exists($full_path)) {
				require_once $full_path;
			}
			else {
				error_log('Comet Components: Embedded plugin not found: ' . $full_path);
			}
		}

		add_action('init', [$this, 'add_to_cache'], 20);
		add_filter('all_plugins', [$this, 'list_in_admin'], 20);
	}

	private function get_absolute_path($plugin_path): string {
		// Where this is called from can yield slightly different results which is weird
		// but I don't know why and am just going to work around it
		$path = str_replace('\\', '/', (dirname(__FILE__, 3) . '/' . $plugin_path));
		if (!file_exists($path)) {
			$path = str_replace('\\', '/', (dirname(__FILE__, 2) . '/' . $plugin_path));
		}

		return $path;
	}

	private function get_relative_path($plugin_path): string {
		return 'comet-plugin' . str_replace('\\', '/', $plugin_path);
	}

	private function get_plugin_definition($plugin_path): array {
		$full_path = $this->get_absolute_path($plugin_path);

		return get_plugin_data($full_path, true, false);
	}

	/**
	 * Function to add our embedded plugins to where get_plugins() looks
	 * to trick WP into thinking they're installed in the way it expects.
	 * TODO: How to mark it as active at the right time so false positives/negatives don't happen and cause erroneous admin messages?
	 * @return void
	 */
	public function add_to_cache(): void {
		$plugin_cache = wp_cache_get('plugins', 'plugins');
		if (!$plugin_cache) return;

		$defs = [];
		$relative_paths = array_map([$this, 'get_relative_path'], $this->embedded_plugins);
		foreach ($relative_paths as $relative_path) {
			if (isset($plugin_cache[''][$relative_path])) continue;
			$defs[$relative_path] = $this->get_plugin_definition($relative_path);
		}

		$plugin_cache[''] = array_merge($plugin_cache[''], $defs);
		wp_cache_set('plugins', $plugin_cache, 'plugins');
	}

	public function list_in_admin($plugins): array {
		foreach ($this->embedded_plugins as $plugin_path) {
			$plugin_data = $this->get_plugin_definition($plugin_path);
			$relative_path = $this->get_relative_path($plugin_path);

			$plugins[$relative_path] = $plugin_data;
			$plugins[$relative_path]['Embedded'] = true;
			$plugins[$relative_path]['EmbeddedBy'] = 'Comet Components';
			$plugins[$relative_path]['Network'] = false;
		}

		return $plugins;
	}
}
