<?php
$config['Uploadable'] = [
	// Default thumbnail sizes
	'sizes' => [
		'full' => [],
		'thumbnail' => ['set' => [80,80]],
		'thumbnail-sm' => ['set' => [40, 40]],
		'thumbnail-md' => ['set' => [240, 240]],
		'thumbnail-lg' => ['set' => [360, 360]],
		'banner' => ['set' => [720,360]],
		'small' => ['setSoft' => [80,80]],
		'mid' => ['max' => [120,240]],
		'tiny' => ['set' => [20,20]]
	]
];