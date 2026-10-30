<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * ערוצים: מייל, וואטסאפ (WhatsApp Cloud API של Meta) וטפסים.
 * כל הודעה, נכנסת או יוצאת, נשמרת בשיחה אחת לכל איש קשר.
 */
class MOS_Channels {

	const GRAPH = 'https://graph.facebook.com/v21.0/';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route( 'mos/v1', '/hook/form', array(
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'form_hook' ), 'permission_callback' => '__return_true' ),
			array( 'methods' => 'OPTIONS', 'callback' => function () { return new WP_REST_Response( null, 204 ); }, 'permission_callback' => '__return_true' ),
		) );
		register_rest_route( 'mos/v1', '/hook/whatsapp', array(
			array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'wa_verify' ), 'permission_callback' => '__return_true' ),
			array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'wa_hook' ), 'permission_callback' => '__return_true' ),
		) );
		add_filter( 'rest_pre_serve_request', array( __CLASS__, 'cors' ), 10, 4 );
	}

	/** טפסים מהאתר הראשי בלבד */
	public static function cors( $served, $result, $request, $server ) {
		if ( 0 === strpos( $request->get_route(), '/mos/v1/hook/form' ) ) {
			$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? $_SERVER['HTTP_ORIGIN'] : '';
			if ( preg_match( '#^https://(www\.)?muladigital\.co\.il$#', $origin ) ) {
				header( 'Access-Control-Allow-Origin: ' . $origin );
				header( 'Access-Control-Allow-Methods: POST, OPTIONS' );
				header( 'Access-Control-Allow-Headers: Content-Type' );
			}
		}
		return $served;
	}

	/* ---------------- שיחות והודעות ---------------- */

	public static function conversation( $contact_id ) {
		global $wpdb;
		$t  = MOS_DB::t( 'conversations' );
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE account_id=%d AND contact_id=%d", MOS_DB::account(), $contact_id ) );
		if ( ! $id ) {
			$wpdb->insert( $t, array( 'account_id' => MOS_DB::account(), 'contact_id' => (int) $contact_id ) );
			$id = (int) $wpdb->insert_id;
		}
		return $id;
	}

	public static function record( $contact_id, $direction, $channel, $body, $extra = array() ) {
		global $wpdb;
		$conv = self::conversation( $contact_id );
		$now  = MOS_DB::now();
		$wpdb->insert( MOS_DB::t( 'messages' ), array(
			'conversation_id' => $conv, 'contact_id' => (int) $contact_id, 'direction' => $direction, 'channel' => $channel,
			'subject' => isset( $extra['subject'] ) ? mb_substr( $extra['subject'], 0, 250 ) : '', 'body' => $body,
			'status' => isset( $extra['status'] ) ? $extra['status'] : ( 'in' === $direction ? 'received' : 'sent' ),
			'external_id' => isset( $extra['external_id'] ) ? $extra['external_id'] : null,
			'meta' => isset( $extra['meta'] ) ? wp_json_encode( $extra['meta'], JSON_UNESCAPED_UNICODE ) : null, 'created_at' => $now,
		) );
		$mid = (int) $wpdb->insert_id;
		$wpdb->query( $wpdb->prepare(
			'UPDATE ' . MOS_DB::t( 'conversations' ) . ' SET last_channel=%s, last_message_at=%s, last_preview=%s, status=%s, unread=unread+%d WHERE id=%d',
			$channel, $now, mb_substr( wp_strip_all_tags( $body ), 0, 200 ), 'open', 'in' === $direction ? 1 : 0, $conv
		) );
		MOS_DB::activity( $contact_id, 'in' === $direction ? 'message_in' : 'message_out', array( 'channel' => $channel, 'preview' => mb_substr( wp_strip_all_tags( $body ), 0, 120 ) ) );
		return $mid;
	}

	/** שליחה בערוץ. מחזיר true או WP_Error */
	public static function send( $contact_id, $channel, $body, $subject = '', $opts = array() ) {
		$c = MOS_DB::contact( $contact_id );
		if ( ! $c ) { return new WP_Error( 'mos_contact', 'איש הקשר לא נמצא.' ); }
		if ( $c->dnd && empty( $opts['force'] ) ) { return new WP_Error( 'mos_dnd', 'איש הקשר ביקש לא לקבל הודעות.' ); }
		$body = self::merge( $body, $c );
		$subject = self::merge( $subject, $c );
		if ( 'email' === $channel ) { return self::send_email( $c, $subject, $body ); }
		if ( 'whatsapp' === $channel ) { return self::send_whatsapp( $c, $body, $opts ); }
		return new WP_Error( 'mos_channel', 'ערוץ לא נתמך.' );
	}

	/** שדות דינמיים: {{first_name}}, {{name}}, {{company}}, {{booking_link}} */
	public static function merge( $text, $c ) {
		$map = array(
			'{{first_name}}'   => $c->first_name ? $c->first_name : MOS_DB::name( $c ),
			'{{name}}'         => MOS_DB::name( $c ),
			'{{company}}'      => $c->company,
			'{{booking_link}}' => home_url( '/book/avchun' ),
		);
		return strtr( (string) $text, $map );
	}

	private static function send_email( $c, $subject, $body ) {
		if ( ! is_email( $c->email ) ) { return new WP_Error( 'mos_email', 'אין לאיש הקשר כתובת מייל תקינה.' ); }
		$from  = MOS_DB::setting( 'from_name', 'Mula Digital' );
		$html  = '<div dir="rtl" style="font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.7;color:#111;text-align:right">' . nl2br( esc_html( $body ) );
		$html .= '<br><br><div style="border-top:1px solid #eee;padding-top:12px;font-size:14px;color:#444"><strong>משה מולה</strong><br>Mula Digital<br>050-824-9824 · <a href="https://muladigital.co.il">muladigital.co.il</a></div></div>';
		$headers = array( 'Content-Type: text/html; charset=UTF-8', 'From: ' . $from . ' <' . MOS_DB::setting( 'from_email', get_option( 'admin_email' ) ) . '>' );
		if ( $reply = MOS_DB::setting( 'reply_to' ) ) { $headers[] = 'Reply-To: ' . $reply; }
		$ok = wp_mail( $c->email, $subject ? $subject : 'הודעה מ־Mula Digital', $html, $headers );
		self::record( $c->id, 'out', 'email', $body, array( 'subject' => $subject, 'status' => $ok ? 'sent' : 'failed' ) );
		return $ok ? true : new WP_Error( 'mos_mail', 'שליחת המייל נכשלה בשרת.' );
	}

	private static function send_whatsapp( $c, $body, $opts ) {
		$pid   = MOS_DB::setting( 'wa_phone_id' );
		$token = MOS_DB::setting( 'wa_token' );
		if ( ! $pid || ! $token ) { return new WP_Error( 'mos_wa', 'וואטסאפ עדיין לא מחובר. מחברים בהגדרות.' ); }
		if ( ! $c->phone ) { return new WP_Error( 'mos_wa', 'אין לאיש הקשר מספר טלפון.' ); }
		$to = ltrim( $c->phone, '+' );
		if ( ! empty( $opts['template'] ) ) {
			$payload = array( 'messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'template',
				'template' => array( 'name' => $opts['template'], 'language' => array( 'code' => isset( $opts['lang'] ) ? $opts['lang'] : 'he' ) ) );
			if ( ! empty( $opts['params'] ) ) {
				$payload['template']['components'] = array( array( 'type' => 'body', 'parameters' => array_map( function ( $p ) use ( $c ) { return array( 'type' => 'text', 'text' => MOS_Channels::merge( $p, $c ) ); }, (array) $opts['params'] ) ) );
			}
			$shown = '[תבנית: ' . $opts['template'] . '] ' . $body;
		} else {
			$payload = array( 'messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'text', 'text' => array( 'body' => $body, 'preview_url' => true ) );
			$shown   = $body;
		}
		$r = wp_remote_post( self::GRAPH . rawurlencode( $pid ) . '/messages', array(
			'timeout' => 15, 'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( $payload ),
		) );
		$code = is_wp_error( $r ) ? 0 : wp_remote_retrieve_response_code( $r );
		$res  = is_wp_error( $r ) ? array() : json_decode( wp_remote_retrieve_body( $r ), true );
		$ok   = $code >= 200 && $code < 300;
		$err  = $ok ? '' : ( isset( $res['error']['message'] ) ? $res['error']['message'] : ( is_wp_error( $r ) ? $r->get_error_message() : 'שגיאה ' . $code ) );
		self::record( $c->id, 'out', 'whatsapp', $shown, array( 'status' => $ok ? 'sent' : 'failed', 'external_id' => isset( $res['messages'][0]['id'] ) ? $res['messages'][0]['id'] : null, 'meta' => $ok ? null : array( 'error' => $err ) ) );
		if ( ! $ok && false !== strpos( $err, '24' ) ) { $err .= ' (מחוץ לחלון 24 השעות צריך לשלוח תבנית מאושרת)'; }
		return $ok ? true : new WP_Error( 'mos_wa', 'וואטסאפ: ' . $err );
	}

	/* ---------------- נכנס ---------------- */

	public static function form_hook( WP_REST_Request $req ) {
		$p = $req->get_json_params();
		if ( ! $p ) { $p = $req->get_body_params(); }
		if ( empty( $p['key'] ) || ! hash_equals( (string) MOS_DB::setting( 'form_key' ), (string) $p['key'] ) ) {
			return new WP_Error( 'mos_key', 'forbidden', array( 'status' => 403 ) );
		}
		if ( ! empty( $p['website'] ) ) { return array( 'ok' => true ); } // מלכודת בוטים
		if ( empty( $p['email'] ) && empty( $p['phone'] ) ) { return new WP_Error( 'mos_data', 'חסר טלפון או מייל.', array( 'status' => 422 ) ); }
		$source = ! empty( $p['source'] ) ? mb_substr( sanitize_text_field( $p['source'] ), 0, 80 ) : 'טופס באתר';
		list( $cid, $new ) = MOS_DB::upsert_contact( $p, $source );
		$lines = array();
		foreach ( $p as $k => $v ) {
			if ( in_array( $k, array( 'key', 'website' ), true ) || is_array( $v ) ) { continue; }
			$lines[] = sanitize_key( $k ) . ': ' . mb_substr( sanitize_textarea_field( $v ), 0, 2000 );
		}
		self::record( $cid, 'in', 'form', implode( "\n", $lines ), array( 'subject' => $source ) );
		if ( ! empty( $p['tags'] ) ) { foreach ( (array) explode( ',', $p['tags'] ) as $t ) { MOS_DB::add_tag( $cid, $t ); } }
		MOS_Events::emit( 'form_submitted', array( 'contact_id' => $cid, 'source' => $source, 'new' => $new ) );
		MOS_Events::emit( 'message_received', array( 'contact_id' => $cid, 'channel' => 'form' ) );
		return array( 'ok' => true );
	}

	public static function wa_verify( WP_REST_Request $req ) {
		$mode  = $req->get_param( 'hub_mode' ) ?: $req->get_param( 'hub.mode' );
		$token = $req->get_param( 'hub_verify_token' ) ?: $req->get_param( 'hub.verify_token' );
		if ( 'subscribe' === $mode && hash_equals( (string) MOS_DB::setting( 'wa_verify' ), (string) $token ) ) {
			header( 'Content-Type: text/plain' );
			echo esc_html( $req->get_param( 'hub_challenge' ) ?: $req->get_param( 'hub.challenge' ) );
			exit;
		}
		return new WP_Error( 'mos_verify', 'forbidden', array( 'status' => 403 ) );
	}

	public static function wa_hook( WP_REST_Request $req ) {
		$secret = MOS_DB::setting( 'wa_app_secret' );
		if ( $secret ) {
			$sig = $req->get_header( 'x_hub_signature_256' );
			if ( ! $sig || ! hash_equals( 'sha256=' . hash_hmac( 'sha256', $req->get_body(), $secret ), $sig ) ) {
				return new WP_Error( 'mos_sig', 'bad signature', array( 'status' => 401 ) );
			}
		}
		global $wpdb;
		$data = $req->get_json_params();
		foreach ( (array) ( $data['entry'] ?? array() ) as $entry ) {
			foreach ( (array) ( $entry['changes'] ?? array() ) as $ch ) {
				$v = $ch['value'] ?? array();
				$names = array();
				foreach ( (array) ( $v['contacts'] ?? array() ) as $ct ) { $names[ $ct['wa_id'] ?? '' ] = $ct['profile']['name'] ?? ''; }
				foreach ( (array) ( $v['messages'] ?? array() ) as $m ) {
					$wid = $m['id'] ?? '';
					if ( $wid && $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . MOS_DB::t( 'messages' ) . ' WHERE external_id=%s', $wid ) ) ) { continue; }
					$from = $m['from'] ?? '';
					list( $cid ) = MOS_DB::upsert_contact( array( 'phone' => '+' . $from, 'name' => $names[ $from ] ?? '' ), 'וואטסאפ' );
					$type = $m['type'] ?? 'text';
					$body = 'text' === $type ? ( $m['text']['body'] ?? '' ) : ( 'button' === $type ? ( $m['button']['text'] ?? '' ) : ( 'interactive' === $type ? wp_json_encode( $m['interactive'], JSON_UNESCAPED_UNICODE ) : '[' . $type . ']' ) );
					self::record( $cid, 'in', 'whatsapp', $body, array( 'external_id' => $wid ) );
					MOS_Events::emit( 'message_received', array( 'contact_id' => $cid, 'channel' => 'whatsapp', 'text' => $body ) );
				}
				foreach ( (array) ( $v['statuses'] ?? array() ) as $s ) {
					if ( ! empty( $s['id'] ) ) { $wpdb->update( MOS_DB::t( 'messages' ), array( 'status' => sanitize_key( $s['status'] ?? 'sent' ) ), array( 'external_id' => $s['id'] ) ); }
				}
			}
		}
		return array( 'ok' => true );
	}
}
