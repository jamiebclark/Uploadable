<?php
/**
 * Helper to assist with outputing information using the FieldUpload behavior in the Uploadable plugin
 *
 * @package app.Plugin.Uploadable.VIew
 **/
App::uses('AttrString', 'Uploadable.Lib');

class FieldUploadImageHelper extends AppHelper {
	public $name = 'FieldUploadImage';
	public $helpers = array('Html', 'Form');

	public function beforeRender($viewFile, $options = []) {
		$this->Html->css('Uploadable.style', null, ['inline' => false]);
		return parent::beforeRender($viewFile, $options);
	}
	
	public function resizeLink($text, $model, $id, $field, $size, $options = []) {
		return $this->Html->link($text, [
			'controller' => 'field_upload', 
			'action' => 'edit', 
			$model,
			$id, 
			$field, 
			$field,
			'plugin' => 'uploadable',
			'admin' => false,
		],
		$options);
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
		$fieldParts = explode('.', $name);
		$field = array_pop($fieldParts);
		$dataName = implode('.', $fieldParts);

		// The image display size
		if (!empty($options['size'])) {
			$size = $options['size'];
			unset($options['size']);
		} else {
			$size = null;
		}


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

		$out = $this->Form->input("$dataName.$field", ['type' => 'file'] + (array) $options);
		if ($this->Html->value("uploadable_storage.$dataName")) {
			$data = unserialize(base64_decode($this->Html->value("uploadable_storage.$dataName")));
		} else if ($this->Html->value($dataName)) {
			$data = $this->Html->value($dataName);
		} 

		if (!empty($data)) {
			if ($img = $this->image($data, $field, $size, [
					'style' => 'max-width: 100%',
					'modified' => true,
				])) {
				$out .= $img;
				$out .= $this->Form->input("$dataName.$field.delete", [
					'class' => 'checkbox',
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

/**
 * Outputs an impage passed by a FieldUpload result
 *
 * @param array $data Either the request data or the result set of the given model.
 * 	- Make sure to include the model name, eg: $result['Event'] or $this->request->data['Event']
 * @param string $field The field storing the image address
 * @param array $options The normal options passed to an Html image method
 * @param array $defaultOptions Options to be used only if they're not set elsewhere
 *
 * @return string HTML image
 **/
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

		$attrs = $this->getDataFieldSize($data, $field, $size);
		if (!empty($attrs['modified']) && !empty($options['modified'])) {
			$src .= strpos($src, '?') !== false ? '&' : '?';
			$src .= 'm=' . $attrs['modified'];
			unset($attrs['modified']);
		}

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
			$captionOptions = $this->addClass(['escape' => false, 'class' => 'uploadable-thumbnail thumbnail'], $alignClass);
			unset($options['caption']);

			// Moves certain attributes from the image options to the caption options
			$copyAttrs = ['width', 'height'];
			foreach ($copyAttrs as $attr) {
				if (isset($options[$attr])) {
					$captionOptions[$attr] = $options[$attr];
					unset($options[$attr]);
				}
			}

			// Moves some attributes from individual attributes to CSS styles
			$styleCopy = ['width', 'height'];
			foreach ($styleCopy as $attr) {
				if (isset($captionOptions[$attr])) {
					$captionOptions = $this->addClass($captionOptions, "$attr: {$captionOptions[$attr]};", 'style');
					unset($captionOptions[$attr]);
				}
			}

		} else {
			$options = $this->addClass($options, $alignClass);
		}


		if (!empty($src)) {
			$return = $this->Html->image($src, $options);
		} else {
			$return = '';
		}

		if ($url) {
			$return = $this->Html->link($return, $url, $urlOptions);
		}

		if (!empty($caption)) {
			$return = $this->Html->tag('div', $return . $caption, $captionOptions);
		} 

		return $return;
	}

	public function src($data, $field, $size = null) {
		if ($src = $this->getDataFieldSrc($data, $field, $size)) {
			if (strpos($src, '://') === false) {
				if ($src[0] != '/') {
					$src = Configure::read('App.imageBaseUrl') . $src;
				} else {
					$src = Router::url($src, true);
				}
			}
		}
		return $src;
	}

	public function editLink($model, $id, $result, $field, $size, $options = []) {
		$img = $this->image($result, $field, $size, ['modified' => true]);
		$options['escape'] = false;
		$options = $this->Html->addClass((array)$options, 'uploadable--field-upload--edit-link');
		return $this->Html->link($img, $this->editUrl($model, $id, $field, $size), $options);
	}

	public function editUrl($model, $id, $field, $size) {
		$url = [
			'controller' => 'field_upload',
			'action' => 'edit',
			$model,
			$id,
			$field,
			$size,
			'plugin' => 'uploadable',
		];
		if (!empty($this->request->params['prefix'])) {
			$url[$this->request->params['prefix']] = false;
		}
		return $url;
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