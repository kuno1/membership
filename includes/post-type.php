<?php
/**
 * Manage membership post types.
 *
 * @package membership
 */

// Create membership
add_action( 'init', function() {
	register_post_type( 'membership', [
		'label'     => __( 'Membership', 'membership' ),
		'public'    => false, // 投稿としては表示しない
		'show_ui'   => true, // 管理画面には表示する
		'menu_icon' => 'dashicons-money',
		'supports'  => [ 'title', 'excerpt' ],
	] );
} );
