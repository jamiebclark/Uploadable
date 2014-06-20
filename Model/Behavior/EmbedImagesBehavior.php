<?php
class EmbedImagesBehavior extends ModelBehavior {
	public $name = 'EmbedImages';

	public function setup(Model $Model, $settings = []) {
		// Bind model
		$className = $Model->alias;
		if (!empty($Model->plugin)) {
			$className = $Model->plugin . '.' . $className;
		}
		$Model->bindModel([
			'hasMany' => [
				'EmbeddedImage' => [
					'className' => 'Uploadable.EmbeddedImage',
					'foreignKey' => 'foreign_key',
					'conditions' => ['EmbeddedImage.model' => $className]
				]
			]
		], false);
	}
}