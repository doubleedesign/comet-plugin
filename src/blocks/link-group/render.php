<?php
use Doubleedesign\Comet\Core\LinkGroup;

$colorTheme = $block['colorTheme'] ?? 'primary';
$links = get_field('links');
$links = array_map(function ($data) {
	return [
		'attributes' => [
			'href'   => $data['link']['url'],
			'target' => $data['link']['target']
		],
		'content'    => $data['link']['title'] ?? 'Untitled link'
	];
}, $links);
$component = new LinkGroup(['colorTheme' => $colorTheme], $links);
$component->render();
