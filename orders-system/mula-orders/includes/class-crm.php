<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * לוח בקרה בסגנון CRM: מדדים, צנרת לפי סטטוס, דורש טיפול, פעילות אחרונה ולקוחות.
 */
class MO_CRM {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 99 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function menu() {
		global $submenu;
		add_submenu_page( 'mo-orders', 'לוח בקרה', 'לוח בקרה', 'manage_options', 'mo-crm', array( __CLASS__, 'page_dashboard' ) );
		add_submenu_page( 'mo-orders', 'לקוחות', 'לקוחות', 'manage_options', 'mo-clients', array( __CLASS__, 'page_clients' ) );
		// לוח הבקרה ראשון, כך שלחיצה על "הזמנות" בתפריט פותחת אותו
		if ( isset( $submenu['mo-orders'] ) ) {
			$first = array(); $rest = array();
			foreach ( $submenu['mo-orders'] as $item ) {
				if ( 'mo-crm' === $item[2] ) { $first[] = $item; }
				elseif ( 'mo-clients' === $item[2] ) { $clients = $item; }
				else { $rest[] = $item; }
			}
			if ( isset( $clients ) ) { array_splice( $rest, 1, 0, array( $clients ) ); }
			$submenu['mo-orders'] = array_merge( $first, $rest ); // phpcs:ignore
		}
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'mo-crm' ) && false === strpos( (string) $hook, 'mo-clients' ) ) { return; }
		wp_enqueue_style( 'mo-crm', MO_URL . 'assets/crm.css', array(), MO_VERSION );
		wp_enqueue_style( 'mo-crm-fonts', 'https://fonts.googleapis.com/css2?family=Assistant:wght@400;600;700&family=Heebo:wght@700;800;900&display=swap', array(), null );
	}

	/* ---------------- עזרים ---------------- */

	private static function money( $n ) { return '₪' . number_format( (float) $n, 0, '.', ',' ); }

	private static function edit_url( $id ) { return admin_url( 'admin.php?page=mo-orders&action=edit&id=' . (int) $id ); }

	private static function ago( $mysql ) {
		if ( ! $mysql ) { return ''; }
		$d = time() - strtotime( $mysql . ' UTC' );
		if ( $d < HOUR_IN_SECONDS ) { return 'לפני ' . max( 1, (int) round( $d / 60 ) ) . ' דק׳'; }
		if ( $d < DAY_IN_SECONDS ) { $h = (int) round( $d / HOUR_IN_SECONDS ); return 1 === $h ? 'לפני שעה' : 'לפני ' . $h . ' שעות'; }
		$days = (int) floor( $d / DAY_IN_SECONDS );
		return 1 === $days ? 'אתמול' : 'לפני ' . $days . ' ימים';
	}

	private static function days_since( $mysql ) {
		return $mysql ? (int) floor( ( time() - strtotime( $mysql . ' UTC' ) ) / DAY_IN_SECONDS ) : 0;
	}

	private static function client_name( $o ) {
		return ! empty( $o->client['entity'] ) ? $o->client['entity'] : $o->client_label;
	}

	/** הזמנות "חיות": בלי גרסאות שהוחלפו */
	private static function orders() {
		return array_values( array_filter( MO_Store::all(), function ( $o ) { return 'replaced' !== $o->status; } ) );
	}

	private static function recent_events( $limit = 14 ) {
		global $wpdb;
		$t = MO_Store::t_events(); $o = MO_Store::t_orders();
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT e.*, o.number, o.client_label FROM $t e JOIN $o o ON o.id = e.order_id WHERE e.type <> 'visit' ORDER BY e.id DESC LIMIT %d", $limit
		) );
	}

	/** מה דורש טיפול ממך עכשיו */
	private static function alerts( $orders ) {
		$a = array();
		foreach ( $orders as $o ) {
			$st = $o->eff_status;
			if ( 'fix_requested' === $st ) {
				$a[] = array( 'red', 'הלקוח ביקש תיקון', $o, 'לעבור על הבקשה ולהוציא גרסה מעודכנת.' );
			} elseif ( 'opened' === $st && self::days_since( $o->opened_at ) >= 3 ) {
				$a[] = array( 'amber', 'נפתחה ולא נחתמה ' . self::days_since( $o->opened_at ) . ' ימים', $o, 'זמן טוב לשיחת פולואפ קצרה.' );
			} elseif ( 'sent' === $st && self::days_since( $o->sent_at ) >= 2 ) {
				$a[] = array( 'amber', 'נשלחה ועוד לא נפתחה', $o, 'לוודא שהלקוח קיבל את הקישור.' );
			} elseif ( 'expired' === $st ) {
				$a[] = array( 'gray', 'תוקף הקישור פג', $o, 'לחדש גישה ולשלוח שוב.' );
			} elseif ( in_array( $st, MO_Store::OPEN_STATUSES, true ) && $o->token_expires && strtotime( $o->token_expires . ' UTC' ) - time() < 3 * DAY_IN_SECONDS ) {
				$a[] = array( 'amber', 'הקישור פג בעוד פחות מ־3 ימים', $o, 'להזכיר ללקוח או להאריך.' );
			} elseif ( 'draft' === $st ) {
				$miss = count( MO_Store::missing_terms( $o ) );
				$a[] = array( 'violet', $miss ? 'טיוטה: חסרים ' . $miss . ' תנאים' : 'טיוטה מוכנה לשליחה', $o, $miss ? 'להשלים את התנאים כדי לשלוח.' : 'אפשר לנעול ולשלוח ללקוח.' );
			}
		}
		return $a;
	}

	/* ---------------- לוח בקרה ---------------- */

	public static function page_dashboard() {
		$orders = self::orders();
		$open   = array_filter( $orders, function ( $o ) { return in_array( $o->eff_status, MO_Store::OPEN_STATUSES, true ); } );
		$signed = array_filter( $orders, function ( $o ) { return 'signed' === $o->status; } );
		$sent_ever = array_filter( $orders, function ( $o ) { return ! empty( $o->sent_at ); } );
		$month  = gmdate( 'Y-m' );
		$signed_month = array_filter( $signed, function ( $o ) use ( $month ) { return $o->signed_at && 0 === strpos( $o->signed_at, $month ); } );
		$sum = function ( $list, $k ) { $s = 0; foreach ( $list as $o ) { $s += $o->totals[ $k ]; } return $s; };
		$conv = count( $sent_ever ) ? round( 100 * count( $signed ) / count( $sent_ever ) ) : null;

		echo '<div class="crm" dir="rtl">';
		echo '<header class="crm-top"><div><p class="crm-eyebrow">Mula Digital · מערכת הזמנות</p><h1>לוח בקרה</h1><p class="crm-sub">כל ההזמנות, מה זז ומה מחכה לך. במבט אחד.</p></div>';
		echo '<div class="crm-actions"><a class="crm-btn crm-btn-primary" href="' . esc_url( admin_url( 'admin.php?page=mo-new' ) ) . '">+ הזמנה חדשה</a><a class="crm-btn" href="' . esc_url( admin_url( 'admin.php?page=mo-clients' ) ) . '">לקוחות</a></div></header>';

		// מדדים
		echo '<section class="crm-kpis">';
		self::kpi( 'ממתין לחתימה', self::money( $sum( $open, 'once' ) ), count( $open ) . ' הזמנות פתוחות', 'grad' );
		self::kpi( 'חודשי חוזר בהזמנות פתוחות', self::money( $sum( $open, 'monthly' ) ), 'לחודש, אם ייחתמו', '' );
		self::kpi( 'נחתם החודש', self::money( $sum( $signed_month, 'once' ) ), count( $signed_month ) . ' הזמנות', '' );
		self::kpi( 'הכנסה חודשית חתומה', self::money( $sum( $signed, 'monthly' ) ), 'מכל ההזמנות החתומות', '' );
		self::kpi( 'אחוז סגירה', null === $conv ? '—' : $conv . '%', null === $conv ? 'יחושב אחרי השליחה הראשונה' : count( $signed ) . ' מתוך ' . count( $sent_ever ) . ' שנשלחו', '' );
		echo '</section>';

		echo '<div class="crm-grid">';

		// צנרת
		$cols = array(
			'draft'  => array( 'טיוטות', array( 'draft' ) ),
			'sent'   => array( 'נשלחו', array( 'sent' ) ),
			'opened' => array( 'נפתחו', array( 'opened', 'fix_requested' ) ),
			'signed' => array( 'נחתמו', array( 'signed' ) ),
			'closed' => array( 'נסגרו', array( 'cancelled', 'declined', 'expired' ) ),
		);
		echo '<section class="crm-card crm-pipe"><div class="crm-card-h"><h2>צנרת הזמנות</h2><span class="crm-muted">לפי שלב</span></div><div class="crm-cols">';
		foreach ( $cols as $key => $col ) {
			$items = array_filter( $orders, function ( $o ) use ( $col ) { return in_array( $o->eff_status, $col[1], true ); } );
			printf( '<div class="crm-col crm-col-%s"><div class="crm-col-h"><span>%s</span><b>%d</b></div>', esc_attr( $key ), esc_html( $col[0] ), count( $items ) );
			if ( ! $items ) { echo '<p class="crm-empty">אין כרגע</p>'; }
			foreach ( $items as $o ) {
				$flag = 'fix_requested' === $o->eff_status ? '<span class="crm-flag red">בקשת תיקון</span>' : ( 'expired' === $o->eff_status ? '<span class="crm-flag gray">פג תוקף</span>' : '' );
				printf(
					'<a class="crm-deal" href="%s"><span class="crm-deal-top"><bdi>%s</bdi>%s</span><strong>%s</strong><span class="crm-deal-sum"><bdi>%s</bdi> <em>+ <bdi>%s</bdi> לחודש</em></span><span class="crm-deal-time">%s</span></a>',
					esc_url( self::edit_url( $o->id ) ), esc_html( $o->number . ( $o->version > 1 ? ' · ג׳' . $o->version : '' ) ), $flag,
					esc_html( self::client_name( $o ) ), esc_html( self::money( $o->totals['once'] ) ), esc_html( self::money( $o->totals['monthly'] ) ),
					esc_html( self::ago( $o->updated_at ) )
				);
			}
			echo '</div>';
		}
		echo '</div></section>';

		// דורש טיפול
		$alerts = self::alerts( $orders );
		echo '<section class="crm-card crm-todo"><div class="crm-card-h"><h2>דורש טיפול</h2><span class="crm-count">' . count( $alerts ) . '</span></div>';
		if ( ! $alerts ) { echo '<p class="crm-empty big">הכל מטופל. אין משהו שמחכה לך.</p>'; }
		echo '<ul class="crm-alerts">';
		foreach ( $alerts as $al ) {
			printf( '<li class="crm-al %s"><a href="%s"><span class="crm-dot"></span><span class="crm-al-tx"><strong>%s</strong><span>%s · %s</span><em>%s</em></span></a></li>',
				esc_attr( $al[0] ), esc_url( self::edit_url( $al[2]->id ) ), esc_html( $al[1] ), esc_html( $al[2]->number ), esc_html( self::client_name( $al[2] ) ), esc_html( $al[3] ) );
		}
		echo '</ul></section>';

		// פעילות אחרונה
		$names = array(
			'created' => 'נוצרה הזמנה', 'edited' => 'טיוטה עודכנה', 'sent' => 'ננעלה ונשלח קישור', 'opened' => 'הלקוח פתח את ההזמנה',
			'client_details_saved' => 'הלקוח מילא פרטים', 'fix_requested' => 'הלקוח ביקש תיקון', 'cancelled' => 'ההזמנה בוטלה', 'replaced' => 'יצאה גרסה חדשה',
			'access_renewed' => 'הקישור חודש', 'expired_link_visit' => 'כניסה לקישור שפג', 'sign_attempt_no_provider' => 'ניסיון חתימה', 'note' => 'הערה פנימית',
		);
		$ev = self::recent_events();
		echo '<section class="crm-card crm-feed"><div class="crm-card-h"><h2>פעילות אחרונה</h2></div><ol class="crm-tl">';
		if ( ! $ev ) { echo '<li class="crm-empty">עוד אין פעילות.</li>'; }
		foreach ( $ev as $e ) {
			printf( '<li class="crm-tl-%s"><a href="%s"><strong>%s</strong><span>%s · %s</span><time>%s</time></a></li>',
				esc_attr( $e->actor ), esc_url( self::edit_url( $e->order_id ) ), esc_html( isset( $names[ $e->type ] ) ? $names[ $e->type ] : $e->type ),
				esc_html( $e->number ), esc_html( $e->client_label ), esc_html( self::ago( $e->created_at ) ) );
		}
		echo '</ol></section>';

		echo '</div></div>';
	}

	private static function kpi( $label, $value, $foot, $mod ) {
		printf( '<div class="crm-kpi %s"><span class="crm-kpi-l">%s</span><span class="crm-kpi-v"><bdi>%s</bdi></span><span class="crm-kpi-f">%s</span></div>',
			esc_attr( $mod ), esc_html( $label ), esc_html( $value ), esc_html( $foot ) );
	}

	/* ---------------- לקוחות ---------------- */

	public static function page_clients() {
		$orders  = self::orders();
		$clients = array();
		foreach ( $orders as $o ) {
			$name = self::client_name( $o );
			$key  = md5( mb_strtolower( trim( $name ) ) );
			if ( ! isset( $clients[ $key ] ) ) {
				$clients[ $key ] = array( 'name' => $name, 'orders' => array(), 'once' => 0, 'monthly' => 0, 'signed_monthly' => 0, 'phone' => '', 'email' => '', 'signer' => '', 'last' => '' );
			}
			$c = &$clients[ $key ];
			$c['orders'][] = $o;
			if ( ! in_array( $o->status, array( 'cancelled', 'declined' ), true ) ) { $c['once'] += $o->totals['once']; $c['monthly'] += $o->totals['monthly']; }
			if ( 'signed' === $o->status ) { $c['signed_monthly'] += $o->totals['monthly']; }
			foreach ( array( 'phone', 'email', 'signer' ) as $f ) { if ( ! $c[ $f ] && ! empty( $o->client[ $f ] ) ) { $c[ $f ] = $o->client[ $f ]; } }
			if ( $o->updated_at > $c['last'] ) { $c['last'] = $o->updated_at; }
			unset( $c );
		}
		uasort( $clients, function ( $a, $b ) { return strcmp( $b['last'], $a['last'] ); } );

		echo '<div class="crm" dir="rtl">';
		echo '<header class="crm-top"><div><p class="crm-eyebrow">Mula Digital · מערכת הזמנות</p><h1>לקוחות</h1><p class="crm-sub">כל לקוח, ההזמנות שלו והשווי. הפרטים מגיעים ממה שהלקוח מילא בקישור.</p></div>';
		echo '<div class="crm-actions"><a class="crm-btn crm-btn-primary" href="' . esc_url( admin_url( 'admin.php?page=mo-new' ) ) . '">+ הזמנה חדשה</a><a class="crm-btn" href="' . esc_url( admin_url( 'admin.php?page=mo-crm' ) ) . '">לוח בקרה</a></div></header>';

		if ( ! $clients ) { echo '<section class="crm-card"><p class="crm-empty big">עוד אין לקוחות.</p></section></div>'; return; }

		echo '<section class="crm-clients">';
		foreach ( $clients as $c ) {
			$latest = $c['orders'][0];
			$wa     = $c['phone'] ? 'https://wa.me/972' . ltrim( preg_replace( '/\D/', '', $c['phone'] ), '0' ) : '';
			echo '<article class="crm-card crm-client">';
			printf( '<div class="crm-client-h"><div class="crm-avatar">%s</div><div><h2>%s</h2><p class="crm-muted">%s</p></div>%s</div>',
				esc_html( mb_substr( $c['name'], 0, 1 ) ), esc_html( $c['name'] ),
				esc_html( $c['signer'] ? 'איש קשר: ' . $c['signer'] : 'פרטים יתמלאו כשהלקוח ייכנס לקישור' ),
				'<span class="crm-pill crm-s-' . esc_attr( $latest->eff_status ) . '">' . esc_html( MO_Store::STATUSES[ $latest->eff_status ] ?? $latest->eff_status ) . '</span>' );
			printf( '<div class="crm-client-nums"><div><span>הקמה</span><b><bdi>%s</bdi></b></div><div><span>חודשי</span><b><bdi>%s</bdi></b></div><div><span>הזמנות</span><b>%d</b></div></div>',
				esc_html( self::money( $c['once'] ) ), esc_html( self::money( $c['monthly'] ) ), count( $c['orders'] ) );
			echo '<ul class="crm-client-orders">';
			foreach ( $c['orders'] as $o ) {
				printf( '<li><a href="%s"><bdi>%s</bdi><span class="crm-pill crm-s-%s">%s</span><em>%s</em></a></li>', esc_url( self::edit_url( $o->id ) ),
					esc_html( $o->number . ( $o->version > 1 ? ' · ג׳' . $o->version : '' ) ), esc_attr( $o->eff_status ), esc_html( MO_Store::STATUSES[ $o->eff_status ] ?? $o->eff_status ), esc_html( self::ago( $o->updated_at ) ) );
			}
			echo '</ul><div class="crm-client-act">';
			if ( $c['phone'] ) {
				printf( '<a class="crm-btn sm" href="tel:%s">חיוג</a>', esc_attr( preg_replace( '/[^\d+]/', '', $c['phone'] ) ) );
				printf( '<a class="crm-btn sm" href="%s" target="_blank" rel="noopener">וואטסאפ</a>', esc_url( $wa ) );
			}
			if ( $c['email'] ) { printf( '<a class="crm-btn sm" href="mailto:%s">מייל</a>', esc_attr( $c['email'] ) ); }
			printf( '<a class="crm-btn sm crm-btn-ghost" href="%s">פתיחת ההזמנה האחרונה</a>', esc_url( self::edit_url( $latest->id ) ) );
			echo '</div></article>';
		}
		echo '</section></div>';
	}
}
