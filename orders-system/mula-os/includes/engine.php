<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * מנוע האוטומציות: טריגר ← שלבים. כל ריצה נשמרת בתור ומתקדמת שלב אחרי שלב,
 * כולל המתנות ("חכה שעתיים", "חכה עד שעה לפני הפגישה").
 */
class MOS_Engine {

	const STEPS = array(
		'wait'          => 'המתנה',
		'wait_before'   => 'המתנה עד לפני הפגישה',
		'send_email'    => 'שליחת מייל',
		'send_whatsapp' => 'שליחת וואטסאפ',
		'add_tag'       => 'הוספת תגית',
		'remove_tag'    => 'הסרת תגית',
		'create_task'   => 'יצירת משימה',
		'move_stage'    => 'העברת עסקה לשלב',
		'notify_me'     => 'התראה אליי',
		'if_tag'        => 'תנאי: יש תגית',
	);

	public static function init() {
		add_filter( 'cron_schedules', function ( $s ) { $s['mos_minute'] = array( 'interval' => 60, 'display' => 'Every minute' ); return $s; } );
		if ( ! wp_next_scheduled( 'mos_tick' ) ) { wp_schedule_event( time() + 60, 'mos_minute', 'mos_tick' ); }
		add_action( 'mos_tick', array( __CLASS__, 'process_due' ), 10 );
	}

	public static function on_event( $type, $payload ) {
		global $wpdb;
		$autos = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . MOS_DB::t( 'automations' ) . ' WHERE account_id=%d AND trigger_type=%s AND active=1', MOS_DB::account(), $type ) );
		foreach ( $autos as $a ) {
			$cfg = $a->trigger_cfg ? json_decode( $a->trigger_cfg, true ) : array();
			if ( ! self::matches( $cfg, $payload ) ) { continue; }
			$cid = isset( $payload['contact_id'] ) ? (int) $payload['contact_id'] : null;
			if ( $cid && empty( $cfg['allow_repeat'] ) ) {
				$active = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . MOS_DB::t( 'runs' ) . " WHERE automation_id=%d AND contact_id=%d AND status IN ('queued','waiting')", $a->id, $cid ) );
				if ( $active ) { continue; }
			}
			$wpdb->insert( MOS_DB::t( 'runs' ), array(
				'automation_id' => $a->id, 'contact_id' => $cid, 'step' => 0, 'run_at' => MOS_DB::now(), 'status' => 'queued',
				'context' => wp_json_encode( $payload, JSON_UNESCAPED_UNICODE ), 'log' => '[]', 'created_at' => MOS_DB::now(),
			) );
			$wpdb->query( $wpdb->prepare( 'UPDATE ' . MOS_DB::t( 'automations' ) . ' SET runs=runs+1 WHERE id=%d', $a->id ) );
			self::advance( (int) $wpdb->insert_id );
		}
	}

	private static function matches( $cfg, $p ) {
		if ( ! empty( $cfg['source'] ) && ( empty( $p['source'] ) || false === mb_stripos( $p['source'], $cfg['source'] ) ) ) { return false; }
		if ( ! empty( $cfg['tag'] ) && ( empty( $p['tag'] ) || $p['tag'] !== $cfg['tag'] ) ) { return false; }
		if ( ! empty( $cfg['stage_id'] ) && ( empty( $p['stage_id'] ) || (int) $p['stage_id'] !== (int) $cfg['stage_id'] ) ) { return false; }
		if ( ! empty( $cfg['channel'] ) && ( empty( $p['channel'] ) || $p['channel'] !== $cfg['channel'] ) ) { return false; }
		if ( ! empty( $cfg['calendar_id'] ) && ( empty( $p['calendar_id'] ) || (int) $p['calendar_id'] !== (int) $cfg['calendar_id'] ) ) { return false; }
		return true;
	}

	public static function process_due() {
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . MOS_DB::t( 'runs' ) . " WHERE status IN ('queued','waiting') AND run_at <= %s ORDER BY run_at ASC LIMIT 40", MOS_DB::now() ) );
		foreach ( $ids as $id ) { self::advance( (int) $id ); }
	}

	/** מריץ שלבים עד המתנה או סוף */
	public static function advance( $run_id ) {
		global $wpdb;
		$tr  = MOS_DB::t( 'runs' );
		// נעילה: רק תהליך אחד מקדם ריצה
		$got = $wpdb->query( $wpdb->prepare( "UPDATE $tr SET status='running' WHERE id=%d AND status IN ('queued','waiting')", $run_id ) );
		if ( ! $got ) { return; }
		$run  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $tr WHERE id=%d", $run_id ) );
		$auto = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . MOS_DB::t( 'automations' ) . ' WHERE id=%d', $run->automation_id ) );
		$log  = json_decode( $run->log, true ) ?: array();
		if ( ! $auto || ! $auto->active ) {
			$wpdb->update( $tr, array( 'status' => 'stopped' ), array( 'id' => $run_id ) );
			return;
		}
		$steps = json_decode( $auto->steps, true ) ?: array();
		$ctx   = json_decode( $run->context, true ) ?: array();
		$i     = (int) $run->step;
		$guard = 0;
		while ( $i < count( $steps ) && $guard++ < 30 ) {
			$s   = $steps[ $i ];
			$res = self::run_step( $s, $run, $ctx );
			$log[] = array( 'step' => $i, 'type' => $s['type'], 'at' => MOS_DB::now(), 'result' => is_wp_error( $res ) ? $res->get_error_message() : ( is_array( $res ) ? ( $res['note'] ?? 'ok' ) : 'ok' ) );
			if ( is_array( $res ) && isset( $res['wait_until'] ) ) {
				$wpdb->update( $tr, array( 'status' => 'waiting', 'step' => $i + 1, 'run_at' => $res['wait_until'], 'log' => wp_json_encode( $log, JSON_UNESCAPED_UNICODE ) ), array( 'id' => $run_id ) );
				return;
			}
			if ( is_array( $res ) && ! empty( $res['stop'] ) ) { break; }
			$i++;
		}
		$wpdb->update( $tr, array( 'status' => 'done', 'step' => $i, 'log' => wp_json_encode( $log, JSON_UNESCAPED_UNICODE ) ), array( 'id' => $run_id ) );
	}

	private static function run_step( $s, $run, $ctx ) {
		global $wpdb;
		$cid = (int) $run->contact_id;
		switch ( $s['type'] ) {
			case 'wait':
				$min = max( 1, (int) ( $s['minutes'] ?? 60 ) );
				return array( 'wait_until' => gmdate( 'Y-m-d H:i:s', time() + $min * 60 ), 'note' => 'ממתין ' . $min . ' דקות' );
			case 'wait_before':
				if ( empty( $ctx['appointment_id'] ) ) { return array( 'note' => 'אין פגישה בהקשר' ); }
				$ap = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . MOS_DB::t( 'appointments' ) . ' WHERE id=%d', $ctx['appointment_id'] ) );
				if ( ! $ap || 'booked' !== $ap->status ) { return array( 'stop' => true, 'note' => 'הפגישה בוטלה' ); }
				$at = strtotime( $ap->start_at . ' UTC' ) - max( 0, (int) ( $s['minutes'] ?? 60 ) ) * 60;
				return $at > time() ? array( 'wait_until' => gmdate( 'Y-m-d H:i:s', $at ), 'note' => 'ממתין עד לפני הפגישה' ) : array( 'note' => 'הזמן כבר עבר' );
			case 'send_email':
				return $cid ? MOS_Channels::send( $cid, 'email', $s['body'] ?? '', $s['subject'] ?? '' ) : new WP_Error( 'x', 'אין איש קשר' );
			case 'send_whatsapp':
				return $cid ? MOS_Channels::send( $cid, 'whatsapp', $s['body'] ?? '', '', array( 'template' => $s['template'] ?? '', 'params' => array_filter( array_map( 'trim', explode( '|', $s['params'] ?? '' ) ) ) ) ) : new WP_Error( 'x', 'אין איש קשר' );
			case 'add_tag':
				if ( $cid ) { MOS_DB::add_tag( $cid, $s['tag'] ?? '' ); }
				return true;
			case 'remove_tag':
				if ( $cid ) { MOS_DB::remove_tag( $cid, $s['tag'] ?? '' ); }
				return true;
			case 'create_task':
				$wpdb->insert( MOS_DB::t( 'tasks' ), array( 'account_id' => MOS_DB::account(), 'contact_id' => $cid ?: null, 'title' => $s['title'] ?? 'משימה', 'due_at' => gmdate( 'Y-m-d H:i:s', time() + 60 * (int) ( $s['due_minutes'] ?? 1440 ) ), 'created_at' => MOS_DB::now() ) );
				return true;
			case 'move_stage':
				if ( ! $cid || empty( $s['stage_id'] ) ) { return true; }
				$stage = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . MOS_DB::t( 'stages' ) . ' WHERE id=%d', $s['stage_id'] ) );
				if ( ! $stage ) { return true; }
				$deal = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . MOS_DB::t( 'deals' ) . " WHERE contact_id=%d AND status='open' ORDER BY id DESC LIMIT 1", $cid ) );
				if ( $deal ) {
					MOS_API::move_deal( (int) $deal, (int) $stage->id, false );
				} else {
					$c = MOS_DB::contact( $cid );
					$wpdb->insert( MOS_DB::t( 'deals' ), array( 'account_id' => MOS_DB::account(), 'contact_id' => $cid, 'pipeline_id' => $stage->pipeline_id, 'stage_id' => $stage->id, 'title' => $c ? MOS_DB::name( $c ) : 'עסקה', 'created_at' => MOS_DB::now(), 'updated_at' => MOS_DB::now() ) );
				}
				return true;
			case 'notify_me':
				$c = $cid ? MOS_DB::contact( $cid ) : null;
				$body = $c ? MOS_Channels::merge( $s['body'] ?? '', $c ) : ( $s['body'] ?? '' );
				$link = admin_url( 'admin.php?page=mula-os#/contacts/' . $cid );
				wp_mail( MOS_DB::setting( 'notify_email', get_option( 'admin_email' ) ), '[Mula OS] ' . ( $s['subject'] ?? 'התראה' ), $body . "\n\n" . $link );
				return true;
			case 'if_tag':
				if ( ! $cid ) { return array( 'stop' => true ); }
				$has = in_array( $s['tag'] ?? '', wp_list_pluck( MOS_DB::contact_tags( $cid ), 'name' ), true );
				$want = empty( $s['negate'] );
				return $has === $want ? array( 'note' => 'התנאי התקיים' ) : array( 'stop' => true, 'note' => 'התנאי לא התקיים, עצירה' );
		}
		return true;
	}
}
