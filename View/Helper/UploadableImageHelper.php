<?php
App::uses('AttrString', 'Uploadable.Lib');

class UploadableImageHelper extends AppHelper {
	public $name = 'UploadableImage';
	public $helpers = array('Html', 'Form');

	public function beforeRender($viewFile, $options = []) {
		$this->Html->css('Uploadable.style', null, ['inline' => false]);
		return parent::beforeRender($viewFile, $options);
	}
	
	public function input($name, $options = []) {
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

		$out = $this->Form->input("$dataName.$field", ['type' => 'file'] + $options);
		if ($this->Html->value("uploadable_storage.$dataName")) {
			$data = unserialize(base64_decode($this->Html->value("uploadable_storage.$dataName")));
		} else if ($this->Html->value($dataName)) {
			$data = $this->Html->value($dataName);
		} 

		if (!empty($data)) {
			if ($img = $this->image($data, $field, null, [
					'style' => 'max-width: 100%'
				])) {
				$out .= $img;
				$out .= $this->Form->input("$dataName.$field.delete", [
					'type' => 'checkbox',
					'label' => 'Delete photo',
				]);
			}
			$out .= $this->Form->hidden("uploadable_storage.$dataName", [
				'value' => base64_encode(serialize($data))
			]);
		}
		return $out;
	}

	public function image($data, $field, $size = null, $options = [], $defaultOptions = []) {
		if (is_string($options)) {
			$options = $this->parseOptions($options);
		}
		if (is_array($defaultOptions)) {
			$options = array_merge($defaultOptions, $options);
		}

		if (!empty($options['size'])) {
			$size = $options['size'];
			unset($options['size']);
		}

		$src = $this->getDataFieldSrc($data, $field, $size);

		$url = $urlOptions = false;
		if (!empty($options['url'])) {
			$url = $options['url'];
			$urlOptions = ['escape' => false];
			unset($options['url']);
		}

		if (!empty($options['media'])) {
			$options = $this->addClass($options, 'media-object');
			if ($url) {
				$urlOptions = $this->addClass($urlOptions, 'pull-left');
			} else {
				$options = $this->addClass($options, 'pull-left');
			}
			unset($options['media']);
		}

		$alignClass = '';
		if (!empty($options['align'])) {
			switch($options['align']) {
				case 'left':
					$alignClass = 'pull-left';
					break;
				case 'right':
					$alignClass = 'pull-right';
					break;
				case 'center':
					$alignClass = 'text-center';
					break;
			}
			unset($options['align']);
		}

		if (!empty($options['caption'])) {
			$caption = $this->Html->tag('p', $options['caption'], ['class' => 'caption', 'escape' => false]);
			$captionOptions = $this->addClass(['escape' => false, 'class' => 'thumbnail'], $alignClass);
			unset($options['caption']);
		} else {
			$options = $this->addClass($options, $alignClass);
		}


		if (!empty($src)) {
			$return = $this->Html->image($src, $options);
		} else {
			$return = '';
		}
		if (!empty($caption)) {
			$return = $this->Html->tag('span', 
				$return . $caption, 
				$captionOptions
			);
		} 

		if ($url) {
			$return = $this->Html->link($return, $url, $urlOptions);
		}

		return $return;
	}

/**
 * Finds the information pertaining to the Uploadable image from a passed result
 * 
 * @param Array $data Passed result
 * @param string $field The table field with file informaiton store
 * @return Array|null the Uploadable array of information
 **/
	private function getDataField($data, $field) {
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
 	private function getDataFieldSize($data, $field, $size = null) {
 		$dataField = $this->getDataField($data, $field);
 		$dataFieldSize = null;
		if (!empty($dataField['sizes'])) {
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
	private function getDataFieldSrc($data, $field, $size = null) {
		$dataFieldSize = $this->getDataFieldSize($data, $field, $size);
		if (!empty($dataFieldSize['src'])) {
			return $dataFieldSize['src'];
		} else {
			return null;
		}
	}

	private function parseOptions($options) {
		if (is_string($options)) {
			if (substr($options, 0, 1) == '|') {
				$options = AttrString::parse($options);
			} else {
				// Legacy format
				$options = AttrString::parseColonQuote($options);
			}
		}
		$options = $this->addClass($options, 'uploadable-image');
		return $options;
	}
}