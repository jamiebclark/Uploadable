<h3>Resize the image</h3>
<div class="row">
	<div class="col-sm-9">
		<?php 
		echo $this->Form->create($alias, [
			'class' => 'field-upload-image-resize-form',
			'url' => [
				'plugin' => 'uploadable',
				'controller' => 'field_upload',
				'action' => 'resize',
				$modelName,
				$result[$alias][$primaryKey],
				$field,
				$size,
			]
		]);
		echo $this->Form->hidden('id', ['value' => $result[$alias][$primaryKey]]);
		echo $this->Form->hidden('redirect', ['value' => $redirect]);
		echo $this->element('Uploadable.field_upload_images/resize_input', compact('field', 'size'));
		echo $this->Form->submit('Resize');
		echo $this->Form->end();
		?>
	</div>
	<div class="col-sm-3">
		<label>Existing Image</label>
		<?php 
		echo $this->FieldUploadImage->image($result[$alias], $field, $size, [
			'modified' => true,
			'style' => 'max-width:100%;',
		]);
		?>
	</div>
</div>