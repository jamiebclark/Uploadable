<?php
/**
 * Works with the Uploadable Plugin to handle the finer points of uploading a file
 *
 * @package app.Plugin.Uploadable.Lib
 **/

App::uses('File', 'Utility');
App::uses('Folder', 'Utility');

App::uses('Param', 'Uploadable.Lib');
App::uses('Image', 'Uploadable.Lib');
App::uses('EasyLog', 'Uploadable.Lib');

App::uses('CakeEvent', 'Event');
App::uses('CakeEventManager', 'Event');

class Upload {

/**
 * Copies uploaded information into a permanent destination
 *
 * @param string|Array $src Either a path to a file, or a $_POST data array
 * @param string|Array $dst Eitehr a path or an array of paths
 * @param Array $options Optional additional parameters
 * @return array An array with the result information
 * @access public
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
			$dst = array($dst);
		}

		$results = array();
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
				$result += array(
					'filename' => $dstFilename,
					'size' => $TmpFile->size(),
					'type' => !empty($data['type']) ? $data['type'] : null,
					'ext' => $ext,
					'location' => $dst,
				);
				@chmod($dst, 0777);
			}

			$results[] = $result;
		}

		$return = compact('results') + array(
			'log' => array(
				'msgs' => EasyLog::getLog(),
				'errors' => EasyLog::getErrors()
			)
		);
		EasyLog::log("Finished Upload copy");
		return $return;
	}

/**
 * Creates a gitignore file in the upload directory if one hasn't been created
 * 
 * @param string $dir The upload directory path
 * @return void
 * @access public
 **/
	public static function gitIgnore($dir) {
		$dir = Folder::slashTerm(self::_dsFixFile($dir));
		
		$emptyFile = $dir . 'empty';
		$ignoreFile = $dir . '.gitignore';
		
		// Creates an empty file to make sure folder is saved in git
		if (!is_file($emptyFile)) {
			if (!($file = fopen($emptyFile, 'w'))) {
				throw new Exception("Could not create empty file: $emptyFile");
			}
			fclose($file);
		}
		
		// Creates .gitignore file
		if (!is_file($ignoreFile)) {
			if (!$file = fopen($ignoreFile, 'w')) {
				throw new Exception("Could not create .gitignore file: $ignoreFile");
			}
			fwrite($file, "*\n");			//Ignores everything
			fwrite($file, "!empty\n");		//Except the empty file
			fwrite($file, "default.jpg\n");	//And a default file
			fclose($file);			
		}
	}

/**
 * Copies an image from a destination to a source, processing it through a number of rules
 *
 * @param string $src The image source
 * @param string $dst The copy destination
 * @param array $rules An array of rules to dictate how the image is processed
 * 		- `convert` - Convert the image to a new image type
 *		- `max` - Resize an image if it's boundaries exceed this value
 *		- `set` - Force an image to fit dimensions. Sizes until it fits, the crops off anything hanging off the sides
 *		- `setSoft` - Force an image to fit dimensions. Sizes until it all fits. If dimensions don't match, background will show
 * 		- `copyResized`
 * @return bool True on success, false on failure
 * @access public
 **/
	public static function copyImage($src, $dst, $rules = array()) {
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
		if (empty($dst)) {
			EasyLog::error("Destination directory is blank");
			return false;
		}

		// Since we're converting to JPG, remove the transparency color
		$img = Image::replaceTransparency($img, array(255,255,255));
		
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
			EasyLog::log('Forcing image to fit dimensions: ' . implode(', ', $rules['set']));
			$img = Image::constrainCrop($img, $rules['set'][0], $rules['set'][1]);
		}
		if (!empty($rules['setSoft'])) {
			//Force an image to fit dimensions. Sizes until it all fits. If dimensions don't match, background will show
			$img = Image::constrainCrop($img, $rules['setSoft'][0], $rules['setSoft'][1], true);
		}

		if (!empty($rules['copyResized'])) {
			$srcX = $rules['copyResized']['srcX'];
			$srcY = $rules['copyResized']['srcY'];
			$srcW = $rules['copyResized']['srcW'];
			$srcH = $rules['copyResized']['srcH'];
			$dstW = $rules['copyResized']['dstW'];
			$dstH = $rules['copyResized']['dstH'];
			$img = Image::cropPortion($img, $srcX, $srcY, $srcW, $srcH, $dstW, $dstH);
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
	
//debug(compact(['dstName', 'dstDir', 'dst', 'src']));
	
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

		self::dispatchAfterUpload($dst);
		return true;
	}

	public static function dispatchAfterUpload($path) {
		if (is_object($path)) {
			$File = $path;
		} else {
			$File = new File($path);
		}
		$event = new CakeEvent('File.afterUpload', $File);
		return CakeEventManager::instance()->dispatch($event);
	}

/**
 * Copies a file from a source to a destination path
 *
 * @param string $src The file path
 * @param string $dst The destination where it will be copied
 * @return bool True on success, false on failure
 * @access public
 **/
	public static function copyFile($src, $dst) {
		$Src = new File($src);
		App::uses('File', 'Utility');

		$success = $Src->copy($dst, true);
		if (!$success) {
			EasyLog::error($Src->errors());
		}
		self::dispatchAfterUpload($dst);
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

/**
 * Deletes any empty subfolders within an upload directory
 * 
 * @param string $dir The path to the upload directory
 * @param bool $isRoot Makes sure the recursive calls remembers if the current iteration is the root folder or not
 * @return bool True on success. False on failure
 * @access public
 **/
	public static function removeEmptySubFolders($dir, $isRoot = true) {
		$empty = true;
		$files = glob(Folder::slashTerm($dir) . '*');
		foreach ($files as $file) {
			if (is_dir($file)) {
				$empty = self::removeEmptySubFolders($file, false);
			} else {
				$empty = false;
			}
		}
		if ($empty && !$isRoot && is_dir($dir)) {
			rmdir($dir);
		}
		return $empty;
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
