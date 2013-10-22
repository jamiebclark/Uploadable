<?php
class DefaultImage extends UploadableAppModel {
	public $name = 'DefaultImage';
	public $useTable = false;
	
	private $behaviorKey = 'Uploadable.ImageUploadable';
	
	public function find($type = 'first', $models = array()) {
		if (!empty($models) && !is_array($models)) {
			$models = array($models);
		}
		$allModels = $this->_getModelNames();
		$result = array();
		foreach ($allModels as $model) {
			if (!empty($models) && !in_array($model, $models)) {
				continue;
			}
			$result = $this->_getModel($model, $result);
		}
		if ($type == 'first' && !empty($result)) {
			$result = array_shift($result);
		}
		return $result;
	}
	
	private function _getModel($model, $result = array()) {
		$Model = ClassRegistry::init($model);
		list($plugin, $alias) = pluginSplit($model);
		if (!empty($Model->actsAs[$this->behaviorKey])) {
			$uploadDir = $Model->getUploadDir();
			$uploadDirRoot = $Model->getUploadDir(null, true);
			$result[$model] = compact('alias', 'plugin') + array(
				'root' => $this->_filePath($uploadDirRoot),
				'dir' => $this->_filePath($uploadDir),
			);
			if (!empty($Model->actsAs[$this->behaviorKey]['dirs'])) {
				foreach ($Model->actsAs[$this->behaviorKey]['dirs'] as $dir => $config) {
					$result[$model]['dirs'][] = $dir;
				}
			} else {
				$result[$model]['dirs'] = array($uploadDir);
			}
		}
		return $result;
	}
	
	private function _getModelNames() {
		$allModels = App::objects('Model');
		if ($plugins = App::objects('plugin')) {
			foreach($plugins as $plugin) {
				if (CakePlugin::loaded($plugin)) {
					if ($pluginModels = App::objects("$plugin.Model")) {
						foreach ($pluginModels as $model) {
							$allModels[] = "$plugin.$model";
						}
					}
				}
			}
		}
		return $allModels;	
	}
	
	private function _filePath($files = array(), $ds = DS) {
		$path = is_array($files) ? implode($ds, $files) : $files;
		return str_replace(array('//', '\\\\', '\\', '/', '\\/'), $ds, $path);
	}
}