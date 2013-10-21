<?php
class DefaultImage extends UploadableAppModel {
	public $name = 'DefaultImage';
	public $useTable = false;
	
	private $behaviorKey = 'Uploadable.ImageUploadable';
	
	public function find($type = 'first', $models = array()) {
		if (!empty($models) && !is_array($models)) {
			$models = array($models);
		}
		
		$modelNames = App::objects('model');
		$dirs = array();
		foreach ($modelNames as $model) {
			if (!empty($models) && !in_array($model, $models)) {
				continue;
			}
			$Model = ClassRegistry::init($model);
			if (!empty($Model->actsAs[$this->behaviorKey])) {
				$uploadDir = $Model->getUploadDir();
				$uploadDirRoot = $Model->getUploadDir(null, true);
				$dirs[$model] = array(
					'alias' => $model,
					'root' => $uploadDirRoot,
					'dir' => $uploadDir,
				);
				if (!empty($Model->actsAs[$this->behaviorKey]['dirs'])) {
					foreach ($Model->actsAs[$this->behaviorKey]['dirs'] as $dir => $config) {
						$dirs[$model]['dirs'][] = $dir;
					}
				} else {
					$dirs[$model]['dirs'] = array($uploadDir);
				}
			}
			if ($type == 'first') {
				return $dirs[$model];
			}
		}
		return $dirs;
	}
}