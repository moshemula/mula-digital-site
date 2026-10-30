<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * יומן וקביעת פגישות: עמוד ציבורי ממותג /book/{slug}, זמינות שבועית, מניעת התנגשויות,
 * אישור במייל עם קובץ יומן, וקישור ביטול אישי.
 */
class MOS_Booking {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'rewrites' ) );
		add_filter( 'query_vars', function ( $v ) { $v[] = 'mos_book'; $v[] = 'mos_appt'; return $v; } );
		add_action( 'template_redirect', array( __CLASS__, 'route' ), -1 );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function rewrites() {
		add_rewrite_rule( '^book/manage/([a-f0-9]{32})/?$', 'index.php?mos_appt=$matches[1]', 'top' );
		add_rewrite_rule( '^book/([a-z0-9-]+)/?$', 'index.php?mos_book=$matches[1]', 'top' );
	}

	public static function calendar_by_slug( $slug ) {
		global $wpdb;
		$c = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . MOS_DB::t( 'calendars' ) . ' WHERE slug=%s AND active=1', $slug ) );
		if ( $c ) { $c->availability = json_decode( $c->availability, true ) ?: array(); }
		return $c;
	}

	private static function tz() { return new DateTimeZone( MOS_TZ ); }

	/** כל החלונות הפנויים בטווח, מקובצים לפי תאריך מקומי */
	public static function slots( $cal ) {
		global $wpdb;
		$tz    = self::tz();
		$now   = new DateTime( 'now', $tz );
		$min   = ( clone $now )->modify( '+' . (int) $cal->notice_hours . ' hours' );
		$step  = max( 15, (int) $cal->duration );
		$dur   = (int) $cal->duration;
		$buf   = (int) $cal->buffer;
		$end_r = ( clone $now )->setTime( 0, 0 )->modify( '+' . ( (int) $cal->days_ahead + 1 ) . ' days' );
		$busy  = $wpdb->get_results( $wpdb->prepare( 'SELECT start_at, end_at FROM ' . MOS_DB::t( 'appointments' ) . " WHERE calendar_id=%d AND status='booked' AND end_at >= %s", $cal->id, gmdate( 'Y-m-d H:i:s' ) ) );
		$busy_ts = array_map( function ( $b ) use ( $buf ) { return array( strtotime( $b->start_at . ' UTC' ) - $buf * 60, strtotime( $b->end_at . ' UTC' ) + $buf * 60 ); }, $busy );
		$out = array();
		for ( $d = ( clone $now )->setTime( 0, 0 ); $d < $end_r; $d->modify( '+1 day' ) ) {
			$wd = (int) $d->format( 'w' );
			if ( empty( $cal->availability[ $wd ] ) ) { continue; }
			foreach ( $cal->availability[ $wd ] as $win ) {
				list( $fh, $fm ) = array_map( 'intval', explode( ':', $win[0] ) );
				list( $th, $tm ) = array_map( 'intval', explode( ':', $win[1] ) );
				$t   = ( clone $d )->setTime( $fh, $fm );
				$lim = ( clone $d )->setTime( $th, $tm );
				while ( true ) {
					$e = ( clone $t )->modify( '+' . $dur . ' minutes' );
					if ( $e > $lim ) { break; }
					if ( $t >= $min ) {
						$s = $t->getTimestamp(); $x = $e->getTimestamp(); $free = true;
						foreach ( $busy_ts as $b ) { if ( $s < $b[1] && $x > $b[0] ) { $free = false; break; } }
						if ( $free ) { $out[ $d->format( 'Y-m-d' ) ][] = array( 'start' => gmdate( 'Y-m-d\TH:i:s\Z', $s ), 'label' => $t->format( 'H:i' ) ); }
					}
					$t->modify( '+' . $step . ' minutes' );
				}
			}
		}
		return $out;
	}

	public static function routes() {
		register_rest_route( 'mos/v1', '/book/(?P<slug>[a-z0-9-]+)/slots', array( 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => function ( $r ) {
			$cal = self::calendar_by_slug( $r['slug'] );
			return $cal ? array( 'slots' => self::slots( $cal ) ) : new WP_Error( 'nf', 'לא נמצא', array( 'status' => 404 ) );
		} ) );
		register_rest_route( 'mos/v1', '/book/(?P<slug>[a-z0-9-]+)', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'book' ) ) );
		register_rest_route( 'mos/v1', '/book/cancel/(?P<token>[a-f0-9]{32})', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'cancel_public' ) ) );
	}

	public static function book( WP_REST_Request $r ) {
		global $wpdb;
		$k = 'mos_bk_' . md5( isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '' );
		$n = (int) get_transient( $k );
		if ( $n > 8 ) { return new WP_Error( 'rate', 'יותר מדי ניסיונות. נסו שוב מאוחר יותר.', array( 'status' => 429 ) ); }
		set_transient( $k, $n + 1, HOUR_IN_SECONDS );

		$cal = self::calendar_by_slug( $r['slug'] );
		if ( ! $cal ) { return new WP_Error( 'nf', 'היומן לא נמצא.', array( 'status' => 404 ) ); }
		$p = $r->get_json_params();
		if ( ! empty( $p['website'] ) ) { return array( 'ok' => true ); }
		$name  = trim( sanitize_text_field( $p['name'] ?? '' ) );
		$phone = MOS_DB::phone( $p['phone'] ?? '' );
		$email = sanitize_email( $p['email'] ?? '' );
		if ( mb_strlen( $name ) < 2 || strlen( $phone ) < 11 ) { return new WP_Error( 'data', 'צריך שם וטלפון תקין.', array( 'status' => 422 ) ); }
		$start = strtotime( (string) ( $p['start'] ?? '' ) );
		if ( ! $start ) { return new WP_Error( 'data', 'בחרו שעה.', array( 'status' => 422 ) ); }

		// החלון עדיין פנוי? (נבדק מחדש בשרת)
		$valid = false;
		foreach ( self::slots( $cal ) as $day ) { foreach ( $day as $s ) { if ( strtotime( $s['start'] ) === $start ) { $valid = true; break 2; } } }
		if ( ! $valid ) { return new WP_Error( 'taken', 'השעה הזו כבר לא פנויה. בחרו שעה אחרת.', array( 'status' => 409 ) ); }

		list( $cid ) = MOS_DB::upsert_contact( array( 'name' => $name, 'phone' => $phone, 'email' => $email ), 'קביעת פגישה' );
		$token = bin2hex( random_bytes( 16 ) );
		$wpdb->insert( MOS_DB::t( 'appointments' ), array(
			'account_id' => MOS_DB::account(), 'calendar_id' => $cal->id, 'contact_id' => $cid,
			'start_at' => gmdate( 'Y-m-d H:i:s', $start ), 'end_at' => gmdate( 'Y-m-d H:i:s', $start + 60 * (int) $cal->duration ),
			'notes' => mb_substr( sanitize_textarea_field( $p['note'] ?? '' ), 0, 1000 ), 'token' => $token, 'created_at' => MOS_DB::now(),
		) );
		$aid  = (int) $wpdb->insert_id;
		$when = self::local( $start );
		MOS_DB::activity( $cid, 'appointment_booked', array( 'calendar' => $cal->name, 'when' => $when, 'appointment_id' => $aid ) );
		MOS_DB::add_tag( $cid, 'קבע פגישה' );
		self::confirmation( $cal, $aid, $cid, $start, $token );
		MOS_Events::emit( 'appointment_booked', array( 'contact_id' => $cid, 'appointment_id' => $aid, 'calendar_id' => (int) $cal->id ) );
		return array( 'ok' => true, 'when' => $when, 'manage' => home_url( '/book/manage/' . $token ) );
	}

	public static function local( $ts, $fmt = 'EEEE, d בMMMM · HH:mm' ) {
		$days   = array( 'ראשון', 'שני', 'שלישי', 'רביעי', 'חמישי', 'שישי', 'שבת' );
		$months = array( 'ינואר', 'פברואר', 'מרץ', 'אפריל', 'מאי', 'יוני', 'יולי', 'אוגוסט', 'ספטמבר', 'אוקטובר', 'נובמבר', 'דצמבר' );
		$d = new DateTime( '@' . $ts ); $d->setTimezone( self::tz() );
		return 'יום ' . $days[ (int) $d->format( 'w' ) ] . ', ' . $d->format( 'j' ) . ' ב' . $months[ (int) $d->format( 'n' ) - 1 ] . ' · ' . $d->format( 'H:i' );
	}

	private static function ics( $cal, $start, $uid ) {
		$f = function ( $t ) { return gmdate( 'Ymd\THis\Z', $t ); };
		return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Mula Digital//Mula OS//HE\r\nMETHOD:REQUEST\r\nBEGIN:VEVENT\r\nUID:" . $uid . "@muladigital.co.il\r\nDTSTAMP:" . $f( time() ) . "\r\nDTSTART:" . $f( $start ) . "\r\nDTEND:" . $f( $start + 60 * (int) $cal->duration ) .
			"\r\nSUMMARY:" . $cal->name . ' · Mula Digital' . "\r\nLOCATION:" . $cal->location . "\r\nDESCRIPTION:משה מולה · 050-824-9824\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
	}

	private static function confirmation( $cal, $aid, $cid, $start, $token ) {
		$c    = MOS_DB::contact( $cid );
		$when = self::local( $start );
		$file = wp_tempnam( 'mula-meeting' ) . '.ics';
		file_put_contents( $file, self::ics( $cal, $start, 'appt-' . $aid ) );
		if ( $c && is_email( $c->email ) ) {
			$body = "היי {{first_name}},\n\nקבענו " . $cal->name . ".\n" . $when . "\n" . $cal->location . "\n\nצריך לשנות או לבטל? אפשר כאן:\n" . home_url( '/book/manage/' . $token ) . "\n\nנתראה,";
			$html = '<div dir="rtl" style="font-family:Arial,sans-serif;font-size:16px;line-height:1.7;text-align:right">' . nl2br( esc_html( MOS_Channels::merge( $body, $c ) ) ) . '<br><strong>משה מולה</strong><br>Mula Digital · 050-824-9824</div>';
			wp_mail( $c->email, 'נקבע: ' . $cal->name . ' · ' . $when, $html, array( 'Content-Type: text/html; charset=UTF-8' ), array( $file ) );
			MOS_Channels::record( $cid, 'out', 'email', MOS_Channels::merge( $body, $c ), array( 'subject' => 'אישור פגישה' ) );
		}
		wp_mail( MOS_DB::setting( 'notify_email', get_option( 'admin_email' ) ), '[Mula OS] פגישה חדשה: ' . ( $c ? MOS_DB::name( $c ) : '' ) . ' · ' . $when,
			'נקבעה ' . $cal->name . "\n" . $when . "\n" . ( $c ? MOS_DB::name( $c ) . ' · ' . MOS_DB::phone_local( $c->phone ) : '' ) . "\n\n" . admin_url( 'admin.php?page=mula-os#/calendar' ), array(), array( $file ) );
		@unlink( $file );
	}

	public static function cancel_public( WP_REST_Request $r ) {
		global $wpdb;
		$ap = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . MOS_DB::t( 'appointments' ) . " WHERE token=%s AND status='booked'", $r['token'] ) );
		if ( ! $ap ) { return new WP_Error( 'nf', 'הפגישה לא נמצאה או שכבר בוטלה.', array( 'status' => 404 ) ); }
		self::cancel( $ap, 'client' );
		return array( 'ok' => true );
	}

	public static function cancel( $ap, $by = 'admin' ) {
		global $wpdb;
		$wpdb->update( MOS_DB::t( 'appointments' ), array( 'status' => 'cancelled' ), array( 'id' => $ap->id ) );
		MOS_DB::activity( $ap->contact_id, 'appointment_cancelled', array( 'by' => $by, 'when' => self::local( strtotime( $ap->start_at . ' UTC' ) ) ) );
		MOS_Events::emit( 'appointment_cancelled', array( 'contact_id' => (int) $ap->contact_id, 'appointment_id' => (int) $ap->id, 'calendar_id' => (int) $ap->calendar_id ) );
		if ( 'client' === $by ) {
			wp_mail( MOS_DB::setting( 'notify_email', get_option( 'admin_email' ) ), '[Mula OS] פגישה בוטלה', 'הלקוח ביטל את הפגישה ב־' . self::local( strtotime( $ap->start_at . ' UTC' ) ) );
		}
	}

	/* ---------------- עמודים ציבוריים ---------------- */

	public static function route() {
		$slug  = get_query_var( 'mos_book' );
		$token = get_query_var( 'mos_appt' );
		if ( ! $slug && ! $token ) { return; }
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		global $wpdb;
		$cal = null; $ap = null;
		if ( $token ) {
			$ap = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . MOS_DB::t( 'appointments' ) . ' WHERE token=%s', $token ) );
			if ( $ap ) { $cal = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . MOS_DB::t( 'calendars' ) . ' WHERE id=%d', $ap->calendar_id ) ); }
		} else {
			$cal = self::calendar_by_slug( $slug );
		}
		status_header( $cal ? 200 : 404 );
		include MOS_DIR . 'templates/booking.php';
		exit;
	}

	public static function shell( $part ) {
		return class_exists( 'MO_Public' ) ? MO_Public::shell( $part ) : '';
	}
}
