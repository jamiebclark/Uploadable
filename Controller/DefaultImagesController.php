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
				$this->Flash->success('Saved new default image for ' . $model);
				$this->redirect(array('action' => 'view', $model));
			} else {
				$this->Flash->error('Error saving default image for ' . $model);
			}
		}
		$defaultImage = $this->DefaultImage->find('first', $model);
		$this->set(compact('defaultImage', 'model'));
	}
}