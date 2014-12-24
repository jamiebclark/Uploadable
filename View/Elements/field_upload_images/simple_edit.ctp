<?php
/**
 * A form for updating a single field image
 *
 **/
$default = array(
	'className' => Inflector::classify($this->request->params['controller']),
	'field' => null,
	'size' => null,
);
extract(array_merge($default, compact(array_keys($default))));

echo $this->Form->create($className, array('type' => 'file'));
echo $this->Form->hidden('id');
echo $this->FieldUploadImage->input($field, compact('size'));
echo $this->Form->button('Update Image', array('class' => 'btn btn-primary btn-lg'));
echo $this->Form->end();