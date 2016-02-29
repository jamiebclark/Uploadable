<h3>Upload new image</h3>
<?php
echo $this->Form->create($modelName, [
	'type' => 'file',
	'class' => 'field-upload-image-upload-form',
	'url' => [
		'plugin' => 'uploadable',
		'controller' => 'field_upload',
		'action' => 'upload',
		$modelName,
		$result[$alias][$primaryKey],
		$field,
		$size,
	]
]);

echo $this->Form->hidden('id', ['value' => $result[$alias][$primaryKey]]);
echo $this->Form->hidden('field', ['value' => $field]);
echo $this->Form->hidden('size', ['value' => $size]);
echo $this->Form->hidden('redirect', ['value' => $redirect]);

echo $this->FieldUploadImage->input($field, ['size' => $size, 'model' => $alias]);
echo $this->Form->submit('Upload Photo');
echo $this->Form->end();
