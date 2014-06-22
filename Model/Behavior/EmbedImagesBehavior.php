<?php
class EmbedImagesBehavior extends ModelBehavior {
	public $name = 'EmbedImages';

	public function setup(Model $Model, $settings = []) {
		// Bind model
		$className = $Model->alias;
		if (!empty($Model->plugin)) {
			$className = $Model->plugin . '.' . $className;
		}
		
		if (!$Model->Behaviors->loaded('Uploadable.ContainFieldUpload')) {
			$Model->Behaviors->load('Uploadable.ContainFieldUpload');
		}

		$Model->bindModel([
			'hasMany' => [
				'EmbeddedImage' => [
					'className' => 'Uploadable.EmbeddedImage',
					'dependent' => true,
					'foreignKey' => 'foreign_key',
					'conditions' => ['EmbeddedImage.model' => $className]
				]
			]
		], false);
	}
}