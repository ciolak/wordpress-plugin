<?php
/**
 * Adtechmedia_Config
 *
 * @category Adtechmedia_Config
 * @package  Adtechmedia_Plugin
 * @author   yamagleb
 */

/**
 * Class Adtechmedia_Config
 */
class Adtechmedia_Config {

	/**
	 * Plugin config
	 *
	 * @var array
	 */
	private static $conf = [
		'api_end_point' => 'https://api.adtechmedia.io/prod/',
		'plugin_table_name' => 'adtechmedia',
		'plugin_cache_table_name' => 'adtechmedia_cache',
		'maxTries' => 7,
		'minDelay' => 150000,
		'factor' => 1.7,
		'atm_js_cache_time' => 86400,
	];

	/**
	 * Function to get param value
	 *
	 * @param string $name kay name.
	 * @return mixed
	 */
	public static function get( $name ) {
		return self::$conf[ $name ];
	}
}