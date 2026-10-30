<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * מסד הנתונים של Mula OS. כל רשומה שייכת לחשבון (account_id), כך שבהמשך אפשר לפתוח חשבונות ללקוחות.
 */
class MOS_DB {

	public static function t( $name ) { global $wpdb; return $wpdb->prefix . 'mos_' . $name; }

	public static function account() { return (int) apply_filters( 'mos_current_account', 1 ); }

	public static function now() { return current_time( 'mysql', true ); }

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		$tables = array(
			'accounts' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT, name varchar(191) NOT NULL, settings longtext NULL, created_at datetime NOT NULL, PRIMARY KEY  (id)",
			'contacts' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT, account_id bigint(20) unsigned NOT NULL DEFAULT 1, first_name varchar(120) NOT NULL DEFAULT '', last_name varchar(120) NOT NULL DEFAULT '', company varchar(191) NOT NULL DEFAULT '', email varchar(191) NOT NULL DEFAULT '', phone varchar(32) NOT NULL DEFAULT '', source varchar(80) NOT NULL DEFAULT '', type varchar(20) NOT NULL DEFAULT 'lead', custom longtext NULL, dnd tinyint(1) NOT NULL DEFAULT 0, created_at datetime NOT NULL, updated_at datetime NOT NULL, last_activity_at datetime NULL, PRIMARY KEY  (id), KEY account_id (account_id), KEY email (email), KEY phone (phone)",
			'tags' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT, account_id bigint(20) unsigned NOT NULL DEFAULT 1, name varchar(80) NOT NULL, color varchar(16) NOT NULL DEFAULT '#7B2FBE', PRIMARY KEY  (id), KEY account_id (account_id)",
			'contact_tags' => "contact_id bigint(20) unsigned NOT NULL, tag_id bigint(20) unsigned NOT NULL, PRIMARY KEY  (contact_id,tag_id)",
			'pipelines' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT, account_id bigint(20) unsigned NOT NULL DEFAULT 1, name varchar(120) NOT NULL, position int(11) NOT NULL DEFAULT 0, PRIMARY KEY  (id)",
			'stages' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT, pipeline_id bigint(20) unsigned NOT NULL, name varchar(120) NOT NULL, position int(11) NOT NULL DEFAULT 0, color varchar(16) NOT NULL DEFAULT '#7B2FBE', kind varchar(10) NOT NULL DEFAULT 'open', PRIMARY KEY  (id), KEY pipeline_id (pipeline_id)",
			'deals' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT, account_id bigint(20) unsigned NOT NULL DEFAULT 1, contact_id bigint(20) unsigned NULL, pipeline_id bigint(20) unsigned NOT NULL, stage_id bigint(20) unsigned NOT NULL, title varchar(191) NOT NULL, value decimal(12,2) NOT NULL DEFAULT 0, monthly decimal(12,2) NOT NULL DEFAULT 0, status varchar(10) NOT NULL DEFAULT 'open', order_id bigint(20) unsigned NULL, position int(11) NOT NULL DEFAULT 0, created_at datetime NOT NULL, updated_at datetime NOT NULL, closed_at datetime NULL, PRIMARY KEY  (id), KEY account_id (account_id), KEY stage_id (stage_id), KEY contact_id (contact_id)",
			'tasks' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT, account_id bigint(20) unsigned NOT NULL DEFAULT 1, contact_id bigint(20) unsigned NULL, deal_id bigint(20) unsigned NULL, title varchar(255) NOT NULL, due_at datetime NULL, done tinyint(1) NOT NULL DEFAULT 0, done_at datetime NULL, created_at datetime NOT NULL, PRIMARY KEY  (id), KEY account_id (account_id), KEY contact_id (contact_id)",
			'notes' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT, contact_id bigint(20) unsigned NOT NULL, body text NOT NULL, author bigint(20) unsigned NULL, created_at datetime NOT NULL, PRIMARY KEY  (id), KEY contact_id (contact_id)",
			'activities' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT, account_id bigint(20) unsigned NOT NULL DEFAULT 1, contact_id bigint(20) unsigned NULL, type varchar(40) NOT NULL, data longtext NULL, created_at datetime NOT NULL, PRIMARY KEY  (id), KEY contact_id (contact_id), KEY account_id (account_id)",
			'conversations' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT, account_id bigint(20) unsigned NOT NULL DEFAULT 1, contact_id bigint(20) unsigned NOT NULL, last_channel varchar(20) NOT NULL DEFAULT '', last_message_at datetime NULL, last_preview varchar(255) NOT NULL DEFAULT '', unread int(11) NOT NULL DEFAULT 0, status varchar(10) NOT NULL DEFAULT 'open', PRIMARY KEY  (id), UNIQUE KEY contact (account_id,contact_id)",
			'messages' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT, conversation_id bigint(20) unsigned NOT NULL, contact_id bigint(20) unsigned NOT NULL, direction varchar(4) NOT NULL, channel varchar(20) NOT NULL, subject varchar(255) NOT NULL DEFAULT '', body longtext NOT NULL, status varchar(20) NOT NULL DEFAULT 'sent', external_id varchar(191) NULL, meta longtext NULL, created_at datetime NOT NULL, PRIMARY KEY  (id), KEY conversation_id (conversation_id), KEY external_id (external_id)",
			'automations' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT, account_id bigint(20) unsigned NOT NULL DEFAULT 1, name varchar(191) NOT NULL, trigger_type varchar(40) NOT NULL, trigger_cfg longtext NULL, steps longtext NOT NULL, active tinyint(1) NOT NULL DEFAULT 0, runs int(11) NOT NULL DEFAULT 0, created_at datetime NOT NULL, updated_at datetime NOT NULL, PRIMARY KEY  (id), KEY trig (account_id,trigger_type,active)",
			'runs' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT, automation_id bigint(20) unsigned NOT NULL, contact_id bigint(20) unsigned NULL, step int(11) NOT NULL DEFAULT 0, run_at datetime NOT NULL, status varchar(12) NOT NULL DEFAULT 'queued', context longtext NULL, log longtext NULL, created_at datetime NOT NULL, PRIMARY KEY  (id), KEY due (status,run_at)",
			'calendars' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT, account_id bigint(20) unsigned NOT NULL DEFAULT 1, name varchar(191) NOT NULL, slug varchar(80) NOT NULL, description text NULL, duration int(11) NOT NULL DEFAULT 30, buffer int(11) NOT NULL DEFAULT 10, notice_hours int(11) NOT NULL DEFAULT 12, days_ahead int(11) NOT NULL DEFAULT 21, location varchar(255) NOT NULL DEFAULT '', availability longtext NULL, active tinyint(1) NOT NULL DEFAULT 1, PRIMARY KEY  (id), UNIQUE KEY slug (slug)",
			'appointments' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT, account_id bigint(20) unsigned NOT NULL DEFAULT 1, calendar_id bigint(20) unsigned NOT NULL, contact_id bigint(20) unsigned NULL, start_at datetime NOT NULL, end_at datetime NOT NULL, status varchar(12) NOT NULL DEFAULT 'booked', notes text NULL, token char(32) NOT NULL, created_at datetime NOT NULL, PRIMARY KEY  (id), KEY cal (calendar_id,start_at), KEY account_id (account_id)",
		);
		foreach ( $tables as $name => $cols ) {
			dbDelta( 'CREATE TABLE ' . self::t( $name ) . " ( $cols ) $c;" );
		}
		update_option( 'mos_db', MOS_DB );
		self::seed();
	}

	private static function seed() {
		global $wpdb;
		if ( ! $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::t( 'accounts' ) ) ) {
			$wpdb->insert( self::t( 'accounts' ), array( 'id' => 1, 'name' => 'Mula Digital', 'settings' => '{}', 'created_at' => self::now() ) );
		}
		if ( ! $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::t( 'pipelines' ) ) ) {
			$wpdb->insert( self::t( 'pipelines' ), array( 'account_id' => 1, 'name' => 'מכירות', 'position' => 0 ) );
			$pid    = (int) $wpdb->insert_id;
			$stages = array(
				array( 'ליד חדש', '#94A3B8', 'open' ), array( 'נוצר קשר', '#5B8DEF', 'open' ), array( 'שיחת אבחון', '#7B2FBE', 'open' ),
				array( 'נשלחה הצעה', '#C026D3', 'open' ), array( 'במשא ומתן', '#F5A524', 'open' ), array( 'נסגר', '#19B36B', 'won' ), array( 'לא רלוונטי', '#A1A1AA', 'lost' ),
			);
			foreach ( $stages as $i => $s ) {
				$wpdb->insert( self::t( 'stages' ), array( 'pipeline_id' => $pid, 'name' => $s[0], 'color' => $s[1], 'kind' => $s[2], 'position' => $i ) );
			}
		}
		if ( ! $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::t( 'calendars' ) ) ) {
			$week = array();
			foreach ( array( 0, 1, 2, 3, 4 ) as $d ) { $week[ $d ] = array( array( '09:00', '17:30' ) ); } // א׳–ה׳
			$wpdb->insert( self::t( 'calendars' ), array(
				'account_id' => 1, 'name' => 'שיחת אבחון', 'slug' => 'avchun',
				'description' => 'שיחה קצרה להבין איפה העסק נמצא, מה חסר ומה הצעד הבא.',
				'duration' => 30, 'buffer' => 15, 'notice_hours' => 12, 'days_ahead' => 21,
				'location' => 'שיחת טלפון או זום', 'availability' => wp_json_encode( $week ), 'active' => 1,
			) );
		}
		if ( ! get_option( 'mos_settings' ) ) {
			update_option( 'mos_settings', array(
				'form_key'  => wp_generate_password( 24, false, false ),
				'wa_verify' => wp_generate_password( 20, false, false ),
				'from_name' => 'משה מולה · Mula Digital',
			), false );
		}
	}

	/* ---------------- עזרים ---------------- */

	public static function phone( $raw ) {
		$d = preg_replace( '/\D/', '', (string) $raw );
		if ( '' === $d ) { return ''; }
		if ( 0 === strpos( $d, '972' ) ) { return '+' . $d; }
		if ( 0 === strpos( $d, '0' ) ) { return '+972' . substr( $d, 1 ); }
		return '+' . $d;
	}

	public static function phone_local( $e164 ) {
		return 0 === strpos( (string) $e164, '+972' ) ? '0' . substr( $e164, 4 ) : (string) $e164;
	}

	public static function contact( $id ) {
		global $wpdb;
		$c = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'contacts' ) . ' WHERE id=%d AND account_id=%d', $id, self::account() ) );
		if ( $c ) { $c->custom = $c->custom ? json_decode( $c->custom, true ) : array(); $c->tags = self::contact_tags( $c->id ); $c->name = self::name( $c ); }
		return $c;
	}

	public static function name( $c ) {
		$n = trim( $c->first_name . ' ' . $c->last_name );
		return '' !== $n ? $n : ( $c->company ? $c->company : ( $c->email ? $c->email : self::phone_local( $c->phone ) ) );
	}

	public static function contact_tags( $id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT t.id, t.name, t.color FROM ' . self::t( 'tags' ) . ' t JOIN ' . self::t( 'contact_tags' ) . ' ct ON ct.tag_id=t.id WHERE ct.contact_id=%d ORDER BY t.name', $id ) );
	}

	/** מוצא איש קשר לפי מייל או טלפון, או יוצר חדש. מחזיר [id, נוצר_חדש] */
	public static function upsert_contact( $data, $source = '' ) {
		global $wpdb;
		$email = isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '';
		$phone = isset( $data['phone'] ) ? self::phone( $data['phone'] ) : '';
		$id    = 0;
		if ( $email ) { $id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::t( 'contacts' ) . ' WHERE account_id=%d AND email=%s LIMIT 1', self::account(), $email ) ); }
		if ( ! $id && $phone ) { $id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::t( 'contacts' ) . ' WHERE account_id=%d AND phone=%s LIMIT 1', self::account(), $phone ) ); }
		$fields = array();
		foreach ( array( 'first_name', 'last_name', 'company' ) as $f ) {
			if ( ! empty( $data[ $f ] ) ) { $fields[ $f ] = mb_substr( sanitize_text_field( $data[ $f ] ), 0, 120 ); }
		}
		if ( ! empty( $data['name'] ) && empty( $data['first_name'] ) ) {
			$parts = preg_split( '/\s+/', trim( sanitize_text_field( $data['name'] ) ), 2 );
			$fields['first_name'] = $parts[0];
			if ( isset( $parts[1] ) ) { $fields['last_name'] = $parts[1]; }
		}
		if ( $email ) { $fields['email'] = $email; }
		if ( $phone ) { $fields['phone'] = $phone; }
		$now = self::now();
		if ( $id ) {
			if ( $fields ) { $wpdb->update( self::t( 'contacts' ), $fields + array( 'updated_at' => $now ), array( 'id' => $id ) ); }
			return array( $id, false );
		}
		$wpdb->insert( self::t( 'contacts' ), $fields + array( 'account_id' => self::account(), 'source' => $source, 'created_at' => $now, 'updated_at' => $now, 'last_activity_at' => $now ) );
		$id = (int) $wpdb->insert_id;
		self::activity( $id, 'contact_created', array( 'source' => $source ) );
		MOS_Events::emit( 'contact_created', array( 'contact_id' => $id, 'source' => $source ) );
		return array( $id, true );
	}

	public static function activity( $contact_id, $type, $data = array() ) {
		global $wpdb;
		$now = self::now();
		$wpdb->insert( self::t( 'activities' ), array( 'account_id' => self::account(), 'contact_id' => $contact_id ? (int) $contact_id : null, 'type' => $type, 'data' => wp_json_encode( $data, JSON_UNESCAPED_UNICODE ), 'created_at' => $now ) );
		if ( $contact_id ) { $wpdb->update( self::t( 'contacts' ), array( 'last_activity_at' => $now ), array( 'id' => (int) $contact_id ) ); }
	}

	public static function tag_id( $name ) {
		global $wpdb;
		$name = mb_substr( trim( sanitize_text_field( $name ) ), 0, 80 );
		if ( '' === $name ) { return 0; }
		$id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::t( 'tags' ) . ' WHERE account_id=%d AND name=%s', self::account(), $name ) );
		if ( ! $id ) {
			$palette = array( '#7B2FBE', '#E8003D', '#5B8DEF', '#19B36B', '#F5A524', '#C026D3', '#0EA5E9' );
			$wpdb->insert( self::t( 'tags' ), array( 'account_id' => self::account(), 'name' => $name, 'color' => $palette[ crc32( $name ) % count( $palette ) ] ) );
			$id = (int) $wpdb->insert_id;
		}
		return $id;
	}

	public static function add_tag( $contact_id, $name ) {
		global $wpdb;
		$tid = self::tag_id( $name );
		if ( ! $tid ) { return; }
		$added = $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO ' . self::t( 'contact_tags' ) . ' (contact_id, tag_id) VALUES (%d, %d)', $contact_id, $tid ) );
		if ( $added ) {
			self::activity( $contact_id, 'tag_added', array( 'tag' => $name ) );
			MOS_Events::emit( 'tag_added', array( 'contact_id' => (int) $contact_id, 'tag' => $name ) );
		}
	}

	public static function remove_tag( $contact_id, $name ) {
		global $wpdb;
		$tid = self::tag_id( $name );
		$wpdb->delete( self::t( 'contact_tags' ), array( 'contact_id' => (int) $contact_id, 'tag_id' => $tid ) );
	}

	public static function setting( $key, $default = '' ) {
		$s = get_option( 'mos_settings', array() );
		return isset( $s[ $key ] ) && '' !== $s[ $key ] ? $s[ $key ] : $default;
	}
}
