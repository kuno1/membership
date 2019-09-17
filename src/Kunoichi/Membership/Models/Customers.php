<?php

namespace Kunoichi\Membership\Models;


use Hametuha\Pattern\Singleton;
use Stripe\Customer;
use Stripe\Subscription;

class Customers extends Singleton {
	
	protected function init() {
	
	}
	
	/**
	 * Stripe での ID を取得する
	 *
	 * @param int  $user_id
	 * @param bool $generate true にした場合は生成する
	 * @return string
	 */
	public function get_stripe_id( $user_id, $generate = false ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return '';
		}
		$stripe_id = (string) get_user_meta( $user_id, 'stripe_id', true );
		if ( ! $stripe_id && $generate ) {
			// ない場合は生成する
			try {
				$customer = Customer::create( [
					'email'    => $user->user_email,
					'name'     => $user->user_login,
					'metadata' => [
						'user_id' => $user_id,
					],
				] );
				// 保存する
				update_user_meta( $user_id, 'stripe_id', $customer->id );
				$stripe_id = $customer->id;
			} catch ( \Exception $e ) {
				return '';
			}
		}
		return $stripe_id;
	}
	
	/**
	 * ユーザーが有効な定期購入を持っているか判定
	 *
	 * @param int               $user_id 判定したいユーザー
	 * @param null|int|\WP_Post $product 判定したい商品
	 * @return bool
	 */
	public function is_valid( $user_id, $product = null ) {
		$product = get_post( $product );
		$user    = get_userdata( $user_id );
		if ( ! $product || ! $user) {
			return false;
		}
		$stripe_id  = $this->get_stripe_id( $user_id, true );
		if ( ! $stripe_id ) {
			return false;
		}
		foreach ( Products::get_instance()->get_plans( $product->ID ) as $plan ) {
			try {
				$subscriptions = Subscription::all( [
					'customer' => $stripe_id,
					'plan'     => $plan->id,
					'status'   => 'active',
				] );
				if ( $subscriptions->data ) {
					return true;
				}
			} catch ( \Exception $e ) {
				continue;
			}
		}
		return false;
	}
	
	/**
	 * ユーザーを定期購入させる
	 *
	 * @param int    $user_id
	 * @param string $plan_id
	 * @param string $token   支払い時に生成されたトークン
	 * @return true|\WP_Error
	 */
	public function subscribe( $user_id, $plan_id, $token ) {
		try {
			$stripe_id = $this->get_stripe_id( $user_id );
			// Save card.
			$card = Customer::createSource( $stripe_id, [
				'source' => $token,
			] );
			$result = Subscription::create( [
				"customer"       => $stripe_id,
				'default_source' => $card->id,
				"items"          => [
					[
						"plan" => $plan_id,
					],
				]
			] );
			return true;
		} catch ( \Exception $e ) {
			return new \WP_Error( $e->getCode(), $e->getMessage() );
		}
	}
	
	/**
	 * ユーザーの定期購入を解除する
	 *
	 * @param int    $user_id
	 * @param string $plan_id
	 * @return true|\WP_Error
	 */
	public function unsubscribe( $user_id, $plan_id ) {
		$stripe_id = $this->get_stripe_id( $user_id );
		if ( ! $stripe_id ) {
			return new \WP_Error( 'invalid_user', __( 'User does not exist.', 'membership' ) );
		}
		try {
			foreach ( Subscription::all( [
				'customer' => $stripe_id,
				'plan'     => $plan_id,
				'active'   => true,
			] ) as $subscription ) {
				$subscription->cancel();
			};
			return true;
		} catch ( \Exception $e ) {
			return new \WP_Error( $e->getCode(), $e->getMessage() );
		}
	}
}
