<?php

/**
 * The public-facing functionality of the plugin.
 * https://docs.woocommerce.com/wc-apidocs/class-WC_Cart.html
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Mobile_Builder
 * @subpackage Mobile_Builder/api
 * @author     RNLAB <ngocdt@rnlab.io>
 */

class Mobile_Builder_Cart {

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

	private $namespace;

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
		$this->namespace   = $plugin_name . '/v' . intval( $version );

	}

	/**
	 * Registers a REST API route
	 *
	 * @since 1.0.0
	 */
	public function add_api_routes() {

		register_rest_route( $this->namespace, 'cart', array(
			array(
				'methods'  => WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_cart' ),
			),
			array(
				'methods'  => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'mobile_builder_add_to_cart' ),
			)
		) );

		register_rest_route( $this->namespace, 'update-shipping', array(
			'methods'  => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'update_shipping' ),
		) );

		register_rest_route( $this->namespace, 'update-order-review', array(
			'methods'  => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'mobile_builder_update_order_review' ),
		) );

		register_rest_route( $this->namespace, 'checkout', array(
			'methods'  => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'mobile_builder_checkout' ),
		) );

		register_rest_route( $this->namespace, 'cart-total', array(
			'methods'  => WP_REST_Server::READABLE,
			'callback' => array( $this, 'get_total' ),
		) );

		register_rest_route( $this->namespace, 'shipping-methods', array(
			'methods'  => WP_REST_Server::READABLE,
			'callback' => array( $this, 'shipping_methods' ),
		) );

		register_rest_route( $this->namespace, 'set-quantity', array(
			'methods'  => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'set_quantity' ),
		) );

		register_rest_route( $this->namespace, 'remove-cart-item', array(
			'methods'  => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'remove_cart_item' ),
		) );

		register_rest_route( $this->namespace, 'add-discount', array(
			'methods'  => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'add_discount' ),
		) );

		register_rest_route( $this->namespace, 'remove-coupon', array(
			'methods'  => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'remove_coupon' ),
		) );

	}

	public function simulate_as_not_rest( $is_rest_api_request ) {

		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return $is_rest_api_request;
		}

		if ( false === strpos( $_SERVER['REQUEST_URI'], $this->namespace ) ) {
			return $is_rest_api_request;
		}

		return false;
	}

	public function mobile_builder_woocommerce_persistent_cart_enabled() {
		return false;
	}

	/**
	 * @throws Exception
	 * @since    1.0.0
	 */
	public function mobile_builder_pre_car_rest_api() {

		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '3.6.0', '>=' ) && WC()->is_rest_api_request() ) {
			require_once( WC_ABSPATH . 'includes/wc-cart-functions.php' );
			require_once( WC_ABSPATH . 'includes/wc-notice-functions.php' );

			// Disable cookie authentication REST check and only if site is secure.
			if ( is_ssl() ) {
				remove_filter( 'rest_authentication_errors', 'rest_cookie_check_errors', 100 );
			}

			if ( is_null( WC()->session ) ) {
				$session_class = 'WC_Session_Handler';

				if ( false === strpos( $session_class, '\\' ) ) {
					$session_class = '\\' . $session_class;
				}

				WC()->session = new $session_class();
				WC()->session->init();
			}

			/**
			 * Choose the location save data user
			 */
			if ( is_null( WC()->customer ) ) {

				$customer_id = strval( get_current_user_id() );

				// If the ID is not ZERO, then the user is logged in.
				if ( $customer_id > 0 ) {
					WC()->customer = new WC_Customer( $customer_id ); // Loads from database.
				} else {
					WC()->customer = new WC_Customer( $customer_id, true ); // Loads from session.
				}

				add_action( 'shutdown', array( WC()->customer, 'save' ), 10 );
			}

			// Init cart if null
			if ( is_null( WC()->cart ) ) {
				WC()->cart = new WC_Cart();
			}
		}
	}

	/**
	 * Get list cart
	 * @return array
	 */
	public function get_cart() {
//		WC()->cart->calculate_totals();
//		WC()->cart->calculate_shipping();

		$items = WC()->cart->get_cart();

		foreach ( $items as $cart_item_key => $cart_item ) {
			$_product  = $cart_item['data'];
			$vendor_id = wcfm_get_vendor_id_by_post( $_product->get_id() );

			$image = wp_get_attachment_image_src( get_post_thumbnail_id( $_product->get_id() ), 'single-post-thumbnail' );
			if ( $_product && $_product->exists() && $cart_item['quantity'] > 0 ) {
				$items[ $cart_item_key ]['thumbnail']            = $_product->get_image();
				$items[ $cart_item_key ]['thumb']                = $image[0];
				$items[ $cart_item_key ]['is_sold_individually'] = $_product->is_sold_individually();
				$items[ $cart_item_key ]['name']                 = $_product->get_name();
				$items[ $cart_item_key ]['price']                = WC()->cart->get_product_price( $_product );
				$items[ $cart_item_key ]['vendor_id']            = $vendor_id;
				$items[ $cart_item_key ]['store']                = $vendor_id ? $store_user = get_user_meta( $vendor_id, 'wcfmmp_profile_settings', true ) : null;
			}

		}

		return array(
			'items'  => $items,
			'totals' => WC()->cart->get_totals(),
		);
	}

	/**
	 *
	 * Method Add to cart
	 *
	 * @param $request
	 *
	 * @return array|WP_Error
	 * @since    1.0.0
	 */
	public function mobile_builder_add_to_cart( $request ) {

		// Login before add to cart
		if ( get_current_user_id() == 0 ) {
			return new WP_Error(
				'mobile_builder_add_to_cart',
				'Login to add to cart!',
				array(
					'status' => 403,
				)
			);
		}

		try {
			$product_id     = $request->get_param( 'product_id' );
			$quantity       = $request->get_param( 'quantity' );
			$variation_id   = $request->get_param( 'variation_id' );
			$variation      = $request->get_param( 'variation' );
			$cart_item_data = $request->get_param( 'cart_item_data' );

			$cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation, $cart_item_data );

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
		}
	}

	/**
	 *
	 * Update shipping method
	 *
	 * @param $request
	 *
	 * @return array
	 * @since    1.0.0
	 */
	public function update_shipping( $request ) {

		$posted_shipping_methods = $request->get_param( 'shipping_method' ) ? wc_clean( wp_unslash( $request->get_param( 'shipping_method' ) ) ) : array();
		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );

		if ( is_array( $posted_shipping_methods ) ) {
			foreach ( $posted_shipping_methods as $i => $value ) {
				$chosen_shipping_methods[ $i ] = $value;
			}
		}

		WC()->session->set( 'chosen_shipping_methods', $chosen_shipping_methods );

		WC()->customer->save();

		// Calculate shipping before totals. This will ensure any shipping methods that affect things like taxes are chosen prior to final totals being calculated. Ref: #22708.
		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();

		// Get messages if reload checkout is not true.
		$reload_checkout = isset( WC()->session->reload_checkout ) ? true : false;

		unset( WC()->session->refresh_totals, WC()->session->reload_checkout );

		return array(
			'messages' => $reload_checkout,
		);

	}

	public function mobile_builder_update_order_review( $request ) {
//		check_ajax_referer( 'update-order-review', 'security' );

		wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );

		if ( WC()->cart->is_empty() && ! is_customize_preview() && apply_filters( 'woocommerce_checkout_update_order_review_expired', true ) ) {
			return new WP_Error( 404, 'Sorry, your session has expired.' );
		}

//		do_action( 'woocommerce_checkout_update_order_review', $request->get_param( 'post_data') ) ? wp_unslash( $request->get_param( 'post_data') ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
		$posted_shipping_methods = $request->get_param( 'shipping_method' ) ? wc_clean( wp_unslash( $request->get_param( 'shipping_method' ) ) ) : array();

		if ( is_array( $posted_shipping_methods ) ) {
			foreach ( $posted_shipping_methods as $i => $value ) {
				$chosen_shipping_methods[ $i ] = $value;
			}
		}

		WC()->session->set( 'chosen_shipping_methods', $chosen_shipping_methods );
		WC()->session->set( 'chosen_payment_method', empty( $request->get_param( 'shipping_method' ) ) ? '' : wc_clean( wp_unslash( $request->get_param( 'shipping_method' ) ) ) );
		WC()->customer->set_props(
			array(
				'billing_country'   => $request->get_param( 'country' ) ? wc_clean( wp_unslash( $request->get_param( 'country' ) ) ) : null,
				'billing_state'     => $request->get_param( 'state' ) ? wc_clean( wp_unslash( $request->get_param( 'state' ) ) ) : null,
				'billing_postcode'  => $request->get_param( 'postcode' ) ? wc_clean( wp_unslash( $request->get_param( 'postcode' ) ) ) : null,
				'billing_city'      => $request->get_param( 'city' ) ? wc_clean( wp_unslash( $request->get_param( 'city' ) ) ) : null,
				'billing_address_1' => $request->get_param( 'address' ) ? wc_clean( wp_unslash( $request->get_param( 'address' ) ) ) : null,
				'billing_address_2' => $request->get_param( 'address_2' ) ? wc_clean( wp_unslash( $request->get_param( 'address_2' ) ) ) : null,
				'billing_company'   => $request->get_param( 'company' ) ? wc_clean( wp_unslash( $request->get_param( 'company' ) ) ) : null,
			)
		);

		if ( wc_ship_to_billing_address_only() ) {
			WC()->customer->set_props(
				array(
					'shipping_country'   => $request->get_param( 'country' ) ? wc_clean( wp_unslash( $request->get_param( 'country' ) ) ) : null,
					'shipping_state'     => $request->get_param( 'state' ) ? wc_clean( wp_unslash( $request->get_param( 'state' ) ) ) : null,
					'shipping_postcode'  => $request->get_param( 'postcode' ) ? wc_clean( wp_unslash( $request->get_param( 'postcode' ) ) ) : null,
					'shipping_city'      => $request->get_param( 'city' ) ? wc_clean( wp_unslash( $request->get_param( 'city' ) ) ) : null,
					'shipping_address_1' => $request->get_param( 'address' ) ? wc_clean( wp_unslash( $request->get_param( 'address' ) ) ) : null,
					'shipping_address_2' => $request->get_param( 'address_2' ) ? wc_clean( wp_unslash( $request->get_param( 'address_2' ) ) ) : null,
					'shipping_company'   => $request->get_param( 'company' ) ? wc_clean( wp_unslash( $request->get_param( 'company' ) ) ) : null,
				)
			);
		} else {
			WC()->customer->set_props(
				array(
					'shipping_country'   => $request->get_param( 's_country' ) ? wc_clean( wp_unslash( $request->get_param( 's_country' ) ) ) : null,
					'shipping_state'     => $request->get_param( 's_state' ) ? wc_clean( wp_unslash( $request->get_param( 's_state' ) ) ) : null,
					'shipping_postcode'  => $request->get_param( 's_postcode' ) ? wc_clean( wp_unslash( $request->get_param( 's_postcode' ) ) ) : null,
					'shipping_city'      => $request->get_param( 's_city' ) ? wc_clean( wp_unslash( $request->get_param( 's_city' ) ) ) : null,
					'shipping_address_1' => $request->get_param( 's_address' ) ? wc_clean( wp_unslash( $request->get_param( 's_address' ) ) ) : null,
					'shipping_address_2' => $request->get_param( 's_address_2' ) ? wc_clean( wp_unslash( $request->get_param( 's_address_2' ) ) ) : null,
					'shipping_company'   => $request->get_param( 's_company' ) ? wc_clean( wp_unslash( $request->get_param( 's_company' ) ) ) : null,
				)
			);
		}

		if ( $request->get_param( 'has_full_address' ) && wc_string_to_bool( wc_clean( wp_unslash( $request->get_param( 'has_full_address' ) ) ) ) ) {
			WC()->customer->set_calculated_shipping( true );
		} else {
			WC()->customer->set_calculated_shipping( false );
		}

		WC()->customer->save();

		// Calculate shipping before totals. This will ensure any shipping methods that affect things like taxes are chosen prior to final totals being calculated. Ref: #22708.
		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();

		// Get order review fragment.
		ob_start();
		woocommerce_order_review();
		$woocommerce_order_review = ob_get_clean();

		// Get checkout payment fragment.
		ob_start();
		woocommerce_checkout_payment();
		$woocommerce_checkout_payment = ob_get_clean();

		// Get messages if reload checkout is not true.
		$reload_checkout = isset( WC()->session->reload_checkout ) ? true : false;
		if ( ! $reload_checkout ) {
			$messages = wc_print_notices( true );
		} else {
			$messages = '';
		}

		unset( WC()->session->refresh_totals, WC()->session->reload_checkout );

		wp_send_json(
			array(
				'result'    => empty( $messages ) ? 'success' : 'failure',
				'messages'  => $messages,
				'reload'    => $reload_checkout,
				'nonce'     => wp_create_nonce( 'woocommerce-process_checkout' ),
				'fragments' => apply_filters(
					'woocommerce_update_order_review_fragments',
					array(
						'.woocommerce-checkout-review-order-table' => $woocommerce_order_review,
						'.woocommerce-checkout-payment'            => $woocommerce_checkout_payment,
					)
				),
			)
		);
	}

	public function mobile_builder_checkout() {

		wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );
		WC()->checkout()->process_checkout();
		wp_die( 0 );
	}

	/**
	 * Get shipping methods.
	 *
	 * @since    1.0.0
	 */
	public function shipping_methods() {

//		global $woocommerce;

//		WC()->customer->set_props(
//			array(
//				'shipping_country'   => 'VE',
//				'shipping_state'     => $request->get_param( 'state') ) ? wc_clean( wp_unslash( $request->get_param( 'state') ) ) : null,
//				'shipping_postcode'  => $request->get_param( 'postcode') ) ? wc_clean( wp_unslash( $request->get_param( 'postcode') ) ) : null,
//				'shipping_city'      => $request->get_param( 'city') ) ? wc_clean( wp_unslash( $request->get_param( 'city') ) ) : null,
//				'shipping_address_1' => $request->get_param( 'address') ) ? wc_clean( wp_unslash( $request->get_param( 'address') ) ) : null,
//				'shipping_address_2' => $request->get_param( 'address_2') ) ? wc_clean( wp_unslash( $request->get_param( 'address_2') ) ) : null,
//			)
//		);

//		$data = 'billing_first_name=&billing_last_name=&billing_company=&billing_country=VE&billing_address_1=&billing_address_2=&billing_city=&billing_state=&billing_postcode=&billing_phone=&billing_email=admin%40gmail.com&shipping_first_name=&shipping_last_name=&shipping_company=&shipping_country=VN&shipping_address_1=&shipping_address_2=&shipping_postcode=&shipping_city=&shipping_state=&order_comments=&shipping_method%5B15%5D=free_shipping%3A3&shipping_method%5B18%5D=free_shipping%3A3&payment_method=bacs&woocommerce-process-checkout-nonce=a1194af571&_wp_http_referer=%2Fwpdev%2F%3Fwc-ajax%3Dupdate_order_review';
//
//
//		do_action( 'woocommerce_checkout_update_order_review', isset( $data ) ? wp_unslash( $data ) : '' );

//		WC()->customer->save();

		// Calculate shipping before totals. This will ensure any shipping methods that affect things like taxes are chosen prior to final totals being calculated. Ref: #22708.
		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();

		$packages = WC()->shipping()->get_packages();

		$first   = true;
		$methods = array();

		foreach ( $packages as $i => $package ) {
			$chosen_method = isset( WC()->session->chosen_shipping_methods[ $i ] ) ? WC()->session->chosen_shipping_methods[ $i ] : '';
			$product_names = array();

			if ( count( $packages ) > 1 ) {
				foreach ( $package['contents'] as $item_id => $values ) {
					$product_names[ $item_id ] = $values['data']->get_name() . ' &times;' . $values['quantity'];
				}
				$product_names = apply_filters( 'woocommerce_shipping_package_details_array', $product_names, $package );
			}

//			print_r($package['rates']['flat_rate:2']->get_label()); die;

//			$rate = $package['rates'][  ];

//			echo $rate->get_label();

			$available_methods = array();

			foreach ( $package['rates'] as $i => $value ) {
				$available_methods[] = array(
					'label' => wc_cart_totals_shipping_method_label( $value ),
					'id'    => $i,
				);
			}

			$methods[] = array(
				'package'                  => $package,
				'available_methods'        => $available_methods,
				'show_package_details'     => count( $packages ) > 1,
				'show_shipping_calculator' => is_cart() && apply_filters( 'woocommerce_shipping_show_shipping_calculator', $first, $i, $package ),
				'package_details'          => implode( ', ', $product_names ),
				/* translators: %d: shipping package number */
				'package_name'             => apply_filters( 'woocommerce_shipping_package_name', ( ( $i + 1 ) > 1 ) ? sprintf( _x( 'Shipping %d', 'shipping packages', 'woocommerce' ), ( $i + 1 ) ) : _x( 'Shipping', 'shipping packages', 'woocommerce' ), $i, $package ),
				'index'                    => $i,
				'chosen_method'            => $chosen_method,
				'formatted_destination'    => WC()->countries->get_formatted_address( $package['destination'], ', ' ),
				'has_calculated_shipping'  => WC()->customer->has_calculated_shipping(),
				'store'                    => $store = get_user_meta( $package['vendor_id'], 'wcfmmp_profile_settings', true ),
			);

			$first = false;
		}

		return $methods;

	}

	/**
	 * Get total cart
	 * @return array
	 * @since    1.0.0
	 */
	public function get_total() {
		return WC()->cart->get_totals();
	}

	/**
	 *
	 * Set cart item quantity
	 *
	 * @param $request
	 *
	 * @return Array | WP_Error
	 * @since    1.0.0
	 */
	public function set_quantity( $request ) {

		$cart_item_key = $request->get_param( 'cart_item_key' ) ? wc_clean( wp_unslash( $request->get_param( 'cart_item_key' ) ) ) : '';
		$quantity      = $request->get_param( 'quantity' ) ? wc_clean( wp_unslash( $request->get_param( 'quantity' ) ) ) : 1;

		if ( ! $cart_item_key ) {
			return new WP_Error(
				'set_quantity_error',
				'Cart item key not exist.'
			);
		}

		if ( 0 === $quantity || $quantity < 0 ) {
			return new WP_Error(
				'set_quantity_error',
				'The quantity not validate'
			);
		}

		try {
			return array(
				"success" => WC()->cart->set_quantity( $cart_item_key, $quantity ),
			);
		} catch ( Exception $e ) {
			return new WP_Error(
				'set_quantity_error',
				$e->getMessage()
			);
		}
	}

	/**
	 *
	 * Remove cart item
	 *
	 * @param $request
	 *
	 * @return Array |WP_Error
	 * @since    1.0.0
	 */
	public function remove_cart_item( $request ) {

		$cart_item_key = $request->get_param( 'cart_item_key' ) ? wc_clean( wp_unslash( $request->get_param( 'cart_item_key' ) ) ) : '';

		if ( ! $cart_item_key ) {
			return new WP_Error(
				'remove_cart_item',
				'Cart item key not exist.'
			);
		}

		WC()->cart->set_applied_coupons();

		try {
			return array(
				"success" => WC()->cart->remove_cart_item( $cart_item_key )
			);
		} catch ( Exception $e ) {
			return new WP_Error(
				'set_quantity_error',
				$e->getMessage()
			);
		}
	}

	/**
	 *
	 * Add coupon code
	 *
	 * @param $request
	 *
	 * @return Array |WP_Error
	 * @author ngocdt
	 * @since 1.0.0
	 */
	public function add_discount( $request ) {
		$coupon_code = $request->get_param( 'coupon_code' ) ? wc_format_coupon_code( wp_unslash( $request->get_param( 'coupon_code' ) ) ) : "";

		if ( ! $coupon_code ) {
			return new WP_Error(
				'add_discount',
				'Coupon not exist.'
			);
		}

		try {
			return array(
				"success" => WC()->cart->add_discount( $coupon_code ),
			);
		} catch ( Exception $e ) {
			return new WP_Error(
				'set_quantity_error',
				$e->getMessage()
			);
		}

	}

	/**
	 *
	 * Remove coupon code
	 *
	 * @param $request
	 *
	 * @return Array |WP_Error
	 * @author ngocdt
	 * @since 1.0.0
	 */
	public function remove_coupon( $request ) {
		$coupon_code = $request->get_param( 'coupon_code' ) ? wc_format_coupon_code( wp_unslash( $request->get_param( 'coupon_code' ) ) ) : "";

		if ( ! $coupon_code ) {
			return new WP_Error(
				'remove_coupon',
				'Coupon not exist.'
			);
		}

		try {
			$status = WC()->cart->remove_coupon( $coupon_code );
			WC()->cart->calculate_totals();

			return array(
				"success" => $status,
			);
		} catch ( Exception $e ) {
			return new WP_Error(
				'set_quantity_error',
				$e->getMessage()
			);
		}

	}
}
