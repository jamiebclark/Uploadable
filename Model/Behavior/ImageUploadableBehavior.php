<?php
App::import('Lib', 'Uploadable.Image');
App::import('Behavior', 'Uploadable.Uploadable');
class ImageUploadableBehavior extends UploadableBehavior {
	function setup(Model $Model, $settings = array()) {
		$settings['exts'] = array('gif', 'jpg', 'jpeg', 'png');
		return parent::setup($Model, $settings);
	}
	
	//Manually saves the image to the system, bypassing the need to upload it
	function saveImage(Model $Model, $id, $imageFile, $saveOptions = array()) {
		return $this->saveUpload($Model, $id, $imageFile, $saveOptions);
	}
	
	//Returns the file name and path of the image
	function getImageFilename(Model $Model, $id, $dir = null, $root = false) {
		return $this->getUploadFilename($Model, $id, $dir, $root);
	}

	/**
	 * Copies uploaded file to its new destination
	 * Overwrites UploadableBehavior's function
	 **/
	function copyUploadedFile($src, $dst, $options = array()) {
		$this->_log("Copying image from $src to $dst");
		
		$Src = new File($src);
		$Dst = new File($dst);

		$img = Image::createFromFile($src);
		if (!$img) {
			$msg = "Could not create image resource from $src.";
			if (!is_file($src)) {
				$msg .= ' Image is NOT a file';
			}
			$this->_error($msg);
			return false;
		}
		
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
			$this->_error('Image conversion failed');
			return false;
		}

		$dstDir = $Dst->Folder->path . DS;
		$dstName = $Dst->name() . '.' . $Dst->ext();

		$this->_log(compact('dst', 'src'));
		
		
		if (!is_dir( $dstDir)) {
			if (!mkdir($dstDir, 0777, true)) {
				$this->_error('Upload image directory, ' . $dstDir . ' does not exist and could not be created');
			}
		}
		$dst = $dstDir . $dstName;
		if (!Image::imageOutput($ext, $img, $dst, 100)) {
			$this->_error("Could not upload image $dstName from $src to directory $dstDir");
			return false;
		} else {
			@chmod($dst, 0755);
		}
		return true;
	}
	
	function afterFileSaveCheck(Model $Model, $value, $options = array()) {
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
