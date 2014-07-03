<?php
/**
 * Utility to handle Plugin configuration
 *
 * PHP 5
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @package       Cake.Utility
 * @since         CakePHP(tm) v 1.2.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

/**
 * Class to manage Plugin configuration values
 *
 **/
class PluginConfig {

	static protected $_isLoaded = [];

/**
 * Intializes a Plugin's config file
 *
 * @param string $plugin Plugin name
 * @param bool $merge Whether config files should be merged or replaced
 * @return void
 **/
	public static function init($plugin, $merge = true) {
		if ($merge) {
			return self::initMerge($plugin);
		} else {
			return self::initReplace($plugin);
		}
	}

/**
 * Loads local config file if it exists, plugin config file if not
 *
 * @param string $plugin Plugin name
 * @return Array Config settings
 **/
	public static function initReplace($plugin) {
		$config = self::getPluginConfig($plugin, true);
		if (empty($config)) {
			$config = self::getPluginConfig($plugin, false);
		}
		return $config;
	}

/**
 * Loads local and plugin config and merges them together
 * 
 * @param string $plugin Plugin name
 * @return Array Config settings
 **/
	public static function initMerge($plugin) {
		$pluginUnderscore = Inflector::underscore($plugin);
		$config = array();

		// Plugin config
		// Checks in the Plugin Config folder for an underscore-formatted file
		if ($getConfig = self::getPluginConfig($plugin, false)) {
			$config = $getConfig;
		}

		// Local app config
		// Checks in the App Config folder for an underscore-formatted file
		if ($getConfig = self::getPluginConfig($plugin, true)) {
			$config = Hash::merge($config, $getConfig);
		}

		$config = (array)Configure::read($plugin);
		return $config;
	}

/**
 * Finds the config array of a plugin
 *
 * @param string $plugin Plugin name
 * @param bool $isApp Look for the config file in the App/Config folder if true, checks the Plugin folder if not
 * @return Array|Null Returns config array if found, null if not
 **/
	private static function getPluginConfig($plugin, $isApp = false) {
		$pluginUnderscore = Inflector::underscore($plugin);
		if ($isApp) {
			// Checks in the App/Config folder
			$pluginFile = APP . 'Config' . DS . $pluginUnderscore . '.php';
			$pluginVar = $pluginUnderscore;
		} else {
			// Checks in the App/Plugin/{PLUGIN NAME} folder
			$pluginFile = APP . 'Plugin' . DS . $plugin . DS . 'Config' . DS . $pluginUnderscore . '.php';
			$pluginVar = "$plugin.$pluginUnderscore";
		}

		if (file_exists($pluginFile)):
			// Checks if the plugin has already been loaded
			if (empty(self::$_isLoaded[$plugin][$pluginFile])) {
				Configure::load($pluginVar);
				self::$_isLoaded[$plugin][$pluginFile] = true;
			}
			return Configure::read($plugin);
		endif;

		return null;
	}

}