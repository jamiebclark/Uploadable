<?php
class EasyLog {
	protected static $_msgs = [];
	protected static $_errors = [];

	public static function log($msg) {
		self::$_msgs[] = $msg;
	}

	public static function error($msg) {
		self::log('ERROR: ' . $msg);
		self::$_errors[] = $msg;
	}

	public static function getLog() {
		return self::$_msgs;
	}

	public static function getErrors() {
		return self::$_errors;
	}
}