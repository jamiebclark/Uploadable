<?php
class DefaultImageHelper extends AppHelper {
	public $name = 'DefaultImage';
	public $helpers = array('Html');
	
	private $filename = '0.jpg';
	
	public function image($result, $options = array()) {
		if (!isset($options['dir'])) {
			$dirs = $result['dirs'];
			$options['dir'] = array_pop($dirs);
		}
		$dir = $options['dir'];
		unset($options['dir']);
		$file = $this->_filePath(array($dir, $this->filename));
		$path = $this->_filePath(array($result['root'], $file));
		
		if (!is_file($path)) {
			$out = $this->Html->tag('em', 'N/A');
			if (!empty($options['url'])) {
				$out = $this->Html->link($out, $options['url'], array('escape' => false));
			}
		} else {
			$src = $this->_filePath(array($result['dir'], $file), '/');
			if (!empty($result['plugin'])) {
				$src = $result['plugin'] . '.' . $src;
			}
			$out = $this->Html->image($src, $options);
		}
		return $out;
	}
	
	private function _filePath($files = array(), $ds = DS) {
		$path = implode($ds, $files);
		return str_replace(array('\\', '/', '\\/'), $ds, $path);
	}
}