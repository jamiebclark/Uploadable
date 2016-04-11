<?php
/**
 * Helper to assist with outputing information using the FieldUpload behavior in the Uploadable plugin
 *
 * @package app.Plugin.Uploadable.VIew
 **/
App::uses('AttrString', 'Uploadable.Lib');
App::uses('FieldUploadHelper', 'Uploadable.View/Helper');
class FieldUploadImageHelper extends FieldUploadHelper {
	public $name = 'FieldUploadImage';

	public function resizeLink($text, $model, $id, $field, $size, $options = []) {
		return $this->Html->link($text, [
			'controller' => 'field_upload', 
			'action' => 'edit', 
			$model,
			$id, 
			$field, 
			$size,
			'plugin' => 'uploadable',
			'admin' => false,
		],
		$options);
	}

	protected function inputDataDisplay($data, $field, $options = []) {
		// The image display size
		if (!empty($options['size'])) {
			$size = $options['size'];
			unset($options['size']);
		} else {
			$size = null;
		}

		$out = '';

		if ($img = $this->image($data, $field, $size, [
				'style' => 'max-width: 100%',
				'modified' => true,
			])) {
			$out .= $img;
			$out .= $this->Form->input("$dataName.$field.delete", [
				'class' => 'checkbox',
				'type' => 'checkbox',
				'label' => 'Delete',
			]);
		}
		return $out . parent::inputDataDisplay($data, $field, $options);
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

	protected function parseOptions($options) {
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

	protected function getBaseUrl() {
		return Configure::read('App.imageBaseUrl');
	}
}