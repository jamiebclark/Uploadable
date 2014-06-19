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
		$fields = $settings['fields'];
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
		foreach ($this->fields[$Model->alias] as $field => $fieldConfig) {
			if (isset($results[$field])) {
				$results['uploadable'][$field] = $this->_setResultField($Model, $results[$field]);
			} else if (isset($results[$Model->alias][$field])) {
				$results[$Model->alias]['uploadable'][$field] = $this->_setResultField($Model, $field, $results[$Model->alias][$field]);
			} else if (isset($results[0][$Model->alias][$field])) {
				foreach ($results as $k => $row) {
					$results[$k][$Model->alias]['uploadable'][$field] = $this->_setResultField($Model, $field, $results[$k][$Model->alias][$field]);
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

		foreach ($this->fields[$Model->alias] as $field => $config):
			if (isset($data[$field]) && is_array($data[$field])) {
				if (!empty($data[$field]['tmp_name'])) {
					$this->_uploadQueue[$Model->alias][$field] = $data[$field];
				}
				if (!empty($data[$field]['delete'])) {
					$this->_deleteQueue[$Model->alias][$field] = true;
				} 
				unset($data[$field]);
			}
		endforeach;
	}

	public function afterSave(Model $Model, $created, $options = []) {
		$this->_startUploadQueue($Model);
		$this->_startDeleteQueue($Model);
		return parent::afterSave($Model, $created, $options);
	}

	public function beforeDelete(Model $Model, $cascade = true) {
		$this->_deleteFields($Model, $Model->id);
		return parent::beforeDelete($Model, $cascade);
	}

	private function _startUploadQueue(Model $Model) {
		if (!empty($this->_uploadQueue[$Model->alias])):
			foreach ($this->_uploadQueue[$Model->alias] as $field => $data):
				$this->_uploadField($Model, $field, $data);
				unset($this->_uploadQueue[$Model->alias][$field]);
			endforeach;
		endif;
	}

	private function _startDeleteQueue(Model $Model) {
		if (!empty($this->_deleteQueue[$Model->alias])):
			$this->_deleteFields($Model, $Model->id, array_keys($this->_deleteQueue[$Model->alias]));
			$this->_deleteQueue[$Model->alias] = [];
		endif;
	}

	private function _uploadField(Model $Model, $field, $data) {
		App::uses('Folder', 'Utility');

		if (!isset($this->fields[$Model->alias][$field])) {
			throw new Exception(sprintf('FieldUpload cannot work with field: "%s". No config information found', $field));
		}
		$config = $this->fields[$Model->alias][$field];

		$dirs = $this->_getFieldSizeDirs($Model, $field);

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
 * @param Model $Model The model calling the function
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
					foreach ($dirs as $dir) {
						$path = Folder::slashTerm($dir) . $value;
						if (is_file($path)) {
							unlink($path);
						}
					}
				}
			endforeach;
		endif;
		return true;
	}

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
}