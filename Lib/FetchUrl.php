<?php
class FetchUrl {

/**
 * Stores a file from a remote location to a local location
 *
 * @param string $url The remote url
 * @param string $dst The destination path
 * @return bool
 * @throws Exception if it can't place the contents
 **/
	static public function put($url, $dst) {
		$content = self::get($url);
		if (!@file_put_contents($dst, $content)) {
			throw new Exception("Could not save content of $url to $dst");
		}
		return true;
	}

/**
 * Retrieves the contents of a remote URL
 * 
 * @param string $url The remote file URL
 * @return mixed The content of the file
 * @throws Exception if cannot retrieve
 **/
	static public function get($url) {
		if (!($content = @file_get_contents($url))) {
			throw new Exception("Could not retrieve file: $url");
		}
		return $content;
	}
}