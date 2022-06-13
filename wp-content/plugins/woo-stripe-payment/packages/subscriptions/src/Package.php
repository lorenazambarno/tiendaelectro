<?php

namespace PaymentPlugins\Stripe\WooCommerceSubscriptions;

use PaymentPlugins\Stripe\WooCommerceSubscriptions\Controllers\PaymentIntent;

class Package {

	public static function init() {
		add_action( 'woocommerce_init', [ __CLASS__, 'initialize' ] );
	}

	public static function initialize() {
		if ( self::is_enabled() ) {
			new PaymentIntent( new FrontendRequests() );
		}
	}

	private static function is_enabled() {
		return wcs_stripe_active();
	}

}