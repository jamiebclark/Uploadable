<?php
App::uses('RemoteUrlField', 'Uploadable.Lib');

class FieldUploadHelper extends AppHelper {
	public $helpers = array('Html', 'Form');

	public function beforeRender($viewFile, $options = []) {
		$this->Html->css('Uploadable.style', null, ['inline' => false]);
		return parent::beforeRender($viewFile, $options);
	}

	public function extension($data, $field, $size = null) {
		return $this->getDataFieldSizeField($data, $field, $size, 'extension');
	}

	public function src($data, $field, $size = null) {
		if ($src = $this->getDataFieldSrc($data, $field, $size)) {
			if (strpos($src, '://') === false) {
				if ($src[0] != '/') {
					$src = $this->getBaseUrl() . $src;
				} else {
					$src = Router::url($src, true);
				}
			}
		}
		return $src;
	}

/** 
 * Outputs a file form input for use with the FieldUpload Behavior
 *
 * @param string $name The field name
 * @param array $options The FormHelper options
 *
 * @return string The form input HTML
 **/
	public function input($name, $options = []) {
		list($dataName, $field) = $this->_getDataName($name, $options);

		$fromUrl = !empty($options['fromUrl']);
		unset($options['fromUrl']);
		unset($options['model']);

		if ($fromUrl) {
			$remoteUrlInput = $this->inputRemoteUrl($name, ['label' => false]);
			$options['beforeInput'] = '<div class="or-split"><div class="or-split-col">';
			$options['afterInput'] = '</div><div class="or-split-col">' . $remoteUrlInput . '</div></div>';
		}

		$out = $this->Form->input("$dataName.$field", ['type' => 'file'] + (array) $options);
		if ($this->Html->value("uploadable_storage.$dataName")) {
			$data = unserialize(base64_decode($this->Html->value("uploadable_storage.$dataName")));
		} else if ($this->Html->value($dataName)) {
			$data = $this->Html->value($dataName);
		} 

		if (!empty($data)) {
			$out .= $this->inputDataDisplay($dataName, $data, $field, $options);
		}

		if ($fromUrl) {
			/*
			ob_start(); ?>
			<div class="row">
				<div class="col-md-6"><?php echo $out; ?></div>
				<div class="col-md-6"><?php echo $this->inputRemoteUrl($name); ?></div>
			</div><?php
			$out = ob_get_clean();
			*/
			//$options

		}

		return $out;
	}

	public function inputRemoteUrl($name, $options = []) {
		$options = array_merge($options, [
			'label' => 'From URL:',
			'placeholder' => 'http://',
			'class' => 'form-control code',
			'beforeInput' => '<div class="input-group"><div class="input-group-addon">URL:</div>',
			'afterInput' => '</div>',
		], $options);

		$name = RemoteUrlField::field($name);
		list($dataName, $field) = $this->_getDataName($name, $options);
		unset($options['model']);
		return $this->Form->input($name, $options);
	}


	private function _getDataName($name, $options = []) {
		$fieldParts = explode('.', $name);
		$field = array_pop($fieldParts);
		$dataName = implode('.', $fieldParts);
		if (!empty($fieldParts)) {
			$count = count($fieldParts);
			$model = $fieldParts[$count - 1];
			if (is_numeric($model)) {
				$index = $fieldParts[$count - 1];
				$model = $count > 1 ? $fieldParts[$count - 2] : null;
			}
		}
		// Get the form model
		if (empty($model)) {
			if (empty($options['model'])) {
				$modelNames = array_keys($this->request->params['models']);
				$model = array_shift($modelNames);
			} else {
				$model = $options['model'];
			}
		}
		unset($options['model']);
		if (empty($dataName)) {
			$dataName = $model;
		}
		return [$dataName, $field];
	}


	protected function inputDataDisplay($dataName, $data, $field, $options = []) {
		$out = '';
		$out .= $this->Form->hidden("uploadable_storage.$dataName", [
			'value' => base64_encode(serialize($data))
		]);
		return $out;
	}

/**
 * Finds the information pertaining to the Uploadable image from a passed result
 * 
 * @param Array $data Passed result
 * @param string $field The table field with file informaiton store
 * @return Array|null the Uploadable array of information
 **/
	protected function getDataField($data, $field) {
		if (isset($data['uploadable'][$field])) {
			$data = $data['uploadable'][$field];
		} else if (isset($data[$field]) && is_array($data[$field])) {
			$data = $data[$field];
		} else {
			$data = null;
		}
		return $data;
	}

/**
 * Finds the information for a specific size of image from the passed data
 *
 * @param Array $data Returned information
 * @param string $field The table field with file informaiton store
 * @param string|null $size The specific size of the image source
 * @return Array|null Array of image information if found, null if not
 **/
 	protected function getDataFieldSize($data, $field, $size = null) {
 		$dataField = $this->getDataField($data, $field);
 		$dataFieldSize = null;
 		if (empty($size) && !empty($dataField['file'])) {
 			$dataFieldSize = $dataField['file'];
 		} elseif (!empty($dataField['sizes'])) {
			// If no size is passed, pick the first size
			if ($size === null) {
				$dataFieldSize = reset($dataField['sizes']);
			} else if (!empty($dataField['sizes'][$size])) {
				$dataFieldSize = $dataField['sizes'][$size];
			}
		}
		return $dataFieldSize;
	}

/**
 * Finds just the image src from the uploadable data
 *
 * @param Array $data Returned information
 * @param string $field The table field with file informaiton store
 * @param string|null $size The specific size of the image source
 * @return string|null Src path to image if found, null if not
 **/
	protected function getDataFieldSrc($data, $field, $size = null) {
		return $this->getDataFieldSizeField($data, $field, $size, 'src');
	}

	protected function getDataFieldSizeField($data, $field, $size, $sizeField) {
		$dataFieldSize = $this->getDataFieldSize($data, $field, $size);
		if (!empty($dataFieldSize[$sizeField])) {
			return $dataFieldSize[$sizeField];
		} else {
			return null;
		}
	}

	protected function getBaseUrl() {
		return APP . 'webroot' . DS;
	}
}