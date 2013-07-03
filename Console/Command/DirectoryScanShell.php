<?php
class DirectoryScanShell extends AppShell {
	public function main() {
		$model = $this->args[0];
		
		$this->out("Scanning Auto Upload Directory for $model");
		$this->uses[] = $model;
		ClassRegistry::init($model)->scanAutoUploadDirectory($dir);
	}
}