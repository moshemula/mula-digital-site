<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * חתימה אלקטרונית פנימית, בלי ספק חיצוני.
 *
 * הזרימה: קוד חד־פעמי למייל של הלקוח → חתימה ביד + הקלדת שם → נעילה,
 * רישום ראיות (זמן, IP, מכשיר, טביעת מסמך, חותמת) → עותק חתום לשני הצדדים.
 */
class MO_Sign {

	const CODE_TTL   = 600;  // 10 דקות
	const MAX_TRIES  = 5;
	const RESEND_GAP = 45;   // שניות בין שליחות קוד
	const MAX_SIG    = 350000; // בייטים

	const CONSENTS = array(
		'c1' => 'קראתי את המסמך המלא ואני מסכים לתנאי ההזמנה.',
		'c2' => 'אני מסכים לחתום באופן אלקטרוני ולקבל עותק חתום לדוא״ל.',
	);

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'rest' ) );
		add_action( 'admin_post_mo_copy', array( __CLASS__, 'admin_copy' ) );
	}

	public static function rest() {
		$a = array( 'methods' => 'POST', 'permission_callback' => '__return_true' );
		register_rest_route( 'mo/v1', '/sign/start', $a + array( 'callback' => array( __CLASS__, 'api_start' ) ) );
		register_rest_route( 'mo/v1', '/sign/confirm', $a + array( 'callback' => array( __CLASS__, 'api_confirm' ) ) );
	}

	/* ---------------- בדיקות משותפות ---------------- */

	/** כל מה שחייב להתקיים לפני חתימה. מחזיר את המסמך הנעול או שגיאה. */
	private static function preflight( $o, $req ) {
		$doc = json_decode( (string) $o->doc_json, true );
		if ( ! $doc || ! hash_equals( (string) $o->doc_hash, hash( 'sha256', (string) $o->doc_json ) ) ) {
			MO_Store::log( $o->id, 'integrity_fail', null, 'system' );
			return new WP_Error( 'mo_integrity', 'שגיאת שלמות מסמך. דברו עם משה.', array( 'status' => 500 ) );
		}
		foreach ( $doc['terms'] as $t ) {
			if ( '' === trim( $t['body'] ) ) { return new WP_Error( 'mo_terms', 'יש תנאים שעדיין לא הושלמו.', array( 'status' => 409 ) ); }
		}
		list( , $err ) = MO_Public::validate_client( $o->client );
		if ( $err ) { return new WP_Error( 'mo_client', 'פרטי הלקוח לא הושלמו.', array( 'status' => 409 ) ); }
		if ( ! $req->get_param( 'c1' ) || ! $req->get_param( 'c2' ) ) {
			return new WP_Error( 'mo_consent', 'צריך לסמן את שתי ההסכמות.', array( 'status' => 409 ) );
		}
		return $doc;
	}

	private static function otp_key( $o ) { return 'mo_otp_' . (int) $o->id; }

	private static function code_hash( $code, $o ) {
		return hash_hmac( 'sha256', $code . '|' . $o->id . '|' . $o->doc_hash, wp_salt( 'auth' ) );
	}

	public static function mask_email( $e ) {
		$p = explode( '@', (string) $e );
		if ( 2 !== count( $p ) ) { return ''; }
		$u = $p[0];
		$keep = mb_substr( $u, 0, min( 2, max( 1, mb_strlen( $u ) - 1 ) ) );
		return $keep . str_repeat( '•', max( 2, mb_strlen( $u ) - mb_strlen( $keep ) ) ) . '@' . $p[1];
	}

	private static function client_ip() {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}

	/* ---------------- שלב 1: קוד למייל ---------------- */

	public static function api_start( WP_REST_Request $req ) {
		if ( MO_Public::limited( 'sign_start', 6 ) ) { return new WP_Error( 'mo_rate', 'יותר מדי בקשות. נסו שוב בעוד כמה דקות.', array( 'status' => 429 ) ); }
		$o = MO_Public::open_order( $req );
		if ( is_wp_error( $o ) ) { return $o; }
		$doc = self::preflight( $o, $req );
		if ( is_wp_error( $doc ) ) { return $doc; }

		$prev = get_transient( self::otp_key( $o ) );
		if ( is_array( $prev ) && isset( $prev['at'] ) && time() - $prev['at'] < self::RESEND_GAP ) {
			$wait = self::RESEND_GAP - ( time() - $prev['at'] );
			return new WP_Error( 'mo_wait', 'קוד כבר נשלח. אפשר לבקש חדש בעוד ' . $wait . ' שניות.', array( 'status' => 429, 'wait' => $wait ) );
		}

		$code = (string) random_int( 100000, 999999 );
		set_transient( self::otp_key( $o ), array(
			'h'   => self::code_hash( $code, $o ),
			'n'   => 0,
			'exp' => time() + self::CODE_TTL,
			'at'  => time(),
		), self::CODE_TTL );

		$email = $o->client['email'];
		$body  = self::mail_html(
			'קוד האימות שלך',
			'<p>לחתימה על הזמנה ' . esc_html( $o->number ) . ' מול Mula Digital, הקלידו את הקוד:</p>'
			. '<p style="font:800 34px/1 Heebo,Arial,sans-serif;letter-spacing:8px;margin:22px 0;color:#0B0B0F" dir="ltr">' . esc_html( $code ) . '</p>'
			. '<p style="color:#6B6B78">הקוד בתוקף ל־10 דקות. אם לא ביקשתם לחתום, אפשר להתעלם מההודעה.</p>'
		);
		$sent = wp_mail( $email, 'קוד אימות לחתימה · הזמנה ' . $o->number, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
		if ( ! $sent ) {
			delete_transient( self::otp_key( $o ) );
			MO_Store::log( $o->id, 'sign_code_failed', null, 'system' );
			return new WP_Error( 'mo_mail', 'לא הצלחנו לשלוח את הקוד. נסו שוב או דברו עם משה.', array( 'status' => 502 ) );
		}
		MO_Store::log( $o->id, 'sign_code_sent', array( 'to' => self::mask_email( $email ) ), 'client' );
		return array( 'ok' => true, 'to' => self::mask_email( $email ), 'ttl' => self::CODE_TTL, 'gap' => self::RESEND_GAP );
	}

	/* ---------------- שלב 2: אימות וחתימה ---------------- */

	private static function norm_name( $s ) {
		$s = preg_replace( '/[\x{0591}-\x{05C7}"\'״׳.,\-]/u', '', (string) $s );
		return trim( preg_replace( '/\s+/u', ' ', $s ) );
	}

	public static function api_confirm( WP_REST_Request $req ) {
		if ( MO_Public::limited( 'sign_confirm', 12 ) ) { return new WP_Error( 'mo_rate', 'יותר מדי ניסיונות. נסו שוב בעוד כמה דקות.', array( 'status' => 429 ) ); }
		$o = MO_Public::open_order( $req );
		if ( is_wp_error( $o ) ) { return $o; }
		$doc = self::preflight( $o, $req );
		if ( is_wp_error( $doc ) ) { return $doc; }

		// קוד
		$otp  = get_transient( self::otp_key( $o ) );
		$code = preg_replace( '/\D/', '', (string) $req->get_param( 'code' ) );
		if ( ! is_array( $otp ) || $otp['exp'] < time() ) {
			delete_transient( self::otp_key( $o ) );
			return new WP_REST_Response( array( 'ok' => false, 'field' => 'code', 'error' => 'תוקף הקוד הסתיים. בקשו קוד חדש.', 'expired' => true ), 422 );
		}
		if ( ! hash_equals( $otp['h'], self::code_hash( $code, $o ) ) ) {
			$otp['n']++;
			if ( $otp['n'] >= self::MAX_TRIES ) {
				delete_transient( self::otp_key( $o ) );
				MO_Store::log( $o->id, 'sign_code_locked', null, 'client' );
				return new WP_REST_Response( array( 'ok' => false, 'field' => 'code', 'error' => 'יותר מדי ניסיונות שגויים. בקשו קוד חדש.', 'expired' => true ), 422 );
			}
			set_transient( self::otp_key( $o ), $otp, max( 1, $otp['exp'] - time() ) );
			MO_Store::log( $o->id, 'sign_code_wrong', null, 'client' );
			return new WP_REST_Response( array( 'ok' => false, 'field' => 'code', 'error' => 'הקוד שגוי. נשארו ' . ( self::MAX_TRIES - $otp['n'] ) . ' ניסיונות.' ), 422 );
		}

		// שם מוקלד מול שם החותם שנמסר
		$typed = sanitize_text_field( wp_unslash( (string) $req->get_param( 'name' ) ) );
		if ( '' === self::norm_name( $typed ) || mb_strtolower( self::norm_name( $typed ) ) !== mb_strtolower( self::norm_name( $o->client['signer'] ) ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'field' => 'name', 'error' => 'הקלידו את השם המלא בדיוק כפי שמופיע בפרטי החותם: ' . $o->client['signer'] ), 422 );
		}

		// חתימה ביד
		$sig = (string) $req->get_param( 'sig' );
		$png = null;
		if ( 0 === strpos( $sig, 'data:image/png;base64,' ) ) { $png = base64_decode( substr( $sig, 22 ), true ); }
		$dim = $png ? @getimagesizefromstring( $png ) : false;
		$ink = (int) $req->get_param( 'ink' );
		if ( ! $png || strlen( $png ) > self::MAX_SIG || "\x89PNG" !== substr( $png, 0, 4 ) || ! $dim || $dim[0] < 200 || $ink < 60 ) {
			return new WP_REST_Response( array( 'ok' => false, 'field' => 'sig', 'error' => 'החתימה קצרה מדי. חתמו שוב במסגרת.' ), 422 );
		}

		// נעילה אטומית: רק הזמנה שעדיין פתוחה עוברת למצב חתום
		global $wpdb;
		$now     = time();
		$now_sql = gmdate( 'Y-m-d H:i:s', $now );
		$client  = wp_json_encode( $o->client, JSON_UNESCAPED_UNICODE );
		$sig_b64 = base64_encode( $png );
		$seal    = hash( 'sha256', implode( '|', array( $o->doc_hash, hash( 'sha256', $client ), hash( 'sha256', $png ), $now_sql ) ) );
		$record  = array(
			'v'         => 1,
			'method'    => 'internal-otp-email',
			'signed_at' => $now_sql,
			'typed'     => $typed,
			'signer'    => $o->client['signer'],
			'role'      => isset( $o->client['role'] ) ? $o->client['role'] : '',
			'email'     => $o->client['email'],
			'ip'        => self::client_ip(),
			'ua'        => mb_substr( isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '', 0, 300 ),
			'consents'  => self::CONSENTS,
			'doc_hash'  => $o->doc_hash,
			'client_h'  => hash( 'sha256', $client ),
			'sig_h'     => hash( 'sha256', $png ),
			'seal'      => $seal,
			'sig'       => $sig_b64,
		);
		$ok = $wpdb->query( $wpdb->prepare(
			'UPDATE ' . MO_Store::t_orders() . " SET status='signed', signed_at=%s, updated_at=%s, provider='internal', provider_ref=%s, sign_data=%s WHERE id=%d AND status IN ('sent','opened','fix_requested')",
			$now_sql, $now_sql, substr( $seal, 0, 32 ), MO_Store::encrypt( wp_json_encode( $record, JSON_UNESCAPED_UNICODE ) ), $o->id
		) );
		if ( 1 !== (int) $ok ) {
			return new WP_Error( 'mo_state', 'ההזמנה כבר אינה פתוחה לחתימה.', array( 'status' => 409 ) );
		}
		delete_transient( self::otp_key( $o ) );
		MO_Store::log( $o->id, 'signed', array( 'method' => 'internal-otp-email', 'doc_hash' => $o->doc_hash, 'seal' => $seal ), 'client' );

		$o = MO_Store::get( $o->id );
		self::deliver( $o );
		do_action( 'mo_order_signed', $o );

		return array( 'ok' => true, 'seal' => substr( $seal, 0, 16 ), 'copy' => self::copy_url_client( (string) $req->get_param( 'token' ) ) );
	}

	/* ---------------- עותק חתום ---------------- */

	public static function record( $o ) {
		$raw = MO_Store::decrypt( isset( $o->sign_data ) ? $o->sign_data : null );
		return $raw ? json_decode( $raw, true ) : null;
	}

	public static function copy_url_client( $token ) {
		return home_url( '/o/' . rawurlencode( $token ) . '/?copy=1' );
	}

	private static function il_time( $utc ) {
		$d = new DateTime( $utc, new DateTimeZone( 'UTC' ) );
		$d->setTimezone( new DateTimeZone( 'Asia/Jerusalem' ) );
		return $d->format( 'd/m/Y · H:i:s' );
	}

	private static function device( $ua ) {
		$os = 'מכשיר לא מזוהה';
		foreach ( array( 'iPhone' => 'iPhone', 'iPad' => 'iPad', 'Android' => 'Android', 'Windows' => 'Windows', 'Mac OS' => 'Mac' ) as $k => $v ) {
			if ( false !== strpos( $ua, $k ) ) { $os = $v; break; }
		}
		$br = '';
		foreach ( array( 'Edg/' => 'Edge', 'SamsungBrowser' => 'Samsung Internet', 'Chrome/' => 'Chrome', 'Firefox/' => 'Firefox', 'Safari/' => 'Safari' ) as $k => $v ) {
			if ( false !== strpos( $ua, $k ) ) { $br = $v; break; }
		}
		return trim( $os . ( $br ? ' · ' . $br : '' ) );
	}

	/** מסמך HTML עצמאי: ההזמנה הנעולה + החתימה + דף ראיות. מוכן להדפסה ול־PDF. */
	public static function copy_html( $o, $printable_button = true ) {
		$doc = json_decode( (string) $o->doc_json, true );
		$r   = self::record( $o );
		if ( ! $doc || ! $r ) { return ''; }
		$c     = $o->client;
		$money = function ( $n ) { return '₪' . number_format( (float) $n, 0, '.', ',' ); };
		$h     = function ( $s ) { return esc_html( (string) $s ); };
		$events = array_reverse( MO_Store::events( $o->id ) );
		$ev_names = array(
			'sent' => 'ההזמנה ננעלה ונשלח קישור', 'opened' => 'הלקוח פתח את הקישור', 'client_details_saved' => 'הלקוח מילא ושמר פרטים',
			'sign_code_sent' => 'נשלח קוד אימות לדוא״ל', 'sign_code_wrong' => 'הוקלד קוד שגוי', 'signed' => 'ההזמנה נחתמה',
		);
		ob_start();
		?><!DOCTYPE html>
<html lang="he" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title><?php echo $h( 'הזמנה חתומה ' . $doc['number'] . ' · Mula Digital' ); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Assistant:wght@400;600;700&family=Heebo:wght@700;800;900&display=swap" rel="stylesheet">
<style>
:root{--ink:#0B0B0F;--muted:#6B6B78;--line:#E7E5EE;--grad:linear-gradient(90deg,#7A2BD4,#C72C7C,#F2522A);}
*{box-sizing:border-box}body{margin:0;background:#F4F3F8;color:var(--ink);font:16px/1.65 Assistant,Arial,sans-serif}
.pg{max-width:860px;margin:28px auto;background:#fff;border-radius:18px;box-shadow:0 10px 40px rgba(11,11,15,.08);overflow:hidden}
.top{background:#0B0B0F;color:#fff;padding:28px 34px 24px;position:relative}.top:after{content:"";position:absolute;inset:auto 0 0;height:4px;background:var(--grad)}
.top h1{font:900 26px/1.2 Heebo,Arial;margin:6px 0 4px}.top p{margin:0;color:rgba(255,255,255,.7);font-size:14.5px}
.badge{display:inline-flex;gap:8px;align-items:center;font:700 13px Heebo,Arial;background:rgba(25,179,107,.16);color:#3EE09A;border:1px solid rgba(62,224,154,.35);padding:5px 12px;border-radius:99px}
.in{padding:26px 34px 34px}h2{font:800 18px Heebo,Arial;margin:26px 0 10px;padding-bottom:8px;border-bottom:1px solid var(--line)}
.kv{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px 24px;margin:0}.kv div{display:flex;gap:8px}.kv dt{color:var(--muted);min-width:110px}.kv dd{margin:0;font-weight:600}
table{width:100%;border-collapse:collapse;font-size:15px}th,td{text-align:start;padding:10px 8px;border-bottom:1px solid var(--line);vertical-align:top}th{font:700 13.5px Heebo,Arial;color:var(--muted)}
td.n{white-space:nowrap;text-align:end;font-weight:700}td small{display:block;color:var(--muted)}
.tot{display:flex;gap:28px;justify-content:flex-end;margin-top:12px;font-weight:700}.tot span{color:var(--muted);font-weight:400;margin-inline-end:6px}
.term{margin:0 0 14px}.term h3{font:700 15.5px Heebo,Arial;margin:0 0 4px}.term p{margin:0;white-space:pre-line}
ul.nt{margin:0;padding-inline-start:20px;color:#3A3A45}
.sig{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:22px;margin-top:6px}
.sigbox{border:1px solid var(--line);border-radius:14px;padding:16px}.sigbox img{display:block;max-width:100%;height:110px;object-fit:contain;margin:0 auto 8px}
.sigbox .who{border-top:1px dashed var(--line);padding-top:8px;font-size:14.5px}.sigbox .who b{display:block;font:800 16px Heebo,Arial}
.ev{font-size:14px}.ev td{padding:7px 8px}.mono{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12.5px;word-break:break-all;direction:ltr;unicode-bidi:isolate;text-align:left}
.legal{font-size:13px;color:var(--muted);margin-top:18px}
.bar{max-width:860px;margin:18px auto 0;display:flex;justify-content:flex-end;padding:0 16px}
.bar button{font:700 15px Heebo,Arial;border:0;border-radius:99px;padding:12px 22px;background:var(--grad);color:#fff;cursor:pointer}
@media(max-width:640px){.pg{margin:12px;border-radius:14px}.top,.in{padding-inline:18px}.kv,.sig{grid-template-columns:1fr}.tot{flex-direction:column;align-items:flex-end;gap:4px}}
@media print{body{background:#fff}.pg{box-shadow:none;margin:0;max-width:none;border-radius:0}.bar{display:none}.top{-webkit-print-color-adjust:exact;print-color-adjust:exact}h2{break-after:avoid}.sig,.sigbox,tr{break-inside:avoid}}
</style></head><body>
<?php if ( $printable_button ) : ?><div class="bar"><button type="button" onclick="window.print()">שמירה כ־PDF / הדפסה</button></div><?php endif; ?>
<div class="pg">
 <div class="top">
  <span class="badge">✓ נחתם אלקטרונית</span>
  <h1>הזמנת שירותים <?php echo $h( $doc['number'] ); ?><?php echo $doc['version'] > 1 ? $h( ' · גרסה ' . $doc['version'] ) : ''; ?></h1>
  <p>Mula Digital · משה מולה · נחתם ב־<?php echo $h( self::il_time( $r['signed_at'] ) ); ?> (שעון ישראל)</p>
 </div>
 <div class="in">
  <h2>הצדדים</h2>
  <dl class="kv">
   <div><dt>נותן השירות</dt><dd><?php echo $h( $doc['supplier'] ); ?></dd></div>
   <div><dt>הלקוח</dt><dd><?php echo $h( $c['entity'] ); ?></dd></div>
   <div><dt><?php echo 'company' === $c['ctype'] ? 'ח.פ.' : 'עוסק מורשה'; ?></dt><dd><bdi><?php echo $h( $c['reg'] ); ?></bdi></dd></div>
   <div><dt>כתובת</dt><dd><?php echo $h( $c['addr'] ); ?></dd></div>
   <div><dt>החותם</dt><dd><?php echo $h( $c['signer'] . ( ! empty( $c['role'] ) ? ', ' . $c['role'] : '' ) ); ?></dd></div>
   <div><dt>טלפון</dt><dd><bdi><?php echo $h( $c['phone'] ); ?></bdi></dd></div>
   <div><dt>דוא״ל</dt><dd><bdi><?php echo $h( $c['email'] ); ?></bdi></dd></div>
  </dl>

  <h2>השירותים</h2>
  <table><thead><tr><th>שירות</th><th>סוג</th><th style="text-align:end">מחיר</th></tr></thead><tbody>
  <?php foreach ( $doc['services'] as $s ) : $m = isset( $s['type'] ) && 'monthly' === $s['type']; ?>
   <tr><td><?php echo $h( $s['name'] ); ?><?php if ( ! empty( $s['desc'] ) ) : ?><small><?php echo $h( $s['desc'] ); ?></small><?php endif; ?></td>
   <td><?php echo $m ? 'חודשי' : 'חד־פעמי'; ?></td><td class="n"><bdi><?php echo $h( $money( $s['price'] ) . ( $m ? ' לחודש' : '' ) ); ?></bdi></td></tr>
  <?php endforeach; ?>
  </tbody></table>
  <div class="tot">
   <?php if ( $doc['totals']['once'] ) : ?><div><span>סה״כ חד־פעמי</span><bdi><?php echo $h( $money( $doc['totals']['once'] ) ); ?></bdi></div><?php endif; ?>
   <?php if ( $doc['totals']['monthly'] ) : ?><div><span>סה״כ חודשי</span><bdi><?php echo $h( $money( $doc['totals']['monthly'] ) ); ?></bdi></div><?php endif; ?>
  </div>
  <p class="legal"><?php echo $h( $doc['vat'] ); ?></p>

  <h2>תנאי ההזמנה</h2>
  <?php $i = 0; foreach ( $doc['terms'] as $t ) : $i++; ?>
   <div class="term"><h3><?php echo $h( $i . '. ' . $t['title'] ); ?></h3><p><?php echo $h( $t['body'] ); ?></p></div>
  <?php endforeach; ?>
  <?php if ( ! empty( $doc['notes'] ) ) : ?>
   <h2>הבהרות</h2><ul class="nt"><?php foreach ( $doc['notes'] as $n ) : ?><li><?php echo $h( $n ); ?></li><?php endforeach; ?></ul>
  <?php endif; ?>

  <h2>חתימה</h2>
  <div class="sig">
   <div class="sigbox">
    <img src="data:image/png;base64,<?php echo esc_attr( $r['sig'] ); ?>" alt="חתימת הלקוח">
    <div class="who"><b><?php echo $h( $r['signer'] ); ?></b><?php echo $h( ( $r['role'] ? $r['role'] . ' · ' : '' ) . $c['entity'] ); ?><br><?php echo $h( self::il_time( $r['signed_at'] ) ); ?></div>
   </div>
   <div class="sigbox">
    <div style="height:110px;display:grid;place-items:center;font:800 20px Heebo,Arial">Mula Digital</div>
    <div class="who"><b>משה מולה</b>נותן השירות · ההזמנה הוכנה ונשלחה לחתימה ב־<?php echo $h( $o->sent_at ? self::il_time( $o->sent_at ) : '' ); ?></div>
   </div>
  </div>
  <p class="legal">הלקוח אישר: «<?php echo $h( self::CONSENTS['c1'] ); ?>» וגם «<?php echo $h( self::CONSENTS['c2'] ); ?>». השם שהוקלד בעת החתימה: <?php echo $h( $r['typed'] ); ?>.</p>

  <h2>תיעוד החתימה</h2>
  <dl class="kv">
   <div><dt>שיטת זיהוי</dt><dd>קוד חד־פעמי לדוא״ל של החותם</dd></div>
   <div><dt>דוא״ל מאומת</dt><dd><bdi><?php echo $h( $r['email'] ); ?></bdi></dd></div>
   <div><dt>כתובת IP</dt><dd><bdi><?php echo $h( $r['ip'] ); ?></bdi></dd></div>
   <div><dt>מכשיר</dt><dd><?php echo $h( self::device( $r['ua'] ) ); ?></dd></div>
  </dl>
  <table class="ev" style="margin-top:12px"><tbody>
  <?php foreach ( $events as $e ) : if ( ! isset( $ev_names[ $e->type ] ) ) { continue; } ?>
   <tr><td style="white-space:nowrap"><bdi><?php echo $h( self::il_time( $e->created_at ) ); ?></bdi></td><td><?php echo $h( $ev_names[ $e->type ] ); ?></td></tr>
  <?php endforeach; ?>
  </tbody></table>
  <p class="legal">טביעת המסמך הנעול (SHA-256):</p><p class="mono"><?php echo $h( $r['doc_hash'] ); ?></p>
  <p class="legal">חותמת החתימה (מסמך + פרטי לקוח + חתימה + זמן):</p><p class="mono"><?php echo $h( $r['seal'] ); ?></p>
  <p class="legal">המסמך נעול. כל שינוי בתוכן ההזמנה מחייב גרסה חדשה וחתימה חדשה, ולא ישנה את הטביעה שלמעלה.</p>
 </div>
</div>
</body></html>
		<?php
		return ob_get_clean();
	}

	/** שולח עותק חתום ללקוח ולמשה (קובץ HTML מצורף שאפשר לפתוח ולשמור כ־PDF). */
	private static function deliver( $o ) {
		$html = self::copy_html( $o, true );
		if ( ! $html ) { return; }
		$dir = trailingslashit( get_temp_dir() ) . 'mo-' . wp_generate_password( 12, false );
		wp_mkdir_p( $dir );
		$file = $dir . '/' . sanitize_file_name( 'הזמנה-חתומה-' . $o->number . '.html' );
		file_put_contents( $file, $html );

		$head = array( 'Content-Type: text/html; charset=UTF-8' );
		$c    = $o->client;
		wp_mail(
			$c['email'],
			'ההזמנה נחתמה · ' . $o->number . ' · Mula Digital',
			self::mail_html( 'ההזמנה נחתמה', '<p>' . esc_html( $c['signer'] ) . ', תודה.</p><p>מצורף העותק החתום של הזמנה ' . esc_html( $o->number ) . '. פותחים את הקובץ, ואפשר לשמור אותו כ־PDF.</p><p>נדבר בקרוב,<br>משה</p>' ),
			$head,
			array( $file )
		);
		$to = get_option( 'mo_notify_email', get_option( 'admin_email' ) );
		wp_mail(
			$to,
			'[הזמנות] נחתמה ' . $o->number . ' · ' . $c['entity'],
			self::mail_html( 'הזמנה נחתמה', '<p><strong>' . esc_html( $c['entity'] ) . '</strong> חתמו על הזמנה ' . esc_html( $o->number ) . '.</p><p>חד־פעמי: ' . esc_html( number_format( $o->totals['once'] ) ) . ' ₪ · חודשי: ' . esc_html( number_format( $o->totals['monthly'] ) ) . ' ₪</p><p><a href="' . esc_url( admin_url( 'admin.php?page=mo-orders&action=edit&id=' . $o->id ) ) . '">לכרטיס ההזמנה</a></p>' ),
			$head,
			array( $file )
		);
		@unlink( $file );
		@rmdir( $dir );
		MO_Store::log( $o->id, 'signed_copy_sent', array( 'to' => self::mask_email( $c['email'] ) ), 'system' );
	}

	private static function mail_html( $title, $inner ) {
		return '<!DOCTYPE html><html lang="he" dir="rtl"><body style="margin:0;background:#F4F3F8;padding:24px 12px;font:16px/1.6 Arial,sans-serif;color:#0B0B0F">'
			. '<div style="max-width:520px;margin:0 auto;background:#fff;border-radius:14px;overflow:hidden;direction:rtl;text-align:right">'
			. '<div style="height:4px;background:linear-gradient(90deg,#7A2BD4,#C72C7C,#F2522A)"></div>'
			. '<div style="padding:26px 26px 22px"><p style="font:800 13px Arial;color:#C72C7C;margin:0 0 6px">Mula Digital</p>'
			. '<h1 style="font:800 22px Arial;margin:0 0 14px">' . esc_html( $title ) . '</h1>' . $inner . '</div></div></body></html>';
	}

	/** עותק חתום מתוך ממשק הניהול */
	public static function admin_copy() {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'mo_copy_' . $id ) ) { wp_die( 'אין גישה' ); }
		$o = MO_Store::get( $id );
		if ( ! $o || 'signed' !== $o->status ) { wp_die( 'ההזמנה לא חתומה.' ); }
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		echo self::copy_html( $o, true ); // phpcs:ignore
		exit;
	}

	public static function admin_copy_url( $id ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=mo_copy&id=' . (int) $id ), 'mo_copy_' . (int) $id );
	}
}
