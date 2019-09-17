<?php
/**
 * コンテンツの表示をコントロール
 *
 * @package membership
 */

use Kunoichi\Membership\Models\Customers;
use Kunoichi\Membership\Models\Products;

add_filter( 'the_content', function( $content ) {
	if ( ! is_singular( 'post' ) ) {
		return $content;
	}
	if ( ! is_user_logged_in() ) {
		// ログインしていなかったらログインリンク
		return sprintf(
			'<p>%s</p><p style="text-align: center"><a class="button" href="%s">%s</a></p>',
			esc_html__( 'Please login to continue reading.', 'membership' ),
			wp_login_url( get_permalink( get_queried_object() ) ),
			__( 'Log in or Sign up', 'membership' )
		);
	}
	$products = get_posts( [
		'post_type'      => 'membership',
		'post_status'    => 'publish',
		'posts_per_page' => - 1,
	] );
	foreach ( $products as $product ) {
		if ( Customers::get_instance()->is_valid( get_current_user_id(), $product ) ) {
			continue;
		}
		ob_start();
		printf( '<p>%s</p>', esc_html__( 'To continue reading, please subscribe our membership plan!', 'membership' ) );
		$plans = Products::get_instance()->get_plans( $product->ID );
		?>
		<div class="buy">
			<h2><?php echo esc_html( get_the_title( $product ) ) ?></h2>
			<div style="display: flex">
				<?php foreach ( $plans as $plan ) : ?>
					<div style="text-align: center; padding: 10px; margin: 10px; border: 1px solid #ddd">
						<h3><?php echo esc_html( $plan->nickname ) ?></h3>
						<p><?php printf( '%s %s / %s', number_format( $plan->amount ), strtoupper( $plan->currency ), __( ucfirst( $plan->interval ) ) ) ?></p>
						<p>
							<button data-post-id="<?php echo $product->ID ?>"
									data-plan-id="<?php echo esc_attr( $plan->id ) ?>" class="button">
								<?php esc_html_e( 'Subscribe', 'membership' ); ?>
							</button>
						</p>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		// カード入力欄表示用プレイスホルダー
		echo '<div id="enter-card-info" style="background-color: #f9f9f9; padding: 20px;"></div>';
		// 文字列として取得し、改行コードと行頭を削除。
		// これはWordPressのコードフォーマット対策です。
		$content = implode( "\n", array_map( 'trim', explode( "\n", ob_get_contents() ) ) );
		ob_end_clean();
	}
	// REST リクエスト用のJSを読み込み。
	wp_enqueue_script( 'wp-api-request' );
	return $content;
} );


/**
 * JavaScript を書き出し
 */
add_action( 'wp_footer', function() {
	?>
<script src="https://js.stripe.com/v3/"></script>
<script>
jQuery( document ).ready( function( $ ) {
  var stripe = Stripe('<?php echo esc_js( STRIPE_PK ) ?>');
  var elements = stripe.elements();
  var card = elements.create( 'card' );
  card.mount( '#enter-card-info' );
  
  // ボタンクリック
  $( 'button[data-plan-id]' ).click( function( e ) {
    e.preventDefault();
	var plan = $( this ).attr( 'data-plan-id' );
    var post = $( this ).attr( 'data-post-id' );
    stripe.createToken( card ).then( function( result ) {
      if ( result.error ) {
        alert( result.error.message );
      } else {
        var token = result.token;
        wp.apiRequest( {
		  path: '/membership/v1/subscriptions/' + post,
		  data: {
		    plan_id: plan,
			token: result.token.id
		  },
		  method: 'POST'
		} ).done(function( res ){
  			window.location.reload();
		}).fail( function() {
          alert( '失敗しました。' );
		} );
      }
    });
  } );
} );
</script>
	<?php
}, 1000 );
