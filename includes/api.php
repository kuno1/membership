<?php
/**
 * Stripe API関連の処理
 *
 * @package membership
 */

// APIキーを登録
add_action( 'init', function () {
	if ( ! defined( 'STRIPE_SK' ) ) {
		return;
	}
	\Stripe\Stripe::setApiKey( STRIPE_SK );
} );

// シングルトン実装を全部登録
add_action( 'plugins_loaded', function() {
	foreach ( [ 'Rest', 'Models' ] as $dirname ) {
		$dir = dirname( __DIR__ ) . '/src/Kunoichi/Membership/' . $dirname;
		if ( ! is_dir( $dir ) ) {
			return;
		}
		foreach ( scandir( $dir ) as $file ) {
			if ( ! preg_match( '/^(.*)\.php$/u', $file, $match ) ) {
				continue;
			}
			// ファイル名からクラス名を作成。
			$class_name = "Kunoichi\\Membership\\{$dirname}\\{$match[1]}";
			if ( ! class_exists( $class_name ) ) {
				// 存在しなければスキップ。
				continue;
			}
			// クラスが存在したら、get_instance メソッドを呼び出し。
			$class_name::get_instance();
		}
	}
} );
