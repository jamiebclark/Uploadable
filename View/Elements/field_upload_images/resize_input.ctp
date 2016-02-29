<?php
echo $this->element('Uploadable.assets/cropbox');
$prefix = "$alias.FieldUploadCropCopy.$field";

$default = [
	'alias' => $this->Form->value("$prefix.alias"),
	'field' => $this->Form->value("$prefix.field"),
	'size' => $this->Form->value("$prefix.size"),
	'fullSizeKey' => $this->Form->value("$prefix.full_size"),
	'dimensions' => [0,0],
	'result' => $this->request->data,
];


extract(array_merge($default, compact(array_keys($default))));


echo $this->Form->hidden("$prefix.dimensions.0", ['value' => $dimensions[0]]);
echo $this->Form->hidden("$prefix.dimensions.1", ['value' => $dimensions[1]]);
echo $this->Form->hidden("$prefix.field", ['value' => $field]);
echo $this->Form->hidden("$prefix.size", ['value' => $size]);
echo $this->Form->hidden("$prefix.full_size", ['value' => $fullSizeKey]);
echo $this->FieldUploadImage->image($result[$alias], $field, $fullSizeKey, [
	'id' => 'cropbox',
	'data-select-w' => $this->Form->value("$prefix.dimensions.0"),
	'data-select-h' => $this->Form->value("$prefix.dimensions.1"),
	'modified' => true,
]);
foreach (['x','y','w','h'] as $coord):
	echo $this->Form->hidden("$prefix.$coord", ['id' => $coord]);
endforeach;
