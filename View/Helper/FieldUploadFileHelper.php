<?php
App::uses('FieldUploadHelper', 'Uploadable.View/Helper');
class FieldUploadFileHelper extends FieldUploadHelper {

	protected function getBaseUrl() {
		return APP . 'webroot' . DS . 'files' . DS;
	}

}