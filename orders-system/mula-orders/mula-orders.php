<?php
/**
 * Plugin Name: Mula Orders
 * Description: הזמנת שירותים וחתימה דיגיטלית עבור Mula Digital. הזמנות מתבניות, קישורים אישיים, מעקב סטטוסים ונעילת מסמך.
 * Version: 0.2.0
 * Author: Mula Digital
 * Text Domain: mula-orders
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'MO_VERSION', '0.2.0' );
define( 'MO_DB_VERSION', '1' );
define( 'MO_DIR', plugin_dir_path( __FILE__ ) );
define( 'MO_URL', plugin_dir_url( __FILE__ ) );
define( 'MO_SITE', 'https://muladigital.co.il/' );

require_once MO_DIR . 'includes/class-store.php';
require_once MO_DIR . 'includes/class-public.php';
require_once MO_DIR . 'includes/class-admin.php';
require_once MO_DIR . 'includes/class-crm.php';

register_activation_hook( __FILE__, array( 'MO_Store', 'install' ) );
register_activation_hook( __FILE__, array( 'MO_Public', 'activate' ) );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

add_action( 'plugins_loaded', function () {
	if ( get_option( 'mo_db_version' ) !== MO_DB_VERSION ) {
		MO_Store::install();
	}
	MO_Public::init();
	if ( is_admin() ) {
		MO_Admin::init();
		MO_CRM::init();
	}
} );
