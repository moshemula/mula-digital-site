<?php
/**
 * Plugin Name: Mula OS
 * Description: מערכת ניהול לקוחות מאחורי הקלעים של Mula Digital: אנשי קשר, צנרת, תיבת הודעות מאוחדת, אוטומציות ויומן.
 * Version: 1.0.0
 * Author: Mula Digital
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'MOS_VERSION', '1.0.0' );
define( 'MOS_DB', '1' );
define( 'MOS_DIR', plugin_dir_path( __FILE__ ) );
define( 'MOS_URL', plugin_dir_url( __FILE__ ) );
define( 'MOS_TZ', 'Asia/Jerusalem' );

require_once MOS_DIR . 'includes/db.php';
require_once MOS_DIR . 'includes/events.php';
require_once MOS_DIR . 'includes/channels.php';
require_once MOS_DIR . 'includes/engine.php';
require_once MOS_DIR . 'includes/booking.php';
require_once MOS_DIR . 'includes/api.php';
require_once MOS_DIR . 'includes/admin.php';

register_activation_hook( __FILE__, array( 'MOS_DB', 'install' ) );
register_activation_hook( __FILE__, function () { MOS_Booking::rewrites(); flush_rewrite_rules(); } );
register_deactivation_hook( __FILE__, function () { wp_clear_scheduled_hook( 'mos_tick' ); flush_rewrite_rules(); } );

add_action( 'plugins_loaded', function () {
	if ( get_option( 'mos_db' ) !== MOS_DB ) { MOS_DB::install(); }
	MOS_Events::init();
	MOS_Channels::init();
	MOS_Engine::init();
	MOS_Booking::init();
	MOS_API::init();
	if ( is_admin() ) { MOS_Admin::init(); }
} );
