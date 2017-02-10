<?php
App::uses('Image', 'Uploadable.Lib');
App::uses('Upload', 'Uploadable.Lib');
App::uses('PluginConfig', 'Uploadable.Lib');
App::uses('UrlPath', 'Uploadable.Lib');
App::uses('FetchUrl', 'Uploadable.Lib');
App::uses('RemoteUrlField', 'Uploadable.Lib');

App::uses('CakeSession', 'Model/Datasource');

App::uses('Hash', 'Utility');
App::uses('Folder', 'Utility');
App::uses('CakeNumber', 'Utility');


class FieldUploadBehavior extends ModelBehavior {

	public $name = 'FieldUpload';

	public $fields = [];

	// Created in beforeSave and executed in afterSave
	protected $_uploadQueue = [];
	// Created in beforeSave and exectued in afterSave
	protected $_uploadFromUrlQueue = [];
	// Created in beforeSave and executed in afterSave
	protected $_deleteQueue = [];
	// A list of files to be deleted before the object unloads
	protected $_unlinkQueue = [];
	// A list of files to be copied and cropped
	protected $_cropCopyQueue = [];

	// The webroot for any file paths
	protected $_webroot = WWW_ROOT;
	// The URL base for any file urls
	protected $_urlBase = null;

	private $_deleteId;

	protected $_skipSetFieldUpload;

	const URL_CACHE = 'Uploadable.FieldUploadUrl';
	const FROM_URL_TMP = 'from_web';

	private $_sessionId;
	private $_imgExtensions = ['jpg', 'jpeg', 'gif', 'png'];

	public function __destructor() {
		$this->_startUnlinkQueue();
		return parent::__destructor();
	}

	public function setup(Model $Model, $settings =[]) {
		App::uses('PluginConfig', 'Uploadable.Lib');
		$PluginConfig = new PluginConfig();
		if (method_exists($PluginConfig, 'initReplace')) {
			PluginConfig::initReplace('Uploadable');
		}

		// Fields Settings
		$defaultFieldSettings = [
			// The base upload directory
			'dir' => null,			
			// An array of sizes. They'll be set in the _initFieldSettings method
			'sizes' => null,		
			// The file root. Defaults to CakePHP's webroot
			'root' => null,

			// Whether the uploaded file is an image or not
			'isImage' => true,	
			// Whether a ranom path of folders (eg: "/01/05/72/") should be inserted between the path and filname.
			//  This is helpful for folders with many images	
			'randomPath' => true,
			// Makes sure an "empty" and ".gitignore" file are created in any upload directories	
			'gitignore' => true,

			// Path to a default image if a user does not specify one
			'default' => null,

			// Whether or not the 
			'plugin' => null,

			// Valid extensions
			'extensions' => [],
		];

		// Corrects for development branch environment
		if ($defaultFieldSettings['root'] == '/home/souper/public_sub_html/development/app/webroot/') {
			$defaultFieldSettings['root'] = '/home/souper/public_html/app/webroot/';
			$this->_setUrlBase('http://souperbowl.org');
			$this->_setWebRoot($defaultFieldSettings['root']);
		}

		if (empty($settings['fields'])) {
			$fields = $settings;
			$settings = [];
		} else {
			$fields = $settings['fields'];
		}
		unset($settings['fields']);
		if (!isset($this->fields[$Model->alias])) {
			$this->fields[$Model->alias] = [];
		}
		
		$tmpFields = [];
		foreach ($fields as $k => $v) {
			if (is_numeric($k)) {
				$k = $v;
				$v = $defaultFieldSettings;
			} else {
				$v = Hash::merge($defaultFieldSettings, $v);
			}
			$tmpFields[$k] = $this->_initFieldSettings($Model, $k, $v);
		}
		$fields = $tmpFields;
		$this->fields[$Model->alias] = array_merge($this->fields[$Model->alias], (array) $fields);

		// General Settings
		if (!isset($this->settings[$Model->alias])) {
			$this->settings[$Model->alias] = [];
		}
		$this->settings[$Model->alias] = array_merge($this->settings[$Model->alias], (array) $settings);

		return parent::setup($Model, $settings);
	}

	public function beforeFind(Model $Model, $query) {
		$oQuery = $query;
		if (isset($query['fieldUpload']) && $query['fieldUpload'] === false) {
			$this->_skipSetFieldUpload = true;
			unset($query['fieldUpload']);
		}

		if ($oQuery != $query) {
			return $query;
		}
		return parent::beforeFind($Model, $query);
	}

	public function afterFind(Model $Model, $results, $primary = false) {
		// Adds additional information to the find result pertaining to the uploaded files
		if (empty($this->_skipSetFieldUpload)) {
			$results = $this->setFieldUploadResultFields($Model, $results, $primary);
		} else {
			$this->_skipSetFieldUpload = false;
		}
		return $results;
	}

	public function beforeValidate(Model $Model, $options = []) {
		$sessionId = $this->getSessionId();
		if (isset($Model->data[$Model->alias])) {
			$data =& $Model->data[$Model->alias];
		} else {
			$data =& $Model->data;
		}
		$id = !empty($data['id']) ? $data['id'] : '';
		foreach ($this->fields[$Model->alias] as $field => $config):
			$validateField = false;
			$urlField = RemoteUrlField::field($field);
			if (!empty($data[$field]['tmp_name'])) {
				// File Upload
				// ------------------------
				$path = $data[$field]['tmp_name'];
				$name = $data[$field]['name'];
				$validateField = $field;
			} else if (!empty($data[$urlField])) {
				// Remote URL 
				// ------------------------
				$validateField = $urlField;

				// Pre-saves the remote file
				try {
					$path = $this->storeFileFromUrl($Model, $id, $field, $data[$urlField]);
				} catch (Exception $e) {
					$this->invalidate($Model, $validateField, $e->getMessage());
					return false;
				}
				$name = $data[$urlField];
			}

			if (!empty($validateField)) {
				// Validates the extension of the uploaded file
				if (!$this->validateFieldExtension($Model, $field, $name, $validateField)) {
					return false;
				}
				if (!$this->validateFilesize($Model, $field, $path, $validateField)) {
					return false;
				}
			}
		endforeach;
	}

	public function validateFieldExtension($Model, $field, $filePath, $validateField = null) {
		if (empty($validateField)) {
			$validateField = $field;
		}
		$config = $this->fields[$Model->alias][$field];
		$ext = UrlPath::getExtension($filePath);
		if (empty($ext)) {
			$ext = Image::mimeExtension(mime_content_type($filePath));
		}
		if (!empty($config['extensions'])) {
			$exts = $config['extensions'];
		} else if ($config['isImage']) {
			$exts = $this->_imgExtensions;
		}
		if (!empty($exts)) {
			if (is_array($exts)) {
				$exts = implode(', ', $exts);
			}
			if (!$this->matchExtension($ext, $exts)) {
				$this->invalidate(
					$Model,
					$validateField, 
					sprintf('Invalid file type: %s. (Please only select: %s)', strtoupper($ext), $exts)
				);
				return false;
			}
		}
		return true;
	}

	public function validateFilesize($Model, $field, $filePath, $validateField = null) {
		if (empty($validateField)) {
			$validateField = $field;
		}
		$filesize = filesize($filePath);
		$uploadLimit = Upload::getUploadLimit();
		// Detects user-specific file size
		if (!empty($this->fields[$Model->alias][$field]['maxSize']) && $this->fields[$Model->alias][$field]['maxSize'] < $uploadLimit) {
			$uploadLimit = $this->fields[$Model->alias][$field]['maxSize'];
		}
		if ($filesize >= $uploadLimit) {
			$this->invalidate($Model, $validateField, CakeNumber::toReadableSize($filesize) . ' is too big');
			return false;
		}
		return true;
	}

	public function beforeSave(Model $Model, $options = []) {
		$sessionId = $this->getSessionId();

		if (isset($Model->data[$Model->alias])) {
			$data =& $Model->data[$Model->alias];
		} else {
			$data =& $Model->data;
		}
		$id = !empty($data['id']) ? $data['id'] : '';
		foreach ($this->fields[$Model->alias] as $field => $config):
			$urlField = RemoteUrlField::field($field);
			// If any files have been uploaded, queue them for upload
			if (!empty($data[$field]['tmp_name'])) {
				$this->_addUploadQueue($Model, $id, $field, $data[$field]);
			} else if (!empty($data[$urlField])) {
				$this->_addUploadFromUrlQueue($Model, $id, $field, $data[$urlField]);
			}
			// If the files have been marked for deletion, queue them for deletion
			if (!empty($data[$field]['delete'])) {
				$this->_addDeleteQueue($Model, $id, $field);
			} 
			unset($data[$field]);
		endforeach;

		if (!empty($data['FieldUploadCropCopy'])) {
			foreach ($data['FieldUploadCropCopy'] as $field => $sizes) {
				foreach ($sizes as $size => $attrs) {
					$this->_addCropCopyQueue($Model, $id, $field, $size, $attrs);
					if (!empty($attrs['copySizes'])) {
						$copySizes = array_map('trim', explode(',', $attrs['copySizes']));
						unset($attrs['copySizes']);
						foreach ($copySizes as $copySize) {
							if ($this->hasSize($Model, $field, $copySize)) {
								$this->_addCropCopyQueue($Model, $id, $field, $copySize, $attrs);
							}
						}
					}
				}
			}
			unset($data['FieldUploadCropCopy']);
		}
		return parent::beforeSave($Model, $options);
	}

	public function afterSave(Model $Model, $created, $options = []) {
		$id = $Model->id;
		if ($created) {
			$this->_updateQueueModelIdKeys($Model, $id);
		}
		$this->_startUploadQueue($Model, $id);		// Uploads anything found in beforeSave
		$this->_startUploadFromUrlQueue($Model, $id);
		$this->_startCropCopyQueue($Model, $id);	// Crops and copies anything set in beforeSave
		$this->_startDeleteQueue($Model, $id);		// Deletes anything marked for deletion
		$this->_startUnlinkQueue();

		return parent::afterSave($Model, $created, $options);
	}

	public function beforeDelete(Model $Model, $cascade = true) {
		// Stores files to be deleted after the Model is deleted
		$this->_addDeleteQueue($Model, $Model->id);
		$this->_deleteId = $Model->id;
		return parent::beforeDelete($Model, $cascade);
	}

	public function afterDelete(Model $Model) {
		// Deletes any queueed files
		if (!empty($this->_deleteId)) {
			$this->_startDeleteQueue($Model, $this->_deleteId);
			unset($this->_deleteId);
		}
		return parent::afterDelete($Model);
	}

/**
 * Uploads a file and saves it associated to the model
 *
 * @param Model $Model The associated Model object
 * @param int $id The model id of the object
 * @param string $field The field of the image in the model
 * @param string $filepath The location of the file to upload
 * @return array The result array generated by the Upload utility
 **/
	public function uploadField(Model $Model, $id, $field, $filepath) {
		return $this->_uploadField($Model, $id, $field, [
			'tmp_name' => $filepath,
			'name' => $id . '.jpg'
		]);
	}

	public function uploadFieldFromUrl(Model $Model, $id, $field, $url) {
		$filename = $this->getStoredFileFromUrl($Model, $id, $field, $url);
		$this->_addUnlinkQueue($filename);
		return $this->uploadField($Model, $id, $field, $filename);
	}

/**
 * Retrieves a unique session ID for the given transaction
 *
 * @return string;
 **/
	protected function getSessionId() {
		if (!$this->_sessionId) {
			$this->_sessionId = time();
		}
		return $this->_sessionId;
	}

/**
 * Returns a unique pathname where a file downloaded from a URL can be stored
 *
 * @param Model $Model The model object
 * @param int $id The model id
 * @param string $field The field used to store the value
 * @param string $url The URL where the remote file is located
 * @return string The filename
 **/
	private function _getFromUrlTmpPath($Model, $id, $field, $url) {
		if (!($dir = $this->_getTmpDir(self::FROM_URL_TMP))) {
			throw new Exception("Could not create directory to store uploadFieldFromUrl");
		}
		$path = $dir . $Model->alias . '-' . $id . '-' . $field . '-' . time();
		if ($ext = UrlPath::getExtension($path)) {
			$path .= '.' . $ext;
		}
		return $path;
	}

/**
 * Stores a file from a remote URL to a local location
 *
 * @param Model $Model The model object
 * @param int $id The model id
 * @param string $field The field used to store the value
 * @param string $url The URL where the remote file is located
 * @return string The filename
 **/
	protected function storeFileFromUrl($Model, $id, $field, $url) {
		$sessionId = $this->getSessionId();
		$filename = $this->_getFromUrlTmpPath($Model, $id, $field, $url);
		try {
			FetchUrl::put($url, $filename);
		} catch (Exception $e) {
			throw $e;
			return false;
		}
		$this->_filesFromUrl[$sessionId][$Model->alias][$field][$url] = $filename;
		return $filename;
	}

	protected function getStoredFileFromUrl($Model, $id, $field, $url) {
		$sessionId = $this->getSessionId();
		if (empty($this->_filesFromUrl[$sessionId][$Model->alias][$field][$url])) {
			$this->storeFileFromUrl($Model, $id, $field, $url);			
		}
		return $this->_filesFromUrl[$sessionId][$Model->alias][$field][$url];
	}

	protected function purgeStoredFilesFromUrl($Model) {
		$sessionId = $this->getSessionId();
		if (!empty($this->_filesFromUrl[$sessionId][$Model->alias])) {
			foreach ($this->_filesFromUrl[$sessionId][$Model->alias] as $field => $files) {
				foreach ($files as $url => $filePath) {
					unlink($filePath);
				}
			}
		}
	}

	public function setUploadFieldWebRoot(Model $Model, $root) {
		$this->_setWebRoot($root);
		$this->setUploadFieldSetting($Model, null, 'root', $root);
	}

	public function setFieldUploadResultFields(Model $Model, $results, $primary = false) {
		foreach ($this->fields[$Model->alias] as $field => $fieldConfig) {
			if (array_key_exists($field, $results)) {
				$results['uploadable'][$field] = $this->_setResultField($Model, $field, $results[$field]);
			} else if (isset($results[$Model->alias]) && array_key_exists($field, $results[$Model->alias])) {
				// Single row result
				$results[$Model->alias]['uploadable'][$field] = $this->_setResultField($Model, $field, $results[$Model->alias][$field]);
			} else if (isset($results[0][$Model->alias]) && array_key_exists($field, $results[0][$Model->alias])) {
				// Multiple row result
				foreach ($results as $k => $row) {
					$results[$k][$Model->alias]['uploadable'][$field] = $this->_setResultField($Model, $field, $row[$Model->alias][$field]);
				}
			} else if (isset($results[$Model->alias][0]) && array_key_exists($field, $results[$Model->alias][0])) {
				// Associated result
				foreach ($results[$Model->alias] as $k => $row) {
					$results[$Model->alias][$k]['uploadable'][$field] = $this->_setResultField($Model, $field, $row[$field]);
				}
			}
		}
		return $results;
	}

/**
 * Refreshes all of the sizes of a field based on one size
 *
 * @param Model $Model The associated Model object
 * @param int $id The model id of the object
 * @param string $field The field of the image in the model
 * @param string $size The size of the image to load and re-save
 * @return mixed The result array generated by the Upload utility, or null of file is not found
 **/
	public function refreshFieldUpload(Model $Model, $id, $field, $size = '') {
		$success = null;
		if (!empty($this->fields[$Model->alias][$field]['default'])) {
			$oDefault = $this->fields[$Model->alias][$field]['default'];
		} else {
			$oDefault = null;
		}
		$this->fields[$Model->alias][$field]['default'] = false;
		$filepath = $this->getFieldUploadImage($Model, $id, $field, $size);

		$hasCrop = false;
		$settings = $this->fields[$Model->alias][$field];
		$sizes = array_keys($settings['sizes']);

		$query = $this->getAllCropCopyFieldUploadSettings($Model, $id, $field);
		$query = $query[$Model->alias]['FieldUploadCropCopy'][$field];

		foreach ($sizes as $size) {
			if (!empty($query[$size]) && !empty($query[$size]['x'])) {
				$hasCrop = true;
				$this->_addCropCopyQueue($Model, $id, $field, $size, $query[$size]);
			}
		}
		
		if (!empty($filepath)) {
			if (is_file($filepath)) {
				$success = $this->uploadField($Model, $id, $field, $filepath);
				if ($hasCrop) {
					$this->_startCropCopyQueue($Model, $id);
				}
			}
		}
		$this->fields[$Model->alias][$field]['default'] = $oDefault;
		return $success;
	}

/**
 * Forcibly updates and overwrites a model and field's default image
 *
 * @param Model $Model The associated Model object
 * @param string $field The field of the image in the model
 * @return 
 **/
	public function refreshFieldUploadDefaultImage(Model $Model, $field) {
		return $this->_updateDefaultImage($Model, $field);
	}

	protected function _setWebRoot($root) {
		$this->_webroot = $root;
	}

	protected function _setUrlBase($base) {
		if (substr($base, -1) != '/') {
			$base .= '/';
		}
		$this->_urlBase = $base;
	}

	public function getUploadFieldWebRoot(Model $Model) {
		$config = $this->fields[$Model->alias];
		return $this->_getWebroot($config['plugin']);
	}

	public function getFieldUploadConfig(Model $Model, $field = null, $size = null) {
		$config = $this->fields[$Model->alias];
		if (!empty($field)) {
			$config = $config[$field];
			if (!empty($size)) {
				$config = $config['sizes'][$size];
			}
		}
		return $config;
	}

	public function getFieldUploadDimensions(Model $Model, $field, $size) {
		$config = $this->getFieldUploadConfig($Model, $field, $size);
		$sizeKeys = ['max', 'setSoft', 'set'];
		foreach ($sizeKeys as $key) {
			if (!empty($config[$key])) {
				return $config[$key];
			}
		}
		return null;
	}

	public function getFieldUploadFullSizeKey(Model $Model, $field) {
		$config = $this->getFieldUploadConfig($Model, $field);
		foreach (['', 'full'] as $key) {
			if (array_key_exists($key, $config['sizes'])) {
				return $key;
			}
		}
		return null;
	}

/**
 * Copies a cropped portion of one field's size onto another
 *
 * @param Model $Model The associated Model object
 * @param int $id The model id
 * @param string $field The that stores image path information
 * @param string $dstSize The destination size of the field to copy information
 * @param string $srcSize The source size of the field to find the source of the information
 * @param int $srcX The source X position
 * @param int $srcY The source Y position
 * @param int $srcW The source width
 * @param int $srcH The source height
 * @return bool;
 **/
	public function cropCopyFieldUploadImageField(Model $Model, $id, $field, $dstSize, $srcSize, $srcX, $srcY, $srcW, $srcH) {
		list($dstW, $dstH) = $this->getFieldUploadDimensions($Model, $field, $dstSize);
		$dstPath = $this->getFieldUploadImage($Model, $id, $field, $dstSize);
		$srcPath = $this->getFieldUploadImage($Model, $id, $field, $srcSize);

		try {
			Upload::copyImage($srcPath, $dstPath, [
				'copyResized' => compact('srcX', 'srcY', 'srcW', 'srcH', 'dstW', 'dstH'),
			]);
		} catch (Exception $e) {
			throw new Exception("Could not crop copy image: " . $e->getMessage());
		}

		//Saves Value
		$vals = [];
		foreach (compact('srcX', 'srcY', 'srcW', 'srcH') as $v) {
			$vals[] = round($v, 2);
		}
		$this->setFieldUploadQueryVal($Model, $id, $field, $dstSize, implode(',', $vals));
		return true;
	}

	public function getCropCopyFieldUploadSettings(Model $Model, $id, $field, $size, $data = []) {
		$settings = [];
		$settings['alias'] = $Model->alias;
		$settings['field'] = $field;
		$settings['dimensions'] = $this->getFieldUploadDimensions($Model, $field, $size);
		$query = $this->getFieldUploadQuery($Model, $id, $field, $size);
		$settings['full_size'] = $this->getFieldUploadFullSizeKey($Model, $field);

		if (!empty($query)) {
			$vals = explode(',', $query);
			foreach (['x', 'y', 'w', 'h'] as $k => $v) {
				if (array_key_exists($k, $vals)) {
					$settings[$v] = $vals[$k];
				}
			}
		}

		$data[$Model->alias]['FieldUploadCropCopy'][$field][$size] = $settings;
		return $data;
	}

	public function getAllCropCopyFieldUploadSettings(Model $Model, $id, $field, $data = []) {
		$sizes = array_keys($this->fields[$Model->alias][$field]['sizes']);
		foreach ($sizes as $size) {
			$data = $this->getCropCopyFieldUploadSettings($Model, $id, $field, $size, $data);
		}
		return $data;
	}

/**
 * Saves additional field information to the field
 *
 **/
	public function setFieldUploadQueryVal(Model $Model, $id, $field, $size, $value) {
		list($path, $query) = $this->fieldUploadQuerySplit($Model, $id, $field);
		
		// Removes any settings for sizes that no longer exist
		$sizes = $this->fields[$Model->alias][$field]['sizes'];
		foreach ($query as $querySize => $queryVal) {
			if (!array_key_exists($querySize, $sizes)) {
				unset($query[$querySize]);
			}
		}

		$query[$size] = $value;

		$path .= '?' . http_build_query($query);
	
		$Model->create();
		return $Model->save([
			$Model->primaryKey => $id,
			$field => $path,
		], ['callbacks' => false]);
	}

/**
 * Retrieves the field information, separating the filename from the additional sizing information
 *
 **/
	public function fieldUploadQuerySplit(Model $Model, $id, $field) {
		$result = $Model->read($field, $id);
		$path = $result[$Model->alias][$field];
		$query = [];
		if (($pos = strpos($path, '?')) !== false) {
			$queryString = urldecode(substr($path, $pos + 1));
			parse_str($queryString, $query);
			$path = substr($path, 0, $pos);
		}
		return [$path, $query];
	}

/**
 * Retrieves only the additional sizing information from a field
 *
 **/
	public function getFieldUploadQuery(Model $Model, $id, $field, $size = false) {
		list($path, $query) = $this->fieldUploadQuerySplit($Model, $id, $field);
		if (empty($query)) {
			$query = [];
		} else if ($size !== false) {
			$query = !empty($query[$size]) ? $query[$size] : [];
		}
		return $query;
	}


	public function setUploadFieldSetting(Model $Model, $field, $varName, $value) {
		if (empty($field)) {
			foreach ($this->fields[$Model->alias] as $field => $config) {
				$this->setUploadFieldSetting($Model, $field, $varName, $value);
			}
		} else if (empty($this->fields[$Model->alias][$field])) {
			throw new Exception (sprintf('Could not set field setting for %s model field: %s. Field not found', $Model->alias, $field));
		} else {
			$this->fields[$Model->alias][$field][$varName] = $value;
		}
	}

/**
 * Finds the image path of a specific size and field
 * 
 * @param Model $Model The associated Model object
 * @param int $id The model id of the object
 * @param string $field The field of the image in the model
 * @param string $size The size of the image to use
 * @return string The path to the image
 **/
	public function getFieldUploadImage(Model $Model, $id, $field, $size) {
		if (empty($this->fields[$Model->alias][$field])) {
			throw new Exception (sprintf('Cannot find FieldUpload field "%s" for model %s', $field, $Model->alias));
		}
		if (!$this->hasSize($Model, $field, $size)) {
			throw new Exception (sprintf('Model %s and field %s does not have a size set for size key: "%s"', $Model->alias, $field, $size));
		}
		$result = $Model->find('first', array(
			'fields' => array($Model->escapeField($field)),
			'conditions' => array($Model->escapeField() => $id)
		));

		$result = $result[$Model->alias][$field];
		$result = $this->_setResultField($Model, $field, $result);
		return !empty($result['sizes'][$size]['path']) ? $result['sizes'][$size]['path'] : null;
	}

	public function copyFromOldUploadable() {

	}

	private function _addUploadQueue(Model $Model, $id, $field, $fieldData) {
		$this->_uploadQueue[$Model->alias][$id][$field] = $fieldData;
	}

	protected function invalidate($Model, $field, $message = null) {
		$this->purgeStoredFilesFromUrl($Model);
		return $Model->invalidate($field, $message);
	}

/**
 * Matches a given extension to a group of extensions
 *
 * @param string $ext The given file extension
 * @param string|array A comma-separated list of an array of extensions
 * @return bool True if there's a match, false if not
 **/
	protected function matchExtension($ext, $exts) {
		$ext = strtolower($ext);
		if (!is_array($exts)) {
			$exts = array_map('trim', explode(',', $exts));
		}
		$exts = array_map('strtolower', $exts);
		return in_array($ext, $exts);
	}


/**
 * Uploads all files in the queue
 *
 * @param Model $Model The associated Model object
 * @return void
 **/
	private function _startUploadQueue(Model $Model, $id = null) {
		if (empty($id)) {
			$id = $Model->id;
		}
		if (!empty($this->_uploadQueue[$Model->alias][$id])):
			foreach ($this->_uploadQueue[$Model->alias][$id] as $field => $data):
				$this->_uploadField($Model, $id, $field, $data);
				unset($this->_uploadQueue[$Model->alias][$id][$field]);
			endforeach;
			unset($this->_uploadQueue[$Model->alias][$id]);
		endif;
	}

	private function _addUploadFromUrlQueue(Model $Model, $id, $field, $url) {
		$this->_uploadFromUrlQueue[$Model->alias][$id][$field] = $url;
	}

	private function _startUploadFromUrlQueue(Model $Model, $id = null) {
		if (empty($id)) {
			$id = $Model->id;
		}
		if (!empty($this->_uploadFromUrlQueue[$Model->alias][$id])):
			foreach ($this->_uploadFromUrlQueue[$Model->alias][$id] as $field => $url):
				$this->uploadFieldFromUrl($Model, $id, $field, $url);
				unset($this->_uploadFromUrlQueue[$Model->alias][$id][$field]);
			endforeach;
			unset($this->_uploadFromUrlQueue[$Model->alias][$id]);
		endif;
		$this->purgeStoredFilesFromUrl($Model);
	}

	private function _addCropCopyQueue(Model $Model, $id, $field, $size, $attrs) {
		$this->_cropCopyQueue[$Model->alias][$id][$field][$size] = $attrs;
	}

	private function _startCropCopyQueue(Model $Model, $id) {
		if (empty($id)) {
			$id = $Model->id;
		}
		if (!empty($this->_cropCopyQueue[$Model->alias][$id])) {
			foreach ($this->_cropCopyQueue[$Model->alias][$id] as $field => $sizes) {
				foreach ($sizes as $size => $attrs) {
					$Model->cropCopyFieldUploadImageField($id, $field, $size, 
						$attrs['full_size'], 
						$attrs['x'], $attrs['y'], 
						$attrs['w'], $attrs['h']
					);
				}
				unset($this->_cropCopyQueue[$Model->alias][$id][$size]);
			}
		}
	}

/**
 * Adds an item to the delete queue
 *
 * @param Model $Model The associated Model object
 * @param int $id The Model ID
 * @param string|null $field The field to delete. If not set, it will add all fields for the ID
 * @return void;
 **/
	private function _addDeleteQueue(Model $Model, $id, $field = null) {
		if (empty($field)) {
			foreach ($this->fields[$Model->alias] as $field => $fieldConfig) {
				$this->_addDeleteQueue($Model, $id, $field);
			}
		} else {
			$this->_deleteQueue[$Model->alias][$id][$field] = true;	
		}
	}

/**
 * Deletes any files stored in the delete queue
 *
 * @param Model $Model The associated Model object
 * @param int $id The Model ID
 * @return Boolean|Null True if succes, null if no queue exists
 **/
	private function _startDeleteQueue(Model $Model, $id) {
		if (!empty($this->_deleteQueue[$Model->alias][$id])):
			$fields = array_keys($this->_deleteQueue[$Model->alias][$id]);
			$this->_deleteFields($Model, $id, $fields);
			unset($this->_deleteQueue[$Model->alias][$id]);
			return true;
		endif;
		return null;
	}

	private function _addUnlinkQueue($filename) {
		if (is_array($filename)) {
			foreach ($filename as $subFilename) {
				$this->_addUnlinkQueue($subFilename);
			}
		} else {
			$this->_unlinkQueue[$filename] = $filename;
		}
	}

	private function _startUnlinkQueue() {
		foreach ($this->_unlinkQueue as $k => $filename) {
			if (is_file($filename)) {
				unlink($filename);
			}
			unset($this->_unlinkQueue[$k]);
		}
	}

/**
 * Copies uploaded field data to a permanent folder
 *
 * @param Model $Model The associated Model object
 * @param int $id The ID of the specific model instance
 * @param String $field The Model field to use
 * @param Array $data The passed request data for the specific model
 * @return Array The result array generated by the Upload utility
 **/
	private function _uploadField(Model $Model, $id, $field, $data) {
		App::uses('Folder', 'Utility');
		App::uses('File', 'Utility');

		if (!isset($this->fields[$Model->alias][$field])) {
			throw new Exception(sprintf('FieldUpload cannot work with field: "%s". No config information found', $field));
		}
		$config = $this->fields[$Model->alias][$field];
		$dirs = $this->_getFieldSizeDirs($Model, $field);
 
		if (!is_array($data)) {
			$data = ['tmp_name' => $data];
		}
		if (empty($data['name'])) {
			$tmpNameParts = explode('/', $data['tmp_name']);
			$data['name'] = array_pop($tmpNameParts);
		}

		$File = new File($data['name']);
		$ext = $File->ext();

		if ($ext == 'pdf') {
			$uniqId = md5(uniqid(time(), true));

			$convertExt = 'jpg';
			$convertDir = $this->_getTmpDir('convert_pdf_to_jpg');

			$srcName = $uniqId . '-from';
			$dstName = $uniqId . '-to';

			$src = $convertDir . $srcName . '.' . $ext;
			$dst = $convertDir . $dstName . '.' . $convertExt;

			if (is_file($src)) {
				unlink($src);	
			}
			if (is_file($dst)) {
				unlink($dst);
			}
			
			if (!move_uploaded_file($data['tmp_name'], $src)) {
				if (!copy($data['tmp_name'], $src)) {
					throw new Exception("Could not move temp file to $src");
				}
			}

			//copy($data['tmp_name'], $src);

			try {
				$im = new Imagick();
				$im->setResolution(200,200);

				$im->readImage($src);
				$im->setImageFormat('jpeg');
				$im->writeImages($dst, true);

			} catch (Exception $e) {
				debug($e->getMessage());
			}

			ini_set('memory_limit', '1G');

			if (!is_file($dst)) {
				$images = array();
				$imageH = 0;
				$imageW = 0;
				for ($i = 0; $i < 25; $i++) {
					$filename = $convertDir . $dstName . '-' . $i . '.' . $convertExt;
					if (!is_file($filename)) {
						break;
					}
					$img = imagecreatefromjpeg($filename);
					$w = imagesx($img);
					$h = imagesy($img);
					if ($w > $imageW) {
						$imageW = $w;
					}
					$imageH += $h;
					$images[] = compact('img', 'w', 'h', 'filename');
				}
				try {
					$img = imagecreatetruecolor($imageW, $imageH);
				} catch (Exception $e) {
					throw new Exception("Could not create destination file: " . $e->getMessage());
				}
				$pointerX = 0;
				$pointerY = 0;
				foreach ($images as $image) {
					imagecopyresampled($img, $image['img'], $pointerX, $pointerY, 0, 0, $image['w'], $image['h'], $image['w'], $image['h']);
					unlink($image['filename']);
					$pointerY += $image['h'];
				}
				imagejpeg($img, $dst, 100);
			}
			$data['tmp_name'] = $dst;
			$ext = $convertExt;
			$this->_addUnlinkQueue(array($src, $dst));
		}

		$config['filename'] = $id . '.' . $ext;
		if (!empty($config['randomPath'])) {
			$config['filename'] = Folder::slashTerm(Upload::randomPath($config['filename'])) . $config['filename'];
			if (DS == '\\') {
				$config['filename'] = str_replace('\\', '/', $config['filename']);
			}
			$config['randomPath'] = false;	//Turns it off before passing to the Upload Utility so we don't do it twice
		}

		if ($result = Upload::copy($data, $dirs, $config)) {
			if (!empty($config['gitignore'])) {
				Upload::gitIgnore($this->_getFieldDir($Model, $field));
			}
			$Model->save([
				$Model->primaryKey => $id,
				$field => $config['filename']
			], ['callbacks' => false, 'validate' => false]);
		}
		return $result;
	}

/**
 * Deletes a file stored in the FieldUpload fields
 *
 * @param Model $Model The associated Model object
 * @param int $id The ID of the current model item
 * @param string|Array The field(s) to find and delete. Default '*' for all of them
 * @return true;
 **/
	private function _deleteFields(Model $Model, $id, $fields = '*') {
		App::uses('Folder', 'Utility');
		if ($result = $Model->read($fields, $id)) :
			foreach ($result[$Model->alias] as $field => $val):
				if (isset($this->fields[$Model->alias][$field])) {
					$dirs = $this->_getFieldSizeDirs($Model, $field);
					foreach ($dirs as $dir => $config) {
						$path = Folder::slashTerm($dir) . $val;
						if (is_file($path)) {
							try {
								unlink($path);
							} catch (Exception $e) {
								throw new Exception ('Could not delete file: ' . $path);
							}
						}

						$Model->create();
						if (!$Model->save(['id' => $id, $field => ''], ['validate' => false, 'callbacks' => false])) {
							throw new Exception("Could empty model field `$field` after deleting image");
						}
						Upload::removeEmptySubFolders($dir);
					}
				}
			endforeach;
		endif;
		return true;
	}

/**
 * Returns an array of directories based on the various sizes of a field with their respective configureation
 * 
 * @param Model $Model The associated Model object
 * @param string $field The specific Model field
 * @return Array An array with each directory as a key and each directory config as the values
 *
 **/
	private function _getFieldSizeDirs(Model $Model, $field) {
		App::uses('Folder', 'Utility');
		if (empty($this->fields[$Model->alias][$field])) {
			return null;
		}
		$config = $this->fields[$Model->alias][$field];

		if (empty($config['dir'])) {
			throw new Exception(sprintf('No upload directory found for field: "%s"', $field));
		}

		$dir = $this->_getFieldDir($Model, $field);
		$dirs = [];
		if (empty($config['sizes'])) {
			$dirs[$dir] = [];
		} else {
			foreach ($config['sizes'] as $size => $sizeConfig) {
				if (is_numeric($size)) {
					$size = $sizeConfig;
					$sizeConfig = [];
				}
				if ($sizeConfig !== false) {
					$dirs[Folder::slashTerm(Folder::addPathElement($dir, $size))] = $sizeConfig;
				}
			}
		}
		return $dirs;
	}

/**
 * Initializes a field configuration
 *
 **/
	private function _initFieldSettings(Model $Model, $field, $config) {

		if (empty($config['dir'])) {
			$config['dir'] = sprintf('img{DS}%s{DS}%s', Inflector::tableize($Model->alias), $field);
		}

		if (empty($config['root'])) {
			$config['root'] = $this->_getWebroot(!empty($config['plugin']) ? $config['plugin'] : null);
		}

		// Replaces constants within the directory
		$config['dir'] = $this->_dsReplace($config['dir']);

		if (empty($config['isImage'])):
			$config['sizes'] = false;
		else:
			// Configures sizes
			$sizes = [];
			if (!isset($config['sizes'])) {
				// If no sizes are set, it copies all from the config file
				$sizes = Configure::read('Uploadable.sizes');
			} else {
				if (!is_array($config['sizes'])) {
					$config['sizes'] = [$config['sizes']];
				}
				foreach ($config['sizes'] as $size => $sizeConfig) {
					if (is_numeric($size)) {
						$size = $sizeConfig;
						$sizeConfig = null;
					}
					// If size config is set to false it skips that size
					if ($sizeConfig !== false) {
						if ($sizeConfig === null && Configure::check('Uploadable.sizes.' . $size)) {
							$sizeConfig = Configure::read('Uploadable.sizes.' . $size);
						}
						$sizes[$size] = $sizeConfig;
					}

				}
			}
			$config['sizes'] = $sizes;
		endif;

		return $config;
	}

/**
 * Retrieves the image information stored in a field, used for a result array
 *
 * @param Model $Model The associated model
 * @param string $field The model field
 * @param string $value The current value of the model's field
 * @return array An array with information for each size associated with the image field
 **/
	private function _setResultField(Model $Model, $field, $value) {
		App::uses('Folder', 'Utility');

		$config = $this->fields[$Model->alias][$field];

		$result = ['isDefault' => false];
		$webroot = $config['root'];

		if (!empty($config['sizes']) && is_array($config['sizes'])):
			foreach ($config['sizes'] as $size => $sizeConfig):
				$result['sizes'][$size] = $this->_getResultFieldRow($Model, $field, $value, $size);
				if (!empty($result['sizes'][$size]['isDefault'])) {
					$result['isDefault'] = true;
				}
			endforeach;
		else:
			$result['file'] = $this->_getResultFieldRow($Model, $field, $value);
			if (!empty($result['file']['isDefault'])) {
				$result['isDefault'] = true;
			}
		endif;
		return $result;
	}

	private function _getResultFieldRow($Model, $field, $value = null, $size = null) {
		$root = Folder::slashTerm($this->_getFieldDir($Model, $field));
		$config = $this->fields[$Model->alias][$field];

		$path = $src = $width = $height = $mime = $filesize = $modified = null;
		if (isset($size)) {
			$basePath = Folder::addPathElement($root, $size);
		} else {
			$basePath = $root;
		}
		$row = array();

		if (!empty($value)) {
			$path = UrlPath::normalizeFilePath([$basePath, $value]);
			$row = $this->_getFileInfo($path, $config['plugin'], $config['isImage']);
		}

		// Checks for default images if path is not found
		if (empty($row['path']) && !empty($config['default'])) {
			$row['isDefault'] = true;
			$path = Folder::slashTerm($basePath) . 'default.jpg';
			$row = $this->_getFileInfo($path, $config['plugin'], $config['isImage']);
			if (empty($row['path'])) {
				$this->_updateDefaultImage($Model, $field);
				$row = $this->_getFileInfo($path, $config['plugin'], $config['isImage']);
			}
		}
		return $row;
	}

/**
 * Determines if a size exists for a Model's field
 *
 * @param Model $Model the referenced Model object
 * @param string $field The model field
 * @param string $size The size to check
 * @return bool;
 **/
	private function hasSize($Model, $field, $size) {
		if (!empty($this->fields[$Model->alias][$field]['sizes'])) {
			return array_key_exists($size, $this->fields[$Model->alias][$field]['sizes']);
		} else {
			return false;
		}
	}


/**
 * Retrieves information about a file
 *
 * @param string $path The path to the file
 * @param string $plugin If applicable, the plugin where the image is stored
 * @return array An array of file information. All will be set to null if file is not found
 **/
	private function _getFileInfo($path, $plugin = false, $isImage = false) {
		/*
		$pluginSplit = pluginSplit($path);
		if (!empty($pluginSplit[0])) {
			list($plugin, $path) = $pluginSplit;
		}
		*/
		//$path = str_replace('/', DS, $path);
		$webroot = $this->_getWebroot($plugin);
		$fileAttrs = ['path', 'src', 'mime', 'extension', 'webroot', 'filesize', 'modified'];
		if ($isImage) {
			$fileAttrs = array_merge($fileAttrs, ['width', 'height']);
		}

		if (!empty($webroot) && strpos($path, $webroot) === 0) {
			$src = [substr($path, strlen($webroot) - 1)];
			if ($plugin) {
				array_unshift($src, '/' . Inflector::underscore($plugin));
			}
			if (!empty($this->_urlBase)) {
				array_unshift($src, $this->_urlBase);
			}
			$src = Router::url(UrlPath::normalizeUrlPath($src), true);
		}

		if (($queryCut = strpos($path, '?')) !== false) {
			$path = substr($path, 0, $queryCut);
		}
		if (is_file($path)) {
			$pathInfo = pathinfo($path);
			$extension = $pathInfo['extension'];
			if (function_exists('mime_content_type')) {
				$mime = mime_content_type($path);
			} else {
				$mime = null;
			}

			$filesize = filesize($path);
			$modified = filemtime($path);
			if ($isImage) {
				$imageSize = getimagesize($path);
				list($width, $height) = $imageSize;
			}
		} else {
			$path = null;
		}
		$info = compact($fileAttrs);
		return $info;
	}

/**
 * Takes a default image in the config and makes sure it exists for all sizes of the image
 *
 * @param Model $Model The associated model
 * @param string $field The field of the image in the model
 * @return bool True on success, false on failure
 **/
	private function _updateDefaultImage(Model $Model, $field) {
		$config = $this->fields[$Model->alias][$field];
		$webroot = $this->_getWebroot($config['plugin']);

		if (!empty($config['default'])) {
			$dirs = $this->_getFieldSizeDirs($Model, $field);
			$defaultImagePath = $webroot . $this->_dsReplace($config['default'], '/');

			$data = array('name' => $defaultImagePath, 'tmp_name' => $defaultImagePath);
			$config['filename'] = 'default.jpg';
			unset($config['randomPath']);

			if (!Upload::copy($data, $dirs, $config)) {
				throw new Exception('Could not create new default image: ' . $defaultImagePath);
				return false;
			}
		}
		return true;
	}

/**
 * Returns the base upload directory for a Model's field
 *
 * @param Model $Model The associated model
 * @param string $field The field of the image in the model
 * @return string The base upload directory
 **/
	private function _getFieldDir(Model $Model, $field) {
		return Folder::addPathElement($this->fields[$Model->alias][$field]['root'], $this->fields[$Model->alias][$field]['dir']);
	}

/**
 * Updates any queues that were saved before an ID was created
 *
 * @param Model $Model The Model object
 * @param int $createdId The newly created ID
 * @return void;
 **/
	private function _updateQueueModelIdKeys(Model $Model, $createdId) {
		$queues = ['_deleteQueue', '_uploadQueue', '_uploadFromUrlQueue', '_cropCopyQueue'];
		foreach ($queues as $queue) {
			if (isset ($this->{$queue}[$Model->alias][''])) {
				$this->{$queue}[$Model->alias][$createdId] = $this->{$queue}[$Model->alias][''];
				unset($this->{$queue}[$Model->alias]['']);
			}
		}
	}


/**
 * Replaces Directory Separator constant placeholder with actual constant
 *
 * @param string $path The user-defined path element with {DS} as the directory separator
 * @param string $ds The new directory separator to use
 * @return string The newly-formed path
 **/
	private function _dsReplace($path, $ds = DS) {
		return str_replace('{DS}', $ds, $path);
	}

/**
 * Returns the webroot where files are stored
 *
 * @param string $plugin A plugin the current model belongs to
 * @return string;
 **/	
	private function _getWebroot($plugin = false) {
		return $plugin ? APP . 'Plugin' . DS . $plugin . DS . 'webroot' . DS : $this->_webroot;
	}


/**
 * Returns directory
 * If the directory does not exist, creates it
 *
 * @param string $dir The directory in question
 * @return string Formatted directory string
 **/
	private function _getDir($dir) {
		if (substr($dir, -1) !== DS) {
			$dir .= DS;
		}
		if (!is_dir($dir)) {
			if (!mkdir($dir, 755, true)) {
				throw new Exception("COULD NOT CREATE DIR: $dir");
				return false;
			}
		}
		return $dir;
	}

/**
 * Returns a sub directory of the uploadable temporary directory
 * 
 * @param string $dir The directory in question
 * @return string Formatted directory string
 **/
	private function _getTmpDir($dir) {
		return $this->_getDir(TMP . 'uploadable' . DS . $dir);
	}
}