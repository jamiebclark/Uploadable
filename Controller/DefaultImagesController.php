<?php
class DefaultImagesController extends UploadableAppController {
	public $name = 'DefaultImages';
	public $helpers = array('Uploadable.DefaultImage');
	
	public function admin_index() {
		$defaultImages = $this->DefaultImage->find('all');
		$this->set(compact('defaultImages'));
	}
	
	public function admin_view($model = null) {
		if (!empty($this->request->data)) {
			$Model = ClassRegistry::init($model);
			if ($Model->saveDefaultImage($this->request->data['DefaultImage']['default_image'])) {
				$this->Session->setFlash('Saved new default image for ' . $model, 'default', array('class' => 'alert-success'));
				$this->redirect(array('action' => 'view', $model));
			} else {
				$this->Session->setFlash('Error saving default image for ' . $model, 'default', array('class' => 'alert-error'));
			}
		}
		$defaultImage = $this->DefaultImage->find('first', $model);
		$this->set(compact('defaultImage', 'model'));
	}
}