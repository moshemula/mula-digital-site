<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * הצד של הלקוח: הקישור האישי, עמוד ההזמנה ופעולות REST.
 */
class MO_Public {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'rewrites' ) );
		add_filter( 'query_vars', function ( $v ) { $v[] = 'mo_token'; $v[] = 'mo_preview'; return $v; } );
		add_action( 'template_redirect', array( __CLASS__, 'route' ), 0 );
		add_action( 'rest_api_init', array( __CLASS__, 'rest' ) );
		add_filter( 'xmlrpc_enabled', '__return_false' );
		add_filter( 'wp_sitemaps_enabled', '__return_false' );
		add_filter( 'rest_endpoints', array( __CLASS__, 'hide_users_endpoint' ) );
	}

	public static function activate() {
		self::rewrites();
		flush_rewrite_rules();
		update_option( 'blog_public', '0' );
	}

	public static function rewrites() {
		add_rewrite_rule( '^o/([A-Za-z0-9_-]{40,64})/?$', 'index.php?mo_token=$matches[1]', 'top' );
		add_rewrite_rule( '^o-preview/([0-9]+)/?$', 'index.php?mo_preview=$matches[1]', 'top' );
	}

	public static function hide_users_endpoint( $e ) {
		if ( ! is_user_logged_in() ) {
			unset( $e['/wp/v2/users'], $e['/wp/v2/users/(?P<id>[\d]+)'] );
		}
		return $e;
	}

	private static function private_headers() {
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
		header( 'Referrer-Policy: no-referrer', true );
		header( 'X-Content-Type-Options: nosniff', true );
		header( 'X-Frame-Options: SAMEORIGIN', true );
		header( 'Cache-Control: no-store, private', true );
	}

	public static function route() {
		$token   = get_query_var( 'mo_token' );
		$preview = get_query_var( 'mo_preview' );

		if ( $token ) {
			self::private_headers();
			$o = MO_Store::get_by_token( $token );
			if ( ! $o ) { self::render_gone( 'הקישור אינו פעיל', 'ייתכן שההזמנה עודכנה או בוטלה. לקבלת קישור מעודכן דברו עם משה.' ); }
			if ( 'expired' === $o->eff_status ) {
				MO_Store::log( $o->id, 'expired_link_visit', null, 'client' );
				self::render_gone( 'תוקף הקישור הסתיים', 'אפשר לבקש קישור חדש ממשה בטלפון או בוואטסאפ.' );
			}
			if ( in_array( $o->status, array( 'sent', 'opened', 'fix_requested' ), true ) ) {
				$first = ( 'sent' === $o->status );
				MO_Store::mark_opened( $o );
				if ( $first ) {
					self::notify( $o, 'ההזמנה ' . $o->number . ' נפתחה', 'הלקוח פתח את קישור ההזמנה.' );
				}
				$o = MO_Store::get( $o->id );
			}
			self::render( $o, 'client', $token );
		}

		if ( $preview ) {
			self::private_headers();
			if ( ! current_user_can( 'manage_options' ) || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'mo_preview_' . (int) $preview ) ) {
				self::render_gone( 'אין גישה', 'התצוגה זמינה רק למנהל המחובר.' );
			}
			$o = MO_Store::get( (int) $preview );
			if ( ! $o ) { self::render_gone( 'לא נמצא', '' ); }
			self::render( $o, 'preview', '' );
		}

		// כל שאר עמודי האתר הציבוריים מופנים לאתר הראשי
		if ( ! is_admin() && ! wp_doing_ajax() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) && ! is_user_logged_in() ) {
			wp_redirect( MO_SITE, 302 );
			exit;
		}
	}

	private static function render( $o, $mode, $token ) {
		$doc      = ( 'preview' !== $mode && $o->doc_json ) ? json_decode( $o->doc_json, true ) : MO_Store::document( $o );
		$missing  = array();
		foreach ( $doc['terms'] as $t ) { if ( '' === trim( $t['body'] ) ) { $missing[] = $t['key']; } }
		$provider = get_option( 'mo_provider', '' );
		$rest     = esc_url_raw( rest_url( 'mo/v1/' ) );
		status_header( 200 );
		include MO_DIR . 'templates/order-page.php';
		exit;
	}

	private static function render_gone( $title, $text ) {
		status_header( 410 );
		$gone = array( 'title' => $title, 'text' => $text );
		include MO_DIR . 'templates/gone.php';
		exit;
	}

	/* ---------------- REST ---------------- */

	public static function rest() {
		$args = array( 'methods' => 'POST', 'permission_callback' => '__return_true' );
		register_rest_route( 'mo/v1', '/client', $args + array( 'callback' => array( __CLASS__, 'api_client' ) ) );
		register_rest_route( 'mo/v1', '/fix', $args + array( 'callback' => array( __CLASS__, 'api_fix' ) ) );
		register_rest_route( 'mo/v1', '/sign', $args + array( 'callback' => array( __CLASS__, 'api_sign' ) ) );
	}

	private static function limited( $bucket, $max = 20 ) {
		$k = 'mo_rl_' . substr( MO_Store::ip_hash(), 0, 24 ) . '_' . $bucket;
		$n = (int) get_transient( $k );
		if ( $n >= $max ) { return true; }
		set_transient( $k, $n + 1, 10 * MINUTE_IN_SECONDS );
		return false;
	}

	/** מאתר הזמנה פתוחה לפי טוקן או מחזיר שגיאה */
	private static function open_order( $req ) {
		$o = MO_Store::get_by_token( (string) $req->get_param( 'token' ) );
		if ( ! $o ) { return new WP_Error( 'mo_link', 'הקישור אינו פעיל.', array( 'status' => 404 ) ); }
		if ( 'expired' === $o->eff_status ) { return new WP_Error( 'mo_expired', 'תוקף הקישור הסתיים.', array( 'status' => 410 ) ); }
		if ( ! in_array( $o->status, MO_Store::OPEN_STATUSES, true ) ) { return new WP_Error( 'mo_state', 'ההזמנה אינה פתוחה לפעולה.', array( 'status' => 409 ) ); }
		return $o;
	}

	public static function validate_client( $p ) {
		$s   = function ( $k ) use ( $p ) { return isset( $p[ $k ] ) ? trim( sanitize_text_field( wp_unslash( $p[ $k ] ) ) ) : ''; };
		$c   = array(
			'ctype'  => 'company' === $s( 'ctype' ) ? 'company' : 'osek',
			'entity' => $s( 'entity' ),
			'reg'    => preg_replace( '/\D/', '', $s( 'reg' ) ),
			'signer' => $s( 'signer' ),
			'role'   => $s( 'role' ),
			'phone'  => preg_replace( '/[^\d+\-]/', '', $s( 'phone' ) ),
			'email'  => sanitize_email( $s( 'email' ) ),
			'addr'   => $s( 'addr' ),
		);
		$err = array();
		if ( mb_strlen( $c['entity'] ) < 2 ) { $err['entity'] = 1; }
		if ( ! preg_match( '/^\d{9}$/', $c['reg'] ) ) { $err['reg'] = 1; }
		if ( mb_strlen( $c['signer'] ) < 2 ) { $err['signer'] = 1; }
		if ( 'company' === $c['ctype'] && mb_strlen( $c['role'] ) < 2 ) { $err['role'] = 1; }
		if ( ! preg_match( '/^0\d{1,2}-?\d{7}$/', $c['phone'] ) ) { $err['phone'] = 1; }
		if ( ! is_email( $c['email'] ) ) { $err['email'] = 1; }
		if ( mb_strlen( $c['addr'] ) < 4 ) { $err['addr'] = 1; }
		if ( 'company' !== $c['ctype'] ) { $c['role'] = ''; }
		foreach ( $c as $k => $v ) { $c[ $k ] = mb_substr( $v, 0, 200 ); }
		return array( $c, $err );
	}

	public static function api_client( WP_REST_Request $req ) {
		if ( self::limited( 'client' ) ) { return new WP_Error( 'mo_rate', 'יותר מדי ניסיונות. נסו שוב בעוד כמה דקות.', array( 'status' => 429 ) ); }
		$o = self::open_order( $req );
		if ( is_wp_error( $o ) ) { return $o; }
		list( $c, $err ) = self::validate_client( $req->get_params() );
		if ( $err ) { return new WP_REST_Response( array( 'ok' => false, 'errors' => $err ), 422 ); }
		MO_Store::save_client( $o, $c );
		return array( 'ok' => true, 'entity' => $c['entity'] );
	}

	public static function api_fix( WP_REST_Request $req ) {
		if ( self::limited( 'fix', 5 ) ) { return new WP_Error( 'mo_rate', 'יותר מדי בקשות. נסו שוב מאוחר יותר.', array( 'status' => 429 ) ); }
		$o = self::open_order( $req );
		if ( is_wp_error( $o ) ) { return $o; }
		$text = trim( sanitize_textarea_field( wp_unslash( (string) $req->get_param( 'text' ) ) ) );
		if ( mb_strlen( $text ) < 3 ) { return new WP_REST_Response( array( 'ok' => false, 'error' => 'כתבו מה לתקן.' ), 422 ); }
		$text = mb_substr( $text, 0, 2000 );
		MO_Store::set_status( $o->id, 'fix_requested', 'fix_requested', array( 'text' => $text ), 'client' );
		self::notify( $o, 'בקשת תיקון בהזמנה ' . $o->number, "הלקוח ביקש תיקון:\n\n" . $text );
		return array( 'ok' => true );
	}

	/**
	 * חתימה. בודק בצד השרת שכל התנאים מתקיימים.
	 * כל עוד לא הוגדר ספק חתימה, לא נוצר תהליך חתימה.
	 */
	public static function api_sign( WP_REST_Request $req ) {
		if ( self::limited( 'sign', 10 ) ) { return new WP_Error( 'mo_rate', 'יותר מדי ניסיונות.', array( 'status' => 429 ) ); }
		$o = self::open_order( $req );
		if ( is_wp_error( $o ) ) { return $o; }
		$doc = json_decode( $o->doc_json, true );
		if ( ! $doc || hash( 'sha256', $o->doc_json ) !== $o->doc_hash ) {
			MO_Store::log( $o->id, 'integrity_fail', null, 'system' );
			return new WP_Error( 'mo_integrity', 'שגיאת שלמות מסמך. דברו עם משה.', array( 'status' => 500 ) );
		}
		foreach ( $doc['terms'] as $t ) {
			if ( '' === trim( $t['body'] ) ) { return new WP_Error( 'mo_terms', 'יש תנאים שעדיין לא הושלמו.', array( 'status' => 409 ) ); }
		}
		list( , $err ) = self::validate_client( $o->client );
		if ( $err ) { return new WP_Error( 'mo_client', 'פרטי הלקוח לא הושלמו.', array( 'status' => 409 ) ); }
		if ( ! $req->get_param( 'c1' ) || ! $req->get_param( 'c2' ) ) { return new WP_Error( 'mo_consent', 'צריך לסמן את שתי ההסכמות.', array( 'status' => 409 ) ); }
		if ( ! get_option( 'mo_provider' ) ) {
			MO_Store::log( $o->id, 'sign_attempt_no_provider', null, 'client' );
			return new WP_Error( 'mo_provider', 'החתימה תיפתח בקרוב. ספק החתימה עדיין לא חובר.', array( 'status' => 503 ) );
		}
		// כאן יחובר ספק החתימה שייבחר: יצירת תהליך חד־פעמי (idempotent לפי doc_hash) והחזרת כתובת הטמעה.
		return new WP_Error( 'mo_provider', 'ספק החתימה אינו זמין כרגע.', array( 'status' => 503 ) );
	}

	public static function notify( $o, $subject, $body ) {
		$to = get_option( 'mo_notify_email', get_option( 'admin_email' ) );
		$link = admin_url( 'admin.php?page=mo-orders&action=edit&id=' . $o->id );
		wp_mail( $to, '[הזמנות] ' . $subject, $body . "\n\nלקוח: " . $o->client_label . "\nבממשק: " . $link );
	}
}
