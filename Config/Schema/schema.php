<?php 
class UploadableSchema extends CakeSchema {

	public function before($event = array()) {
		return true;
	}

	public function after($event = array()) {
	}

	public $embedded_images = array(
		'id' => array('type' => 'integer', 'null' => false, 'default' => null, 'key' => 'primary'),
		'model' => array('type' => 'string', 'null' => true, 'default' => null, 'length' => 64, 'key' => 'index', 'collate' => 'latin1_swedish_ci', 'charset' => 'latin1'),
		'foreign_key' => array('type' => 'integer', 'null' => true, 'default' => null),
		'uid' => array('type' => 'integer', 'null' => true, 'default' => null, 'length' => 5),
		'filename' => array('type' => 'string', 'null' => true, 'default' => null, 'length' => 128, 'collate' => 'latin1_swedish_ci', 'charset' => 'latin1'),
		'indexes' => array(
			'PRIMARY' => array('column' => 'id', 'unique' => 1),
			'model_2' => array('column' => array('model', 'foreign_key', 'uid'), 'unique' => 1),
			'model' => array('column' => array('model', 'foreign_key'), 'unique' => 0)
		),
		'tableParameters' => array('charset' => 'latin1', 'collate' => 'latin1_swedish_ci', 'engine' => 'InnoDB')
	);

}
