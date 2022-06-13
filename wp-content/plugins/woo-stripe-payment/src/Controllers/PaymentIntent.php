<?php

namespace PaymentPlugins\Stripe\Controllers;

class PaymentIntent {

	/**
	 * @var \WC_Stripe_Gateway
	 */
	private $client;

	/**
	 * @var array The list of payment methods ID's that are compatible
	 */
	private $payment_method_ids;

	private $retrys = 0;

	private $max_retries = 1;

	private $intent_exists;

	private static $instance;

	/**
	 * @param       $client
	 * @param array $payment_method_ids
	 */
	public function __construct( $client, $payment_method_ids ) {
		$this->client             = $client;
		$this->payment_method_ids = $payment_method_ids;
		$this->initialize();
		self::$instance = $this;
	}

	public static function instance() {
		return self::$instance;
	}

	private function initialize() {
		add_action( 'template_redirect', [ $this, 'maybe_create_intent' ] );
		add_action( 'woocommerce_before_pay_action', [ $this, 'set_order_pay_constants' ] );
		add_action( 'woocommerce_checkout_update_order_review', [ $this, 'update_order_review' ] );
		add_filter( 'wc_stripe_localize_script_wc-stripe', [ $this, 'add_script_params' ], 10, 2 );
		add_filter( 'wc_stripe_can_update_payment_intent', [ $this, 'can_update_payment_intent' ], 10, 2 );
	}

	protected function is_payment_intent_required_for_frontend() {
		return count( $this->get_payment_method_types() ) > 0;
	}

	private function get_payment_method_types() {
		$payment_method_types = [];
		$payment_gateways     = WC()->payment_gateways()->payment_gateways();
		foreach ( $this->payment_method_ids as $id ) {
			$payment_method = isset( $payment_gateways[ $id ] ) ? $payment_gateways[ $id ] : null;
			if ( $payment_method && $payment_method instanceof \WC_Payment_Gateway_Stripe ) {
				if ( method_exists( $payment_method, 'get_confirmation_method' ) ) {
					if ( $payment_method->get_confirmation_method( null ) === \WC_Stripe_Constants::AUTOMATIC ) {
						if ( wc_string_to_bool( $payment_method->enabled ) ) {
							$payment_method_types[] = $payment_method->get_payment_method_type();
						}
					}
				}
			}
		}

		return $payment_method_types;
	}

	public function maybe_create_intent() {
		if ( $this->is_setup_intent_needed() ) {
			$intent = \WC_Stripe_Utils::get_payment_intent_from_session();
			if ( ! \WC_Stripe_Utils::is_setup_intent( $intent ) || ! \WC_Stripe_Utils::is_intent_mode_equal( $intent ) ) {
				$this->create_setup_intent();
			}
		} else {
			if ( ( ( is_checkout() || is_checkout_pay_page() ) && ! is_order_received_page() )
			     && apply_filters( 'wc_stripe_create_payment_intent_for_payment_element', $this->is_payment_intent_required_for_frontend() )
			) {
				$payment_intent       = \WC_Stripe_Utils::get_payment_intent_from_session();
				$payment_method_types = $this->get_payment_method_types();
				if ( $payment_method_types ) {
					if ( is_checkout_pay_page() ) {
						global $wp;
						$order_id = absint( $wp->query_vars['order-pay'] );
						$order    = wc_get_order( $order_id );
						if ( $order ) {
							if ( $payment_intent && ! in_array( $payment_intent->status, [ 'succeeded', 'requires_capture' ] ) && \WC_Stripe_Utils::is_intent_mode_equal( $payment_intent, wc_stripe_order_mode( $order ) ) ) {
								$this->update_payment_intent_from_order( $payment_intent->id, $order, $payment_method_types );
							} else {
								$this->create_payment_intent_from_order( $order, $payment_method_types );
							}
						}
					} else {
						if ( \WC_Stripe_Utils::is_payment_intent( $payment_intent ) && ! in_array( $payment_intent->status, [ 'succeeded', 'requires_capture' ] ) && \WC_Stripe_Utils::is_intent_mode_equal( $payment_intent ) ) {
							$this->update_payment_intent_from_cart( $payment_intent, $payment_method_types );
						} else {
							$this->create_payment_intent_from_cart( $payment_method_types );
						}
					}
				}
			}
		}
	}

	private function is_setup_intent_needed() {
		return ( is_add_payment_method_page() || apply_filters( 'wc_stripe_create_setup_intent', false ) ) && $this->is_payment_intent_required_for_frontend();
	}

	public function set_order_pay_constants() {
		wc_maybe_define_constant( \WC_Stripe_Constants::WOOCOMMERCE_STRIPE_ORDER_PAY, true );
	}

	public function create_payment_intent_from_cart( $payment_method_types ) {
		// create the payment intent
		if ( WC()->cart && WC()->cart->needs_payment() ) {
			$currency    = get_woocommerce_currency();
			$customer_id = wc_stripe_get_customer_id();
			$params      = [
				'amount'               => wc_stripe_add_number_precision( WC()->cart->total, $currency ),
				'currency'             => $currency,
				'confirmation_method'  => 'automatic',
				'payment_method_types' => $payment_method_types
			];
			if ( $customer_id ) {
				$params['customer'] = $customer_id;
			}
			$payment_intent = $this->client->paymentIntents->create( apply_filters( 'wc_stripe_create_payment_intent_params_from_cart', $params, $this ) );
			if ( ! is_wp_error( $payment_intent ) ) {
				$this->save_payment_intent_to_session( $payment_intent );
			}
		}
	}

	public function create_payment_intent_from_order( $order, $payment_method_types ) {
		// create the payment intent
		$currency    = $order->get_currency();
		$customer_id = wc_stripe_get_customer_id( $order->get_customer_id() );
		if ( 0 < $order->get_total() ) {
			$params = [
				'amount'               => wc_stripe_add_number_precision( $order->get_total(), $currency ),
				'currency'             => $currency,
				'confirmation_method'  => \WC_Stripe_Constants::AUTOMATIC,
				'payment_method_types' => $payment_method_types
			];
			if ( $customer_id ) {
				$params['customer'] = $customer_id;
			}
			$payment_intent = $this->client->paymentIntents->create( apply_filters( 'wc_stripe_create_payment_intent_params_from_order', $params, $order, $this ) );
			if ( ! is_wp_error( $payment_intent ) ) {
				$order->update_meta_data( \WC_Stripe_Constants::PAYMENT_INTENT, $payment_intent->toArray() );
				$order->save();
				$this->save_payment_intent_to_session( $payment_intent );
			}
		}
	}

	private function update_payment_intent_from_cart( $payment_intent, $payment_method_types ) {
		// create the payment intent
		if ( WC()->cart && WC()->cart->needs_payment() ) {
			$id          = $payment_intent->id;
			$currency    = get_woocommerce_currency();
			$customer_id = wc_stripe_get_customer_id();
			$params      = [
				'amount'               => wc_stripe_add_number_precision( WC()->cart->total, $currency ),
				'currency'             => $currency,
				'payment_method_types' => $payment_method_types
			];
			if ( $customer_id ) {
				$params['customer'] = $customer_id;
			}
			$payment_intent = $this->client->paymentIntents->update( $id, apply_filters( 'wc_stripe_update_payment_intent_params_from_cart', $params, $this ) );
			if ( ! is_wp_error( $payment_intent ) ) {
				$this->save_payment_intent_to_session( $payment_intent );
			} else {
				\WC_Stripe_Utils::delete_payment_intent_to_session();
				if ( $this->can_retry_request() ) {
					$this->retrys += 1;
					$this->create_payment_intent_from_cart( $payment_method_types );
				}
			}
		}
	}

	private function update_payment_intent_from_order( $id, $order, $payment_method_types ) {
		// create the payment intent
		$currency    = $order->get_currency();
		$customer_id = wc_stripe_get_customer_id( $order->get_customer_id() );
		$params      = [
			'amount'               => wc_stripe_add_number_precision( $order->get_total(), $currency ),
			'currency'             => $currency,
			'payment_method_types' => $payment_method_types
		];
		if ( $customer_id ) {
			$params['customer'] = $customer_id;
		}
		$payment_intent = $this->client->paymentIntents->update( $id, apply_filters( 'wc_stripe_update_payment_intent_params_from_order', $params, $order, $this ) );
		if ( ! is_wp_error( $payment_intent ) ) {
			$order->update_meta_data( \WC_Stripe_Constants::PAYMENT_INTENT, $payment_intent->toArray() );
			$order->save();
			$this->save_payment_intent_to_session( $payment_intent );
		} else {
			\WC_Stripe_Utils::delete_payment_intent_to_session();
			if ( $this->can_retry_request() ) {
				$this->retrys += 1;
				$this->create_payment_intent_from_order( $order, $payment_method_types );
			}
		}
	}

	private function create_setup_intent() {
		$params = [
			'usage'                => 'off_session',
			'payment_method_types' => $this->get_payment_method_types()
		];
		foreach ( $this->get_payment_methods() as $payment_method ) {
			if ( $payment_method->is_active( 'force_3d_secure' ) ) {
				$params['payment_method_options']['card']['request_three_d_secure'] = 'any';
				break;
			}
		}
		$setup_intent = $this->client->setupIntents->create( apply_filters( 'wc_stripe_create_setup_intent_params', $params ) );
		if ( ! is_wp_error( $setup_intent ) ) {
			$this->save_setup_intent_to_session( $setup_intent );
		}
	}

	private function save_payment_intent_to_session( \Stripe\PaymentIntent $payment_intent ) {
		$this->intent_exists = true;
		\WC_Stripe_Utils::save_payment_intent_to_session( $payment_intent );
	}

	private function save_setup_intent_to_session( \Stripe\SetupIntent $setup_intent ) {
		$this->intent_exists = true;
		\WC_Stripe_Utils::save_payment_intent_to_session( $setup_intent->toArray() );
	}

	private function get_payment_methods() {
		$payment_gateways = WC()->payment_gateways()->payment_gateways();
		$payment_methods  = [];
		foreach ( $this->payment_method_ids as $id ) {
			$payment_method = isset( $payment_gateways[ $id ] ) ? $payment_gateways[ $id ] : null;
			if ( $payment_method && $payment_method instanceof \WC_Payment_Gateway_Stripe ) {
			}
			$payment_methods[ $id ] = $payment_method;
		}

		return $payment_methods;
	}

	private function can_retry_request() {
		return $this->retrys < $this->max_retries;
	}

	public function update_order_review() {
		if ( $this->is_payment_intent_required_for_frontend() ) {
			// assign action so intents can be updated
			add_action( 'woocommerce_after_calculate_totals', [ $this, 'after_calculate_totals' ] );
			add_filter( 'woocommerce_update_order_review_fragments', [ $this, 'add_payment_intent_to_fragments' ] );
		}
	}

	/**
	 * @param \WC_Cart $cart
	 */
	public function after_calculate_totals() {
		$intent = \WC_Stripe_Utils::get_payment_intent_from_session();
		if ( $this->is_setup_intent_needed() ) {
			if ( ! \WC_Stripe_Utils::is_setup_intent( $intent ) ) {
				$this->create_setup_intent();
			}
		} else {
			if ( ! \WC_Stripe_Utils::is_payment_intent( $intent ) ) {
				$this->create_payment_intent_from_cart( $this->get_payment_method_types() );
			}
		}
	}

	public function add_payment_intent_to_fragments( $fragments ) {
		$intent = \WC_Stripe_Utils::get_payment_intent_from_session();
		if ( $intent ) {
			$fragments['.wc-stripe-intent-secret'] = $intent->client_secret;
		}

		return $fragments;
	}

	public function add_script_params( $data, $name ) {
		if ( $name === 'wc_stripe_params_v3' && $this->intent_exists ) {
			$data['stripeParams']['apiVersion'] .= ';server_side_confirmation_beta=v1';
			$data['stripeParams']['betas'][]    = 'server_side_confirmation_beta_1';
		}

		return $data;
	}

	/**
	 * @param bool                  $result
	 * @param \Stripe\PaymentIntent $payment_intent
	 */
	public function can_update_payment_intent( $result, $payment_intent ) {
		if ( ! $result ) {
			if ( ! isset( $payment_intent->metadata['order_id'] ) ) {
				$result = true;
			}
		}

		return $result;
	}

}