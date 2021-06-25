<?php
/**
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2016-2021 Jean-Sebastien Morisset (https://wpsso.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {

	die( 'These aren\'t the droids you\'re looking for.' );
}

if ( ! class_exists( 'WpssoRestConfig' ) ) {

	class WpssoRestConfig {

		public static $cf = array(
			'plugin' => array(
				'wpssorest' => array(			// Plugin acronym.
					'version'     => '2.8.1',	// Plugin version.
					'opt_version' => '1',		// Increment when changing default option values.
					'short'       => 'WPSSO REST',	// Short plugin name.
					'name'        => 'WPSSO REST API',
					'desc'        => 'Enhances the WordPress REST API post, term, and user query results with an array of meta tags and Schema JSON-LD markup.',
					'slug'        => 'wpsso-rest-api',
					'base'        => 'wpsso-rest-api/wpsso-rest-api.php',
					'update_auth' => '',		// No premium version.
					'text_domain' => 'wpsso-rest-api',
					'domain_path' => '/languages',

					/**
					 * Required plugin and its version.
					 */
					'req' => array(
						'wp' => array(
							'name'           => 'WordPress',
							'home'           => 'https://wordpress.org/',
							'version_global' => 'wp_version',
							'min_version'    => '4.7.0',
						),
						'wpsso' => array(
							'name'          => 'WPSSO Core',
							'home'          => 'https://wordpress.org/plugins/wpsso/',
							'plugin_class'  => 'Wpsso',
							'version_const' => 'WPSSO_VERSION',
							'min_version'   => '8.25.2',
						),
					),

					/**
					 * URLs or relative paths to plugin banners and icons.
					 */
					'assets' => array(

						/**
						 * Icon image array keys are '1x' and '2x'.
						 */
						'icons' => array(
							'1x' => 'images/icon-128x128.png',
							'2x' => 'images/icon-256x256.png',
						),
					),

					/**
					 * Library files loaded and instantiated by WPSSO.
					 */
					'lib' => array(
					),
				),
			),
		);

		public static function get_version( $add_slug = false ) {

			$info =& self::$cf[ 'plugin' ][ 'wpssorest' ];

			return $add_slug ? $info[ 'slug' ] . '-' . $info[ 'version' ] : $info[ 'version' ];
		}

		public static function set_constants( $plugin_file ) {

			if ( defined( 'WPSSOREST_VERSION' ) ) {	// Define constants only once.

				return;
			}

			$info =& self::$cf[ 'plugin' ][ 'wpssorest' ];

			/**
			 * Define fixed constants.
			 */
			define( 'WPSSOREST_FILEPATH', $plugin_file );
			define( 'WPSSOREST_PLUGINBASE', $info[ 'base' ] );	// Example: wpsso-rest-api/wpsso-rest-api.php.
			define( 'WPSSOREST_PLUGINDIR', trailingslashit( realpath( dirname( $plugin_file ) ) ) );
			define( 'WPSSOREST_PLUGINSLUG', $info[ 'slug' ] );	// Example: wpsso-rest-api.
			define( 'WPSSOREST_URLPATH', trailingslashit( plugins_url( '', $plugin_file ) ) );
			define( 'WPSSOREST_VERSION', $info[ 'version' ] );
		}

		public static function require_libs( $plugin_file ) {

			require_once WPSSOREST_PLUGINDIR . 'lib/register.php';
			require_once WPSSOREST_PLUGINDIR . 'lib/filters.php';

			add_filter( 'wpssorest_load_lib', array( 'WpssoRestConfig', 'load_lib' ), 10, 3 );
		}

		public static function load_lib( $success = false, $filespec = '', $classname = '' ) {

			if ( false === $success && ! empty( $filespec ) ) {

				$file_path = WPSSOREST_PLUGINDIR . 'lib/' . $filespec . '.php';

				if ( file_exists( $file_path ) ) {

					require_once $file_path;

					if ( empty( $classname ) ) {

						return SucomUtil::sanitize_classname( 'wpssorest' . $filespec, $allow_underscore = false );
					}

					return $classname;
				}
			}

			return $success;
		}
	}
}
