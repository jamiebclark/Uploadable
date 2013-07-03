<?php
class DirectoryScanShell extends AppShell {
	public function main() {
		list($model, $dir) = $this->args + array(null, null);
		$this->out("Scanning Auto Upload Directory for $model");
		$this->uses[] = $model;
		ClassRegistry::init($model)->scanAutoUploadDirectory($dir);
	}
}