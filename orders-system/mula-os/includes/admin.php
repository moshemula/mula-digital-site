<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * מעטפת האפליקציה בתוך וורדפרס: עמוד אחד במסך מלא, וכל השאר נטען מה־API.
 */
class MOS_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'admin_body_class', function ( $c ) { return ( isset( $_GET['page'] ) && 'mula-os' === $_GET['page'] ) ? $c . ' mos-full' : $c; } );
		add_action( 'admin_init', function () {
			// כניסה לממשק הניהול מפנה ישר ל־Mula OS
			global $pagenow;
			if ( 'index.php' === $pagenow && empty( $_GET['page'] ) && current_user_can( 'manage_options' ) && ! wp_doing_ajax() ) {
				wp_safe_redirect( admin_url( 'admin.php?page=mula-os' ) );
				exit;
			}
		} );
	}

	public static function menu() {
		add_menu_page( 'Mula OS', 'Mula OS', 'manage_options', 'mula-os', array( __CLASS__, 'page' ), 'dashicons-superhero', 2 );
	}

	public static function assets( $hook ) {
		if ( 'toplevel_page_mula-os' !== $hook ) { return; }
		wp_enqueue_style( 'mos-fonts', 'https://fonts.googleapis.com/css2?family=Assistant:wght@400;500;600;700&family=Heebo:wght@500;600;700;800;900&display=swap', array(), null );
		wp_enqueue_style( 'mos-app', MOS_URL . 'assets/app.css', array(), MOS_VERSION );
		wp_enqueue_script( 'mos-app', MOS_URL . 'assets/app.js', array(), MOS_VERSION, true );
		wp_localize_script( 'mos-app', 'MOS', array(
			'api'    => esc_url_raw( rest_url( 'mos/v1/' ) ),
			'nonce'  => wp_create_nonce( 'wp_rest' ),
			'logo'   => 'https://muladigital.co.il/assets/logo-light.webp',
			'wpadmin'=> admin_url(),
			'orders' => admin_url( 'admin.php?page=mo-crm' ),
			'newOrder' => admin_url( 'admin.php?page=mo-new' ),
		) );
	}

	public static function page() {
		echo '<div id="mos-app" dir="rtl"><div class="mos-boot"><div class="mos-boot-mark"></div></div></div>';
	}
}
