<?php
/*
CREATE TABLE embedded_images (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    model VARCHAR(64),
    foreign_key INT(11),
    INDEX (model, foreign_key),
    uid INT(5),
    UNIQUE INDEX(model, foreign_key, uid),
    filename VARCHAR(128)
);
*/
App::uses('UploadableAppModel', 'Uploadable.Model');
class EmbeddedImage extends UploadableAppModel {
	public $name = 'EmbeddedImage';
	public $actsAs = [
		'Uploadable.FieldUpload' => [
			'fields' => ['filename']
		]
	];

	public $order = ['EmbeddedImage.uid' => 'ASC'];

	public function beforeSave($options = []) {
		if (isset($this->data[$this->alias])) {
			$data =& $this->data[$this->alias];
		} else {
			$data =& $this->data;
		}

		// Find max uid if not present
		if (!empty($data['model']) && !empty($data['foreign_key'])) {
			$result = $this->find('all', [
				//'fields' => [$this->escapeField('uid'), $this->escapeField('id')],
				'conditions' => [
					$this->escapeField('model') => $data['model'],
					$this->escapeField('foreign_key') => $data['foreign_key'],
				],
				'order' => [$this->escapeField('uid') => 'DESC'],
			]);
			$uids = Hash::extract($result, '{n}.EmbeddedImage.uid');
			$uidIds = Hash::combine($result, '{n}.EmbeddedImage.uid', '{n}.EmbeddedImage.id');

			if (empty($data['uid']) // No UID was set
				|| (empty($data['id']) && !empty($uidIds[$data['uid']])) // This is a new ID and the UID exists already
				|| (!empty($data['id']) && !empty($uidIds[$data['uid']]) && $uidIds[$data['uid']] != $data['id']) // Some other model has the UID
			) {
				if (!empty($uidIds)) {
					$data['uid'] = $uids[0] + 1;
				} else {
					$data['uid'] = 1;
				}
			} 
		}
		return parent::beforeSave($options);
	}

	public function afterSave($created, $options = []) {
		$this->deleteEmpty();
		return parent::afterSave($created, $options);
	}

	public function deleteEmpty() {
		return $this->deleteAll([$this->escapeField('filename') => null]);
	}

	public function reorder($model, $foreignKey) {
		$result = $this->find('all', [
			'conditions' => [
				$this->escapeField('model') => $model,
				$this->escapeField('foreign_key') => $foreignKey,
			]
		]);
		$data = [];
		foreach ($result as $k => $row) {
			$data[] = [
				'id' => $row[$this->alias]['id'],
				'uid' => ($k + 1)
			];
		}
		$success = $this->saveAll($data);
		return $success;
	}
}