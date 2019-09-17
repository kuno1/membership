<?php

namespace Kunoichi\Membership\Rest;


use Kunoichi\Membership\Models\Customers;
use Kunoichi\Membership\Models\Products;
use Kunoichi\Membership\Pattern\RestBase;

class Subscriptions extends RestBase {
	
	protected $route = 'subscriptions/(?P<product_id>\d+)';
	
	/**
	 * 受け取る引数を定義
	 *
	 * @param string $http_method
	 *
	 * @return array
	 */
	protected function get_args( $http_method ) {
		return [
			'token' => [
				'required' => true,
				'validate_callback' => function( $var ) {
					return ! empty( $var );
				},
			],
			'product_id' => [
				'required' => true,
				'validate_callback' => function( $var ) {
					// 実際に存在する商品か確かめる。
					$post = get_post( $var );
					return $post && 'publish' === $post->post_status && 'membership' === $post->post_type;
				},
			],
			'plan_id' => [
				'required' => true,
				'validate_callback' => function( $var, \WP_REST_Request $request ) {
					// プランが存在するか確かめる。
					foreach ( Products::get_instance()->get_plans( $request->get_param( 'product_id' ) ) as $plan ) {
						if ( $var === $plan->id ) {
							return true;
						}
					}
					return false;
				}
			],
		];
	}
	
	/**
	 * POST リクエストを処理
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_Error|array|\stdClass
	 */
	public function handle_post( $request ) {
		$result = Customers::get_instance()->subscribe( get_current_user_id(), $request->get_param( 'plan_id' ), $request->get_param( 'token' ) );
		return is_wp_error( $result ) ? $result : [
			'success' => true,
			'message' => __( 'Thank you for subscribing!', 'membership' ),
		];
	}
	
	/**
	 * ユーザーのパーミッションをチェック
	 *
	 * @param \WP_REST_Request $request
	 * @return bool
	 */
	public function permission_callback( $request ) {
		return current_user_can( 'read' );
	}
}
