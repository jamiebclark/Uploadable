<?php
/**
 * Library class to handle file paths, url paths, and converting between the two
 *
 **/
App::uses('Folder', 'Utility');

class UrlPath {
	static public function normalizeFilePath($path) {
		$find = DS == '/' ? '\\' : '/';
		return self::_normalizePath($path, $find);
	}

	static public function normalizeUrlPath($path) {
		return self::_normalizePath($path, '\\', '/');
	}

	static protected function _normalizePath($path, $find, $replace = DS) {
		if (is_array($path)) {
			$newPath = '';
			$lastPathKey = count($path) - 1;
			foreach ($path as $k => $term) {
				if ($k < $lastPathKey) {
					$term = Folder::slashTerm($term);
				}
				$newPath .= $term;
			}
			$path = $newPath;
		}
		return str_replace($find, $replace, $path);
	}
}