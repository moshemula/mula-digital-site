<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * אוטובוס אירועים: כל דבר שקורה במערכת עובר כאן, ומכאן לאוטומציות.
 * כולל גשר למערכת ההזמנות (Mula Orders): פתיחה, מילוי פרטים, בקשת תיקון וחתימה נכנסים לציר הזמן ולאוטומציות.
 */
class MOS_Events {

	const TYPES = array(
		'contact_created'   => 'איש קשר חדש נוצר',
		'form_submitted'    => 'טופס נשלח',
		'message_received'  => 'הודעה נכנסת',
		'tag_added'         => 'תגית נוספה',
		'stage_changed'     => 'עסקה עברה שלב',
		'appointment_booked'=> 'נקבעה פגישה',
		'appointment_cancelled' => 'פגישה בוטלה',
		'order_opened'      => 'לקוח פתח הזמנה',
		'order_fix'         => 'לקוח ביקש תיקון בהזמנה',
		'order_signed'      => 'הזמנה נחתמה',
	);

	public static function init() {
		add_action( 'mos_tick', array( __CLASS__, 'sync_orders' ), 5 );
	}

	public static function emit( $type, $payload = array() ) {
		do_action( 'mos_event', $type, $payload );
		MOS_Engine::on_event( $type, $payload );
	}

	/** קורא אירועים חדשים מטבלת ההזמנות ומתרגם אותם */
	public static function sync_orders() {
		global $wpdb;
		$ev = $wpdb->prefix . 'mo_events';
		$or = $wpdb->prefix . 'mo_orders';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $ev ) ) !== $ev ) { return; }
		$last = (int) get_option( 'mos_orders_cursor', 0 );
		if ( ! $last ) { // בהפעלה ראשונה מתחילים מעכשיו
			update_option( 'mos_orders_cursor', (int) $wpdb->get_var( "SELECT MAX(id) FROM $ev" ), false );
			return;
		}
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $ev WHERE id > %d AND type IN ('opened','client_details_saved','fix_requested','signed','sent') ORDER BY id ASC LIMIT 50", $last ) );
		foreach ( $rows as $r ) {
			$last  = (int) $r->id;
			if ( ! class_exists( 'MO_Store' ) ) { continue; }
			$order = MO_Store::get( (int) $r->order_id );
			if ( ! $order ) { continue; }
			$cid = self::contact_for_order( $order );
			$map = array( 'opened' => 'order_opened', 'fix_requested' => 'order_fix', 'signed' => 'order_signed' );
			MOS_DB::activity( $cid, 'order_' . $r->type, array( 'order' => $order->number, 'order_id' => (int) $order->id ) );
			if ( isset( $map[ $r->type ] ) ) {
				self::emit( $map[ $r->type ], array( 'contact_id' => $cid, 'order_id' => (int) $order->id, 'order' => $order->number ) );
			}
			if ( 'signed' === $r->type ) {
				$wpdb->update( MOS_DB::t( 'deals' ), array( 'status' => 'won', 'closed_at' => MOS_DB::now() ), array( 'order_id' => (int) $order->id ) );
			}
		}
		update_option( 'mos_orders_cursor', $last, false );
	}

	/** איש קשר להזמנה: לפי פרטי הלקוח, ואם עוד אין, לפי שם הלקוח בהזמנה */
	public static function contact_for_order( $order ) {
		global $wpdb;
		$c = is_array( $order->client ) ? $order->client : array();
		if ( ! empty( $c['email'] ) || ! empty( $c['phone'] ) ) {
			list( $id ) = MOS_DB::upsert_contact( array(
				'name' => isset( $c['signer'] ) ? $c['signer'] : '', 'company' => isset( $c['entity'] ) ? $c['entity'] : '',
				'email' => isset( $c['email'] ) ? $c['email'] : '', 'phone' => isset( $c['phone'] ) ? $c['phone'] : '',
			), 'הזמנה' );
		} else {
			$id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT contact_id FROM ' . MOS_DB::t( 'deals' ) . ' WHERE order_id=%d', $order->id ) );
			if ( ! $id ) {
				$wpdb->insert( MOS_DB::t( 'contacts' ), array( 'account_id' => MOS_DB::account(), 'company' => $order->client_label, 'source' => 'הזמנה', 'created_at' => MOS_DB::now(), 'updated_at' => MOS_DB::now() ) );
				$id = (int) $wpdb->insert_id;
			}
		}
		// עסקה מקושרת להזמנה
		$deal = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . MOS_DB::t( 'deals' ) . ' WHERE order_id=%d', $order->id ) );
		if ( ! $deal ) {
			$stage = $wpdb->get_row( "SELECT s.id, s.pipeline_id FROM " . MOS_DB::t( 'stages' ) . " s WHERE s.name='נשלחה הצעה' LIMIT 1" );
			if ( $stage ) {
				$wpdb->insert( MOS_DB::t( 'deals' ), array(
					'account_id' => MOS_DB::account(), 'contact_id' => $id, 'pipeline_id' => $stage->pipeline_id, 'stage_id' => $stage->id,
					'title' => $order->number . ' · ' . $order->client_label, 'value' => $order->totals['once'], 'monthly' => $order->totals['monthly'],
					'order_id' => (int) $order->id, 'created_at' => MOS_DB::now(), 'updated_at' => MOS_DB::now(),
				) );
			}
		} else {
			$wpdb->update( MOS_DB::t( 'deals' ), array( 'contact_id' => $id ), array( 'id' => (int) $deal ) );
		}
		return $id;
	}
}
