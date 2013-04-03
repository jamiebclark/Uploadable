<?php
App::import('Lib', 'Uploadable.Param');
App::import('Lib', 'File');
App::import('Lib', 'Folder');

class UploadableBehavior extends ModelBehavior {
	var $settings = array();
	var $errorTypes = array(
		1=>'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
		'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
		'The uploaded file was only partially uploaded.',
		'No file was uploaded.',
		6=>'Missing a temporary folder.',
		'Failed to write file to disk.',
		'A PHP extension stopped the file upload.'
	); 
	
	function setup(&$Model, $settings = array()) {
		$default = array(
			'upload_dir' =>		'files/tmp/',		//Directory to upload the files
			'upload_var' => 	'add_file',			//The variable name to check for in the data
			'delete_var' => 	'delete_file',		//The variable name to check for removal of existing file
			
			'exts' =>			false,				//Array of allowed extensions
			//Extensions not allowed upload
			'block_exts' => 	array('exe', 'com', 'bat'),		
			
			'required' => 		false,				//Does the file need to be present to validate?
			'maxsize' =>		false,				//Maximum file size
			'filename_match' =>	'id',				//Creates a filename by matching it on a returned column, usually ID
			'filename_rule' =>	false,				//Rules for matching the fileanem
			//Columns in the model to update, if Column and value names are different, use Value => Database Column
			'update' => array(
				'location', 
				'type',
				'size',
			),
			
			'bypass_is_uploaded' => false,
			
			'root' =>			'web',				//Whether or not to use the web root with the upload_dir
			'random_path' =>	true,				//Adds an additional sub-directory to the given upload (to cut down on slowdown as the directory grows)


		);
		
		$Model->uploadedFiles = array();
		
		if (empty($this->settings[$Model->alias])) {
			$this->settings[$Model->alias] = $default;
		}
		
		if (!empty($settings)) {
			$this->settings[$Model->alias] = array_merge(
				$this->settings[$Model->alias],
				(array) $settings
			);
		}
	}
	
	function beforeValidate(&$Model) {
		$uploadVar = $this->settings[$Model->alias]['upload_var'];
		$settings = $this->settings[$Model->alias];
		$created = empty($Model->data[$Model->alias][$Model->primaryKey]);
		$this->_log('Looking for variable: ' . $uploadVar);
		if (!empty($Model->data[$Model->alias][$uploadVar])) {
			$this->_log('Found it!');
			$uploadArray = $Model->data[$Model->alias][$uploadVar];
			App::uses('File', 'Utility');
			$File = new File($uploadArray['name']);
			$ext = strtolower($File->ext());
			$errMsg = array();
			
			//Checks if no file is present
			$noFile = (!empty($uploadArray['error']) && $uploadArray['error'] == 4) || !$this->_isUploadedFile($Model, $uploadArray);
	
			if ($noFile) {
				$this->_log('No file found');
				if ((!empty($settings['required']) || ($created && !empty($settings['requiredCreated']))) 
					&& !$this->_isUploadedFile($Model, $uploadArray)) {
					$errMsg[] = 'You must select a file to continue';
				} else {
					//If it's not required, validate. No file will be added
					return true;
				}
			}
				
			if (!empty($uploadArray['error'])) {
				$errMsg[] = $this->errorTypes[$uploadArray['error']];
			}
			if (!empty($settings['required']) && !$this->_isUploadedFile($Model, $uploadArray)) {
				$errMsg[] = 'You must select a file to continue';
			}
			if (!empty($settings['maxsize']) && $settings['maxsize'] < $uploadArray['size']) {
				$errMsg[] = 'File exceeds the max file size';
			}
			if (!empty($settings['block_exts']) && (in_array($ext, $settings['block_exts']))) {
				$errMsg[] = 'Invalid file type: ' . $ext;
			} else if (!empty($settings['exts']) && (!in_array($ext, $settings['exts']))) {
				$errMsg[] = 'Invalid file type: ' . $ext;
			}
			
			if (!empty($errMsg)) {
				$this->_log($errMsg);
				$Model->invalidate($uploadVar, implode(', ', $errMsg));
				return false;
			}
		}
		return true;
	}
	
	function getUploadDir(&$Model, $subDir = null, $root = false) {
		$dir = $this->settings[$Model->alias]['upload_dir'];
		if (!empty($subDir) && !empty($this->settings[$Model->alias]['dirs'])) {
			$dirs = $this->settings[$Model->alias]['dirs'];
			if (!empty($dirs[$subDir]) || !empty($dirs[$subDir . '/'])) {
				$dir .= $subDir . '/';
			}
		}
		//if ($this->settings[$Model->alias]['upload_dir'] == 'web') {
		if (substr($dir,0,1) != '/') {
			$dir = '/' . $dir;
		}
		if ($root) {
			$dir = $this->getRoot($Model) . $dir;
		}
		return $dir;
	}

	function hasUploadedFile($Model) {
		$uploadVar = $this->settings[$Model->alias]['upload_var'];
		if (!empty($Model->data[$Model->alias])) {
			$data =& $Model->data[$Model->alias];
		} else {
			$data =& $Model->data;
		}
		if (empty($data[$uploadVar])) {
			return false;
		} else {
			return $this->_isUploadedFile($Model, $data[$uploadVar]);
		}
	}
		
	function beforeSave(&$Model, $options) {
		$uploadVar = $this->settings[$Model->alias]['upload_var'];
		$settings = $this->settings[$Model->alias];
		$data =& $Model->data[$Model->alias];
		//Doesn't save if file doesn't exist
		if (empty($data[$uploadVar]) || !$this->_isUploadedFile($Model, $data[$uploadVar])) {
			unset($data[$uploadVar]);
			
			if (!empty($settings['requiredToCreate'])) {
				//If entry is being created and no uploaded file, escape
				if (empty($data['id'])) {
					$Model->data = array();
					return false;
				}
			}
			
		}
		return true;
	}
	
	function afterSave(&$Model, $created) {
		//Looks to see if a file was passed as well
		$settings =& $this->settings[$Model->alias];
		$data =& $Model->data[$Model->alias];
		
		$uploadVar = $settings['upload_var'];
		$deleteVar = Param::keyCheck($settings, 'delete_var');

		//Uploads file (if success is not true, meaning the file has been saved once already)
		if (!empty($data[$uploadVar]) && empty($settings['success'])) {
			$options = array();
			
			if (!$created) {
				$filenameCol = Param::keyValCheck($settings['update'], 'filename');
				//Makes sure to remove the old file if it exists
				if (!empty($filenameCol) && !empty($data[$filenameCol])) {
					
					$options['old_filename'] = $data[$filenameCol];
					$options['filename'] = $data[$filenameCol];
					$settings['no_random'] = true;
				}
			}
			//debug('Uploading...');
			$this->uploadFile($Model, $Model->data[$Model->alias][$uploadVar], $options);
			
			unset($this->settings[$Model->alias]['set_random_path']);
			unset($this->settings[$Model->alias]['no_random']);
		} else if (!empty($data[$deleteVar])) {
			$this->deleteFile($Model, $Model->id);
			unset($data[$deleteVar]);
		}
		return true;
	}
	
	function beforeDelete(&$Model, $cascade) {
		//Removes associated files as well
		$this->deleteFile($Model, $Model->id);
		return true;
	}

	function beforeFileSave(&$Model) {
		return true;
	}
	
	function afterFileSave(&$Model) {
		$this->_updateModel($Model);
		unset($this->settings[$Model->alias]['success']);
		return true;
	}
	
	function afterFileDelete(&$Model) {
		//Resets all updated columns to blank
		$this->_updateModel($Model, true);
		return true;
	}
	
	/**
	 * Updates Model with info about the uploaded files
	 * Uses information stored in the 'update' array in settings
	 * @param Boolean $reset If true, sets all update columns to blank
	 **/
	function _updateModel(&$Model, $reset = false) {
		if ($updateCols = Param::keyCheck($this->settings[$Model->alias], 'update')) {
			
			$data = array('id' => $Model->id);
			$options = $this->buildAfterFileSaveInfo($Model);
			foreach ($updateCols as $val => $col) {
				if (is_int($val)) {
					$val = $col;
				}
				$val = $this->afterFileSaveCheck($Model, $val, $options);
				if (isset($val)) {
					$data[$col] = !$reset ? $val : '';
					/*
					if (strpos($col, '.') === false) {
						$col = $Model->alias . '.' . $col;
					}
					if ($reset) {
						$val = '""';
					} else {
						$val = '"' . $val . '"';
					}
					$data[$col] = $val;
					*/
				}
			}
			$Model->save($data, array('callbacks' => false, 'validate' => false));
		//	$Model->updateAll($data, array($Model->alias . '.id' => $Model->id));
		}
	}

	function buildAfterFileSaveInfo(&$Model) {
		if (isset($this->settings[$Model->alias]['success'])) {
			return $this->settings[$Model->alias]['success'];
		} else {
			return false;
		}
	}
	
	function afterFileSaveCheck(&$Model, $value, $options = array()) {
		if ($value == 'type') {
			return $options['type'];
		} else if ($value == 'filename') {
			$name = '';
			if (!empty($this->settings[$Model->alias]['set_random_path'])) {
				$name .= $this->settings[$Model->alias]['set_random_path'];
			}
			$name .= $options['filename'];
			return $name;
		} else if ($value == 'ext') {
			return $options['ext'];
		} else if ($value == 'dir') {
			return $options['dir'];
		} else if ($value == 'size') {
			return $options['size'];
		} else if ($value == 'location') {
			return $options['location'];
		} else if ($value == 'random' && !empty($this->settings[$Model->alias]['set_random_path'])) {
			//Returns the random generated folder list
			return $this->settings[$Model->alias]['set_random_path'];
		} else {
			return null;
		}
	}
	
	function deleteFile(&$Model, $id) {
		if ($filenames = $this->_findFile($Model, $id)) {
			if (!is_array($filenames)) {
				unlink($filenames);
			} else {
				foreach ($filenames as $filename) {
					if (is_file($filename)) {
						unlink($filename);
					}
				}
			}
			
			$this->afterFileDelete($Model);
			return true;
		}		
	}
	
	/**
	 * Uploads a passed array of info
	 *
	 * @param array $uploadArray The passed array from $this->data
	 * @param array $options Additional formatting options
	 **/
	function uploadFile(&$Model, $uploadArray, $options = null) {
		//Makes sure there is something to upload
		if (!$this->_isUploadedFile($Model, $uploadArray)) {
			return false;
		}
		//Upload Directory
		if (!($dirs = $this->getDirs($Model, $options))) {
			$this->_error('No upload directory set');
			return false;
		}
		
		if (!$this->beforeFileSave($Model)) {
			return false;
		}

		$tmp = $uploadArray['tmp_name'];
		$TmpFile = new File($tmp);
						
		$File = new File($uploadArray['name']);
		$ext = $File->ext();
		
		if (isset($options['filename']) && $options['filename'] != '') {
			$filename = $options['filename'];
		} else {
			$filename = $this->getFilename($Model, $uploadArray);
		}
		
		if (!empty($ext) && strpos($filename, '.') === false) {
			//Adds an extension if none has been provided
			$filename .= '.' . $ext;
		}
		foreach ($dirs as $dir => $conversionRules) {
			if (is_int($dir)) {
				$dir = $conversionRules;
				$conversionRules = array();
			}
			
			if (!is_dir( $dir)) {
				if (!mkdir($dir, 0777, true)) {
					$this->_error('Upload file directory, ' . $dir . ' does not exist and could not be created');
				}
			}
			
			//Removes existing file
			if (!empty($options['old_filename'])) {
				$oldFile = $dir . $options['old_filename'];
				if (is_file($oldFile)) {
					unlink($oldFile);
				}
			}

			
			$dst = $dir . $filename;
			
			if (!$this->copyUploadedFile($tmp, $dst, $conversionRules)) {
				$this->_error("Could not upload file $filename to directory $dir");
				return false;
			} else {
				@chmod($dst, 0777);
			}
			
			$successInfo = array(
				'dir' => $dir,
				'filename' => $filename,
				'size' => $TmpFile->size(),
				'type' => !empty($uploadArray['type']) ? $uploadArray['type'] : null,
				'ext' => $ext,
				'location' => $dst,
			);
			$Model->uploadedFiles[] = $successInfo;
			
			//Only saves file info on the first directory
			if (empty($savedDirSettings)) {
				$this->settings[$Model->alias]['success'] = $successInfo;
				$savedDirSettings = true;
			}
		}		
		
		return $this->afterFileSave($Model);
	}

	
	/**
	 * Returns a new filename for the uploaded file
	 *
	 **/
	function getFilename(&$Model, $uploadArray) {
		$filenameRule = Param::keyCheck($this->settings[$Model->alias], 'filename_rule');
		$filenameMatch = Param::keyCheck($this->settings[$Model->alias], 'filename_match');
		
		$file = new File($uploadArray['tmp_name']);
		
		if ($filenameMatch == 'id' || $filenameRule == 'id') {
			$filename = $Model->id;
		} else if ($filenameRule === false) {
			$filename = $file->name();		//Keeps it same as what is was uploaded as
		} else {
			$filename = uniqid();
		}
		
		return $filename;
	}

	
	/**
	 * Internal getter for the current upload directory
	 *
	 **/
	function __getUploadDir(&$Model, $options = array()) {
		if (!empty($options['upload_dir'])) {
			$uploadDir = $options['upload_dir'];
		} else if (empty($this->settings[$Model->alias]['upload_dir'])) {
			return false;
		} else {
			$uploadDir = $this->settings[$Model->alias]['upload_dir'];
		}
		if (isset($options['dir'])) {
			$uploadDir .= $options['dir'] . DS;
		}
		//Root Directory
		if (!Param::keyValCheck($options, 'no_root')) {
			$uploadDir = $this->getRoot($Model) . $uploadDir;
		}
		return $uploadDir;
	}
	
	function getRoot(&$Model, $options = array()) {
		if (!empty($this->settings[$Model->alias]['root'])) {
			$root = $this->settings[$Model->alias]['root'];
			if ($root == 'web') {
				return WWW_ROOT;
			} else if ($root == 'app') {
				return APP;
			} else if ($root == 'image') {
				return IMAGES;
			}
		}
		return '';
	}
	
	function getDirs(&$Model, $options = array()) {
		if (!($uploadDir = $this->__getUploadDir($Model, $options))) {
			return false;
		}	

		if (!empty($this->settings[$Model->alias]['dirs'])) {
			$loadedDirs = $this->settings[$Model->alias]['dirs'];
		} else {
			$loadedDirs = array('');
		}
		
		$dirs = array();
		foreach ($loadedDirs as $loadedDir => $conversionRules) {
			if (is_int($loadedDir)) {
				$loadedDir = $conversionRules;
				$conversionRules = array();
			}
			$loadedDir = $this->_folderSlashCheck($loadedDir);
			
			$dir = $uploadDir . $loadedDir;
			//Optional random extra directory
			if (!empty($this->settings[$Model->alias]['random_path']) && empty($options['no_random']) && empty($options['filename'])) {
				if (empty($this->settings[$Model->alias]['set_random_path'])) {
					$random = $this->_randomPath($Model->id);
					$this->settings[$Model->alias]['set_random_path'] = $random;
				} else {
					//Stores the random path, so if you're adding multiple sub-folders 
					//it maintains the same random structure for each
					$random = $this->settings[$Model->alias]['set_random_path'];
				}
				$dir .= $random;
			}
			$dirs[$dir] = $conversionRules;
		}
		return $dirs;
	}
	
	/**
	 * Copies uploaded file to its new destination
	 *
	 **/
	function copyUploadedFile($src, $dst, $options = array()) {
		$Src = new File($src);
		return $Src->copy($dst, true);
	}
	
	function _findFile(&$Model, $id) {
		$Model->id = $id;
		$result = $Model->read();
		$settings =& $this->settings[$Model->alias];

		//Retrieves file using database filename column
		if ($filenameCol = Param::keyValCheck($settings['update'], 'filename')) {
			$dirs = $this->getDirs($Model, array('no_random' => true));
			$files = array();
			foreach ($dirs as $dir => $rules) {
				if (is_int($dir)) {
					$dir = $rules;
				}
				$files[] = $dir . $result[$Model->alias][$filenameCol];
			}
			return $files;
		}
		
		return false;
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
	 * @access protected
	 */
	protected function _randomPath($string, $level = 3) {
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
	
    // Based on comment 8 from: http://bakery.cakephp.org/articles/view/improved-advance-validation-with-parameters
	protected function _isUploadedFile(&$Model, $uploadArray){
		if (!empty($this->settings[$Model->alias]['bypass_is_uploaded'])) {
			return true;
		}
		if ((isset($uploadArray['error']) && $uploadArray['error'] == 0) || 
		 (!empty( $uploadArray['tmp_name']) && $uploadArray['tmp_name'] != 'none')) {
			return is_uploaded_file($uploadArray['tmp_name']);
		}
		return false;
	}

	protected function _folderSlashCheck($folder) {
		$folder = str_replace(array('/', '\\'), DS, $folder);
		if (substr($folder, -1) != DS) {
			$folder .= DS;
		}
		return $folder;	
	}
	
	function _error($msg) {
		$msg = __($msg);
		$this->errors[] = $msg;
		trigger_error($msg, E_USER_WARNING);
	}
	
	function _log($msg) {
		//FireCake::log($msg);
		//debug($msg);
	}
}