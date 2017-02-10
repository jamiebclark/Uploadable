<?php
/**
 * Used to maintain consistency when manipulating a field to include a remote URL
 *
 **/
class RemoteUrlField {
	static public function field($field) {
		return $field . '--url';
	}
}