<?php
App::uses('Hash', 'Utility');

class EmbeddedImageHelper extends AppHelper {
	public $name = 'EmbeddedImage';
	public $helpers = [
		'Html', 
		'Form', 
		'Layout.FormLayout', 
		'Layout.DisplayText',
		'Uploadable.UploadableImage'
	];

	private $_embedImageResult = null;
	private $_embedImageOptions = null;

	public function beforeRender($viewFile) {
		$this->Html->script('Uploadable.embedded_image', ['inline' => false]);
		return parent::beforeRender($viewFile);
	}

	public function input($options = []) {
		if (!empty($options['model'])) {
			$model = $options['model'];
		} else if (!empty($this->request->params['models'])) {
			$model = reset($this->request->params['models'])['className'];
		} else {
			throw new Exception ('Could not use input without associated model');
		}
		
		$View = $this;
		return $this->FormLayout->inputList(function($count) use ($View, $model) {
			$prefix = "EmbeddedImage.$count";
			$uid = $count + 1;
			if ($View->Html->value("$prefix.uid")) {
				$uid = $View->Html->value("$prefix.uid");
			} else if ($View->Html->value('EmbeddedImage')) {
				// Finds largest stored UID
				foreach ($View->Html->value('EmbeddedImage') as $embeddedImage) {
					if ($embeddedImage['uid'] > $uid) {
						$uid = $embeddedImage['uid'] + 1;
					}
				}
			}

			$out = '';
			$out .= $View->Form->hidden("$prefix.id");
			$out .= $View->Form->hidden("$prefix.uid", ['value' => $uid]);
			$out .= $View->Form->hidden("$prefix.model", ['value' => $model]);

			$out .=  $View->FormLayout->inputCopy('tmp', array(
				'class' => 'input-small',
				'name' => "$prefix.tmp",
				'form' => false,
				'value' => "<Photo $uid>",
				'label' => 'Copy and Paste',
			));
			$out .= $View->UploadableImage->input("$prefix.filename", ['size' => 'thumb', 'class' => 'no-clone']);

			return $out;
		}, [
			'model' => 'EmbeddedImage',
			'class' => 'embedded-image-input-list',
		]);
	}

	// Stores the images result
	public function setImages($result, $options = []) {
		// Assigns the DisplayText callback to swap out images
		$this->_embedImageResult = $result;
		$this->_embedImageOptions = $options;

		$this->DisplayText->registerTextMethod('embedImages', [$this, 'replace']);
	}

	public function replace($text, $result = null, $options = []) {
		if (!empty($result)) {
			$this->_embedImageResult = $result;
		} else {
			$result = $this->_embedImageResult;
		}

		if (!empty($options)) {
			$this->_embedImageOptions = $options;
		} else {
			$options = $this->_embedImageOptions;
		}

		$options = array_merge([
			'size' => null,
		], $options);
		extract($options);

		if (isset($result['EmbeddedImage'])) {
			$result = $result['EmbeddedImage'];
		}
		$replace = [];
		$uids = Hash::combine($result, '{n}.uid', '{n}.uid');
		debug($text);
		if (preg_match_all('/<Photo ([\d]+)([^>]*]){0,1}>/', $text, $matches)) {
			debug($matches);

		}
		//foreach ($result as $row) {
		//	$replace["<Photo {$row['uid']}>"] = $this->UploadableImage->image($row, 'filename', $size);
		//}
		return str_replace(array_keys($replace), $replace, $text);
	}
}