<?php
/**
 * IMPORTANT: READ THE LICENSE AGREEMENT CAREFULLY.
 *
 * BY INSTALLING, COPYING, RUNNING, OR OTHERWISE USING THE WPSSO CORE PRO
 * APPLICATION, YOU AGREE TO BE BOUND BY THE TERMS OF ITS LICENSE AGREEMENT.
 * 
 * License: Nontransferable License for a WordPress Site Address URL
 * License URI: https://wpsso.com/wp-content/plugins/wpsso/license/pro.txt
 *
 * IF YOU DO NOT AGREE TO THE TERMS OF ITS LICENSE AGREEMENT, PLEASE DO NOT
 * INSTALL, RUN, COPY, OR OTHERWISE USE THE WPSSO CORE PRO APPLICATION.
 * 
 * Copyright 2012-2018 Jean-Sebastien Morisset (https://wpsso.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for...' );
}

if ( ! class_exists( 'WpssoRestFilters' ) ) {

	class WpssoRestFilters {

		private $p;
		private $mod_name;
		private $obj_array;

		public function __construct( &$plugin ) {
			$this->p =& $plugin;

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			add_action( 'rest_api_init', array( $this, 'register_callbacks' ) );
		}

		public function register_callbacks() {

			if ( ! function_exists( 'register_rest_field' ) ) {	// Just in case.
				return;
			}

			$field_name = 'head';

			foreach ( $this->p->util->get_post_types( 'names' ) as $ptn ) {
				register_rest_field( $ptn, 'head', array(
					'get_callback' => array( $this, 'get_post' ),
					'update_callback' => null,
					'schema' => null,
				) );
			}

			foreach ( $this->p->util->get_taxonomies( 'names' ) as $ttn ) {
				register_rest_field( $ttn, 'head', array(
					'get_callback' => array( $this, 'get_term' ),
					'update_callback' => null,
					'schema' => null,
				) );
			}

			register_rest_field( 'user', 'head', array(
				'get_callback' => array( $this, 'get_user' ),
				'update_callback' => null,
				'schema' => null,
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

			if ( ! defined( 'SUCOM_DOING_API' ) ) {
				define( 'SUCOM_DOING_API', true );
			}

			/**
			 * Reference variables for filter_get_term_object() and filter_get_user_object().
			 */
			$this->mod_name  = $mod_name;
			$this->obj_array = $obj_array;

			/**
			 * Pre-define an array element order, and create a default array to return in case $mod_name is unknown.
			 */
			$api_ret  = array( 'html' => array(), 'json' => array(), 'parts' => array() );

			$head_array = array();	// Pre-define - just in case.

			switch ( $this->mod_name ) {
			
				case 'post':

					$mod = $this->p->m['util'][$this->mod_name]->get_mod( $this->obj_array['id'] );

					$head_array = $this->p->head->get_head_array( $this->obj_array['id'], $mod );

					break;

				case 'term':

					add_filter( 'sucom_is_term_page', '__return_true', 10 );
					add_filter( 'sucom_get_term_object', array( $this, 'filter_get_term_object' ), 10 );

					$mod = $this->p->m['util'][$this->mod_name]->get_mod( $this->obj_array['id'], $this->obj_array['taxonomy'] );

					$head_array = $this->p->head->get_head_array( $this->obj_array['id'], $mod );

					remove_filter( 'sucom_is_term_page', '__return_true', 10 );
					remove_filter( 'sucom_get_term_object', array( $this, 'filter_get_term_object' ), 10 );

					break;

				case 'user':

					add_filter( 'sucom_is_user_page', '__return_true', 10 );
					add_filter( 'sucom_get_user_object', array( $this, 'filter_get_user_object' ), 10 );

					$mod = $this->p->m['util'][$this->mod_name]->get_mod( $this->obj_array['id'] );

					$head_array = $this->p->head->get_head_array( $this->obj_array['id'], $mod );

					remove_filter( 'sucom_is_user_page', '__return_true', 10 );
					remove_filter( 'sucom_get_user_object', array( $this, 'filter_get_user_object' ), 10 );

					break;

				default:

					return $api_ret;	// Object type is unknown - stop here.
			}

			/**
			 * Save any pre-existing array values.
			 */
			foreach ( $api_ret as $idx => $arr ) {
				if ( isset( $this->obj_array['head'][$idx] ) && is_array( $this->obj_array['head'][$idx] ) ) {
					$api_ret[$idx] = $this->obj_array['head'][$idx];
				}
			}

			/**
			 * Add meta tags to the API array.
			 */
			foreach ( $head_array as $meta ) {

				/**
				 * Save the first array element, which is the html formatted meta tag or script.
				 */
				if ( ! empty( $meta[0] ) ) {		// Just in case we don't have an html value.

					$api_ret['html'][] = $meta[0];	// Save the html, including any json script blocks.

					/**
					 * If the html contains a script, decode and save the ld+json as an array.
					 */
					if ( strpos( $meta[0], '<script ' ) === 0 ) {

						/**
						 * Extract the script type and its value.
						 */
						if ( preg_match( '/^<script type="([^\'"]+)">(.*)<\/script>$/s', $meta[0], $matches ) ) {

							switch ( $matches[1] ) {

								case 'application/ld+json':

									$api_ret['json'][] = json_decode( $matches[2] );

									break;
							}
						}
					}
				}

				array_shift( $meta );			// Remove the html element (first element in array).

				if ( ! empty( $meta ) ) {		// Just in case we only had an html value.
					$api_ret['parts'][] = $meta;	// Save the meta tag array, without the html element.
				}
			}

			return $api_ret;
		}

		public function filter_get_term_object( $term_obj ) {
			return get_term_by( 'term_taxonomy_id', $this->obj_array['id'], $this->obj_array['taxonomy'], OBJECT, 'raw' );
		}

		public function filter_get_user_object( $user_obj ) {
			return get_userdata( $this->obj_array['id'] );
		}
	}
}