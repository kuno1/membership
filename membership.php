<?php
/**
 * Plugin Name: Membership
 * Plugin URI: https://github.com/kuno1/membership
 * Description: A WordPress plugin for membership.
 * Version: 1.0.0
 * Author: Kunoichi INC
 * Author URI: https://kunoichiwp.com
 * Text Domain: membership
 * Domain Path: /languages/
 *
 * @package membership
 */

// 直接読み込まれたら終了。
defined( 'ABSPATH' ) || exit;

// 初期化処理
add_action( 'plugin_loaded', function() {
	// 翻訳を登録
	load_plugin_textdomain( 'membership', false, dirname( __DIR__ ) . '/languages' );
	// Composerを読み込み
	require __DIR__ . '/vendor/autoload.php';
	// includes ディレクトリを全部読み込み
	$includes = __DIR__ . '/includes';
	if ( is_dir( $includes ) ) {
		foreach ( scandir( $includes ) as $file ) {
			// _や.で始まるファイルはスキップ
			if ( ! preg_match( '/^[^._].*\.php$/u', $file ) ) {
				continue;
			}
			require $includes . '/' . $file;
		}
	}
} );
