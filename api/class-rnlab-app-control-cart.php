<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://rnlab.io
 * @since      1.0.0
 *
 * @package    Rnlab_App_Control
 * @subpackage Rnlab_App_Control/cart
 */

/**
 * The public-facing functionality of the plugin.
 * https://docs.woocommerce.com/wc-apidocs/class-WC_Cart.html
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Rnlab_App_Control
 * @subpackage Rnlab_App_Control/api
 * @author     RNLAB <ngocdt@rnlab.io>
 */

use Exception as E;

class Rnlab_App_Control_Cart {

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

	public function rnlab_pre_car_rest_api() {
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '3.6.0', '>=' ) && WC()->is_rest_api_request() ) {
			require_once( WC_ABSPATH . 'includes/wc-cart-functions.php' );
			require_once( WC_ABSPATH . 'includes/wc-notice-functions.php' );

			// Disable cookie authentication REST check and only if site is secure.
			if ( is_ssl() ) {
				remove_filter( 'rest_authentication_errors', 'rest_cookie_check_errors', 100 );
			}

			if ( null === WC()->session ) {
				$session_class = 'WC_Session_Handler';

				// Prefix session class with global namespace if not already namespaced
				if ( false === strpos( $session_class, '\\' ) ) {
					$session_class = '\\' . $session_class;
				}

				WC()->session = new $session_class();
				WC()->session->init();
			}

			/**
			 * For logged in customers, pull data from their account rather than the
			 * session which may contain incomplete data.
			 */
			if ( is_null( WC()->customer ) ) {
				$customer_id = strval( get_current_user_id() );

				// If the ID is not ZERO, then the user is logged in.
				if ( $customer_id > 0 ) {
					WC()->customer = new WC_Customer( $customer_id ); // Loads from database.
				} else {
					WC()->customer = new WC_Customer( $customer_id, true ); // Loads from session.
				}

				// Customer should be saved during shutdown.
				add_action( 'shutdown', array( WC()->customer, 'save' ), 10 );
			}

			// Load Cart.
			if ( null === WC()->cart ) {
				WC()->cart = new WC_Cart();
			}
		}
	}

	/**
	 * Registers a REST API route
	 *
	 * @since 1.0.0
	 */
	public function add_api_routes() {
		$namespace = $this->plugin_name . '/v' . intval( $this->version );

		register_rest_route( $namespace, 'cart', array(
			array(
				'methods'  => WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_cart' ),
			),
			array(
				'methods'  => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'add_to_cart' ),
			)
		) );

		register_rest_route( $namespace, 'cart-total', array(
			'methods'  => WP_REST_Server::READABLE,
			'callback' => array( $this, 'get_total' ),
		) );

	}

	/**
	 * Get list cart
	 * @return array
	 */
	public function get_cart() {
		return WC()->cart->get_cart();
	}

	/**
	 * Add to cart
	 *
	 * @param $request
	 *
	 * @return array|Exception
	 */
	public function add_to_cart( $request ) {
		try {
			$product_id     = $request->get_param( 'product_id' );
			$quantity       = $request->get_param( 'quantity' );
			$variation_id   = $request->get_param( 'variation_id' );
			$variation      = $request->get_param( 'variation' );
			$cart_item_data = $request->get_param( 'cart_item_data' );

			$cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity );


			if ( ! $cart_item_key ) {
				return new WP_Error( 'add_to_cart', "Can't add product item to cart.", array(
					'status' => 403,
				) );
			}

			return WC()->cart->get_cart_item( $cart_item_key );
		} catch ( \Exception $e ) {
			//do something when exception is thrown
			return new WP_Error( 'add_to_cart', $e->getMessage(), array(
				'status' => 403,
			) );
		} catch ( \Throwable $e ) {
			//do something when Throwable is thrown
			return new WP_Error( 'add_to_cart', $e->getMessage(), array(
				'status' => 403,
			) );
		}
	}

	/**
	 * Get total cart
	 * @return array
	 */
	public function get_total() {
		return WC()->cart->get_totals();
	}
}
