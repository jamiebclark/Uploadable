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

	public static function init($plugin) {
		$pluginUnderscore = Inflector::underscore($plugin);
		$config = array();
		
		// Plugin config
		// Checks in the Plugin Config folder for an underscore-formatted file
		if (file_exists(APP . 'Plugin' . DS . $plugin . DS . 'Config' . DS . $pluginUnderscore . '.php')) {
			Configure::load("$plugin.$pluginUnderscore");
			Set::merge($config, (array)Configure::read($plugin));
		}
			
		// Local app config
		// Checks in the App Config folder for an underscore-formatted file
		if (file_exists(APP . 'Config' . DS . $pluginUnderscore . '.php')) {
			Configure::load($pluginUnderscore);
			Set::merge($config, (array)Configure::read($plugin));
		}
		$config = (array)Configure::read($plugin);
		return $config;
	}
}