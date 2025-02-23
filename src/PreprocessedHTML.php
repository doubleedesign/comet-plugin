<?php
namespace Doubleedesign\Comet\WordPress;

/**
 * A class to handle the rendering of preprocessed HTML content
 * in a similar way to how Comet Components are rendered, for compatibility of handling
 */
class PreprocessedHTML {
	private string $content;

	function __construct(array $attributes, string $content) {
		$this->content = $content;
	}

	public function render(): void {
		echo $this->content;
	}
}
