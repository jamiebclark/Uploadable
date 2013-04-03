<?php
App::import('Lib', 'Uploadable.Image');
App::import('Behavior', 'Uploadable.Uploadable');
class ImageUploadableBehavior extends UploadBehavior {
	function setup(&$Model, $settings = array()) {
		$settings['exts'] = array('gif', 'jpg', 'jpeg', 'png');
		return parent::setup($Model, $settings);
	}
	
	//Manually saves the image to the system, bypassing the need to upload it
	function saveImage(&$Model, $id, $imageFile) {
		$settings =& $this->settings[$Model->alias];
		$uploadVar = $settings['upload_var'];

		$settings['bypass_is_uploaded'] = true;
		$data = array(
			'id' => $id,
			$uploadVar => array(
				'tmp_name' => $imageFile,
				'name' => 'UploadImage.jpg',
				'errors' => 0,
			),
		);
		$Model->create();
		$Model->id = $id;
		return $Model->save($data);
	}
	
	//Returns the file name and path of the image
	function getImageFilename(&$Model, $id, $dir = null, $root = false) {
		$settings =& $this->settings[$Model->alias];
		$fileField = 'filename'; //$settings['update']['filename'];
		$result = $Model->read(null, $id);
		$imgFile = $this->__getUploadDir($Model, compact('dir'), $root) . $result[$Model->alias][$fileField];
		return $imgFile;
	}

	//Resaves an image. Used in case you change your save directory formatting parameters
	function refreshUpload(&$Model, $id, $dir = null) {
		$settings =& $this->settings[$Model->alias];
		$tmpDir = '/home/baryaf/tmp/';
		$tmpDir = $this->__getUploadDir($Model) . 'tmp_copy_dir' . DS;
		if (!is_dir($tmpDir)) {
			mkdir($tmpDir);
		}
		
		$imgFile = $this->getImageFilename($Model, $id, $dir, true);
		$dstFile = $tmpDir . $Model->alias . '-copy-' . $id . '.jpg';

		if (is_file($imgFile) && copy($imgFile, $dstFile)) {
			$result = $this->saveImage($Model, $id, $dstFile);
			unlink($dstFile);
			return $result;
		}
		return null;
	}
	
	/**
	 * Copies uploaded file to its new destination
	 *
	 **/
	function copyUploadedFile($src, $dst, $options = array()) {
		$Src = new File($src);
		$Dst = new File($dst);

		$img = Image::createFromFile($src);
		$ext = $Src->ext();
		if (!empty($options['convert'])) {
			$ext = $options['convert'];
		}
		if (!empty($options['max'])) {
			//Don't allow the image to be sized past a max width or height
			$img = Image::constrain($img, $options['max'][0], $options['max'][1]);
		}
		if (!empty($options['set'])) {
			//Force an image to fit dimensions. Sizes until it fits, the crops off anything hanging off the sides
			$img = Image::constrainCrop($img, $options['set'][0], $options['set'][1]);
		}
		if (!empty($options['setSoft'])) {
			//Force an image to fit dimentions. Sizes until it all fits. If dimensions don't match, background will show
			$img = Image::constrainCrop($img, $options['setSoft'][0], $options['setSoft'][1], true);
		}
		
		if (!$img) {
			return false;
		}

		$dstDir = $Dst->Folder->path . DS;
		$dstName = $Dst->name() . '.' . $Dst->ext();

		
		if (!is_dir( $dstDir)) {
			if (!mkdir($dstDir, 0777, true)) {
				$this->_error('Upload image directory, ' . $dstDir . ' does not exist and could not be created');
			}
		}
		$dst = $dstDir . $dstName;
		if (!Image::imageOutput($ext, $img, $dst, 100)) {
			$this->_error("Could not upload file $dstName to directory $dstDir");
			return false;
		} else {
			@chmod($dst, 0755);
		}
		return true;
	}
	
	function afterFileSaveCheck(&$Model, $value, $options = array()) {
		$return = parent::afterFileSaveCheck($Model, $value, $options);	
		if (!isset($return) && !empty($options['location'])) {
			if ($value == 'width') {
				list($w, $h) = getimagesize($options['location']);
				$return = $w;
			} else if ($value == 'height') {
				list($w, $h) = getimagesize($options['location']);
				$return = $h;
			}
		}
		return $return;
	}
}
