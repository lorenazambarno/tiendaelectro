<?php

namespace PaymentPlugins\Blocks\Stripe\Payments\Gateways;

use Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema;
use PaymentPlugins\Blocks\Stripe\Assets\Api;
use PaymentPlugins\Blocks\Stripe\Payments\AbstractStripePayment;
use PaymentPlugins\Blocks\Stripe\StoreApi\EndpointData;
use PaymentPlugins\Stripe\Controllers\PaymentIntent;
use PaymentPlugins\Stripe\Link\LinkIntegration;

class LinkPayment extends AbstractStripePayment {

	protected $name = 'stripe_link_checkout';

	private $link;

	/**
	 * @var \PaymentPlugins\Stripe\Controllers\PaymentIntent
	 */
	private $payment_intent_ctrl;

	/**
	 * @var Api
	 */
	private $assets;

	public function __construct( LinkIntegration $link, Api $assets ) {
		$this->link       = $link;
		$this->assets_api = $assets;
	}

	public function initialize() {
		add_filter( 'wc_stripe_blocks_general_data', [ $this, 'add_stripe_params' ] );
	}

	public function is_active() {
		return $this->link->is_active();
	}

	public function add_stripe_params( $data ) {
		if ( $this->link->is_active() ) {
			$data['stripeParams']['betas'][] = 'link_autofill_modal_beta_1';
		}

		return $data;
	}

	public function get_payment_method_data() {
		return [
			'name'       => $this->name,
			'launchLink' => $this->link->is_autoload_enabled()
		];
	}

	public function get_payment_method_script_handles() {
		$this->assets_api->register_script( 'wc-stripe-blocks-link', 'build/wc-stripe-link-checkout.js' );

		return [ 'wc-stripe-blocks-link' ];
	}

	protected function is_express_checkout_enabled() {
		return true;
	}

	public function set_payment_intent_controller( PaymentIntent $controller ) {
		$this->payment_intent_ctrl = $controller;
	}

	public function get_endpoint_data() {
		if ( $this->link->is_active() ) {
			$data = new EndpointData();
			$data->set_endpoint( CartSchema::IDENTIFIER );
			$data->set_namespace( $this->name );
			$data->set_data_callback( function () {
				$result         = [];
				$payment_intent = \WC_Stripe_Utils::get_payment_intent_from_session();
				if ( \WC_Stripe_Utils::is_setup_intent( $payment_intent ) && WC()->cart->total > 0 ) {
					$payment_intent = null;
				}
				if ( ! $payment_intent ) {
					$this->payment_intent_ctrl->create_payment_intent_from_cart( [ 'card' ] );
					$payment_intent = \WC_Stripe_Utils::get_payment_intent_from_session();
				}
				if ( $payment_intent ) {
					$result['clientSecret'] = $payment_intent->client_secret;
				}

				return $result;
			} );
			$data->set_schema_callback( function () {
				return [
					'client_secret' => [
						'description' => 'Client secret used by Stripe integration',
						'type'        => 'string',
						'readonly'    => true
					]
				];
			} );

			return $data;
		}

		return [];
	}

}