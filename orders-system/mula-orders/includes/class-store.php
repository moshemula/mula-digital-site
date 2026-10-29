<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * נתונים: הזמנות, אירועים, תבניות וספריית שירותים.
 */
class MO_Store {

	const STATUSES = array(
		'draft'         => 'טיוטה',
		'sent'          => 'נשלחה',
		'opened'        => 'נפתחה',
		'fix_requested' => 'בקשת תיקון',
		'signed'        => 'נחתמה',
		'declined'      => 'נדחתה',
		'cancelled'     => 'בוטלה',
		'replaced'      => 'הוחלפה בגרסה חדשה',
		'expired'       => 'פג תוקף',
	);

	/** סטטוסים שבהם הלקוח עדיין יכול לפעול בקישור */
	const OPEN_STATUSES = array( 'sent', 'opened', 'fix_requested' );

	const TERM_KEYS = array(
		'payment'      => 'תנאי תשלום',
		'schedule'     => 'מועדי תחילת עבודה ואספקה',
		'billing'      => 'תחילת השירותים והחיובים החודשיים',
		'duration'     => 'משך התקשרות',
		'cancellation' => 'ביטול וסיום שירותים',
		'deliverables' => 'תוצרים, סבבי תיקונים וזכויות',
		'liability'    => 'אחריות הצדדים וחומרים מהלקוח',
		'privacy'      => 'פרטיות, ספקי עיבוד מידע ושמירת מסמכים',
	);

	public static function t_orders() { global $wpdb; return $wpdb->prefix . 'mo_orders'; }
	public static function t_events() { global $wpdb; return $wpdb->prefix . 'mo_events'; }

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE " . self::t_orders() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			number varchar(32) NOT NULL,
			version int(11) NOT NULL DEFAULT 1,
			parent_id bigint(20) unsigned NULL,
			status varchar(20) NOT NULL DEFAULT 'draft',
			client_label varchar(255) NOT NULL DEFAULT '',
			services longtext NOT NULL,
			terms longtext NOT NULL,
			notes longtext NOT NULL,
			client_data longtext NULL,
			token_hash char(64) NULL,
			token_enc text NULL,
			token_expires datetime NULL,
			doc_json longtext NULL,
			doc_hash char(64) NULL,
			provider varchar(40) NULL,
			provider_ref varchar(191) NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			sent_at datetime NULL,
			opened_at datetime NULL,
			signed_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token_hash (token_hash),
			KEY number (number),
			KEY status (status)
		) $c;" );
		dbDelta( "CREATE TABLE " . self::t_events() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint(20) unsigned NOT NULL,
			type varchar(40) NOT NULL,
			data longtext NULL,
			actor varchar(20) NOT NULL DEFAULT 'system',
			ip_hash char(64) NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY order_id (order_id)
		) $c;" );
		update_option( 'mo_db_version', MO_DB_VERSION );
		self::seed();
	}

	/** ספריית שירותים ותבנית ראשונה, פעם אחת בלבד */
	private static function seed() {
		if ( false === get_option( 'mo_services' ) ) {
			update_option( 'mo_services', array(
				array( 'name' => 'מיתוג: לוגו וצבעים לבחירה', 'desc' => 'עיצוב לוגו ופלטת צבעים לבחירה, שישמשו בכל הנכסים הדיגיטליים.', 'type' => 'once', 'price' => 1500 ),
				array( 'name' => 'הקמת דפי Facebook ו־Instagram וחשבון מודעות', 'desc' => 'פתיחת הדפים, התאמה למיתוג והקמת חשבון מודעות מוכן לקמפיינים.', 'type' => 'once', 'price' => 2000 ),
				array( 'name' => 'אתר מעוצב עד 5 דפים', 'desc' => 'אתר תדמית מעוצב בהתאמה אישית, מותאם לנייד.', 'type' => 'once', 'price' => 5000 ),
				array( 'name' => 'דומיין, שרת ישראלי וגיבוי', 'desc' => 'בהוראת קבע. הוראת הקבע תוסדר בנפרד ואינה חלק מהחתימה.', 'type' => 'monthly', 'price' => 100 ),
				array( 'name' => 'ניהול קמפיינים ב־Facebook וב־Instagram', 'desc' => 'ניהול שוטף של הקמפיינים. תקציב הפרסום אינו כלול.', 'type' => 'monthly', 'price' => 2500 ),
			), false );
		}
		if ( false === get_option( 'mo_templates' ) ) {
			update_option( 'mo_templates', array(
				array(
					'id'       => 'tpl_' . wp_generate_password( 8, false, false ),
					'name'     => 'הקמה מלאה + קמפיינים',
					'services' => get_option( 'mo_services' ),
					'terms'    => self::empty_terms(),
					'notes'    => self::default_notes(),
				),
			), false );
		}
	}

	public static function empty_terms() {
		$t = array();
		foreach ( self::TERM_KEYS as $k => $title ) { $t[ $k ] = ''; }
		return $t;
	}

	public static function default_notes() {
		return array(
			'כל המחירים אינם כוללים מע״מ.',
			'תקציב הפרסום אינו כלול ומשולם ישירות לפלטפורמות.',
			'הוראת הקבע בסך ₪100 מתייחסת לדומיין, לאחסון ולגיבוי.',
			'חתימת ההזמנה אינה הרשאה לחיוב בנקאי.',
			'מועדי תחילת החיובים ותנאי התשלום יושלמו לפי הסיכום בפועל.',
		);
	}

	/* ---------------- הצפנה ---------------- */

	private static function key() {
		return hash( 'sha256', ( defined( 'AUTH_KEY' ) ? AUTH_KEY : '' ) . ( defined( 'SECURE_AUTH_SALT' ) ? SECURE_AUTH_SALT : '' ) . 'mula-orders', true );
	}

	public static function encrypt( $plain ) {
		if ( '' === $plain || null === $plain ) { return null; }
		$iv  = random_bytes( 12 );
		$tag = '';
		$ct  = openssl_encrypt( $plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag );
		return base64_encode( $iv . $tag . $ct );
	}

	public static function decrypt( $enc ) {
		if ( ! $enc ) { return null; }
		$raw = base64_decode( $enc, true );
		if ( false === $raw || strlen( $raw ) < 29 ) { return null; }
		$out = openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ) );
		return false === $out ? null : $out;
	}

	public static function ip_hash() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
		return hash_hmac( 'sha256', $ip, self::key() );
	}

	/* ---------------- הזמנות ---------------- */

	private static function hydrate( $row ) {
		if ( ! $row ) { return null; }
		$row->services    = json_decode( $row->services, true ) ?: array();
		$row->terms       = json_decode( $row->terms, true ) ?: array();
		$row->notes       = json_decode( $row->notes, true ) ?: array();
		$cd               = self::decrypt( $row->client_data );
		$row->client      = $cd ? ( json_decode( $cd, true ) ?: array() ) : array();
		$row->totals      = self::totals( $row->services );
		$row->eff_status  = self::effective_status( $row );
		return $row;
	}

	public static function get( $id ) {
		global $wpdb;
		return self::hydrate( $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t_orders() . ' WHERE id=%d', $id ) ) );
	}

	public static function get_by_token( $token ) {
		global $wpdb;
		if ( ! is_string( $token ) || ! preg_match( '/^[A-Za-z0-9_-]{40,64}$/', $token ) ) { return null; }
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t_orders() . ' WHERE token_hash=%s', hash( 'sha256', $token ) ) );
		return self::hydrate( $row );
	}

	public static function all() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . self::t_orders() . ' ORDER BY id DESC LIMIT 500' );
		return array_map( array( __CLASS__, 'hydrate' ), $rows );
	}

	public static function effective_status( $o ) {
		if ( in_array( $o->status, self::OPEN_STATUSES, true ) && $o->token_expires && strtotime( $o->token_expires . ' UTC' ) < time() ) {
			return 'expired';
		}
		return $o->status;
	}

	public static function totals( $services ) {
		$once = 0; $monthly = 0;
		foreach ( (array) $services as $s ) {
			$p = isset( $s['price'] ) ? (float) $s['price'] : 0;
			if ( isset( $s['type'] ) && 'monthly' === $s['type'] ) { $monthly += $p; } else { $once += $p; }
		}
		return array( 'once' => $once, 'monthly' => $monthly );
	}

	public static function next_number() {
		global $wpdb;
		$max = (int) $wpdb->get_var( "SELECT MAX(CAST(SUBSTRING(number,4) AS UNSIGNED)) FROM " . self::t_orders() );
		return sprintf( 'MD-%04d', $max + 1 );
	}

	public static function create( $data ) {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$wpdb->insert( self::t_orders(), array(
			'number'       => isset( $data['number'] ) ? $data['number'] : self::next_number(),
			'version'      => isset( $data['version'] ) ? (int) $data['version'] : 1,
			'parent_id'    => isset( $data['parent_id'] ) ? (int) $data['parent_id'] : null,
			'status'       => 'draft',
			'client_label' => isset( $data['client_label'] ) ? $data['client_label'] : '',
			'services'     => wp_json_encode( isset( $data['services'] ) ? array_values( $data['services'] ) : array() ),
			'terms'        => wp_json_encode( isset( $data['terms'] ) ? $data['terms'] : self::empty_terms() ),
			'notes'        => wp_json_encode( isset( $data['notes'] ) ? array_values( $data['notes'] ) : self::default_notes() ),
			'client_data'  => isset( $data['client'] ) && $data['client'] ? self::encrypt( wp_json_encode( $data['client'] ) ) : null,
			'created_at'   => $now,
			'updated_at'   => $now,
		) );
		$id = (int) $wpdb->insert_id;
		self::log( $id, 'created', array( 'version' => isset( $data['version'] ) ? (int) $data['version'] : 1 ), 'admin' );
		return $id;
	}

	/** עדכון תוכן מסחרי. מותר רק בטיוטה. */
	public static function update_draft( $id, $data ) {
		global $wpdb;
		$o = self::get( $id );
		if ( ! $o || 'draft' !== $o->status ) { return new WP_Error( 'locked', 'ההזמנה נעולה. שינוי מסחרי מחייב גרסה חדשה.' ); }
		$wpdb->update( self::t_orders(), array(
			'client_label' => $data['client_label'],
			'services'     => wp_json_encode( array_values( $data['services'] ) ),
			'terms'        => wp_json_encode( $data['terms'] ),
			'notes'        => wp_json_encode( array_values( $data['notes'] ) ),
			'updated_at'   => current_time( 'mysql', true ),
		), array( 'id' => $id ) );
		self::log( $id, 'edited', null, 'admin' );
		return true;
	}

	public static function missing_terms( $o ) {
		$missing = array();
		foreach ( self::TERM_KEYS as $k => $title ) {
			if ( empty( $o->terms[ $k ] ) || '' === trim( $o->terms[ $k ] ) ) { $missing[ $k ] = $title; }
		}
		return $missing;
	}

	/** המסמך המסחרי הקפוא, בסדר קבוע, לצורך גיבוב ונעילה */
	public static function document( $o ) {
		$terms = array();
		foreach ( self::TERM_KEYS as $k => $title ) {
			$terms[] = array( 'key' => $k, 'title' => $title, 'body' => isset( $o->terms[ $k ] ) ? (string) $o->terms[ $k ] : '' );
		}
		return array(
			'number'   => $o->number,
			'version'  => (int) $o->version,
			'supplier' => 'Mula Digital · משה מולה',
			'client'   => $o->client_label,
			'services' => array_values( $o->services ),
			'totals'   => self::totals( $o->services ),
			'vat'      => 'המחירים אינם כוללים מע״מ',
			'terms'    => $terms,
			'notes'    => array_values( $o->notes ),
		);
	}

	/**
	 * נעילה ויצירת קישור אישי. מחזיר את הטוקן הגלוי.
	 * $allow_missing מאפשר קישור לבדיקת לקוח גם כשחסרים תנאים; החתימה עצמה תמיד חסומה עד השלמה.
	 */
	public static function send( $id, $days = 14, $allow_missing = false ) {
		global $wpdb;
		$o = self::get( $id );
		if ( ! $o || 'draft' !== $o->status ) { return new WP_Error( 'state', 'רק טיוטה יכולה להישלח.' ); }
		if ( empty( $o->services ) ) { return new WP_Error( 'empty', 'אין שירותים בהזמנה.' ); }
		$missing = self::missing_terms( $o );
		if ( $missing && ! $allow_missing ) {
			return new WP_Error( 'missing', 'חסרים תנאים: ' . implode( ', ', $missing ) );
		}
		$doc   = wp_json_encode( self::document( $o ), JSON_UNESCAPED_UNICODE );
		$token = self::new_token();
		$now   = current_time( 'mysql', true );
		$wpdb->update( self::t_orders(), array(
			'status'        => 'sent',
			'token_hash'    => hash( 'sha256', $token ),
			'token_enc'     => self::encrypt( $token ),
			'token_expires' => gmdate( 'Y-m-d H:i:s', time() + max( 1, (int) $days ) * DAY_IN_SECONDS ),
			'doc_json'      => $doc,
			'doc_hash'      => hash( 'sha256', $doc ),
			'sent_at'       => $now,
			'updated_at'    => $now,
		), array( 'id' => $id ) );
		self::log( $id, 'sent', array( 'days' => (int) $days, 'doc_hash' => hash( 'sha256', $doc ), 'missing_terms' => array_keys( $missing ) ), 'admin' );
		return $token;
	}

	private static function new_token() {
		return rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' );
	}

	public static function link_for( $o ) {
		$t = self::decrypt( $o->token_enc );
		return $t ? home_url( '/o/' . $t ) : '';
	}

	/** חידוש גישה: טוקן חדש ותוקף חדש, הקישור הקודם מפסיק לעבוד */
	public static function renew( $id, $days = 14 ) {
		global $wpdb;
		$o = self::get( $id );
		if ( ! $o || ! in_array( $o->status, self::OPEN_STATUSES, true ) ) { return new WP_Error( 'state', 'אפשר לחדש גישה רק להזמנה פתוחה.' ); }
		$token = self::new_token();
		$wpdb->update( self::t_orders(), array(
			'token_hash'    => hash( 'sha256', $token ),
			'token_enc'     => self::encrypt( $token ),
			'token_expires' => gmdate( 'Y-m-d H:i:s', time() + max( 1, (int) $days ) * DAY_IN_SECONDS ),
			'updated_at'    => current_time( 'mysql', true ),
		), array( 'id' => $id ) );
		self::log( $id, 'access_renewed', array( 'days' => (int) $days ), 'admin' );
		return $token;
	}

	public static function set_status( $id, $status, $event = null, $data = null, $actor = 'admin' ) {
		global $wpdb;
		$fields = array( 'status' => $status, 'updated_at' => current_time( 'mysql', true ) );
		if ( in_array( $status, array( 'cancelled', 'replaced', 'declined' ), true ) ) {
			$fields['token_hash'] = null; // הקישור מפסיק לעבוד מיד
		}
		$wpdb->update( self::t_orders(), $fields, array( 'id' => $id ) );
		self::log( $id, $event ? $event : $status, $data, $actor );
	}

	/** גרסה חדשה מהזמנה קיימת. הקודמת מסומנת "הוחלפה" רק אם עוד לא נחתמה. */
	public static function new_version( $id ) {
		$o = self::get( $id );
		if ( ! $o ) { return new WP_Error( 'nf', 'לא נמצא' ); }
		if ( 'signed' === $o->status ) { return new WP_Error( 'signed', 'הזמנה חתומה לא מוחלפת. צור הזמנה חדשה.' ); }
		$new = self::create( array(
			'number'       => $o->number,
			'version'      => (int) $o->version + 1,
			'parent_id'    => $o->id,
			'client_label' => $o->client_label,
			'services'     => $o->services,
			'terms'        => $o->terms,
			'notes'        => $o->notes,
			'client'       => $o->client,
		) );
		if ( 'draft' !== $o->status ) {
			self::set_status( $o->id, 'replaced', 'replaced', array( 'by' => $new ) );
		}
		return $new;
	}

	public static function mark_opened( $o ) {
		global $wpdb;
		if ( 'sent' === $o->status ) {
			$wpdb->update( self::t_orders(), array( 'status' => 'opened', 'opened_at' => current_time( 'mysql', true ) ), array( 'id' => $o->id, 'status' => 'sent' ) );
			self::log( $o->id, 'opened', null, 'client' );
		} else {
			self::log( $o->id, 'visit', null, 'client' );
		}
	}

	public static function save_client( $o, $client ) {
		global $wpdb;
		$wpdb->update( self::t_orders(), array(
			'client_data' => self::encrypt( wp_json_encode( $client, JSON_UNESCAPED_UNICODE ) ),
			'updated_at'  => current_time( 'mysql', true ),
		), array( 'id' => $o->id ) );
		self::log( $o->id, 'client_details_saved', null, 'client' );
	}

	/* ---------------- אירועים ---------------- */

	public static function log( $order_id, $type, $data = null, $actor = 'system' ) {
		global $wpdb;
		$wpdb->insert( self::t_events(), array(
			'order_id'   => (int) $order_id,
			'type'       => $type,
			'data'       => null === $data ? null : wp_json_encode( $data, JSON_UNESCAPED_UNICODE ),
			'actor'      => $actor,
			'ip_hash'    => 'client' === $actor ? self::ip_hash() : null,
			'created_at' => current_time( 'mysql', true ),
		) );
	}

	public static function events( $order_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t_events() . ' WHERE order_id=%d ORDER BY id DESC LIMIT 200', $order_id ) );
	}

	/* ---------------- תבניות וספרייה ---------------- */

	public static function templates() { $t = get_option( 'mo_templates', array() ); return is_array( $t ) ? $t : array(); }
	public static function template( $tid ) {
		foreach ( self::templates() as $t ) { if ( $t['id'] === $tid ) { return $t; } }
		return null;
	}
	public static function save_templates( $list ) { update_option( 'mo_templates', array_values( $list ), false ); }
	public static function services_library() { $s = get_option( 'mo_services', array() ); return is_array( $s ) ? $s : array(); }
	public static function save_services_library( $list ) { update_option( 'mo_services', array_values( $list ), false ); }
}
