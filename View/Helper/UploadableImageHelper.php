<?php
class UploadableImageHelper extends AppHelper {
	public $name = 'UploadableImage';
	public $helpers = array('Html', 'Form');

	public function input($field, $options = []) {

		list($model, $field) = pluginSplit($field);		
		if (empty($model)) {
			if (empty($options['model'])) {
				$modelNames = array_keys($this->request->params['models']);
				$model = array_shift($modelNames);
			} else {
				$model = $options['model'];
			}
		}
		unset($options['model']);

		$out = $this->Form->input("$model.$field", ['type' => 'file']);
		if (isset($this->request->data[$model])) {
			if ($img = $this->image($this->request->data[$model], $field, null, [
					'style' => 'max-width: 100%'
				])) {
				$out .= $img;
				$out .= $this->Form->input("$model.$field.delete", [
					'type' => 'checkbox',
					'label' => 'Delete photo',
				]);
			}
		}
		return $out;
	}

	public function image($data, $field, $size = null, $options = []) {
		if (isset($data['uploadable'][$field])) {
			$data = $data['uploadable'][$field];
		} else if (isset($data[$field]) && is_array($data[$field])) {
			$data = $data[$field];
		} else {
			$data = null;
		}

		if (!empty($data['sizes'])) {
			// If no size is passed, pick the first size
			if ($size === null) {
				$imageInfo = reset($data['sizes']);
			} else if (!empty($data['sizes'][$size])) {
				$imageInfo = $data['sizes'][$size];
			}
		}

		if (!empty($imageInfo['src'])) {
			return $this->Html->image($imageInfo['src'], $options);
		} else {
			return '';
		}

	}
}