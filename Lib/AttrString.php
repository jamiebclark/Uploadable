<?php
class AttrString {

/**
 * Parses a string formatted like:
 * 	|key=value1|key2=value2|key3=value3
 **/
	public static function parse($str, $attrs = []) {
		$list = explode('|', $str);
		foreach ($list as $val) {
			if (empty($val)) {
				continue;
			}
			list($key, $val) = explode('=', $val) + [null, null];
			$attrs[$key] = $val;
		}
		return $attrs;
	}

/**
 * Parses a string formatted like:
 * 	key:"value" key2:"value2"
 *
 **/
	public static function parseColonQuote($str, $attrs = []) {
		$buffer = '';
		$readStr = false;
		$assign = false;
		$reset = false;
		$skipBuffer = false;
		
		$stringWrap = '"';

		// Cycles through each character
		for ($i = 0; $i < strlen($str); $i++) {
			$c = $str[$i];	// Current character
			if ($i == strlen($str) - 1) {
				$assign = true;
			}
			if (!$readStr) {
				if ($c == $stringWrap) {
				// Opens quote
					$skipBuffer = true;
					$readStr = true;
				} else if ($c == ':') {
				// Stops reading variable name
					$skipBuffer = true;
					$varName = $buffer;
					$buffer = '';
				} else if ($c == ' ') {
					// Skips spaces
					if (!empty($buffer)) {
						$assign = true;
					}
					$skipBuffer = true;					
				}					
			} else if ($c == $stringWrap) {
				$assign = true;
				$skipBuffer = true;
			}
			
			if (!$skipBuffer) {
				$buffer .= $c;
			}
			
			if ($assign) {
				if (!empty($varName)) {
					$attrs[$varName] = $buffer;
				} else {
					$attrs[] = $buffer;
				}
				$assign = false;
				$reset = true;
			}
			
			if ($reset) {
				$buffer = '';
				$varName = null;
				$varVal = null;
				$readStr = false;
				$reset = false;
			}
			$skipBuffer = false;
		}
		return $attrs;
	}
}