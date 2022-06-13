<?php

namespace PaymentPlugins\Stripe\Link;

use PaymentPlugins\Stripe\Assets\AssetDataApi;
use PaymentPlugins\Stripe\Assets\AssetsApi;

class LinkIntegration {

	const DATA_KEY = 'wcStripeLinkParams';

	/**
	 * @var \WC_Stripe_Advanced_Settings
	 */
	private $settings;

	/**
	 * @var \WC_Stripe_Account_Settings
	 */
	private $account_settings;

	/**
	 * @var \PaymentPlugins\Stripe\Assets\AssetsApi
	 */
	private $assets;

	/**
	 * @var \PaymentPlugins\Stripe\Assets\AssetDataApi
	 */
	private $data_api;

	/**
	 * @var bool
	 */
	private $enabled;

	private $supported_countries = [ 'US' ];

	private $supported_currencies = [ 'USD' ];

	private $supported_payment_methods = [ 'stripe_cc' ];

	private static $instance;

	public function __construct( \WC_Stripe_Advanced_Settings $settings, \WC_Stripe_Account_Settings $account_settings, AssetsApi $assets, AssetDataApi $data_api ) {
		self::$instance         = $this;
		$this->settings         = $settings;
		$this->account_settings = $account_settings;
		$this->assets           = $assets;
		$this->data_api         = $data_api;
		$this->enabled          = $settings->is_active( 'link_enabled' );
		if ( $this->is_active() ) {
			$this->initialize();
		}
	}

	public static function get_instance() {
		return self::$instance;
	}

	protected function initialize() {
		$this->register_assets();
		add_action( 'wp_print_scripts', [ $this, 'enqueue_scripts' ], 5 );
		add_filter( 'wc_stripe_localize_script_wc-stripe', [ $this, 'add_script_params' ], 10, 2 );
		add_filter( 'wc_stripe_create_payment_intent_for_payment_element', '__return_true' );
		add_filter( 'wc_stripe_create_payment_intent_params_from_cart', [ $this, 'add_payment_intent_params' ] );
		add_filter( 'wc_stripe_create_payment_intent_params_from_order', [ $this, 'add_payment_intent_params' ] );
		add_filter( 'wc_stripe_update_payment_intent_params_from_cart', [ $this, 'add_payment_intent_params' ] );
		add_filter( 'wc_stripe_update_payment_intent_params_from_order', [ $this, 'add_payment_intent_params' ] );
		add_filter( 'wc_stripe_create_setup_intent_params', [ $this, 'add_setup_intent_params' ] );
		add_filter( 'wc_stripe_payment_intent_args', [ $this, 'add_payment_method_type' ], 10, 2 );
		add_filter( 'woocommerce_checkout_fields', [ $this, 'add_billing_email_priority' ] );
	}

	public function is_active() {
		return $this->enabled && $this->is_valid_account_country();
	}

	private function register_assets() {
		$this->assets->register_script( 'wc-stripe-link-checkout', 'assets/build/link-checkout.js', [ 'wc-stripe-external', 'wc-stripe-wc-stripe' ] );
	}

	private function is_valid_account_country() {
		return \in_array( $this->account_settings->get_account_country( wc_stripe_mode() ), $this->supported_countries );
	}


	/**
	 * @param null|\WC_Order $order
	 *
	 * @return bool|mixed
	 */
	public function can_process_link_payment( $order = null ) {
		if ( $order ) {
			return \in_array( $order->get_payment_method(), $this->supported_payment_methods, true )
			       && \in_array( $order->get_currency(), $this->supported_currencies, true )
			       && \in_array( $this->account_settings->get_account_country( wc_stripe_order_mode( $order ) ), $this->supported_countries );
		} else {
			return is_checkout() && WC()->cart && WC()->cart->needs_payment() && \in_array( get_woocommerce_currency(), $this->supported_currencies, true );
		}
	}

	public function enqueue_scripts() {
		if ( $this->can_process_link_payment() ) {
			$payment_intent = \WC_Stripe_Utils::get_payment_intent_from_session();
			if ( $payment_intent ) {
				$this->data_api->print_data( self::DATA_KEY, [
					'launchLink'   => $this->is_autoload_enabled(),
					'clientSecret' => $payment_intent->client_secret
				] );
				wp_enqueue_script( 'wc-stripe-link-checkout' );
			}
		}
	}

	public function add_script_params( $data, $name ) {
		if ( $name === 'wc_stripe_params_v3' ) {
			$data['stripeParams']['betas'][] = 'link_autofill_modal_beta_1';
		}

		return $data;
	}

	public function add_payment_intent_params( $params ) {
		if ( $params['currency'] === 'USD' ) {
			$params['payment_method_types'][] = 'link';
		}

		return $params;
	}

	public function add_setup_intent_params( $params ) {
		$params['payment_method_types'][] = 'link';

		return $params;
	}

	/**
	 * @param array     $params
	 * @param \WC_Order $order
	 */
	public function add_payment_method_type( $params, $order ) {
		if ( $this->can_process_link_payment( $order ) ) {
			$params['payment_method_types'][] = 'link';
		}

		return $params;
	}

	public function add_billing_email_priority( $fields ) {
		if ( $this->settings->is_active( 'link_email' ) ) {
			if ( isset( $fields['billing']['billing_email'] ) ) {
				$fields['billing']['billing_email']['priority'] = 1;
			}
		}

		return $fields;
	}

	public function is_autoload_enabled() {
		return $this->settings->is_active( 'link_autoload' );
	}

}