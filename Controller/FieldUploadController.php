<?php
App::uses('Inflector', 'Utility');

class FieldUploadController extends UploadableAppController {
	public $uses = [];
	public $components = ['Session'];
	public $helpers = ['Uploadable.FieldUploadImage'];

	private $_Model;

	public function edit($modelName = null, $modelId = null, $field = null, $size = null) {
		$this->_setEditVars($modelName, $modelId, $field, $size);
		
	}

	public function upload($modelName = null, $modelId = null, $field = null, $size = null) {
		$hasData = !empty($this->request->data);
		$result = $this->_setEditVars($modelName, $modelId, $field, $size);
		if ($hasData) {
			$data = $this->request->data[$this->_Model->alias];
			if ($this->_Model->save($data)) {
				$this->_fieldUploadFlash('Successfully added image', $data['redirect']);
			} else {
				$this->_fieldUploadFlash('There was an error saving the image');
			}
		} else {
			$this->request->data = $result;
		}
	}

/**
 * @param string $modelName The name of the model
 * @param int $modelId The model id
 * @param string $field The field to manipulate
 * @return void
 **/
	public function resize($modelName = null, $modelId = null, $field = null, $size = null) {
		$hasData = !empty($this->request->data);
		$result = $this->_setEditVars($modelName, $modelId, $field, $size);
		if ($hasData) {
			$data = $this->request->data[$this->_Model->alias];
			if ($this->_Model->save($data)) {
				$this->_fieldUploadFlash('Successfully resized image', $data['redirect']);
			} else {
				$this->_fieldUploadFlash('There was an error resizing the image');
			}
		}
	}

	private function _setEditVars($modelName, $modelId, $field, $size) {
		$result = $this->_setFieldUploadModel($modelName, $modelId, $field);

		if (!empty($this->request->data[$this->_Model->alias]['redirect'])) {
			$redirect = $this->request->data[$this->_Model->alias]['redirect'];
		} else {
			$redirect = $this->referer();
			if ($redirect == '/') {
				list($plugin, $alias) = pluginSplit($modelName);
				$redirect = Router::url([
					'controller' => Inflector::tableize($alias),
					'action' => 'view',
					$modelId,
					'plugin' => Inflector::underscore($plugin),
				], true);
			}
		}
		$this->set(compact('size', 'redirect'));
		$this->request->data += $this->_Model->getCropCopyFieldUploadSettings($modelId, $field, $size);
		return $result;
	}

	private function _setFieldUploadModel($modelName, $modelId, $field) {
		list($plugin, $alias) = pluginSplit($modelName);
		$this->_Model = ClassRegistry::init($modelName);
		$primaryKey = $this->_Model->primaryKey;
		$result = $this->_Model->find('first', [
			'fields' => [$field, $this->_Model->escapeField()],
			'conditions' => [$this->_Model->escapeField() => $modelId],
		]);
		$config = $this->_Model->getFieldUploadConfig($field);
		$this->set(compact('modelName', 'alias', 'primaryKey', 'modelId', 'field', 'result', 'config'));
		return $result;
	}

	private function _fieldUploadFlash($msg, $redirect = null) {
		$this->Flash->alert($msg);
		if (!empty($redirect)) {
			if ($redirect === true) {
				$redirect = $this->referer();
			}
		}
		if (!empty($redirect)) {
			$this->redirect($redirect);
		}
	}

}