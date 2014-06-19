<?php
App::uses('File', 'Utility');
App::uses('Folder', 'Utility');

App::uses('Param', 'Uploadable.Lib');
App::uses('Image', 'Uploadable.Lib');

App::uses('EasyLog', 'Uploadable.Lib');

class Upload {

/**
 * Copies uploaded information into a permanent destination
 *
 * @param string|Array $src Either a path to a file, or a $_POST data array
 * @param string|Array $dst Eitehr a path or an array of paths
 * @param Array $options Optional additional parameters
 **/
	public static function copy($src, $dst, $options = null) {
		EasyLog::log("Starting Upload copy");
		$options = array_merge(array(
			'filename' => null,			// Preferred name of the new file
			'oldFilename' => null,		// Existing filename that will be deleted before copying
			'isImage' => false,			// If this file is an image
			'randomPath' => false,		// Adds an additional set of random paths between the directory and file name
		), $options);
		
		// Accepts a $_POST data array or single string
		if (is_array($src)) {
			$filename = $src['name'];
			$src = $src['tmp_name'];
		} else {
			$filename = array_pop(explode(DS, $src));
		}

		$src = self::_dsFixFile($src);
		$TmpFile = new File($src);
						
		$File = new File($filename);
		$ext = $File->ext();
		
		// Finds the destination filename
		if (isset($options['filename']) && $options['filename'] != '') {
			$filename = $options['filename'];
		}
		
		if (!empty($ext) && strpos($filename, '.') === false) {
			//Adds an extension if none has been provided
			$filename .= '.' . $ext;
		}
		$filename = self::_dsFixFile($filename);


		if (!is_array($dst)) {
			$dst = [$dst];
		}

		$results = [];
		foreach ($dst as $dir => $conversionRules) {
			if (is_int($dir)) {
				$dir = $conversionRules;
				$conversionRules = array();
			}

			$dir = self::_dsFixFile($dir);
			$dirParts = explode(DS, $dir);
			$lastDirItem = array_pop($dirParts);

			// Checks if the directory contains a filename
			if (strpos($lastDirItem, '.') !== false) {
				$dstFilename = $lastDirItem;
				$dir = implode(DS, $dirParts);
			} else {
				$dstFilename = $filename;
			}

			// Adds random path if necessary
			if (!empty($options['randomPath'])) {
				if ($options['randomPath'] !== true) {
					$randomPath = self::randomPath($filename, $options['randomPath']);
				} else {
					$randomPath = self::randomPath($filename);
				}
				$dir = Folder::addPathElement($dir, $randomPath);
			}
			if (strpos($dstFilename, DS)) {
				$filenameDirs = explode(DS, $dstFilename);
				$dstFilename = array_pop($filenameDirs);
				$dir = Folder::slashTerm(Folder::addPathElement($dir, implode(DS, $filenameDirs)));
			}

			// Makes sure directory exists
			if (!is_dir( $dir)) {
				EasyLog::log('Creating directory: ' . $dir);
				if (!mkdir($dir, 0777, true)) {
					EasyLog::error('Upload file directory, ' . $dir . ' does not exist and could not be created');
				}
			}

			//Removes old existing file
			if (!empty($options['oldFilename'])) {
				$oldFile = $dir . $options['oldFilename'];
				if (is_file($oldFile)) {
					unlink($oldFile);
				}
			}
			
			$dst = $dir . $dstFilename;

			if ($options['isImage']) {
				$success = self::copyImage($src, $dst, $conversionRules);
			} else {
				$success = self::copyFile($src, $dst);
			}

			$result = compact('dst', 'src', 'success');

			if (!$success) {
				EasyLog::error("Could not upload file $dstFilename to directory $dir");
			} else {
				$result += [
					'filename' => $dstFilename,
					'size' => $TmpFile->size(),
					'type' => !empty($data['type']) ? $data['type'] : null,
					'ext' => $ext,
					'location' => $dst,
				];
				@chmod($dst, 0777);
			}

			$results[] = $result;
		}

		$return = compact('results') + [
			'log' => [
				'msgs' => EasyLog::getLog(),
				'errors' => EasyLog::getErrors()
			]
		];
		EasyLog::log("Finished Upload copy");
		return $return;
	}

	public static function copyImage($src, $dst, $rules = []) {
		EasyLog::log("Copying image from $src to $dst");
		$Src = new File($src);

		$img = Image::createFromFile($src);
		if (!$img) {
			EasyLog::error("Could not create image resource from $src.");
			if (!is_file($src)) {
				EasyLog::error("Could not find the source image: $src");
			}
			return false;
		}
		
		$ext = $Src->ext();
		if (!empty($rules['convert'])) {
			$ext = $rules['convert'];
		}
		if (!empty($rules['max'])) {
			//Don't allow the image to be sized past a max width or height
			$img = Image::constrain($img, $rules['max'][0], $rules['max'][1]);
		}
		if (!empty($rules['set'])) {
			//Force an image to fit dimensions. Sizes until it fits, the crops off anything hanging off the sides
			$img = Image::constrainCrop($img, $rules['set'][0], $rules['set'][1]);
		}
		if (!empty($rules['setSoft'])) {
			//Force an image to fit dimensions. Sizes until it all fits. If dimensions don't match, background will show
			$img = Image::constrainCrop($img, $rules['setSoft'][0], $rules['setSoft'][1], true);
		}
		
		if (!$img) {
			EasyLog::error('Image conversion failed');
			return false;
		}

		$dstParts = explode(DS, $dst);
		$dstName = array_pop($dstParts);
		$dstDir = Folder::slashTerm(implode(DS, $dstParts));

		EasyLog::log("Copying image filename '$dstName' into directory '$dstDir'");
		EasyLog::log(compact('dst', 'src'));
		
		if (!is_dir( $dstDir)) {
			if (!mkdir($dstDir, 0777, true)) {
				EasyLog::error('Upload image directory, ' . $dstDir . ' does not exist and could not be created');
			}
		}

		$dst = $dstDir . $dstName;
		if (!Image::imageOutput($ext, $img, $dst, 100)) {
			EasyLog::error("Could not upload image $dstName from $src to directory $dstDir");
			return false;
		} else {
			@chmod($dst, 0755);
		}
		return true;
	}

	public static function copyFile($src, $dst) {
		$Src = new File($src);
		$success = $Src->copy($dst, true);
		if (!$success) {
			EasyLog::error($Src->errors());
		}
		return $success;
	}

/**
 * Builds a semi random path based on the id to avoid having thousands of files
 * or directories in one directory. This would result in a slowdown on most file systems.
 *
 * Works up to 5 level deep
 *
 * @see http://en.wikipedia.org/wiki/Comparison_of_file_systems#Limits
 * @param mixed $string
 * @param integer $level
 * @return mixed
 * @access public
 */
	public static function randomPath($string, $level = 3) {
		if (!$string) {
			throw new Exception(__('First argument is not a string!'));
		}
		$string = crc32($string);
		$decrement = 0;
		$path = null;
		for ($i = 0; $i < $level; $i++) {
			$decrement = $decrement -2;
			$path .= sprintf("%02d" . DS, substr('000000' . $string, $decrement, 2));
		}
		return $path;
	}

	protected static function _isUploadedFile($data){
		if (
			(!isset($data['error']) || $data['error'] == 0) &&
			(!empty( $data['tmp_name']) && $data['tmp_name'] != 'none')) {
			return true;
		}
		return false;
	}

	//Fixes issues with multiple types of directory separators in a filename
	private static function _dsFixFile($filename) {
		$ds = DS;
		$notDs = DS == '/' ? '\\' : '/';
		$filename = str_replace($notDs, $ds, $filename);
		$filename = str_replace($ds . $ds, $ds, $filename);
		return $filename;
	}
}