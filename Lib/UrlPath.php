<?php
/**
 * Library class to handle file paths, url paths, and converting between the two
 *
 **/
App::uses('Folder', 'Utility');
App::uses('Image', 'Uploadable.Lib');

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

	static public function getExtension($path) {
		if (is_file($path)) {
			$info = pathinfo($path);
			$ext = !empty($info['extension']) ? $info['extension'] : false;
			if (empty($ext)) {
				$ext = Image::mimeExtension(mime_content_type($path));
			}
		} else {
			$parts = explode('.', $path);
			if (count($parts) > 1) {
				$ext = array_pop($parts);
			}
		}
		$parts = explode('?', $ext);
		$ext = array_shift($parts);
		return $ext;
	}
}