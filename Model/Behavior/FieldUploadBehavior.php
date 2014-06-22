<?php
App::uses('Image', 'Uploadable.Lib');
App::uses('Upload', 'Uploadable.Lib');
App::uses('PluginConfig', 'Uploadable.Lib');

App::uses('Hash', 'Utility');
App::uses('Folder', 'Utility');

class FieldUploadBehavior extends ModelBehavior {

	public $name = 'FieldUpload';

	public $fields = [];

	// Created in beforeSave and executed in afterSave
	protected $_uploadQueue = [];
	protected $_deleteQueue = [];

	public function setup(Model $Model, $settings =[]) {
		PluginConfig::init('Uploadable');

		// Fields Settings
		$defaultFieldSettings = [
			// The base upload directory
			'dir' => null,			
			// An array of sizes. They'll be set in the _initFieldSettings method
			'sizes' => null,		
			// The file root. Defaults to CakePHP's webroot
			'root' => WWW_ROOT,		
			// Whether the uploaded file is an image or not
			'isImage' => true,	
			// Whether a ranom path of folders (eg: "/01/05/72/") should be inserted between the path and filname.
			//  This is helpful for folders with many images	
			'randomPath' => true,	
		];
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

	public function afterFind(Model $Model, $results, $primary = false) {
		// Adds additional information to the find result pertaining to the uploaded files
		foreach ($this->fields[$Model->alias] as $field => $fieldConfig) {
			if (isset($results[$field])) {
				$results['uploadable'][$field] = $this->_setResultField($Model, $results[$field]);
			} else if (isset($results[$Model->alias][$field])) {
				// Single row result
				$results[$Model->alias]['uploadable'][$field] = $this->_setResultField($Model, $field, $results[$Model->alias][$field]);
			} else if (isset($results[0][$Model->alias][$field])) {
				// Multiple row result
				foreach ($results as $k => $row) {
					$results[$k][$Model->alias]['uploadable'][$field] = $this->_setResultField($Model, $field, $row[$Model->alias][$field]);
				}
			} else if (isset($results[$Model->alias][0][$field])) {
				// Associated result
				foreach ($results[$Model->alias] as $k => $row) {
					$results[$Model->alias][$k]['uploadable'][$field] = $this->_setResultField($Model, $field, $row[$field]);
				}
			}
		}
		return $results;
	}

	public function beforeSave(Model $Model, $options = []) {
		if (isset($Model->data[$Model->alias])) {
			$data =& $Model->data[$Model->alias];
		} else {
			$data =& $Model->data;
		}

		$id = !empty($data['id']) ? $data['id'] : '';
		foreach ($this->fields[$Model->alias] as $field => $config):
			if (isset($data[$field]) && is_array($data[$field])) {
				// If any files have been uploaded, queue them for upload
				if (!empty($data[$field]['tmp_name'])) {
					$this->_addUploadQueue($Model, $id, $field, $data[$field]);
				}
				// If the files have been marked for deletion, queue them for deletion
				if (!empty($data[$field]['delete'])) {
					$this->_addDeleteQueue($Model, $id, $field);
				} 
				unset($data[$field]);
			}
		endforeach;
	}

	public function afterSave(Model $Model, $created, $options = []) {
		$id = $Model->id;
		if ($created) {
			$this->_updateQueueModelIdKeys($Model, $id);
		}
		$this->_startUploadQueue($Model, $id);	// Uploads anything found in beforeSave
		$this->_startDeleteQueue($Model, $id);	// Deletes anything marked for deletion

		return parent::afterSave($Model, $created, $options);
	}

	public function beforeDelete(Model $Model, $cascade = true) {
		// Stores files to be deleted after the Model is deleted
		$this->_addDeleteQueue($Model, $Model->id);
		return parent::beforeDelete($Model, $cascade);
	}

	public function afterDelete(Model $Model) {
		// Deletes any queueed files
		$this->_startDeleteQueue($Model);
		return parent::afterDelete($Model);
	}

	public function uploadField(Model $Model, $id, $field, $filepath) {
		return $this->_uploadField($Model, $id, ['tmp_name' => $filepath]);
	}

	private function _addUploadQueue(Model $Model, $id, $field, $fieldData) {
		$this->_uploadQueue[$Model->alias][$id][$field] = $fieldData;
	}
/**
 * Uploads all files in the queue
 *
 * @param Model $Model The associated Model object
 * @return void
 **/
	private function _startUploadQueue(Model $Model, $id = null) {
		if (!empty($id)) {
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

		$config['filename'] = $Model->id . '.' . $ext;
		if (!empty($config['randomPath'])) {
			$config['filename'] = Folder::slashTerm(Upload::randomPath($config['filename'])) . $config['filename'];
			if (DS == '\\') {
				$config['filename'] = str_replace('\\', '/', $config['filename']);
			}
			$config['randomPath'] = false;	//Turns it off before passing to the Upload Utility so we don't do it twice
		}

		if ($result = Upload::copy($data, $dirs, $config)) {
			$Model->save([
				$Model->primaryKey => $Model->id,
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
						if (!is_file($path) || unlink($path)) {
							$Model->updateAll(
								[$Model->escapeField($field) => '""'], 
								[$Model->escapeField($Model->primaryKey) => $id]
							);
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

		// Replaces constants within the directory
		$replace = ['{DS}' => DS,];
		$config['dir'] = str_replace(array_keys($replace), $replace, $config['dir']);

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

		return $config;
	}

	private function _setResultField(Model $Model, $field, $value) {
		App::uses('Folder', 'Utility');

		$config = $this->fields[$Model->alias][$field];

		$result = [];
		$root = Folder::slashTerm($this->_getFieldDir($Model, $field));

		foreach ($config['sizes'] as $size => $sizeConfig):
			$path = $src = $width = $height = $mime = $filesize = null;
			if (!empty($value)) {
				$path = Folder::addPathElement($root, $size);
				$path = Folder::slashTerm($path) . $value;
				if (strpos($path, WWW_ROOT) === 0) {
					$src = substr($path, strlen(WWW_ROOT) - 1);
					if (DS == '\\') {
						$src = str_replace(DS, '/', $src);
					}
				}
				if (is_file($path)) {
					$info = getimagesize($path);
					list($width, $height) = $info;
					$mime = $info['mime'];
					$filesize = filesize($path);
				}
			} 
			$result['sizes'][$size] = compact('path', 'src', 'width', 'height', 'mime', 'filesize'); 
		endforeach;
		return $result;
	}

/**
 * Returns the base upload directory for a Model's field
 *
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
		$queues = ['_deleteQueue', '_uploadQueue'];
		foreach ($queues as $queue) {
			if (isset ($this->{$queue}[$Model->alias][''])) {
				$this->{$queue}[$Model->alias][$createdId] = $this->{$queue}[$Model->alias][''];
				unset($this->{$queue}[$Model->alias]['']);
			}
		}
	}
}