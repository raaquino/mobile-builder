<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://rnlab.io
 * @since      1.0.0
 *
 * @package    Mobile_Builder
 * @subpackage Mobile_Builder/cart
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Mobile_Builder
 * @subpackage Mobile_Builder/api
 * @author     RNLAB <ngocdt@rnlab.io>
 */
class Mobile_Builder_Vendor {

	public $google_map_api = 'https://maps.googleapis.com/maps/api';

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
			array(
				'methods'  => WP_REST_Server::READABLE,
				'callback' => array( $this, 'vendors' ),
			),
			array(
				'methods'  => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'vendors' ),
			)
		) );

		register_rest_route( $namespace, 'directions', array(
			'methods'  => WP_REST_Server::READABLE,
			'callback' => array( $this, 'directions' ),
		) );

		register_rest_route( $namespace, 'vendor' . '/(?P<id>[\d]+)', array(
			'methods'  => WP_REST_Server::READABLE,
			'callback' => array( $this, 'vendor' ),
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
		$units           = $request->get_param( 'units' ) ? sanitize_text_field( $request->get_param( 'units' ) ) : 'metric';

		$search_data = array();

		$length = absint( $per_page );
		$offset = ( $paged - 1 ) * $length;

		$search_data['excludes'] = $excludes;

		if ( $includes ) {
			$includes = explode( ",", $includes );
		} else {
			$includes = array();
		}

		$wcfmmp_radius_lat   = $request->get_param( 'wcfmmp_radius_lat' );
		$wcfmmp_radius_lng   = $request->get_param( 'wcfmmp_radius_lng' );
		$wcfmmp_radius_range = $request->get_param( 'wcfmmp_radius_range' );

		if ( $wcfmmp_radius_lat && $wcfmmp_radius_lng && $wcfmmp_radius_range ) {
			$search_data['wcfmmp_radius_lat']   = $wcfmmp_radius_lat;
			$search_data['wcfmmp_radius_lng']   = $wcfmmp_radius_lng;
			$search_data['wcfmmp_radius_range'] = $wcfmmp_radius_range;
		}

		$stores = $WCFMmp->wcfmmp_vendor->wcfmmp_search_vendor_list( true, $offset, $length, $search_term, $search_category, $search_data, $has_product, $includes );

		$vendor_stores = [];
		$origins       = [];

		foreach ( $stores as $key => $value ) {

			$store     = get_user_meta( $key, 'wcfmmp_profile_settings', true );
			$origins[] = $store['store_lat'] . ',' . $store['store_lng'];

			// Gravatar image
			$gravatar_url = $store['gravatar'] ? wp_get_attachment_url( $store['gravatar'] ) : '';

			// List Banner URL
			$list_banner_url = $store['list_banner'] ? wp_get_attachment_url( $store['list_banner'] ) : '';

			// Banner URL
			$banner_url = $store['banner'] ? wp_get_attachment_url( $store['banner'] ) : '';

			// Mobile Banner URL
			$mobile_banner_url = $store['mobile_banner'] ? wp_get_attachment_url( $store['mobile_banner'] ) : '';

			$shipping_methods = WCFMmp_Shipping_Zone::get_shipping_methods( 0, $key );

			$store_user = wcfmmp_get_store( $key );

			$vendor_stores[] = array_merge( $store, array(
				'id'                  => $key,
				'gravatar'            => $gravatar_url,
				'list_banner_url'     => $list_banner_url,
				'banner_url'          => $banner_url,
				'mobile_banner_url'   => $mobile_banner_url,
				'avg_review_rating'   => $store_user->get_avg_review_rating(),
				'total_review_rating' => $store_user->get_total_review_rating(),
				'total_review_count'  => $store_user->get_total_review_count(),
				'rating'              => array(
					'rating' => $store_user->get_total_review_rating(),
					'count'  => $store_user->get_total_review_count(),
					'avg'    => $store_user->get_avg_review_rating(),
				),
				'shipping_methods'    => array_column( $shipping_methods, 'id' ),
			) );
		}

		$origin_string       = implode( '|', $origins );
		$destinations_string = "$wcfmmp_radius_lat,$wcfmmp_radius_lng";
		$key                 = MBD_GOOGLE_API_KEY;

		$url = "$this->google_map_api/distancematrix/json?units=$units&origins=$origin_string&destinations=$destinations_string&key=$key";

		$distance_matrix = json_decode( mobile_builder_request( 'GET', $url ) )->rows;

		foreach ( $vendor_stores as $key => $value ) {
			$vendor_stores[ $key ] = array_merge( $value, array(
				'matrix' => $distance_matrix[ $key ]->elements,
			) );
		}

		return $vendor_stores;
	}

	/**
	 * @param $request
	 *
	 * @return array
	 * @since    1.0.0
	 */
	public function vendor( $request ) {

		$params = $request->get_params();

		$id                = $params['id'];
		$wcfmmp_radius_lat = $params['wcfmmp_radius_lat'];
		$wcfmmp_radius_lng = $params['wcfmmp_radius_lng'];

		$store      = get_user_meta( $id, 'wcfmmp_profile_settings', true );
		$store_user = wcfmmp_get_store( $id );

		// Gravatar image
		$gravatar_url = $store['gravatar'] ? wp_get_attachment_url( $store['gravatar'] ) : '';

		// List Banner URL
		$list_banner_url = $store['list_banner'] ? wp_get_attachment_url( $store['list_banner'] ) : '';

		// Banner URL
		$banner_url = $store['banner'] ? wp_get_attachment_url( $store['banner'] ) : '';

		// Mobile Banner URL
		$mobile_banner_url = $store['mobile_banner'] ? wp_get_attachment_url( $store['mobile_banner'] ) : '';

		$shipping_methods = WCFMmp_Shipping_Zone::get_shipping_methods( 0, $id );

		$distance_matrix = array();

		if ( $wcfmmp_radius_lat && $wcfmmp_radius_lng && $store['store_lat'] && $store['store_lng'] ) {
			$origin_string       = $store['store_lat'] . ',' . $store['store_lng'];
			$destinations_string = "$wcfmmp_radius_lat,$wcfmmp_radius_lng";
			$key                 = MBD_GOOGLE_API_KEY;
			$distance_matrix     = mobile_builder_distance_matrix( $origin_string, $destinations_string, $key );
		}

		return array_merge( $store, array(
			'id'                  => $id,
			'gravatar'            => $gravatar_url,
			'list_banner_url'     => $list_banner_url,
			'banner_url'          => $banner_url,
			'mobile_banner_url'   => $mobile_banner_url,
			'avg_review_rating'   => $store_user->get_avg_review_rating(),
			'total_review_rating' => $store_user->get_total_review_rating(),
			'total_review_count'  => $store_user->get_total_review_count(),
			'shipping_methods'    => array_column( $shipping_methods, 'id' ),
			'matrix'              => $distance_matrix[0]->elements,
		) );

	}

	/**
	 *
	 * Filter products by vendors
	 *
	 * @param $args
	 * @param $wp_query
	 *
	 * @return mixed
	 */
	public function mbd_product_list_by_vendor( $args, $wp_query ) {

		global $wpdb;

		$vendor_id = $_GET['vendor_id'];

		if ( $vendor_id ) {
			$args['where'] .= " AND $wpdb->posts.post_author = $vendor_id";
		}

		return $args;
	}

	/**
	 *
	 * Product distance
	 *
	 * @param $args
	 * @param $wp_query
	 *
	 * @return mixed
	 */
	public function mbd_product_distance( $args, $wp_query ) {

		global $wpdb;

		$lat      = $_GET['lat'];
		$lng      = $_GET['lng'];
		$distance = ! empty( $_GET['radius'] ) ? esc_sql( $_GET['radius'] ) : 50;

		if ( $lat && $lng ) {

			$earth_radius = 6371;
			$units        = 'km';
			$degree       = 111.045;

			// add units to locations data.
			$args['fields'] .= ", '{$units}' AS units";

			$args['fields'] .= ", ROUND( {$earth_radius} * acos( cos( radians( {$lat} ) ) * cos( radians( gmw_locations.latitude ) ) * cos( radians( gmw_locations.longitude ) - radians( {$lng} ) ) + sin( radians( {$lat} ) ) * sin( radians( gmw_locations.latitude ) ) ),1 ) AS distance";
			$args['join']   .= " INNER JOIN {$wpdb->base_prefix}gmw_locations gmw_locations ON $wpdb->posts.ID = gmw_locations.object_id ";

			// calculate the between point.
			$bet_lat1 = $lat - ( $distance / $degree );
			$bet_lat2 = $lat + ( $distance / $degree );
			$bet_lng1 = $lng - ( $distance / ( $degree * cos( deg2rad( $lat ) ) ) );
			$bet_lng2 = $lng + ( $distance / ( $degree * cos( deg2rad( $lat ) ) ) );

			$args['where'] .= " AND gmw_locations.object_type = 'post'";
			$args['where'] .= " AND gmw_locations.latitude BETWEEN {$bet_lat1} AND {$bet_lat2}";
			//$args['where'] .= " AND gmw_locations.longitude BETWEEN {$bet_lng1} AND {$bet_lng2} ";

			// filter locations based on the distance.
			$args['having'] = "HAVING distance <= {$distance} OR distance IS NULL";

			$args['orderby'] .= ', distance ASC';

		}

		return $args;
	}

	/**
	 * @param $args
	 * @param $wp_query
	 *
	 * @return mixed
	 * @since    1.0.0
	 */
	public function mbd_product_list_geo_location_filter_post_clauses( $args, $wp_query ) {
		global $WCFM, $WCFMmp, $wpdb, $wcfmmp_radius_lat, $wcfmmp_radius_lng, $wcfmmp_radius_range;

		$wcfm_google_map_api = isset( $WCFMmp->wcfmmp_marketplace_options['wcfm_google_map_api'] ) ? $WCFMmp->wcfmmp_marketplace_options['wcfm_google_map_api'] : '';
		$wcfm_map_lib        = isset( $WCFMmp->wcfmmp_marketplace_options['wcfm_map_lib'] ) ? $WCFMmp->wcfmmp_marketplace_options['wcfm_map_lib'] : '';
		if ( ! $wcfm_map_lib && $wcfm_google_map_api ) {
			$wcfm_map_lib = 'google';
		} elseif ( ! $wcfm_map_lib && ! $wcfm_google_map_api ) {
			$wcfm_map_lib = 'leaftlet';
		}
		if ( ( $wcfm_map_lib == 'google' ) && empty( $wcfm_google_map_api ) ) {
			return $args;
		}

		$enable_wcfm_product_radius = isset( $WCFMmp->wcfmmp_marketplace_options['enable_wcfm_product_radius'] ) ? $WCFMmp->wcfmmp_marketplace_options['enable_wcfm_product_radius'] : 'no';
		if ( $enable_wcfm_product_radius !== 'yes' ) {
			return $args;
		}

		if ( ( ! isset( $_GET['radius_range'] ) && ! isset( $_GET['radius_lat'] ) && ! isset( $_GET['radius_lng'] ) ) ) {
			return $args;
		}

		$max_radius_to_search = isset( $WCFMmp->wcfmmp_marketplace_options['max_radius_to_search'] ) ? $WCFMmp->wcfmmp_marketplace_options['max_radius_to_search'] : '100';

		$radius_addr  = isset( $_GET['radius_addr'] ) ? wc_clean( $_GET['radius_addr'] ) : '';
		$radius_range = isset( $_GET['radius_range'] ) ? wc_clean( $_GET['radius_range'] ) : ( absint( apply_filters( 'wcfmmp_radius_filter_max_distance', $max_radius_to_search ) ) / 10 );
		$radius_lat   = isset( $_GET['radius_lat'] ) ? wc_clean( $_GET['radius_lat'] ) : '';
		$radius_lng   = isset( $_GET['radius_lng'] ) ? wc_clean( $_GET['radius_lng'] ) : '';

		if ( ! empty( $radius_lat ) && ! empty( $radius_lng ) && ! empty( $radius_range ) ) {
			$wcfmmp_radius_lat   = $radius_lat;
			$wcfmmp_radius_lng   = $radius_lng;
			$wcfmmp_radius_range = $radius_range;

			$user_args = array(
				'role__in'    => apply_filters( 'wcfmmp_allwoed_vendor_user_roles', array( 'wcfm_vendor' ) ),
				'count_total' => false,
				'fields'      => array( 'ID', 'display_name' ),
			);
			$all_users = get_users( $user_args );

			$available_vendors = array();
			if ( ! empty( $all_users ) ) {
				foreach ( $all_users as $all_user ) {
					$available_vendors[ $all_user->ID ] = $all_user->ID;
				}
			} else {
				$available_vendors = array( 0 );
			}

			$args['where'] .= " AND $wpdb->posts.post_author in (" . implode( ',', $available_vendors ) . ")";
		}

		return $args;
	}

	/**
	 *
	 * Get directions for vendors
	 *
	 * @param $request
	 *
	 * @return bool|string
	 * @since    1.0.0
	 */
	public function directions( $request ) {
		$origin      = $request->get_param( 'origin' );
		$destination = $request->get_param( 'destination' );
		$key         = MBD_GOOGLE_API_KEY;

		$url = "$this->google_map_api/directions/json?origin=$origin&destination=$destination&key=$key";

		return json_decode( mobile_builder_request( 'GET', $url ) );
	}
}
