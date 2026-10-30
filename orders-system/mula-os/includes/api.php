<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * REST API פנימי לאפליקציה. כל הנתיבים מוגנים בהרשאת מנהל + nonce של וורדפרס.
 */
class MOS_API {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function can() { return current_user_can( 'manage_options' ); }

	private static function r( $path, $method, $cb ) {
		register_rest_route( 'mos/v1', $path, array( 'methods' => $method, 'callback' => $cb, 'permission_callback' => array( __CLASS__, 'can' ) ) );
	}

	public static function routes() {
		$c = __CLASS__;
		self::r( '/boot', 'GET', array( $c, 'boot' ) );
		self::r( '/dashboard', 'GET', array( $c, 'dashboard' ) );
		self::r( '/contacts', 'GET', array( $c, 'contacts' ) );
		self::r( '/contacts', 'POST', array( $c, 'contact_create' ) );
		self::r( '/contacts/(?P<id>\d+)', 'GET', array( $c, 'contact_get' ) );
		self::r( '/contacts/(?P<id>\d+)', 'PATCH', array( $c, 'contact_update' ) );
		self::r( '/contacts/(?P<id>\d+)', 'DELETE', array( $c, 'contact_delete' ) );
		self::r( '/contacts/(?P<id>\d+)/tags', 'POST', array( $c, 'tag_add' ) );
		self::r( '/contacts/(?P<id>\d+)/tags/remove', 'POST', array( $c, 'tag_remove' ) );
		self::r( '/contacts/(?P<id>\d+)/notes', 'POST', array( $c, 'note_add' ) );
		self::r( '/tasks', 'GET', array( $c, 'tasks' ) );
		self::r( '/tasks', 'POST', array( $c, 'task_create' ) );
		self::r( '/tasks/(?P<id>\d+)', 'PATCH', array( $c, 'task_update' ) );
		self::r( '/deals', 'GET', array( $c, 'deals' ) );
		self::r( '/deals', 'POST', array( $c, 'deal_create' ) );
		self::r( '/deals/(?P<id>\d+)', 'PATCH', array( $c, 'deal_update' ) );
		self::r( '/conversations', 'GET', array( $c, 'conversations' ) );
		self::r( '/conversations/(?P<cid>\d+)', 'GET', array( $c, 'thread' ) );
		self::r( '/send', 'POST', array( $c, 'send' ) );
		self::r( '/automations', 'GET', array( $c, 'automations' ) );
		self::r( '/automations', 'POST', array( $c, 'automation_save' ) );
		self::r( '/automations/(?P<id>\d+)', 'POST', array( $c, 'automation_save' ) );
		self::r( '/automations/(?P<id>\d+)/runs', 'GET', array( $c, 'automation_runs' ) );
		self::r( '/calendars', 'GET', array( $c, 'calendars' ) );
		self::r( '/calendars/(?P<id>\d+)', 'POST', array( $c, 'calendar_save' ) );
		self::r( '/appointments', 'GET', array( $c, 'appointments' ) );
		self::r( '/appointments/(?P<id>\d+)/cancel', 'POST', array( $c, 'appointment_cancel' ) );
		self::r( '/settings', 'GET', array( $c, 'settings_get' ) );
		self::r( '/settings', 'POST', array( $c, 'settings_save' ) );
	}

	/* ---------------- עזרים ---------------- */

	private static function t( $n ) { return MOS_DB::t( $n ); }
	private static function acc() { return MOS_DB::account(); }

	private static function contact_row( $c ) {
		return array(
			'id' => (int) $c->id, 'name' => MOS_DB::name( $c ), 'first_name' => $c->first_name, 'last_name' => $c->last_name, 'company' => $c->company,
			'email' => $c->email, 'phone' => MOS_DB::phone_local( $c->phone ), 'source' => $c->source, 'type' => $c->type, 'dnd' => (int) $c->dnd,
			'created_at' => $c->created_at, 'last_activity_at' => $c->last_activity_at,
		);
	}

	/* ---------------- אתחול ---------------- */

	public static function boot() {
		global $wpdb;
		$pipes  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'pipelines' ) . ' WHERE account_id=%d ORDER BY position', self::acc() ) );
		foreach ( $pipes as $p ) { $p->stages = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'stages' ) . ' WHERE pipeline_id=%d ORDER BY position', $p->id ) ); }
		$u = wp_get_current_user();
		return array(
			'user'      => array( 'name' => $u->display_name ),
			'pipelines' => $pipes,
			'tags'      => $wpdb->get_results( $wpdb->prepare( 'SELECT id,name,color FROM ' . self::t( 'tags' ) . ' WHERE account_id=%d ORDER BY name', self::acc() ) ),
			'calendars' => $wpdb->get_results( $wpdb->prepare( 'SELECT id,name,slug FROM ' . self::t( 'calendars' ) . ' WHERE account_id=%d', self::acc() ) ),
			'triggers'  => MOS_Events::TYPES,
			'steps'     => MOS_Engine::STEPS,
			'channels'  => array( 'email' => 'מייל', 'whatsapp' => 'וואטסאפ', 'form' => 'טופס' ),
			'wa_ready'  => (bool) ( MOS_DB::setting( 'wa_phone_id' ) && MOS_DB::setting( 'wa_token' ) ),
			'unread'    => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(unread),0) FROM ' . self::t( 'conversations' ) . ' WHERE account_id=%d', self::acc() ) ),
			'book_url'  => home_url( '/book/' ),
			'orders'    => class_exists( 'MO_Store' ) ? admin_url( 'admin.php?page=mo-crm' ) : '',
		);
	}

	public static function dashboard() {
		global $wpdb;
		$a     = self::acc();
		$month = gmdate( 'Y-m-01 00:00:00' );
		$open  = $wpdb->get_row( $wpdb->prepare( 'SELECT COUNT(*) n, COALESCE(SUM(value),0) v, COALESCE(SUM(monthly),0) m FROM ' . self::t( 'deals' ) . " WHERE account_id=%d AND status='open'", $a ) );
		$won   = $wpdb->get_row( $wpdb->prepare( 'SELECT COUNT(*) n, COALESCE(SUM(value),0) v, COALESCE(SUM(monthly),0) m FROM ' . self::t( 'deals' ) . " WHERE account_id=%d AND status='won' AND closed_at >= %s", $a, $month ) );
		$leads = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::t( 'contacts' ) . ' WHERE account_id=%d AND created_at >= %s', $a, $month ) );
		$closed_all = $wpdb->get_row( $wpdb->prepare( 'SELECT SUM(status=\'won\') w, SUM(status IN (\'won\',\'lost\')) t FROM ' . self::t( 'deals' ) . ' WHERE account_id=%d', $a ) );
		$tasks = $wpdb->get_results( $wpdb->prepare( 'SELECT t.*, c.first_name, c.last_name, c.company, c.email, c.phone FROM ' . self::t( 'tasks' ) . ' t LEFT JOIN ' . self::t( 'contacts' ) . ' c ON c.id=t.contact_id WHERE t.account_id=%d AND t.done=0 ORDER BY (t.due_at IS NULL), t.due_at ASC LIMIT 8', $a ) );
		foreach ( $tasks as $t ) { $t->contact = $t->contact_id ? MOS_DB::name( $t ) : ''; }
		$appts = $wpdb->get_results( $wpdb->prepare( 'SELECT a.*, k.name cal FROM ' . self::t( 'appointments' ) . ' a JOIN ' . self::t( 'calendars' ) . " k ON k.id=a.calendar_id WHERE a.account_id=%d AND a.status='booked' AND a.start_at >= %s ORDER BY a.start_at ASC LIMIT 6", $a, gmdate( 'Y-m-d H:i:s', time() - 3600 ) ) );
		foreach ( $appts as $x ) { $c = MOS_DB::contact( $x->contact_id ); $x->contact = $c ? MOS_DB::name( $c ) : ''; $x->when = MOS_Booking::local( strtotime( $x->start_at . ' UTC' ) ); }
		$acts = $wpdb->get_results( $wpdb->prepare( 'SELECT a.*, c.first_name, c.last_name, c.company, c.email, c.phone FROM ' . self::t( 'activities' ) . ' a LEFT JOIN ' . self::t( 'contacts' ) . ' c ON c.id=a.contact_id WHERE a.account_id=%d ORDER BY a.id DESC LIMIT 12', $a ) );
		foreach ( $acts as $x ) { $x->contact = $x->contact_id ? MOS_DB::name( $x ) : ''; $x->data = json_decode( $x->data, true ); }
		// משפך לפי שלב
		$funnel = $wpdb->get_results( $wpdb->prepare( 'SELECT s.id, s.name, s.color, COUNT(d.id) n, COALESCE(SUM(d.value),0) v FROM ' . self::t( 'stages' ) . ' s JOIN ' . self::t( 'pipelines' ) . ' p ON p.id=s.pipeline_id LEFT JOIN ' . self::t( 'deals' ) . " d ON d.stage_id=s.id AND d.status='open' WHERE p.account_id=%d AND s.kind='open' GROUP BY s.id ORDER BY p.position, s.position", $a ) );
		return array(
			'kpi' => array(
				'pipeline'   => (float) $open->v, 'pipeline_n' => (int) $open->n, 'pipeline_m' => (float) $open->m,
				'won'        => (float) $won->v, 'won_n' => (int) $won->n, 'won_m' => (float) $won->m,
				'leads'      => $leads,
				'conv'       => $closed_all && $closed_all->t ? round( 100 * $closed_all->w / $closed_all->t ) : null,
			),
			'tasks' => $tasks, 'appointments' => $appts, 'activities' => $acts, 'funnel' => $funnel,
		);
	}

	/* ---------------- אנשי קשר ---------------- */

	public static function contacts( WP_REST_Request $r ) {
		global $wpdb;
		$where = array( $wpdb->prepare( 'c.account_id=%d', self::acc() ) );
		$join  = '';
		if ( $q = trim( (string) $r->get_param( 'q' ) ) ) {
			$like    = '%' . $wpdb->esc_like( $q ) . '%';
			$qp      = '%' . $wpdb->esc_like( ltrim( preg_replace( '/\D/', '', $q ), '0' ) ) . '%';
			$where[] = $wpdb->prepare( '(c.first_name LIKE %s OR c.last_name LIKE %s OR c.company LIKE %s OR c.email LIKE %s' . ( strlen( $qp ) > 4 ? ' OR c.phone LIKE %s' : ' OR 0=%s' ) . ')', $like, $like, $like, $like, strlen( $qp ) > 4 ? $qp : '1' );
		}
		if ( $tag = (int) $r->get_param( 'tag' ) ) {
			$join    = ' JOIN ' . self::t( 'contact_tags' ) . ' ct ON ct.contact_id=c.id';
			$where[] = $wpdb->prepare( 'ct.tag_id=%d', $tag );
		}
		if ( $type = sanitize_key( (string) $r->get_param( 'type' ) ) ) { $where[] = $wpdb->prepare( 'c.type=%s', $type ); }
		$page  = max( 1, (int) $r->get_param( 'page' ) );
		$sql_w = implode( ' AND ', $where );
		$total = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::t( 'contacts' ) . " c $join WHERE $sql_w" );
		$rows  = $wpdb->get_results( 'SELECT c.* FROM ' . self::t( 'contacts' ) . " c $join WHERE $sql_w ORDER BY COALESCE(c.last_activity_at,c.created_at) DESC LIMIT 50 OFFSET " . ( ( $page - 1 ) * 50 ) );
		$out   = array();
		foreach ( $rows as $c ) { $row = self::contact_row( $c ); $row['tags'] = MOS_DB::contact_tags( $c->id ); $out[] = $row; }
		return array( 'total' => $total, 'items' => $out );
	}

	public static function contact_create( WP_REST_Request $r ) {
		$p = $r->get_json_params();
		if ( empty( $p['email'] ) && empty( $p['phone'] ) && empty( $p['first_name'] ) && empty( $p['company'] ) ) {
			return new WP_Error( 'mos', 'צריך לפחות שם, טלפון או מייל.', array( 'status' => 422 ) );
		}
		if ( empty( $p['email'] ) && empty( $p['phone'] ) ) {
			global $wpdb;
			$wpdb->insert( self::t( 'contacts' ), array( 'account_id' => self::acc(), 'first_name' => sanitize_text_field( $p['first_name'] ?? '' ), 'last_name' => sanitize_text_field( $p['last_name'] ?? '' ), 'company' => sanitize_text_field( $p['company'] ?? '' ), 'source' => 'ידני', 'created_at' => MOS_DB::now(), 'updated_at' => MOS_DB::now(), 'last_activity_at' => MOS_DB::now() ) );
			$id = (int) $wpdb->insert_id;
			MOS_DB::activity( $id, 'contact_created', array( 'source' => 'ידני' ) );
			MOS_Events::emit( 'contact_created', array( 'contact_id' => $id, 'source' => 'ידני' ) );
		} else {
			list( $id ) = MOS_DB::upsert_contact( $p, 'ידני' );
		}
		return array( 'id' => $id );
	}

	public static function contact_get( WP_REST_Request $r ) {
		global $wpdb;
		$c = MOS_DB::contact( (int) $r['id'] );
		if ( ! $c ) { return new WP_Error( 'nf', 'לא נמצא', array( 'status' => 404 ) ); }
		$out           = self::contact_row( $c );
		$out['tags']   = $c->tags;
		$out['custom'] = $c->custom;
		$out['notes']  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'notes' ) . ' WHERE contact_id=%d ORDER BY id DESC', $c->id ) );
		$out['tasks']  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'tasks' ) . ' WHERE contact_id=%d ORDER BY done, due_at', $c->id ) );
		$out['deals']  = $wpdb->get_results( $wpdb->prepare( 'SELECT d.*, s.name stage, s.color FROM ' . self::t( 'deals' ) . ' d JOIN ' . self::t( 'stages' ) . ' s ON s.id=d.stage_id WHERE d.contact_id=%d ORDER BY d.id DESC', $c->id ) );
		$acts = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'activities' ) . ' WHERE contact_id=%d ORDER BY id DESC LIMIT 80', $c->id ) );
		foreach ( $acts as $a ) { $a->data = json_decode( $a->data, true ); }
		$out['activities'] = $acts;
		$aps = $wpdb->get_results( $wpdb->prepare( 'SELECT a.*, k.name cal FROM ' . self::t( 'appointments' ) . ' a JOIN ' . self::t( 'calendars' ) . ' k ON k.id=a.calendar_id WHERE a.contact_id=%d ORDER BY a.start_at DESC', $c->id ) );
		foreach ( $aps as $x ) { $x->when = MOS_Booking::local( strtotime( $x->start_at . ' UTC' ) ); }
		$out['appointments'] = $aps;
		if ( class_exists( 'MO_Store' ) ) {
			$out['orders'] = array();
			foreach ( $out['deals'] as $d ) {
				if ( $d->order_id && ( $o = MO_Store::get( (int) $d->order_id ) ) ) {
					$out['orders'][] = array( 'id' => (int) $o->id, 'number' => $o->number, 'status' => MO_Store::STATUSES[ $o->eff_status ] ?? $o->eff_status, 'url' => admin_url( 'admin.php?page=mo-orders&action=edit&id=' . $o->id ) );
				}
			}
		}
		return $out;
	}

	public static function contact_update( WP_REST_Request $r ) {
		global $wpdb;
		$c = MOS_DB::contact( (int) $r['id'] );
		if ( ! $c ) { return new WP_Error( 'nf', 'לא נמצא', array( 'status' => 404 ) ); }
		$p = $r->get_json_params();
		$f = array();
		foreach ( array( 'first_name', 'last_name', 'company', 'source' ) as $k ) { if ( isset( $p[ $k ] ) ) { $f[ $k ] = mb_substr( sanitize_text_field( $p[ $k ] ), 0, 120 ); } }
		if ( isset( $p['email'] ) ) { $f['email'] = sanitize_email( $p['email'] ); }
		if ( isset( $p['phone'] ) ) { $f['phone'] = MOS_DB::phone( $p['phone'] ); }
		if ( isset( $p['type'] ) && in_array( $p['type'], array( 'lead', 'client', 'partner' ), true ) ) { $f['type'] = $p['type']; }
		if ( isset( $p['dnd'] ) ) { $f['dnd'] = $p['dnd'] ? 1 : 0; }
		if ( $f ) { $wpdb->update( self::t( 'contacts' ), $f + array( 'updated_at' => MOS_DB::now() ), array( 'id' => $c->id ) ); }
		return array( 'ok' => true );
	}

	public static function contact_delete( WP_REST_Request $r ) {
		global $wpdb;
		$id = (int) $r['id'];
		if ( ! MOS_DB::contact( $id ) ) { return new WP_Error( 'nf', 'לא נמצא', array( 'status' => 404 ) ); }
		foreach ( array( 'contact_tags', 'notes', 'activities', 'tasks', 'messages', 'conversations' ) as $t ) { $wpdb->delete( self::t( $t ), array( 'contact_id' => $id ) ); }
		$wpdb->update( self::t( 'deals' ), array( 'contact_id' => null ), array( 'contact_id' => $id ) );
		$wpdb->delete( self::t( 'contacts' ), array( 'id' => $id ) );
		return array( 'ok' => true );
	}

	public static function tag_add( WP_REST_Request $r ) { MOS_DB::add_tag( (int) $r['id'], (string) $r->get_param( 'tag' ) ); return MOS_DB::contact_tags( (int) $r['id'] ); }
	public static function tag_remove( WP_REST_Request $r ) { MOS_DB::remove_tag( (int) $r['id'], (string) $r->get_param( 'tag' ) ); return MOS_DB::contact_tags( (int) $r['id'] ); }

	public static function note_add( WP_REST_Request $r ) {
		global $wpdb;
		$body = trim( sanitize_textarea_field( (string) $r->get_param( 'body' ) ) );
		if ( '' === $body ) { return new WP_Error( 'mos', 'הערה ריקה', array( 'status' => 422 ) ); }
		$wpdb->insert( self::t( 'notes' ), array( 'contact_id' => (int) $r['id'], 'body' => $body, 'author' => get_current_user_id(), 'created_at' => MOS_DB::now() ) );
		MOS_DB::activity( (int) $r['id'], 'note', array( 'preview' => mb_substr( $body, 0, 120 ) ) );
		return array( 'ok' => true );
	}

	/* ---------------- משימות ---------------- */

	public static function tasks( WP_REST_Request $r ) {
		global $wpdb;
		$done = 'done' === $r->get_param( 'scope' ) ? 1 : 0;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT t.*, c.first_name, c.last_name, c.company, c.email, c.phone FROM ' . self::t( 'tasks' ) . ' t LEFT JOIN ' . self::t( 'contacts' ) . ' c ON c.id=t.contact_id WHERE t.account_id=%d AND t.done=%d ORDER BY ' . ( $done ? 't.done_at DESC' : '(t.due_at IS NULL), t.due_at ASC' ) . ' LIMIT 200', self::acc(), $done ) );
		foreach ( $rows as $t ) { $t->contact = $t->contact_id ? MOS_DB::name( $t ) : ''; }
		return $rows;
	}

	public static function task_create( WP_REST_Request $r ) {
		global $wpdb;
		$p = $r->get_json_params();
		$title = trim( sanitize_text_field( $p['title'] ?? '' ) );
		if ( '' === $title ) { return new WP_Error( 'mos', 'חסרה כותרת', array( 'status' => 422 ) ); }
		$due = ! empty( $p['due_at'] ) ? self::local_to_utc( $p['due_at'] ) : null;
		$wpdb->insert( self::t( 'tasks' ), array( 'account_id' => self::acc(), 'contact_id' => ! empty( $p['contact_id'] ) ? (int) $p['contact_id'] : null, 'deal_id' => ! empty( $p['deal_id'] ) ? (int) $p['deal_id'] : null, 'title' => $title, 'due_at' => $due, 'created_at' => MOS_DB::now() ) );
		if ( ! empty( $p['contact_id'] ) ) { MOS_DB::activity( (int) $p['contact_id'], 'task_created', array( 'title' => $title ) ); }
		return array( 'id' => (int) $wpdb->insert_id );
	}

	public static function task_update( WP_REST_Request $r ) {
		global $wpdb;
		$p = $r->get_json_params(); $f = array();
		if ( isset( $p['done'] ) ) { $f['done'] = $p['done'] ? 1 : 0; $f['done_at'] = $p['done'] ? MOS_DB::now() : null; }
		if ( isset( $p['title'] ) ) { $f['title'] = sanitize_text_field( $p['title'] ); }
		if ( array_key_exists( 'due_at', $p ) ) { $f['due_at'] = $p['due_at'] ? self::local_to_utc( $p['due_at'] ) : null; }
		$wpdb->update( self::t( 'tasks' ), $f, array( 'id' => (int) $r['id'], 'account_id' => self::acc() ) );
		return array( 'ok' => true );
	}

	public static function local_to_utc( $local ) {
		try { $d = new DateTime( $local, new DateTimeZone( MOS_TZ ) ); $d->setTimezone( new DateTimeZone( 'UTC' ) ); return $d->format( 'Y-m-d H:i:s' ); } catch ( Exception $e ) { return null; }
	}

	/* ---------------- עסקאות ---------------- */

	public static function deals( WP_REST_Request $r ) {
		global $wpdb;
		$pid  = (int) $r->get_param( 'pipeline' );
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT d.*, c.first_name, c.last_name, c.company, c.email, c.phone FROM ' . self::t( 'deals' ) . ' d LEFT JOIN ' . self::t( 'contacts' ) . ' c ON c.id=d.contact_id WHERE d.account_id=%d AND d.pipeline_id=%d ORDER BY d.position ASC, d.updated_at DESC', self::acc(), $pid ) );
		foreach ( $rows as $d ) { $d->contact = $d->contact_id ? MOS_DB::name( $d ) : ''; $d->value = (float) $d->value; $d->monthly = (float) $d->monthly; }
		return $rows;
	}

	public static function deal_create( WP_REST_Request $r ) {
		global $wpdb;
		$p = $r->get_json_params();
		$stage = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'stages' ) . ' WHERE id=%d', (int) ( $p['stage_id'] ?? 0 ) ) );
		if ( ! $stage ) { return new WP_Error( 'mos', 'שלב לא תקין', array( 'status' => 422 ) ); }
		$wpdb->insert( self::t( 'deals' ), array(
			'account_id' => self::acc(), 'contact_id' => ! empty( $p['contact_id'] ) ? (int) $p['contact_id'] : null, 'pipeline_id' => $stage->pipeline_id, 'stage_id' => $stage->id,
			'title' => sanitize_text_field( $p['title'] ?? 'עסקה חדשה' ), 'value' => (float) ( $p['value'] ?? 0 ), 'monthly' => (float) ( $p['monthly'] ?? 0 ), 'created_at' => MOS_DB::now(), 'updated_at' => MOS_DB::now(),
		) );
		$id = (int) $wpdb->insert_id;
		if ( ! empty( $p['contact_id'] ) ) { MOS_DB::activity( (int) $p['contact_id'], 'deal_created', array( 'title' => $p['title'] ?? '', 'stage' => $stage->name ) ); }
		return array( 'id' => $id );
	}

	public static function deal_update( WP_REST_Request $r ) {
		global $wpdb;
		$id = (int) $r['id'];
		$p  = $r->get_json_params();
		$f  = array();
		foreach ( array( 'title' ) as $k ) { if ( isset( $p[ $k ] ) ) { $f[ $k ] = sanitize_text_field( $p[ $k ] ); } }
		foreach ( array( 'value', 'monthly' ) as $k ) { if ( isset( $p[ $k ] ) ) { $f[ $k ] = (float) $p[ $k ]; } }
		if ( isset( $p['contact_id'] ) ) { $f['contact_id'] = (int) $p['contact_id'] ?: null; }
		if ( $f ) { $wpdb->update( self::t( 'deals' ), $f + array( 'updated_at' => MOS_DB::now() ), array( 'id' => $id, 'account_id' => self::acc() ) ); }
		if ( ! empty( $p['stage_id'] ) ) { self::move_deal( $id, (int) $p['stage_id'], true, isset( $p['order'] ) ? array_map( 'intval', (array) $p['order'] ) : null ); }
		return array( 'ok' => true );
	}

	/** העברת עסקה לשלב, כולל סטטוס זכייה/הפסד ואירוע לאוטומציות */
	public static function move_deal( $id, $stage_id, $emit = true, $order = null ) {
		global $wpdb;
		$deal  = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'deals' ) . ' WHERE id=%d', $id ) );
		$stage = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'stages' ) . ' WHERE id=%d', $stage_id ) );
		if ( ! $deal || ! $stage ) { return; }
		$changed = (int) $deal->stage_id !== (int) $stage->id;
		$status  = 'won' === $stage->kind ? 'won' : ( 'lost' === $stage->kind ? 'lost' : 'open' );
		$wpdb->update( self::t( 'deals' ), array( 'stage_id' => $stage->id, 'pipeline_id' => $stage->pipeline_id, 'status' => $status, 'closed_at' => 'open' === $status ? null : MOS_DB::now(), 'updated_at' => MOS_DB::now() ), array( 'id' => $id ) );
		if ( $order ) { foreach ( $order as $pos => $did ) { $wpdb->update( self::t( 'deals' ), array( 'position' => $pos ), array( 'id' => $did, 'stage_id' => $stage->id ) ); } }
		if ( $changed ) {
			if ( $deal->contact_id ) { MOS_DB::activity( $deal->contact_id, 'stage_changed', array( 'deal' => $deal->title, 'stage' => $stage->name ) ); }
			if ( $emit ) { MOS_Events::emit( 'stage_changed', array( 'contact_id' => (int) $deal->contact_id, 'deal_id' => $id, 'stage_id' => (int) $stage->id ) ); }
		}
	}

	/* ---------------- תיבת הודעות ---------------- */

	public static function conversations( WP_REST_Request $r ) {
		global $wpdb;
		$w = $wpdb->prepare( 'v.account_id=%d', self::acc() );
		if ( 'unread' === $r->get_param( 'filter' ) ) { $w .= ' AND v.unread>0'; }
		if ( $ch = sanitize_key( (string) $r->get_param( 'channel' ) ) ) { $w .= $wpdb->prepare( ' AND v.last_channel=%s', $ch ); }
		$rows = $wpdb->get_results( 'SELECT v.*, c.first_name, c.last_name, c.company, c.email, c.phone FROM ' . self::t( 'conversations' ) . ' v JOIN ' . self::t( 'contacts' ) . " c ON c.id=v.contact_id WHERE $w AND v.last_message_at IS NOT NULL ORDER BY v.last_message_at DESC LIMIT 100" );
		foreach ( $rows as $v ) { $v->name = MOS_DB::name( $v ); }
		return $rows;
	}

	public static function thread( WP_REST_Request $r ) {
		global $wpdb;
		$cid  = (int) $r['cid'];
		$c    = MOS_DB::contact( $cid );
		if ( ! $c ) { return new WP_Error( 'nf', 'לא נמצא', array( 'status' => 404 ) ); }
		$msgs = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'messages' ) . ' WHERE contact_id=%d ORDER BY id ASC LIMIT 300', $cid ) );
		$wpdb->update( self::t( 'conversations' ), array( 'unread' => 0 ), array( 'contact_id' => $cid, 'account_id' => self::acc() ) );
		$last_in = $wpdb->get_var( $wpdb->prepare( 'SELECT created_at FROM ' . self::t( 'messages' ) . " WHERE contact_id=%d AND channel='whatsapp' AND direction='in' ORDER BY id DESC LIMIT 1", $cid ) );
		return array( 'contact' => self::contact_row( $c ) + array( 'tags' => $c->tags ), 'messages' => $msgs, 'wa_window' => $last_in && ( time() - strtotime( $last_in . ' UTC' ) ) < DAY_IN_SECONDS );
	}

	public static function send( WP_REST_Request $r ) {
		$p   = $r->get_json_params();
		$res = MOS_Channels::send( (int) ( $p['contact_id'] ?? 0 ), sanitize_key( $p['channel'] ?? '' ), wp_kses_post( $p['body'] ?? '' ), sanitize_text_field( $p['subject'] ?? '' ), array( 'template' => sanitize_text_field( $p['template'] ?? '' ), 'force' => true ) );
		return is_wp_error( $res ) ? new WP_Error( 'mos', $res->get_error_message(), array( 'status' => 400 ) ) : array( 'ok' => true );
	}

	/* ---------------- אוטומציות ---------------- */

	public static function automations() {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'automations' ) . ' WHERE account_id=%d ORDER BY id DESC', self::acc() ) );
		foreach ( $rows as $a ) {
			$a->trigger_cfg = json_decode( $a->trigger_cfg, true ) ?: new stdClass();
			$a->steps = json_decode( $a->steps, true ) ?: array();
			$a->waiting = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::t( 'runs' ) . " WHERE automation_id=%d AND status='waiting'", $a->id ) );
		}
		return $rows;
	}

	public static function automation_save( WP_REST_Request $r ) {
		global $wpdb;
		$p = $r->get_json_params();
		$trigger = sanitize_key( $p['trigger_type'] ?? '' );
		if ( ! isset( MOS_Events::TYPES[ $trigger ] ) ) { return new WP_Error( 'mos', 'טריגר לא תקין', array( 'status' => 422 ) ); }
		$steps = array();
		foreach ( (array) ( $p['steps'] ?? array() ) as $s ) {
			if ( empty( $s['type'] ) || ! isset( MOS_Engine::STEPS[ $s['type'] ] ) ) { continue; }
			$clean = array( 'type' => $s['type'] );
			foreach ( $s as $k => $v ) {
				if ( 'type' === $k ) { continue; }
				$clean[ sanitize_key( $k ) ] = is_numeric( $v ) ? $v + 0 : ( is_bool( $v ) ? $v : sanitize_textarea_field( (string) $v ) );
			}
			$steps[] = $clean;
		}
		$cfg = array();
		foreach ( (array) ( $p['trigger_cfg'] ?? array() ) as $k => $v ) { $cfg[ sanitize_key( $k ) ] = is_bool( $v ) ? $v : sanitize_text_field( (string) $v ); }
		$f = array( 'name' => sanitize_text_field( $p['name'] ?? 'אוטומציה' ), 'trigger_type' => $trigger, 'trigger_cfg' => wp_json_encode( $cfg, JSON_UNESCAPED_UNICODE ), 'steps' => wp_json_encode( $steps, JSON_UNESCAPED_UNICODE ), 'active' => ! empty( $p['active'] ) ? 1 : 0, 'updated_at' => MOS_DB::now() );
		if ( ! empty( $r['id'] ) ) {
			$wpdb->update( self::t( 'automations' ), $f, array( 'id' => (int) $r['id'], 'account_id' => self::acc() ) );
			return array( 'id' => (int) $r['id'] );
		}
		$wpdb->insert( self::t( 'automations' ), $f + array( 'account_id' => self::acc(), 'created_at' => MOS_DB::now() ) );
		return array( 'id' => (int) $wpdb->insert_id );
	}

	public static function automation_runs( WP_REST_Request $r ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT r.*, c.first_name, c.last_name, c.company, c.email, c.phone FROM ' . self::t( 'runs' ) . ' r LEFT JOIN ' . self::t( 'contacts' ) . ' c ON c.id=r.contact_id WHERE r.automation_id=%d ORDER BY r.id DESC LIMIT 50', (int) $r['id'] ) );
		foreach ( $rows as $x ) { $x->contact = $x->contact_id ? MOS_DB::name( $x ) : ''; $x->log = json_decode( $x->log, true ); }
		return $rows;
	}

	/* ---------------- יומן ---------------- */

	public static function calendars() {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'calendars' ) . ' WHERE account_id=%d', self::acc() ) );
		foreach ( $rows as $c ) { $c->availability = json_decode( $c->availability, true ) ?: new stdClass(); $c->url = home_url( '/book/' . $c->slug ); }
		return $rows;
	}

	public static function calendar_save( WP_REST_Request $r ) {
		global $wpdb;
		$p = $r->get_json_params();
		$av = array();
		foreach ( (array) ( $p['availability'] ?? array() ) as $d => $wins ) {
			foreach ( (array) $wins as $w ) {
				if ( preg_match( '/^\d{2}:\d{2}$/', $w[0] ?? '' ) && preg_match( '/^\d{2}:\d{2}$/', $w[1] ?? '' ) && $w[0] < $w[1] ) { $av[ (int) $d ][] = array( $w[0], $w[1] ); }
			}
		}
		$wpdb->update( self::t( 'calendars' ), array(
			'name' => sanitize_text_field( $p['name'] ?? '' ), 'description' => sanitize_textarea_field( $p['description'] ?? '' ), 'location' => sanitize_text_field( $p['location'] ?? '' ),
			'duration' => max( 10, (int) ( $p['duration'] ?? 30 ) ), 'buffer' => max( 0, (int) ( $p['buffer'] ?? 0 ) ), 'notice_hours' => max( 0, (int) ( $p['notice_hours'] ?? 12 ) ),
			'days_ahead' => min( 90, max( 1, (int) ( $p['days_ahead'] ?? 21 ) ) ), 'availability' => wp_json_encode( $av ), 'active' => ! empty( $p['active'] ) ? 1 : 0,
		), array( 'id' => (int) $r['id'], 'account_id' => self::acc() ) );
		return array( 'ok' => true );
	}

	public static function appointments( WP_REST_Request $r ) {
		global $wpdb;
		$from = self::local_to_utc( (string) $r->get_param( 'from' ) ) ?: gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS );
		$to   = self::local_to_utc( (string) $r->get_param( 'to' ) ) ?: gmdate( 'Y-m-d H:i:s', time() + 30 * DAY_IN_SECONDS );
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT a.*, k.name cal FROM ' . self::t( 'appointments' ) . ' a JOIN ' . self::t( 'calendars' ) . ' k ON k.id=a.calendar_id WHERE a.account_id=%d AND a.start_at BETWEEN %s AND %s ORDER BY a.start_at', self::acc(), $from, $to ) );
		foreach ( $rows as $x ) {
			$c = MOS_DB::contact( $x->contact_id );
			$x->contact = $c ? MOS_DB::name( $c ) : '';
			$x->phone   = $c ? MOS_DB::phone_local( $c->phone ) : '';
			$x->when    = MOS_Booking::local( strtotime( $x->start_at . ' UTC' ) );
			$x->start_ts = strtotime( $x->start_at . ' UTC' ); $x->end_ts = strtotime( $x->end_at . ' UTC' );
		}
		return $rows;
	}

	public static function appointment_cancel( WP_REST_Request $r ) {
		global $wpdb;
		$ap = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'appointments' ) . " WHERE id=%d AND account_id=%d AND status='booked'", (int) $r['id'], self::acc() ) );
		if ( ! $ap ) { return new WP_Error( 'nf', 'לא נמצא', array( 'status' => 404 ) ); }
		MOS_Booking::cancel( $ap, 'admin' );
		return array( 'ok' => true );
	}

	/* ---------------- הגדרות ---------------- */

	const SECRETS = array( 'wa_token', 'wa_app_secret' );

	public static function settings_get() {
		$s = get_option( 'mos_settings', array() );
		foreach ( self::SECRETS as $k ) { $s[ $k ] = ! empty( $s[ $k ] ) ? '••••••••' . substr( $s[ $k ], -4 ) : ''; }
		$s['form_endpoint'] = rest_url( 'mos/v1/hook/form' );
		$s['wa_webhook']    = rest_url( 'mos/v1/hook/whatsapp' );
		$s['admin_email']   = get_option( 'admin_email' );
		return $s;
	}

	public static function settings_save( WP_REST_Request $r ) {
		$s = get_option( 'mos_settings', array() );
		$p = $r->get_json_params();
		foreach ( array( 'from_name', 'from_email', 'reply_to', 'notify_email', 'wa_phone_id', 'wa_business_id' ) as $k ) {
			if ( isset( $p[ $k ] ) ) { $s[ $k ] = sanitize_text_field( $p[ $k ] ); }
		}
		foreach ( self::SECRETS as $k ) {
			if ( isset( $p[ $k ] ) && '' !== $p[ $k ] && false === strpos( $p[ $k ], '••' ) ) { $s[ $k ] = trim( sanitize_text_field( $p[ $k ] ) ); }
		}
		if ( ! empty( $p['rotate_form_key'] ) ) { $s['form_key'] = wp_generate_password( 24, false, false ); }
		update_option( 'mos_settings', $s, false );
		return self::settings_get();
	}
}
