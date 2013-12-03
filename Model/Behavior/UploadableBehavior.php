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

	private $_verboseDebug = false;
	
	function setup(Model $Model, $settings = array()) {
		$default = array(
			'upload_dir' =>		'files/tmp/',		//Directory to upload the files
			'upload_var' => 	'add_file',			//The variable name to check for in the data
			'delete_var' => 	'delete_file',		//The variable name to check for removal of existing file
			'auto_upload_dir' => false,				//A directory to look for automatically upload
			
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
			'gitignore' => true,					//If true, maintains a .gitignore and empty file to prevent problems with syncing to a git repository
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
	
	function beforeValidate(Model $Model, $options = array()) {
		$uploadVar = $this->settings[$Model->alias]['upload_var'];
		$settings = $this->settings[$Model->alias];
		$created = empty($Model->data[$Model->alias][$Model->primaryKey]);
		$this->_log('Looking for variable: ' . $uploadVar);
		if (!empty($Model->data[$Model->alias][$uploadVar])) {
			$this->_log('Found it!');
			$data = $Model->data[$Model->alias][$uploadVar];
			App::uses('File', 'Utility');
			$File = new File($data['name']);
			$ext = strtolower($File->ext());
			$errMsg = array();
			
			//Checks if no file is present
			$noFile = (!empty($data['error']) && $data['error'] == 4) || !$this->_isUploadedFile($Model, $data);
	
			if ($noFile) {
				$this->_log('No file found');
				if ((!empty($settings['required']) || ($created && !empty($settings['requiredCreated']))) 
					&& !$this->_isUploadedFile($Model, $data)) {
					$errMsg[] = 'You must select a file to continue';
				} else {
					//If it's not required, validate. No file will be added
					return true;
				}
			}
				
			if (!empty($data['error'])) {
				$errMsg[] = $this->errorTypes[$data['error']];
			}
			if (!empty($settings['required']) && !$this->_isUploadedFile($Model, $data)) {
				$errMsg[] = 'You must select a file to continue';
			}
			if (!empty($settings['maxsize']) && $settings['maxsize'] < $data['size']) {
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
	
	#region Callbacks
	function beforeSave(Model $Model, $options = array()) {
		$uploadVar = $this->settings[$Model->alias]['upload_var'];
		$settings = $this->settings[$Model->alias];
		$data =& $Model->data[$Model->alias];
		
		//Doesn't save if file doesn't exist
		$this->_log($data);
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
	
	function afterSave(Model $Model, $created, $options = array()) {
		//Looks to see if a file was passed as well
		$settings =& $this->settings[$Model->alias];
		$data =& $Model->data[$Model->alias];
		
		$uploadVar = $settings['upload_var'];
		$deleteVar = Param::keyCheck($settings, 'delete_var');
		//Uploads file (if success is not true, meaning the file has been saved once already)
		if (!empty($data[$uploadVar]['tmp_name']) && empty($settings['success'])) {
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
			
			if (!empty($settings['gitignore'])) {
				$this->updateGitignore($Model);
			}
			
			unset($this->settings[$Model->alias]['set_random_path']);
			unset($this->settings[$Model->alias]['no_random']);
		} else if (!empty($data[$deleteVar])) {
			$this->deleteFile($Model, $Model->id);
			unset($data[$deleteVar]);
		}
		return true;
	}
	
	function beforeDelete(Model $Model, $cascade = true) {
		//Removes associated files as well
		$this->deleteFile($Model, $Model->id);
		return true;
	}

	//Custom Callbacks
	function beforeFileSave(Model $Model) {
		return true;
	}
	
	function afterFileSave(Model $Model) {
		$this->_updateModel($Model);
		unset($this->settings[$Model->alias]['success']);
		return true;
	}
	
	function afterFileDelete(Model $Model) {
		//Resets all updated columns to blank
		$this->_updateModel($Model, true);
		return true;
	}	
	#endregion
	
	#region Public Functions
	//Manually saves the file to the system, bypassing the need to upload it
	public function saveUpload(Model $Model, $id, $file, $saveOptions = array()) {
		$settings =& $this->settings[$Model->alias];
		$uploadVar = $settings['upload_var'];
		
		$Model->checkIsUploaded(false);
		$data = array(
			'id' => $id,
			$uploadVar => array(
				'tmp_name' => $file,
				'name' => 'UploadImage.jpg',
				'errors' => 0,
			),
		);
		$Model->create();
		$Model->id = $id;
		return $Model->save($data, $saveOptions);
	}
	
	//Returns the file name and path of the image
	public function getUploadFilename(Model $Model, $id, $dir = null, $root = false) {
		$settings =& $this->settings[$Model->alias];
		$fileField = !empty($settings['update']['filename']) ? $settings['update']['filename'] : 'filename';
		$result = $Model->read(null, $id);
		$filename = $this->__getUploadDir($Model, compact('dir'), $root) . $result[$Model->alias][$fileField];
		return $filename;
	}

	//Resaves an uploaded file. Used in case you change your save directory formatting parameters
	public function refreshUpload(Model $Model, $id, $dir = null) {
		$settings =& $this->settings[$Model->alias];
		$tmpDir = $this->__getUploadDir($Model) . 'tmp_copy_dir' . DS;
		if (!is_dir($tmpDir)) {
			mkdir($tmpDir);
		}
		if (!is_dir($tmpDir)) {
			throw new Exception('Could not create temporary directory for refresh: ' . $tmpDir);
			return false;
		}
		$srcFile = $this->getUploadFilename($Model, $id, $dir, true);
		$dstFile = $tmpDir . $Model->alias . '-copy-' . $id . '.jpg';
		if (is_file($srcFile) && copy($srcFile, $dstFile)) {
			$result = $this->saveUpload($Model, $id, $dstFile);
			unlink($dstFile);
			return $result;
		}
		return null;
	}
	
	public function getUploadDir(Model $Model, $subDir = null, $root = false) {
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
	#endregion

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
		
	function scanAutoUploadDirectory($Model, $dir = null, $email = null) {
		App::uses('Router', 'Routing');
		$settings =& $this->settings[$Model->alias];
		if (empty($dir)) {
			$dir = $settings['auto_upload_dir'];
		}
		if (empty($email)) {
			$email = $settings['auto_upload_email'];
		}
		
		$msg = "Auto uploaded images detected\n";
		$successCount = $failedCount = $count = 0;
		if (!is_dir($dir)) {
			throw new Exception("$dir is not a valid directory. Could not open");
		} 
		$handle = opendir($dir);
		while (($file = readdir($handle)) !== false) {
			if ($file != '.' && $file != '..' && file != 'empty') {
				$count++;
				
				$img = $dir . $file;
				$Model->create();
				$success = $Model->saveImage(null, $img);
				
				$msg .= "$file uploaded: " . ($success ? 'SUCCESS' : 'FAILED') . "\n";
				if ($success) {
					$successCount++;
					unlink($img);
				} else {
					$failedCount++;
				}
			}
		}
		$controller = Inflector::tableize($Model->alias);
		$msg .= "\n\n" . Router::url(compact('controller') + array('action' => 'index', 'admin' => true), true);
		if (!empty($count)) {
			$msg = "$count files found. $successCount Successful, $failedCount Failed.\n\n" . $msg;
			if (!empty($email)) {
				mail($email, 'Uploaded files to the website', $msg);
			}
		} else {
			$msg = 'No images detected';
		}
		return $msg;
	}
	
	/**
	 * Updates Model with info about the uploaded files
	 * Uses information stored in the 'update' array in settings
	 * @param Boolean $reset If true, sets all update columns to blank
	 **/
	function _updateModel(Model $Model, $reset = false) {
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

	function buildAfterFileSaveInfo(Model $Model) {
		if (isset($this->settings[$Model->alias]['success'])) {
			return $this->settings[$Model->alias]['success'];
		} else {
			return false;
		}
	}
	
	function afterFileSaveCheck(Model $Model, $value, $options = array()) {
		if ($value == 'type') {
			return $options['type'];
		} else if ($value == 'filename') {
			$name = '';
			if (!empty($this->settings[$Model->alias]['set_random_path'])) {
				$name .= $this->settings[$Model->alias]['set_random_path'];
			}
			$name .= $options['filename'];
			return str_replace('\\','/', $name);
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
	
	function deleteFile(Model $Model, $id) {
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
			$this->deleteEmptySubDirectories($Model);
			
			return true;
		}		
	}
	
	function checkIsUploaded(Model $Model, $set = true) {
		$this->settings[$Model->alias]['bypass_is_uploaded'] = !$set;
	}
	
	/**
	 * Uploads a passed array of info
	 *
	 * @param array $data The passed array from $this->data
	 * @param array $options Additional formatting options
	 **/
	function uploadFile(Model $Model, $data, $options = null) {
		App::uses('File', 'Utility');
		$options = array_merge(array(
			'callbacks' => true,
			'filename' => null,
		), $options);
		
		//Makes sure there is something to upload
		if (!$this->_isUploadedFile($Model, $data)) {
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

		$tmp = $data['tmp_name'];
		$TmpFile = new File($tmp);
						
		$File = new File($data['name']);
		$ext = $File->ext();
		
		if (isset($options['filename']) && $options['filename'] != '') {
			$filename = $options['filename'];
		} else {
			$filename = $this->getFilename($Model, $data);
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
			$tmp = $this->_dsFixFile($tmp);
			$dir = $this->_dsFixFile($dir);
			$filename = $this->_dsFixFile($filename);
			
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
				'type' => !empty($data['type']) ? $data['type'] : null,
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
		return $options['callbacks'] ? $this->afterFileSave($Model) : true;
	}
	
	/**
	 * Returns a new filename for the uploaded file
	 *
	 **/
	function getFilename(Model $Model, $data) {
		$filenameRule = Param::keyCheck($this->settings[$Model->alias], 'filename_rule');
		$filenameMatch = Param::keyCheck($this->settings[$Model->alias], 'filename_match');
		
		$file = new File($data['tmp_name']);
		
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
	function __getUploadDir(Model $Model, $options = array()) {
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
	
	function getRoot(Model $Model, $options = array()) {
		$hasPlugin = false;
		if (!empty($this->settings[$Model->alias]['plugin'])) {
			if ($hasPlugin = $this->settings[$Model->alias]['plugin']) {
				$pluginRoot = APP . 'Plugin/' . $this->settings[$Model->alias]['plugin'] . '/';
			}
		}
		
		if (!empty($this->settings[$Model->alias]['root'])) {
			$root = $this->settings[$Model->alias]['root'];
			if ($root == 'web') {
				return $hasPlugin ? $pluginRoot . 'webroot/' : WWW_ROOT;
			} else if ($root == 'app') {
				return $hasPlugin ? $pluginRoot : APP;
			} else if ($root == 'image') {
				return $hasPlugin ? $pluginRoot . 'webroot/img/' : IMAGES;
			}
			return $root;
		}
		return '';
	}
	
	function getDirs(Model $Model, $options = array()) {
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
		$success = $Src->copy($dst, true);
		if (!$success) {
			debug($Src->errors());
		}
		return $success;
	}
	
	function _findFile(Model $Model, $id) {
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
				if (!empty($result[$Model->alias][$filenameCol])) {
					$files[] = $this->_dsFixFile($dir . $result[$Model->alias][$filenameCol]);
				}
			}
			return $files;
		}
		
		return false;
	}

	/**
	 * Updates folder to comply with a git repository. 
	 * Makes sure there is an 'empty' file so directory is recognized in git, but ignores everything else.
	 *
	 **/
	protected function updateGitignore(Model $Model) {
		$dir = $this->getUploadDir($Model, null, true);
		$dir = $this->_dsFixFile($dir);
		
		$emptyFile = $dir . 'empty';
		$ignoreFile = $dir . '.gitignore';
		
		//Creates an empty file to make sure folder is saved in git
		if (!is_file($emptyFile)) {
			if (!($file = fopen($emptyFile, 'w'))) {
				throw new Exception("Could not create empty file: $emptyFile");
			}
			fclose($file);
		}
		
		//Creates .gitignore file
		if (!is_file($ignoreFile)) {
			if (!$file = fopen($ignoreFile, 'w')) {
				throw new Exception("Could not create .gitignore file: $ignoreFile");
			}
			fwrite($file, "*\n");		//Ignores everything
			fwrite($file, "!empty\n");	//Except the empty file
			fclose($file);			
		}
	}

	//Fixes issues with multiple types of directory separators in a filename
	private function _dsFixFile($filename) {
		$ds = DS;
		$notDs = DS == '/' ? '\\' : '/';
		$filename = str_replace($notDs, $ds, $filename);
		$filename = str_replace($ds . $ds, $ds, $filename);
		return $filename;
	}
	
	//Removes empty directories inside the upload directory
	private function deleteEmptySubDirectories($Model) {
		$settings =& $this->settings[$Model->alias];
		$dirs = !empty($settings['dirs']) ? $settings['dirs'] : array(null);
		
		$root = $this->getRoot($Model);
		$uploadDir = $this->getUploadDir($Model, null, true);
		
		//Minimizes chance of mis-configuring to delete folders you shouldn't
		if (
			empty($settings['random_path']) ||		//Subdirectories are being automatically generated
			empty($settings['upload_dir']) ||		//Upload dir has been set
			strpos($uploadDir, WWW_ROOT) !== 0 || 	//Upload dir is in webroot
			$uploadDir == WWW_ROOT					//Upload dir is not set to webroot
		) {
			return false;
		}
		
		foreach ($dirs as $dir) {
			$this->_deleteEmptyDirectories($this->getUploadDir($Model, $dir, true));
		}
	}
	
	/**
	 * Recursively checks a directory to see if it's empty
	 * Deletes the folder if it is empty
	 *
	 * @param string Directory to check
	 * @return bool True if empty, false if it contains any files
	 **/
	private function _deleteEmptyDirectories($dir) {
		$dir = $this->_folderSlashCheck($dir);
		$isEmpty = true;
		//Cycles through all files and folders in directory
		$success = $this->_dirFilesFunction($dir, function($file) use ($dir, &$isEmpty) {
			$subDir = $dir . $file;
			if (is_dir($subDir)) {
				//Checks sub-directory
				if (!$this->_deleteEmptyDirectories($subDir)) {
					$isEmpty = false;
				}
			} else {
				$isEmpty = false;
			}
		});
		if ($isEmpty) {
			rmdir($dir);
		}
		return $isEmpty;
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
	protected function _isUploadedFile(Model $Model, $data){
		if (!empty($this->settings[$Model->alias]['bypass_is_uploaded'])) {
			return true;
		}
		if ((isset($data['error']) && $data['error'] == 0) || 
		 (!empty( $data['tmp_name']) && $data['tmp_name'] != 'none')) {
			return is_uploaded_file($data['tmp_name']);
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

	public function setUploadableVerboseDebug($Model, $set = true) {
		$this->_verboseDebug = $set;
	}
	
	function _log($msg) {
		//FireCake::log($msg);
		if ($this->_verboseDebug) {
			$bt = debug_backtrace();
			$caller = array_shift($bt);
			debug(array(
				'Caller' => "{$caller['file']} on Line {$caller['line']}",
				'Message' => $msg,
			));
		}
	}
	
	/**
	 * Loops through a directory, applying a function to each file or folder found
	 *
	 * @param string $dir The directory to read
	 * @param function $fn The function to apply. It will be passed the file name string
	 * @return void
	 **/
	private function _dirFilesFunction($dir, $fn) {
		if (!is_dir($dir)) {
			throw new Exception ("Could not open $dir. Does not exist");
		}
		if (!$handle = opendir($dir)) {
			throw new Exception ("Could not open $dir.");
		}
		while(($file = readdir($handle)) !== false) {
			if ($file == '.' || $file == '..') {
				continue;
			}
			//If function returns false, it stops looping
			if ($fn($file) === false) {
				break;
			}
		}
		closedir($handle);
	}
}