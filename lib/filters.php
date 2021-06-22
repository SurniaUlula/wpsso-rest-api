<?php
/**
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Copyright 2016-2021 Jean-Sebastien Morisset (https://wpsso.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {

	die( 'These aren\'t the droids you\'re looking for.' );
}

if ( ! class_exists( 'WpssoRestFilters' ) ) {

	class WpssoRestFilters {

		private $p;	// Wpsso class object.
		private $a;	// WpssoRest class object.

		private $mod_name;
		private $obj_array;

		/**
		 * Instantiated by WpssoRest->init_objects().
		 */
		public function __construct( &$plugin, &$addon ) {

			static $do_once = null;

			if ( true === $do_once ) {

				return;	// Stop here.
			}

			$do_once = true;

			$this->p =& $plugin;
			$this->a =& $addon;

			add_action( 'rest_api_init', array( $this, 'register_callbacks' ) );
		}

		public function register_callbacks() {

			if ( ! function_exists( 'register_rest_field' ) ) {	// Just in case.

				return;
			}

			$field_name = 'head';

			foreach ( SucomUtilWP::get_post_types( $output = 'names' ) as $name ) {

				register_rest_field( $name, 'head', array(
					'get_callback'    => array( $this, 'get_post' ),
					'update_callback' => null,
					'schema'          => null,
				) );
			}

			foreach ( SucomUtilWP::get_taxonomies( $output = 'names' ) as $name ) {

				register_rest_field( $name, 'head', array(
					'get_callback'    => array( $this, 'get_term' ),
					'update_callback' => null,
					'schema'          => null,
				) );
			}

			register_rest_field( 'user', 'head', array(
				'get_callback'    => array( $this, 'get_user' ),
				'update_callback' => null,
				'schema'          => null,
			) );
		}

		public function get_post( $obj_array, $field_name = 'head', WP_REST_Request $request ) {

			return $this->get_head( 'post', $obj_array, $field_name, $request );
		}

		public function get_term( $obj_array, $field_name = 'head', WP_REST_Request $request ) {

			return $this->get_head( 'term', $obj_array, $field_name, $request );
		}

		public function get_user( $obj_array, $field_name = 'head', WP_REST_Request $request ) {

			return $this->get_head( 'user', $obj_array, $field_name, $request );
		}

		private function get_head( $mod_name, $obj_array, $field_name, $request ) {

			/**
			 * Reference variables for filter_get_term_object() and filter_get_user_object().
			 */
			$this->mod_name  = $mod_name;

			$this->obj_array = $obj_array;

			/**
			 * Pre-define an array element order, and create a default array to return in case $mod_name is unknown.
			 */
			$result = array(
				'html'  => array(),
				'json'  => array(),
				'parts' => array(),
			);

			$head_array = array();	// Pre-define - just in case.

			$debug_prev_state = $this->p->debug->disable( 'html' );	// Optimize and maybe disable debug comments.

			$notice_prev_state = $this->p->notice->disable();	// Optimize and maybe disable notice messages.

			switch ( $this->mod_name ) {

				case 'post':

					$mod        = $this->p->post->get_mod( $this->obj_array[ 'id' ] );
					$head_array = $this->p->head->get_head_array( $use_post = false, $mod );

					break;

				case 'term':

					add_filter( 'sucom_is_term_page', '__return_true', 10 );
					add_filter( 'sucom_get_term_object', array( $this, 'filter_get_term_object' ), 10 );

					$mod        = $this->p->term->get_mod( $this->obj_array[ 'id' ], $this->obj_array[ 'taxonomy' ] );
					$head_array = $this->p->head->get_head_array( $use_post = false, $mod );

					remove_filter( 'sucom_is_term_page', '__return_true', 10 );
					remove_filter( 'sucom_get_term_object', array( $this, 'filter_get_term_object' ), 10 );

					break;

				case 'user':

					add_filter( 'sucom_is_user_page', '__return_true', 10 );
					add_filter( 'sucom_get_user_object', array( $this, 'filter_get_user_object' ), 10 );

					$mod        = $this->p->user->get_mod( $this->obj_array[ 'id' ] );
					$head_array = $this->p->head->get_head_array( $use_post = false, $mod );

					remove_filter( 'sucom_is_user_page', '__return_true', 10 );
					remove_filter( 'sucom_get_user_object', array( $this, 'filter_get_user_object' ), 10 );

					break;

				default:

					return $result;	// Object type is unknown - stop here.
			}

			$this->p->notice->enable( $notice_prev_state );		// Maybe reenable debug comments.

			$this->p->debug->enable( 'html', $debug_prev_state );	// Maybe reenable notice messages.

			/**
			 * Save any pre-existing array values.
			 */
			foreach ( $result as $type => $values ) {

				if ( isset( $this->obj_array[ 'head' ][ $type ] ) && is_array( $this->obj_array[ 'head' ][ $type ] ) ) {

					$result[ $type ] = $this->obj_array[ 'head' ][ $type ];
				}
			}

			/**
			 * Add meta tags to the API array.
			 */
			foreach ( $head_array as $meta ) {

				/**
				 * Save the first array element, which is the html formatted meta tag or script.
				 */
				if ( ! empty( $meta[ 0 ] ) ) {		// Just in case we don't have an html value.

					$result[ 'html' ][] = $meta[ 0 ];	// Save the html, including any json script blocks.

					/**
					 * If the html contains a script, decode and save the ld+json as an array.
					 */
					if ( strpos( $meta[ 0 ], '<script ' ) === 0 ) {

						/**
						 * Extract the script type and its value.
						 */
						if ( preg_match( '/^<script type="([^\'"]+)">(.*)<\/script>$/s', $meta[ 0 ], $matches ) ) {

							switch ( $matches[ 1 ] ) {

								case 'application/ld+json':

									$result[ 'json' ][] = json_decode( $matches[ 2 ], $assoc = true );

									break;
							}
						}
					}
				}

				array_shift( $meta );			// Remove the html element (first element in array).

				if ( ! empty( $meta ) ) {		// Just in case we only had an html value.

					$result[ 'parts' ][] = $meta;	// Save the meta tag array, without the html element.
				}
			}

			return $result;
		}

		public function filter_get_term_object( $term_obj ) {

			return get_term_by( 'term_taxonomy_id', $this->obj_array[ 'id' ], $this->obj_array[ 'taxonomy' ], OBJECT, 'raw' );
		}

		public function filter_get_user_object( $user_obj ) {

			return get_userdata( $this->obj_array[ 'id' ] );
		}
	}
}
