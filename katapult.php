<?php
/**
 * Plugin Name: Katapult
 * Plugin URI: https://docs.katapult.com/docs/woocommerce
 * Description: Enable Katapult Checkout for WooCommerce
 * Version:     1.1.7
 * Requires PHP: 7.0
 * Requires at least: 5.5
 * Author:      Cognical
 * Author URI: https://katapult.com/
 * License: GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: wporg
 * Domain Path: /languages
 *
 * @extends     WC_Payment_Gateway
 */

 
if (! defined( 'ABSPATH' ) ) {
    exit;
}

// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    return;
}

/**
 * Katapult Payment Gateway
 *
 * Bulk action to change to leasable and remove leasable.
 */
add_filter('bulk_actions-edit-product', function( $bulk_actions ) {
    $bulk_actions['change-to-leasable'] = __('Set as Leasable', 'txtdomain');
    $bulk_actions['remove-from-leasable'] = __('Remove as Leasable', 'txtdomain');
    return $bulk_actions;
});

/**
 * Katapult Payment Gateway
 *
 * Bulk action to handle bulk actions edit product.
 */
add_filter( 'handle_bulk_actions-edit-product', function( $redirect_url, $action, $post_ids ) {

    if ( $action == 'change-to-leasable' ) {
        foreach ( $post_ids as $post_id ) {
            update_post_meta( $post_id, 'leasable', 'yes' );
        }
        $redirect_url = add_query_arg( 'change-to-leasable', count($post_ids), admin_url('edit.php?post_type=product') );
    }
    if ( $action == 'remove-from-leasable' ) {
        foreach ( $post_ids as $post_id ) {
            update_post_meta( $post_id, 'leasable', 'no' );
        }
        $redirect_url = add_query_arg( 'remove-from-leasable', count($post_ids), admin_url('edit.php?post_type=product') );
    }
    return $redirect_url;
}, 10, 3 );

/**
 * Katapult Payment Gateway
 *
 * Bulk action to admin notices.
 */
add_action( 'admin_notices', function() {
    if ( ! empty( $_REQUEST['change-to-leasable'] ) ) {
        $num_changed = (int) $_REQUEST['change-to-leasable'];
        printf( '<div id="message" class="updated notice is-dismissable"><p>' . __('%d Item set as leasable.', 'txtdomain') . '</p></div>', $num_changed );
    }

    if ( ! empty($_REQUEST['remove-from-leasable'] ) ) {
        $num_changed = (int) $_REQUEST['remove-from-leasable'];
        printf( '<div id="message" class="updated notice is-dismissable"><p>' . __( '%d Item removed form leasable.', 'txtdomain' ) . '</p></div>', $num_changed );
    }
});

/**
 * Katapult Payment Gateway
 *
 * Load after woocommerce has been loaded
 */
add_action( 'plugins_loaded', 'katapult_gateway_init', 11 );
function katapult_gateway_init() {
    /**
     * Class WC_Payment_Gateway_Katapult
     */
    class WC_Payment_Gateway_Katapult extends WC_Payment_Gateway {
        /**
         * Request timeout in seconds
         */
        const POST_REQUEST_TIMEOUT = 60;

        /**
         * Cookie definitions
         */
        const RESPONSE_MESSAGE = 'action_response';
        const RESPONSE_MESSAGE_STATUS = 'action_response_status';

        /**
         * Defined values for payment method
         */
        const PAYMENT_METHOD_CODE = 'Katapult';

        /**
         * @var WC_Payment_Gateway_Katapult
         */
        private static $instance;

        /**
         * Store Order items for refund
         *
         * @var array
         */
        private $collected_katapult_refund_items;

        private function __clone() {
        }

        public function __wakeup() {
        }

        /**
         * @return WC_Payment_Gateway_Katapult
         */
        public static function getInstance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * Constructor for the gateway.
         */
        public function __construct() {
            $this->id = 'katapult';
            $this->has_fields = false;
            $this->method_title = self::PAYMENT_METHOD_CODE;
            $this->icon = plugins_url( 'assets/images/icon.png', __FILE__ );
            $this->title = self::PAYMENT_METHOD_CODE;
            //$this->description = "No credit required, Pay over time";
            $this->method_description = 'Buy now. Pay over time with Katapult';
            $this->dev_mode = false;

            // Load the settings
            $this->init_form_fields();
            $this->init_settings();

            // Get settings
            $this->enable_for_virtual = true;
        }

        public function init() {
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'callback_handler' ) );
            add_action( 'woocommerce_before_checkout_form', array( $this, 'add_jscript' ) );
            add_action( 'woocommerce_checkout_update_order_review', array( $this, 'taxexempt_checkout_update_order_review' ) );
            add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
            add_action( 'woocommerce_order_status_cancelled', array( $this, 'cancel_order_ajax' ), 10, 1 );
            add_action( 'woocommerce_order_refunded', array( $this, 'refund_katapult_items' ), 10, 2 );
            add_action( 'woocommerce_order_status_completed', array( $this, 'set_delivery' ), 10, 1 );
            add_action( 'wp_ajax_cancel_order', array( $this, 'cancel_order_ajax' ), 10 );
            add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
            add_action( 'woocommerce_product_options_general_product_data', array( $this, 'create_leasable_field' ) );
            add_action( 'woocommerce_process_product_meta', array( $this, 'product_leasable_field_save' ) );
            add_action( 'woocommerce_variation_options_pricing', array( $this, 'create_variable_product_leasable_field' ), 10, 3 );
            add_action( 'woocommerce_save_product_variation', array( $this, 'product_variation_leasable_field_save' ), 10, 2 );
            add_action( 'admin_head', array( $this, 'show_update_messages' ), 10 );
    	    add_action('woocommerce_thankyou', array( $this, 'wc_katapult_checkout_complete' ));
        }

        /**
         * Display collected notifications
         */
        public function show_update_messages() {
            if ( ! array_key_exists( self::RESPONSE_MESSAGE, $_COOKIE ) ||
                !array_key_exists( self::RESPONSE_MESSAGE_STATUS, $_COOKIE ) ) {
                return '';
            }

            $message = sanitize_title( $_COOKIE[self::RESPONSE_MESSAGE] );
            $status = sanitize_title( $_COOKIE[self::RESPONSE_MESSAGE_STATUS] );

            if ( ! $message ) {
                return '';
            }

            if ( $status === 'Error' ) {
                $response = '</div><div id="message" class="notice notice-error is-dismissible"><p>' . $message . '</p></div>';
            } else {
                $response = '</div><div id="message" class="updated notice notice-success is-dismissible"><p>' . $message . '</p></div>';
            }

            unset( $_COOKIE[self::RESPONSE_MESSAGE] );
            unset( $_COOKIE[self::RESPONSE_MESSAGE_STATUS] );

            // Force expire on previous cookies
            setcookie( self::RESPONSE_MESSAGE, '', time() - ( 15 * 60 ) );
            setcookie( self::RESPONSE_MESSAGE_STATUS, '', time() - ( 15 * 60 ) );

            echo esc_html( $response );
        }

        public function create_leasable_field() {
            global $post;
            // Get the checkbox value
            $checkbox = get_post_meta( $post->ID, 'leasable', true );
            $checked = ( $checkbox == 'no' ) ? '' : array( 'checked' => 'checked' );
            $args = array(
                'id' => 'leasable',
                'label' => __( 'Leasable with Katapult', 'woocommerce' ),
                'desc_tip' => true,
                'description' => __( 'Check if product is leasable with Katapult.', 'woocommerce' ),
                'custom_attributes' => $checked,
            );

            woocommerce_wp_checkbox( $args );
        }

        /**
         * @param $post_id
         */
        public function product_leasable_field_save( $post_id ) {
            $leasable_field = isset( $_POST['leasable'] ) ? 'yes' : 'no';

            if ( ! empty ( $leasable_field ) ){
                update_post_meta( $post_id, 'leasable', esc_attr( $leasable_field ) );
            }
        }

        /**
         * @param $loop
         * @param $variation_data
         * @param $variation
         */
        public function create_variable_product_leasable_field( $loop, $variation_data, $variation ) {
            global $post;
            // Get the checkbox value
            $checkbox = get_post_meta( $post->ID, 'leasable_variable', true );
            $checked = ( $checkbox == 'no' ) ? '' : array( 'checked' => 'checked' );
            $args = array(
                'id' => 'leasable_variable',
                'label' => __( 'Leasable with Katapult', 'woocommerce' ),
                'desc_tip' => true,
                'description' => __( 'Check if product is leasable with Katapult.', 'woocommerce' ),
                'value' => get_post_meta($variation->ID, 'leasable_variable', true),
                'custom_attributes' => $checked,
            );

            woocommerce_wp_checkbox( $args );
        }

        /**
         * @param $variation_id
         * @param $i
         */
        public function product_variation_leasable_field_save( $variation_id, $i ) {
            $leasable_field = isset( $_POST['leasable_variable'] ) ? 'yes' : 'no';

            if ( ! empty ( $leasable_field ) ) {
                update_post_meta( $variation_id, 'leasable_variable', ( $leasable_field ) );
            }
        }

        public function taxexempt_checkout_update_order_review() {
            if ( $_POST['payment_method'] == 'katapult' ) {
                WC()->customer->set_is_vat_exempt( true );
            } else {
                WC()->customer->set_is_vat_exempt( false );
            }
        }

        /**
         * Build the entire cart object in a way that the Katapult plugin
         * can understand.
         *
         * @return array
         */
        public function get_katapult_checkout_object() {
            $order_id = sanitize_text_field( $_GET['order'] );
            $order = new WC_Order( $order_id );

            $katapult_checkout_object = array(
                'customer' => array(
                    'billing' => array(
                        'first_name' => $order->billing_first_name,
                        'middle_name' => $order->billing_middle_name,
                        'last_name' => $order->billing_last_name,
                        'address' => $order->billing_address_1,
                        'address2' => $order->billing_address_2,
                        'country' => $order->billing_country,
                        'city' => $order->billing_city,
                        'state' => $order->billing_state,
                        'zip' => $order->billing_postcode,
                        'phone' => $order->billing_phone,
                        'email' => $order->billing_email
                    ),
                    'shipping' => array(
                        'first_name' => $order->shipping_first_name,
                        'middle_name' => $order->shipping_middle_name,
                        'last_name' => $order->shipping_last_name,
                        'address' => $order->shipping_address_1,
                        'address2' => $order->shipping_address_2,
                        'country' => $order->shipping_country,
                        'city' => $order->shipping_city,
                        'state' => $order->shipping_state,
                        'zip' => $order->shipping_postcode,
                        'phone' => $order->shipping_phone,
                        'email' => $order->shipping_email
                    )
                ),
                'items' => $this->get_formatted_items_for_katapult(),
                'checkout' => array(
                    'customer_id' => $order_id,
                    'shipping_amount' => $order->get_total_shipping(),
                    'discounts' => $this->get_formatted_discounts_for_katapult($order)
                ),
                'urls' => array(
                    'return' => get_site_url() . '/wc-api/WC_Payment_Gateway_Katapult?order=' . $order_id,
                    'cancel' => ''
                )
            );

            return $katapult_checkout_object;
        }

        /**
         * Iterates over the WooCommerce cart items and formats them in a way
         * that the Katapult plugin can understand.
         *
         * @return array
         */
        function get_formatted_items_for_katapult() {
            $processed_items = array();

            if (empty (WC()->cart->get_cart())) {
                return $processed_items;
            }

            $items = WC()->cart->get_cart();

            foreach ( $items as $item => $values ) {
                $data = $values['data'];
                $product = $data->post;
                // Get leasable item.
                $leasable = get_post_meta( $data->id , 'leasable', true );
                $leasable_variable = get_post_meta( $data->id , 'leasable_variable', true );
                $item = new stdClass();
                $item->sku = $data->id;
                $item->display_name = $product->post_title;
                $item->unit_price = $values['line_subtotal'] / $values['quantity'];
                $item->quantity = $values['quantity'];
                $item->leasable = ( 'yes' === $leasable || 'yes' === $leasable_variable ) ? true : false;
                $processed_items[] = $item;
            }

            return $processed_items;
        }

        /**
         * Iterates over the WooCommerce cart discounts and formats them in a way
         * that the Katapult plugin can understand.
         *
         * @return array
         */
        public function get_formatted_discounts_for_katapult( $order ) {
            $coupons = $order->get_coupons();
            $processed_items = array();

            foreach ( $coupons as $coupon ) {
                $processed_items[] = array(
                    'discount_name' => $coupon->get_code(),
                    'discount_amount' => abs( $coupon->get_discount() )
                );
            }

            return $processed_items;
        }

        /**
         * After WooCommerce validates the checkout form we have it redirect
         * to itself with the 'katapult' query param. This lets us know it's time
         * to bootstrap the Katapult plugin.
         *
         * The cart, config JSON and Katapult JS snippet get embedded into the
         * page here.
         */
        public function add_jscript() {
            wp_enqueue_script( 'woocommerce_katapult_gateway', plugins_url( 'assets/js/katapult-gateway.js', __FILE__ ) );

            if ( isset($_GET['katapult']) && $_GET['katapult'] == 1 ) {
                $katapult_checkout_object = $this->get_katapult_checkout_object();
                if (isset ($katapult_checkout_object['customer']['shipping']['address']) && $katapult_checkout_object['customer']['shipping']['address'] == "") {
                    $katapult_checkout_object['customer']['shipping'] = $katapult_checkout_object['customer']['billing'];
                }
                wp_enqueue_script( 'woocommerce_katapult', plugins_url( 'assets/js/katapult-checkout.js', __FILE__ ) );
                wp_localize_script( 'woocommerce_katapult', 'katapultCart', $katapult_checkout_object );
            }
        }

        /**
         * Katapult only supports US customers and is not available in some states
         *
         * @return bool
         */
        function is_available() {
            $is_available = 'yes' === $this->enabled;
            if ( ! is_admin() && isset(WC()->cart) ) {
                $items = WC()->cart->get_cart();
                $is_leasable = false;
                $leasable = array();
                $non_leasable = array();

                foreach ( $items as $item => $values ) {
                    $product = $values['data']->get_post_data();

                    if ( 'yes' === $product->leasable || 'yes' === $product->leasable_variable ) {
                        array_push( $leasable, 1 );
                    } else {
                        array_push( $non_leasable, 0 );
                    }
                }

                if ( count( $leasable ) >= 1 ) {
                    $is_leasable = true;
                }

                if ( WC()->customer && ( "US" !== WC()->customer->get_billing_country() ) ) {
                    $is_available = false;
                } elseif ( ! $is_leasable ) {
                    $is_available = false;
                } elseif ( WC()->cart->total < $this->get_option( 'min_order_total' ) ||
                    WC()->cart->total > $this->get_option( 'max_order_total' ) ) {
                    $is_available = false;
                } else {
                    $katapult_unavailable_states = ['NJ', 'MN', 'WI', 'WY'];
                    $state = WC()->customer->get_billing_state();

                    if ( in_array ( $state, $katapult_unavailable_states ) ) {
                        $is_available = false;
                    }
                }
            } else {
                $is_available = false;
            }

            return $is_available;
        }

        /**
         * Initialise Gateway Settings Form Fields.
         */
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __( 'Enable Katapult', 'woocommerce' ),
                    'label' => __( 'Enable Katapult Preapproval/Checkout', 'woocommerce' ),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no'
                ),
                'environment' => array(
                    'title' => __( 'Environment', 'woocommerce' ),
                    'type' => 'text',
                    'description' => __( 'Which Katapult domain to use as environment', 'woocommerce' ),
                    'default' => __( 'n/a', 'woocommerce' )
                ),
                'private_token' => array(
                    'title' => __( 'Private API key', 'woocommerce' ),
                    'type' => 'text',
                    'description' => __( 'This is the retailer API key that was provided to you by Katapult', 'woocommerce' ),
                    'default' => __( '', 'woocommerce' )
                ),
                'public_token' => array(
                    'title' => __( 'Public API key', 'woocommerce' ),
                    'type' => 'text',
                    'description' => __( 'This is the retailer API key that was provided to you by Katapult', 'woocommerce' ),
                    'default' => __( '', 'woocommerce' )
                ),
                'min_order_total' => array(
                    'title' => __( 'Minimum Order Total', 'woocommerce' ),
                    'type' => 'text',
                    'description' => __( 'The minimum value for order', 'woocommerce' ),
                    'default' => __( '300.00', 'woocommerce' )
                ),
                'max_order_total' => array(
                    'title' => __('Maximum Order Total', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('The maximum value for order', 'woocommerce'),
                    'default' => __('4500.00', 'woocommerce')
                ),
            );
        }

        /**
         * Process the payment and return the result.
         *
         * @param int $order_id
         * @return array
         */
        public function process_payment( $order_id ) {
            $order = wc_get_order( $order_id );

            $redirect_url = add_query_arg(
                array(
                    'katapult' => '1',
                    'order' => $order_id,
                    'nonce' => wp_create_nonce( 'katapult-checkout-order-' . $order_id )
                ), get_permalink( wc_get_page_id( 'checkout' ) )
            );

            return array(
                'result' => 'success',
                'redirect' => $redirect_url
            );
        }

        /**
         * Called both when the Katapult plugin successfully originates a lease
         * as well as when the user completes the flow.
         *
         * If the request method is POST it means Katapult is attempting to update
         * the status of an order in Woo. Decode the response and update the
         * appropriate order.
         *
         * If the request method is GET, mark payment as processing and redirect to the Woo thank you
         * page.
         *
         * @return array|void
         */
        public function callback_handler() {
            if( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
                $katapult_response = json_decode( file_get_contents( 'php://input' ) );
                $transaction_id = $katapult_response->uid;

                $order = new WC_Order( $katapult_response->customer_id );
                $order->payment_complete( $transaction_id );

                if ( ! $order->get_transaction_id() ) {
                    // Specifically set transaction ID if it missing,
                    // because payment_complete won't on order status processing
                    $this->set_woo_order_transaction_id( $order, $transaction_id );
                }
            }else {
                $order_id = sanitize_text_field ( $_GET['order'] );
                $order = new WC_Order( $order_id );
                header( 'Location:' . $this->get_return_url( $order ) );
            }
        }


        /**
		 *  Katapult plugin successfully originates a lease
		 *  as well as when the user completes the flow.
		 *  @param string $order_id order id.
		 */
		public function wc_katapult_checkout_complete( $order_id ) {
            $order        = new WC_Order( $order_id );
			$payment_type = $order->get_payment_method();			
			if ($payment_type == 'katapult'){
                if(!$order->get_transaction_id() ) {
                    $sync = json_encode(['order_id' => $order_id]);
                    $response = $this->make_authenticated_post_request( 'application/sync/', false,  $sync);
                    $transaction_id = $response->uid;
                    if ($response && $transaction_id) {
                        $order->payment_complete( $transaction_id );  
                        if(!$order->get_transaction_id() ) {
                            $this->set_woo_order_transaction_id( $order, $transaction_id );
                        }                    
                    }
                }				
			}
		}



        /**
         * Set Katapult transaction ID if it missing for Woo order
         *
         * @param $order
         * @param $transaction_id
         */
        public function set_woo_order_transaction_id( $order, $transaction_id ) {
            try {
                if ( ! empty ( $transaction_id ) ) {
                    $order->set_transaction_id( $transaction_id );
                }

                $order->save();
            } catch (\Exception $e) {
                $message = sprintf(
                    __( 'Order transaction ID was not saved, please contact administrator.', 'woocommerce' )
                );

                wc_add_notice( $message, 'error' );
            }
        }

        /**
         * If this is a Katapult order, initialize the Katapult status metabox in the admin.
         */
        public function add_meta_boxes() {
            $meta = get_post_meta( sanitize_text_field( $_GET['post'] ) );

            if (gettype($meta) == 'array' && !array_key_exists( '_cart_hash', $meta ) ) {
                return;
            }

            $order = new WC_Order( sanitize_text_field( $_GET['post'] ) );
            $payment_method = wc_get_payment_gateway_by_order( $order )->title;

            if ( $payment_method != self::PAYMENT_METHOD_CODE ) return;

            $transaction_id = $order->get_transaction_id();

            if ( ! $transaction_id ) return;

            add_meta_box(
                'woocommerce-order-my-custom',
                __( 'Katapult Status' ),
                array( $this, 'get_katapult_status_meta_box' ),
                'shop_order',
                'side',
                'default',
                array( $transaction_id )
            );
        }

        /**
         * Gets the current status of a Katapult order.
         * @param $transaction_id
         *
         * @return string|null
         */
        public function get_status( $transaction_id ) {
            $application = $this->make_authenticated_request(
                'application/' . $transaction_id . '/',
                false,
                'v3'
            );

            if ( ! $application ) {
                return null;
            }

            return $application->status;
        }

        /**
         * Determine environment, API type etc and make authenticated
         * request to Katapult.
         *
         * Used for get requests
         *
         * @param $resource
         * @param $is_private
         * @param $api
         *
         * @return mixed
         */
        public function make_authenticated_request( $resource, $is_private, $api ) {

            $token = $is_private ? $this->get_option( 'private_token' ) : $this->get_option( 'public_token' );
            $url = $this->get_option('environment') . '/api/v3/' . $resource;
            $options = array(
                'method' => 'GET',
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json'
                )
            );

            try {
                $response = wp_remote_get( $url, $options );

                return json_decode($response['body']);
            } catch (\Exception $e) {
                $message = __(
                    sprintf( 'Katapult GET request to %s has failed, error - %s.', $resource, $e->getMessage()),
                    'woocommerce'
                );

                wc_add_notice( $message, 'error' );
            }

            return null;
        }

        /**
         * Determine environment, API type etc and make authenticated
         * request to Katapult.
         *
         * Used for post requests
         *
         * @param $resource
         * @param $is_private
         * @param $body
         *
         * @return mixed
         */
        public function make_authenticated_post_request( $resource, $is_private, $body ) {
            $token = $is_private ? $this->get_option('private_token') : $this->get_option('public_token');
            $url = $this->get_option('environment') . '/api/v3/' . $resource;

            $options = array(
                'method' => 'POST',
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json'
                ),
                'body' => $body,
                'timeout' => self::POST_REQUEST_TIMEOUT
            );
            

            try {
                $response = wp_remote_post( $url, $options );

                if ( is_wp_error ( $response ) ) {
                    setcookie( self::RESPONSE_MESSAGE, $response->get_error_message() );
                    setcookie( self::RESPONSE_MESSAGE_STATUS, __('Error') );

                    return null;
                }
            } catch (\Exception $e) {
                $message = __(
                    sprintf( 'Katapult POST request to %s has failed, error - %s.', $resource, $e->getMessage() ),
                    'woocommerce'
                );

                wc_add_notice( $message, 'error' );

                return null;
            }

            return json_decode( $response['body'] );
        }

        /**
         * @param $order_id
         */
        public function cancel_order_ajax( $order_id = null ) {
            if ( ! $order_id ) {
                $order_id = sanitize_text_field( $_POST['data'] );
            }

            $response = [];
            $order = wc_get_order( $order_id );
            $transaction_id = $order->get_transaction_id();
            $payment_method = wc_get_payment_gateway_by_order( $order )->title;

            if ( $payment_method !== self::PAYMENT_METHOD_CODE ) return;

            if ( $transaction_id ) {
                $response = $this->make_authenticated_request(
                    'application/' . $transaction_id . '/cancel_order/',
                    true,
                    'v3'
                );

                $order->update_status( 'cancelled' );
            }

            if ($response) {
                setcookie( self::RESPONSE_MESSAGE, __( 'Katapult order cancelled' ) );
                setcookie( self::RESPONSE_MESSAGE_STATUS, __( 'Success' ) );
            } else {
                setcookie( self::RESPONSE_MESSAGE, __( 'Could not cancel Katapult order' ) );
                setcookie( self::RESPONSE_MESSAGE_STATUS, __( 'Error' ) );
            }
        }

        /**
         * Read refund item data and trigger cancel_item endpoint in Katapult
         *
         * @param $order_id
         * @param $refund_id
         */
        public function refund_katapult_items( $order_id, $refund_id ) {
            $order = wc_get_order( $order_id );
            $refund = wc_get_order( $refund_id );
            $transaction_id = $order->get_transaction_id();
            $response = null;
            $payment_method = wc_get_payment_gateway_by_order( $order )->title;

            

            if ( $payment_method !== self::PAYMENT_METHOD_CODE ) return;

            if ( $transaction_id ) {
                $itemsToRefund = $this->gather_items_for_cancel( $refund );

                if ( ! $itemsToRefund ) {
                    setcookie( self::RESPONSE_MESSAGE, __( 'No refundable Katapult items found' ) );
                    setcookie( self::RESPONSE_MESSAGE_STATUS, __( 'Error' ) );

                    return;
                }

                $response = $this->make_authenticated_post_request( 'application/' . $transaction_id . '/cancel_item/', true, $itemsToRefund );
            }

            if ( $response && gettype($response) == 'array' && array_key_exists( 'success', $response ) ) {
                setcookie( self::RESPONSE_MESSAGE, __( 'Katapult order items refunded' ) );
                setcookie( self::RESPONSE_MESSAGE_STATUS, __( 'Success' ) );
            } else {
                setcookie( self::RESPONSE_MESSAGE, __( 'Could not refund Katapult items' ) );
                setcookie( self::RESPONSE_MESSAGE_STATUS, __( 'Error' ) );
            }
        }

        /**
         * Collect product data to be later used in refund_katapult_items
         *
         * @param $refund
         *
         * @return false|string
         */
        public function gather_items_for_cancel( $refund ) {
            $refund_items = $refund->get_items();
            $items_for_refund = [];

            foreach ( $refund_items as $item_id => $item ) {
                if ( ! $this->validate_leasability( $item->get_product_id() ) ) {
                    continue;
                }

                $items_for_refund['items'][] = [
                    'sku' => (string) $item->get_product_id(),
                    'display_name' => $item->get_name(),
                    'unit_price' => abs( $item->get_subtotal() ),
                    'quantity' => (int) abs( $item->get_quantity() ),
                ];
            }

            return json_encode( $items_for_refund );
        }


        /**
         * Read submit delivery item's data and trigger submit_delivery endpoint in Katapult
         *
         * @param $order_id
         */
        public function set_delivery( $order_id ) {
            $order = wc_get_order( $order_id );
            $items = $this->get_processed_items( $order->get_items() );

            $delivery_payload = array(
                'items' =>  $items,
                'delivery_date' => date( DATE_RFC3339_EXTENDED, time() )
            );

            $delivery_payload = json_encode( $delivery_payload );

            $transaction_id = $order->get_transaction_id();

            $response = null;
            $payment_method = wc_get_payment_gateway_by_order( $order )->title;

            if ( $payment_method !== self::PAYMENT_METHOD_CODE ) return;

            if ( $transaction_id ) {
                $response = $this->make_authenticated_post_request( 'application/' . $transaction_id . '/delivery/', false, $delivery_payload );
            }

            if ( $response && gettype($response) == 'array' && array_key_exists( 'success', $response ) ) {
                setcookie( self::RESPONSE_MESSAGE, __( 'Katapult order delivery submitted' ) );
                setcookie( self::RESPONSE_MESSAGE_STATUS, __( 'Success' ) );
            } else {
                setcookie( self::RESPONSE_MESSAGE, __( 'katapult order could not submit delivery' ) );
                setcookie( self::RESPONSE_MESSAGE_STATUS, __( 'Error' ) );
            }
        }

        /**
         * Prepare data to be later used in submit delivery.
         *
         * @param $items
         *
         * @return array
         */
        function get_processed_items( $items ) {
            $processed_items = array();
            foreach( $items as $item => $values ) {
                $product_with_sku = new WC_Product( $values['product_id'] );
                $item = new stdClass();
                $item->sku = (string) $product_with_sku->get_id();
                $item->display_name = $product_with_sku->get_name();
                $item->unit_price = $values['line_subtotal'] / $values['quantity'];
                $item->quantity = $values['quantity'];

                $processed_items[] = $item;

            }

            return $processed_items;
        }

        /**
         * Check product leasability
         *
         * @param $product_id
         *
         * @return bool
         */
        public function validate_leasability( $product_id ) {
            $leasability_meta_attributes = [
                'leasable',
                'leasable_variable'
            ];

            foreach ( $leasability_meta_attributes as $attribute ) {
                $leasable = get_post_meta( $product_id, $attribute );

                if ( is_array ( $leasable ) && array_shift( $leasable ) === 'no' ) {
                    return false;
                }
            }

            return true;
        }

        /**
         * @param $hook
         */
        public function admin_scripts( $hook ) {
            if ( 'post.php' != $hook ) {
                return;
            }

            wp_enqueue_script( 'katapult-status', plugins_url( 'assets/js/katapult-status.js', __FILE__ ), 'jQuery' );
        }

        public function get_icon() {
            $icon_html = '<img style="width:100px; "src="' . plugins_url('assets/images/icon.png', __FILE__) . '">';
            return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
        }

        /**
         * Build the HTML/CSS for the Katapult status metabox in the
         * edit order admin.
         *
         * @param $data
         * @param $box
         */
        function get_katapult_status_meta_box( $data, $box ) {
            $status = $box['args']['0'];
            ?>
            <style>
                .katapult-status {
                    color: #0085ba;
                    display: inline-block;
                }

                .katapult-status.canceled {
                    color: red;
                }

                .katapult-status-container .button {
                    margin-top: 10px;
                }

                .katapult-status-container.canceled .button {
                    display: none;
                }
            </style>

            <div class="katapult-status-container <?php echo esc_html( $status ) ?>">
                <p class="katapult-status <?php echo esc_html( $status ) ?>"><?php echo esc_html( $status ); ?></p>
                <a id="katapult-admin-cancel-order" class="cancel-order button button-primary">Cancel order</a>
                <script>
                    jQuery('#katapult-admin-cancel-order').on('click', function() {
                        jQuery('#katapult-admin-cancel-order').attr('disabled', true);
                        jQuery('#katapult-admin-cancel-order').html('Processing...');
                    });
                </script>
            </div>

        <?php }
    }

    class KatapultLoader
    {
        private static $instance = null;
        private $gateway = null;

        private function __clone() {
        }

        public function __wakeup() {
        }

        public static function getInstance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        public function get_gateway() {
            if ( $this->gateway == null ) {
                $this->gateway = WC_Payment_Gateway_Katapult::getInstance();
                $this->gateway->init();
            }

            return $this->gateway;
        }

        public function __construct() {
            $this->get_gateway();
            add_filter( 'woocommerce_payment_gateways', array( $this, 'wc_payment_gateway_katapult' ) );
            add_action( 'wp', array( $this, 'init' ) );
        }

        public function init() {
            $this->inject_preapproval_script();
            $this->is_checkout = is_checkout();
            $katapult_config = array(
                'environment' => $this->get_gateway()->get_option( 'environment' ),
                'api_key' => $this->get_gateway()->get_option( 'public_token' )
            );

            wp_register_script( 'katapult_config', false );
            wp_localize_script( 'katapult_config', '_katapult_config', $katapult_config );
            wp_enqueue_script( 'katapult_config' );

            $dependencies = array();
            if ( $this->is_checkout ) {
                $dependencies[] = 'woocommerce_katapult';
            }

            wp_enqueue_script( 'katapult_js', plugins_url( 'assets/js/katapult.js', __FILE__ ), $dependencies, '', true );
        }

        /**
         * @param $methods
         * @return mixed
         */
        public function wc_payment_gateway_katapult( $methods ) {
            $methods[] = 'WC_Payment_Gateway_Katapult';
            return $methods;
        }

        /**
         * Add katapult script.
         */
        private function inject_preapproval_script() {
            add_action( 'wp_footer', function() {
                echo "<script type='text/javascript'>
                    jQuery('.btn-katapult-preapprove').on('click', function() {
                        katapult.preapprove();
                    });
                </script>";
            });
        }
    }

    $GLOBALS['katapult_loader'] = KatapultLoader::getInstance();
}