<?php
use Doubleedesign\Comet\Core\FileGroup;

$colorTheme = $block['colorTheme'] ?? 'primary';
$files = array_map(function($file) {
	return [
		'url'         => $file['url'],
		'title'       => $file['title'],
		'description' => $file['description'],
		'mimeType'    => $file['mime_type'],
		// TODO: Add field for to have their own colour theme, so certain files can be highlighted by using a different colour
	];
}, get_field('files'));
$component = new FileGroup(['colorTheme' => $colorTheme], $files);
$component->render();
