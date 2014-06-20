<?php
/**
 * Since CakePHP's Containable Behavior won't fire afterFind and beforeFind on contained models,
 * this behavior must be included on models that have models using the FieldUpload Behavior
 *
 **/
class ContainFieldUploadBehavior extends ModelBehavior {
	public $name = 'ContainFieldUpload';

	public function afterFind(Model $Model, $results, $primary = false) {
		if (isset($results[0][$Model->alias])) {
			// Multiple Row Result
			foreach ($results as &$result) {
				$result = $this->_resultAfterFind($Model, $result);
			}
		} else if (isset($results[$Model->alias])) {
			// Single Result
			$results = $this->_resultAfterFind($Model, $results);
		}
		return $results;
	}

	private function _resultAfterFind(Model $Model, $result) {
		foreach ($result as $modelName => $modelResult) {
			if (
				!empty($Model->{$modelName}) && 
				is_object($Model->{$modelName}) && 
				$Model->{$modelName}->Behaviors->loaded('FieldUpload')
			) {
				$result = $Model->{$modelName}->Behaviors->FieldUpload->afterFind($Model->{$modelName}, $result);
			}
		}
		return $result;
	}
}