<?php

class WC_Stripe_Controller_Feedback extends WC_Stripe_Rest_Controller {

	protected $namespace = 'admin';

	private $api_url = 'https://crm.paymentplugins.com/v1/feedback/stripe';

	public function register_routes() {
		register_rest_route(
			$this->rest_uri(), 'feedback', array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'process_request' ),
				'permission_callback' => array( $this, 'admin_permission_check' )
			)
		);
	}

	/**
	 * @param $request \WP_REST_Request
	 */
	public function process_request( $request ) {
		$reason_code = $request['reason_code'];
		$reason_text = isset( $request['reason_text'] ) ? $request['reason_text'] : '';
		$website     = site_url();
		$data        = compact( 'website', 'reason_code', 'reason_text' );
		$account_id  = stripe_wc()->account_settings->get_account_id();
		$account_id  = ! $account_id ? stripe_wc()->account_settings->get_account_id( 'test' ) : $account_id;
		if ( $account_id ) {
			$data['account_id'] = $account_id;
		}
		$result = wp_safe_remote_post( $this->api_url, [
			'method'      => 'POST',
			'timeout'     => 30,
			'httpversion' => 1,
			'blocking'    => true,
			'headers'     => [
				'Content-Type' => 'application/json'
			],
			'body'        => wp_json_encode( $data ),
			'cookies'     => []
		] );
		if ( is_wp_error( $result ) ) {
			return new \WP_Error( 'feedback-error', $result->get_error_message(), array( 'status' => 200 ) );
		}
		if ( wp_remote_retrieve_response_code( $result ) > 299 ) {
			$body = json_decode( wp_remote_retrieve_body( $result ), true );

			return new \WP_Error( 'feedback-error', $body['message'], array( 'status' => 200 ) );
		}

		return rest_ensure_response( [ 'success' => true ] );
	}

}