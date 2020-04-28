<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://rnlab.io
 * @since      1.4.3
 *
 * @package    Rnlab_App_Control
 * @subpackage Rnlab_App_Control/cart
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Rnlab_App_Control
 * @subpackage Rnlab_App_Control/api
 * @author     RNLAB <ngocdt@rnlab.io>
 */
class Rnlab_App_Control_Vendor {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string $plugin_name The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string $version The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param string $plugin_name The name of the plugin.
	 * @param string $version The version of this plugin.
	 *
	 * @since      1.0.0
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;

	}

	/**
	 * Registers a REST API route
	 *
	 * @since 1.0.0
	 */
	public function add_api_routes() {
		$namespace = $this->plugin_name . '/v' . intval( $this->version );

		register_rest_route( $namespace, 'vendors', array(
			'methods'  => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'vendors' ),
		) );

	}

	/**
	 * Get vendors
	 * @return array
	 */
	public function vendors( $request ) {
		global $WCFM, $WCFMmp, $wpdb, $wcfmmp_radius_lat, $wcfmmp_radius_lng, $wcfmmp_radius_range;

		$search_term     = $request->get_param( 'search_term' ) ? sanitize_text_field( $request->get_param( 'search_term' ) ) : '';
		$search_category = $request->get_param( 'wcfmmp_store_category' ) ? sanitize_text_field( $request->get_param( 'wcfmmp_store_category' ) ) : '';
		$paged           = $request->get_param( 'paged' ) ? absint( $request->get_param( 'paged' ) ) : 1;
		$per_page        = $request->get_param( 'per_page' ) ? absint( $request->get_param( 'per_page' ) ) : 10;
		$includes        = $request->get_param( 'includes' ) ? sanitize_text_field( $request->get_param( 'includes' ) ) : '';
		$excludes        = $request->get_param( 'excludes' ) ? sanitize_text_field( $request->get_param( 'excludes' ) ) : '';
		$has_product     = $request->get_param( 'has_product' ) ? sanitize_text_field( $request->get_param( 'has_product' ) ) : '';

		$search_data = array();

		if ( $request->get_param( 'search_data' ) ) {
			parse_str( $request->get_param( 'search_data' ), $search_data );
		}

		$length = absint( $per_page );
		$offset = ( $paged - 1 ) * $length;

		$search_data['excludes'] = $excludes;

		if ( $includes ) {
			$includes = explode( ",", $includes );
		} else {
			$includes = array();
		}

		$wcfmmp_radius_lat   = $search_data['wcfmmp_radius_lat'];
		$wcfmmp_radius_lng   = $search_data['wcfmmp_radius_lng'];
		$wcfmmp_radius_range = $search_data['wcfmmp_radius_range'];

		$stores = $WCFMmp->wcfmmp_vendor->wcfmmp_search_vendor_list( true, $offset, $length, $search_term, $search_category, $search_data, $has_product, $includes );

		$vendor_stores = [];

		foreach ( $stores as $key => $value ) {

			$store = get_user_meta( $key, 'wcfmmp_profile_settings', true );

			// Gravatar image
			$gravatar_url = $store['gravatar'] ? wp_get_attachment_url( $store['gravatar'] ) : '';

			// List Banner URL
			$list_banner_url = $store['list_banner'] ? wp_get_attachment_url( $store['list_banner'] ) : '';

			// Banner URL
			$banner_url = $store['banner'] ? wp_get_attachment_url( $store['banner'] ) : '';

			// Mobile Banner URL
			$mobile_banner_url = $store['mobile_banner'] ? wp_get_attachment_url( $store['mobile_banner'] ) : '';

			$store_user = wcfmmp_get_store( $key );

			$vendor_stores[] = array_merge( $store, array(
				'gravatar'          => $gravatar_url,
				'list_banner_url'   => $list_banner_url,
				'banner_url'        => $banner_url,
				'mobile_banner_url' => $mobile_banner_url,
				'avg_review_rating'        => $store_user->get_avg_review_rating(),
				'total_review_rating'      => $store_user->get_total_review_rating(),
				'total_review_count'      => $store_user->get_total_review_count(),
			) );
		}

		return $vendor_stores;
	}
}
