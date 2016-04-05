<?php
class FieldUploadSimpleComponent extends Component {
	public $name = 'FieldUploadSimple';

	public $components = array('Session');

	public function startup(Controller $controller) {
		$request =& $controller->request;
		$alias = $controller->modelClass;

		// Refreshes the default image
		if (!empty($request->query['refresh_default_image'])) {
			ClassRegistry::init($alias)->refreshFieldUploadDefaultImage($request->query['refresh_default_image']);
		}

		// Prefix-less action
		$action = $request->params['action'];
		if (!empty($request->params['prefix'])) {
			$action = substr($action, strlen($request->params['prefix']) + 1);
		}

		if ($action == 'field_upload_simple_edit') {
			list($id, $field, $size) = ((array) $request->params['pass']) + array(null, null, null);
			$Model = ClassRegistry::init($alias);

			if (!empty($request->data)) {

				$success = $Model->save($request->data[$alias], array('validate' => false));

				if ($success) {
					$this->Flash->success('Successfully updated ' . $field . ' image');
					$controller->redirect(array('action' => 'view', $id));
				} else {
					$this->Flash->error('There was an error saving the image');
				}

			} else {
				$request->data = $Model->find('first', array('conditions' => array($Model->escapeField() => $id)));
			}

			$request->params['action'] = 'field_upload_simple_edit';
			$controller->autoRender = false;
			$controller->set(compact('className', 'field', 'size'));
			return $controller->render('Uploadable./Elements/field_upload_images/simple_edit');
		}
		return parent::startup($controller);
	}
}