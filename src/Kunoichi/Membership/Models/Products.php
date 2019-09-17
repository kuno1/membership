<?php

namespace Kunoichi\Membership\Models;


use Hametuha\Pattern\Singleton;
use Stripe\Plan;
use Stripe\Product;

class Products extends Singleton {
	
	/**
	 * コンストラクタ
	 */
	protected function init() {
		// 商品保存時にStripeに登録する
		add_action( 'save_post', [ $this, 'register_product' ], 10, 2 );
		// プランの作成
		add_action( 'save_post', [ $this, 'save_plan' ], 11, 2 );
		// メタボックスを登録
		add_action( 'add_meta_boxes', function( $post_type ) {
			if ( 'membership' !== $post_type ) {
				return;
			}
			add_meta_box( 'membership-plans', __( 'Plans', 'membership' ), [ $this, 'do_meta_box' ], $post_type );
		} );
	}
	
	/**
	 * Stripe に商品を保存
	 *
	 * @param int      $post_id
	 * @param \WP_Post $post
	 */
	public function register_product( $post_id, $post ) {
		if ( 'membership' !== $post->post_type ) {
			return;
		}
		try {
			$product_id = $this->get_product_id( $post_id );
			if ( ! $product_id ) {
				// 商品として登録されていないので、登録する。
				$result = Product::create( [
					'name'   => get_the_title( $post ),
					'type'   => 'service',
					'active' => 'publish' === $post->post_status
				] );
				update_post_meta( $post_id, '_stripe_product_id', $result->id );
			} else {
				// すでに登録済みなので更新する
				$result = Product::retrieve( $product_id );
				Product::update( $product_id, [
					'name'   => get_the_title( $post ),
					'active' => 'publish' === $post->post_status,
				] );
			}
		} catch ( \Exception $e ) {
			// Stripe API は例外を投げるので、キャッチしておく。
			error_log( $e->getMessage() );
		}
	}
	
	/**
	 * 新規プランを作成
	 *
	 * @param int      $post_id
	 * @param \WP_Post $post
	 */
	public function save_plan( $post_id, $post ) {
		// Nonceが不正なら何もしない
		if ( ! wp_verify_nonce( filter_input( INPUT_POST, '_stripeplannonce' ), 'stripe_product' ) ) {
			return;
		}
		// 商品IDが登録されていなければなにもしない。
		$product_id = $this->get_product_id( $post_id );
		if ( ! $product_id ) {
			return;
		}
		// プラン名が登録されていなければ何もしない
		$name = filter_input( INPUT_POST, 'plan-name' );
		if ( ! $name ) {
			return;
		}
		$price    = (int) filter_input( INPUT_POST, 'plan-price' );
		$interval = filter_input( INPUT_POST, 'plan-interval' );
		// 作成
		try {
			$result = Plan::create( [
				'currency' => 'JPY', // とりあえず日本円で固定
				'interval' => $interval,
				'product'  => $product_id,
				'amount'   => $price,
				'nickname' => $name,
			] );
		} catch ( \Exception $e ) {
			error_log( $e->getMessage() );
		}
	}
	
	/**
	 * メタボックスを表示
	 *
	 * @param \WP_Post $post
	 */
	public function do_meta_box( \WP_Post $post ) {
		wp_nonce_field( 'stripe_product', '_stripeplannonce', false );
		$plans = $this->get_plans( $post->ID );
		// プランリスト
		if ( $plans ) {
			?>
			<ul>
				<?php foreach ( $plans as $plan ) : ?>
					<li>
						<?php printf(
							'<strong>%s</strong> （%s %s / %s）',
							esc_html( $plan->nickname ),
							number_format( $plan->amount ),
							strtoupper( $plan->currency ),
							__( ucfirst( $plan->interval ) )
						) ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php
		} else {
			printf( '<p>%s</p>', esc_html__( 'No plan exists. Please create one.', 'membership' ) );
		}
		// 新規プラン作成フォーム
		?>
		<hr />
		<table class="form-table">
			<caption><?php esc_html_e( 'Create New Plan', 'message' ) ?></caption>
			<tr>
				<th><label for="plan-name"><?php esc_html_e( 'Plan Name', 'membership' ) ?></label></th>
				<td>
					<input class="regular-text" type="text" name="plan-name" id="plan-name" value="" placeholder="<?php esc_attr_e( 'Leave empty not to create new.', 'membership' ); ?>" />
				</td>
			</tr>
			<tr>
				<th><label for="plan-price"><?php esc_html_e( 'Price', 'membership' ) ?></label></th>
				<td>
					<input type="number" name="plan-price" id="plan-price" value="" placeholder="1000" />
				</td>
			</tr>
			<tr>
				<th><label for="plan-interval"><?php esc_html_e( 'Interval', 'membership' ) ?></label></th>
				<td>
					<select name="plan-interval" id="plan-interval">
						<?php foreach ( [ 'month', 'week', 'year', 'day' ] as $interval ) : ?>
							<option value="<?php echo esc_attr( $interval ) ?>"><?php esc_html_e( ucfirst( $interval ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		</table>
		<?php
	}
	
	/**
	 * 書品に紐づいたプランを取得する
	 *
	 * @param int $post_id
	 * @return Plan[]
	 */
	public function get_plans( $post_id ) {
		$product_id = $this->get_product_id( $post_id );
		if ( ! $product_id ) {
			return [];
		}
		try {
			return Plan::all( [
				'product' => $product_id,
			] )->data;
		} catch ( \Exception $e ) {
			error_log( $e->getMessage() );
			return [];
		}
	}

	
	/**
	 * Stripe 上での ID を取得
	 *
	 * @param int $post_id
	 *
	 * @return mixed
	 */
	public function get_product_id( $post_id ) {
		return get_post_meta( $post_id, '_stripe_product_id', true );
	}
}
