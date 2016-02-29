<?php
if (empty($inline)) {
	$inline = false;
}
$out = $this->Html->css('Uploadable./vendor/jcrop/css/jquery.Jcrop.min', null, compact('inline'));
$out .= $this->Html->script([
	'Uploadable./vendor/jcrop/js/jquery.Jcrop.min',
	'Uploadable.cropbox',
], compact('inline'));

if ($inline) {
	echo $out;
}
