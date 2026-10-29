<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * ממשק הניהול בעברית.
 */
class MO_Admin {

	const CAP = 'manage_options';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		foreach ( array( 'new', 'save', 'send', 'renew', 'cancel', 'version', 'save_tpl', 'del_tpl', 'save_lib', 'settings', 'note' ) as $a ) {
			add_action( 'admin_post_mo_' . $a, array( __CLASS__, 'act_' . $a ) );
		}
	}

	public static function menu() {
		add_menu_page( 'הזמנות', 'הזמנות', self::CAP, 'mo-orders', array( __CLASS__, 'page_orders' ), 'dashicons-media-document', 3 );
		add_submenu_page( 'mo-orders', 'כל ההזמנות', 'כל ההזמנות', self::CAP, 'mo-orders', array( __CLASS__, 'page_orders' ) );
		add_submenu_page( 'mo-orders', 'הזמנה חדשה', 'הזמנה חדשה', self::CAP, 'mo-new', array( __CLASS__, 'page_new' ) );
		add_submenu_page( 'mo-orders', 'תבניות', 'תבניות', self::CAP, 'mo-templates', array( __CLASS__, 'page_templates' ) );
		add_submenu_page( 'mo-orders', 'ספריית שירותים', 'ספריית שירותים', self::CAP, 'mo-library', array( __CLASS__, 'page_library' ) );
		add_submenu_page( 'mo-orders', 'הגדרות', 'הגדרות', self::CAP, 'mo-settings', array( __CLASS__, 'page_settings' ) );
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'mo-' ) ) { return; }
		wp_enqueue_style( 'mo-admin', MO_URL . 'assets/admin.css', array(), MO_VERSION );
		wp_enqueue_script( 'mo-admin', MO_URL . 'assets/admin.js', array(), MO_VERSION, true );
		wp_localize_script( 'mo-admin', 'MOA', array( 'library' => MO_Store::services_library() ) );
	}

	/* ---------------- עזרים ---------------- */

	private static function guard( $nonce ) {
		if ( ! current_user_can( self::CAP ) ) { wp_die( 'אין הרשאה' ); }
		check_admin_referer( $nonce );
	}

	private static function back( $url, $msg = '', $type = 'success' ) {
		if ( $msg ) {
			set_transient( 'mo_flash_' . get_current_user_id(), array( 'msg' => $msg, 'type' => $type ), 60 );
		}
		wp_safe_redirect( $url );
		exit;
	}

	private static function flash() {
		$k = 'mo_flash_' . get_current_user_id();
		$f = get_transient( $k );
		if ( $f ) {
			delete_transient( $k );
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $f['type'] ), wp_kses_post( $f['msg'] ) );
		}
	}

	private static function edit_url( $id ) { return admin_url( 'admin.php?page=mo-orders&action=edit&id=' . (int) $id ); }

	private static function money( $n ) { return '₪' . number_format( (float) $n, 0, '.', ',' ); }

	private static function badge( $status ) {
		$l = isset( MO_Store::STATUSES[ $status ] ) ? MO_Store::STATUSES[ $status ] : $status;
		return '<span class="mo-badge mo-s-' . esc_attr( $status ) . '">' . esc_html( $l ) . '</span>';
	}

	private static function dt( $mysql ) {
		if ( ! $mysql ) { return '—'; }
		return esc_html( wp_date( 'd.m.Y H:i', strtotime( $mysql . ' UTC' ) ) );
	}

	/** קליטת שירותים מהטופס */
	private static function read_services() {
		$out = array();
		$n   = isset( $_POST['svc_name'] ) ? (array) $_POST['svc_name'] : array();
		foreach ( $n as $i => $name ) {
			$name = sanitize_text_field( wp_unslash( $name ) );
			if ( '' === $name ) { continue; }
			$out[] = array(
				'name'  => $name,
				'desc'  => sanitize_text_field( wp_unslash( isset( $_POST['svc_desc'][ $i ] ) ? $_POST['svc_desc'][ $i ] : '' ) ),
				'type'  => ( isset( $_POST['svc_type'][ $i ] ) && 'monthly' === $_POST['svc_type'][ $i ] ) ? 'monthly' : 'once',
				'price' => max( 0, round( (float) str_replace( ',', '', wp_unslash( isset( $_POST['svc_price'][ $i ] ) ? $_POST['svc_price'][ $i ] : 0 ) ), 2 ) ),
			);
		}
		return $out;
	}

	private static function read_terms() {
		$t = array();
		foreach ( MO_Store::TERM_KEYS as $k => $title ) {
			$t[ $k ] = isset( $_POST['term'][ $k ] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST['term'][ $k ] ) ) ) : '';
		}
		return $t;
	}

	private static function read_notes() {
		$raw = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';
		return array_values( array_filter( array_map( 'trim', explode( "\n", $raw ) ) ) );
	}

	/* ---------------- עמודים ---------------- */

	public static function page_orders() {
		if ( isset( $_GET['action'], $_GET['id'] ) && 'edit' === $_GET['action'] ) {
			self::page_edit( (int) $_GET['id'] );
			return;
		}
		$orders = MO_Store::all();
		echo '<div class="wrap mo-wrap"><h1 class="wp-heading-inline">הזמנות</h1> <a class="page-title-action" href="' . esc_url( admin_url( 'admin.php?page=mo-new' ) ) . '">הזמנה חדשה</a><hr class="wp-header-end">';
		self::flash();
		if ( ! $orders ) {
			echo '<div class="mo-empty-state"><p>עוד אין הזמנות.</p><a class="button button-primary button-hero" href="' . esc_url( admin_url( 'admin.php?page=mo-new' ) ) . '">יצירת הזמנה ראשונה</a></div></div>';
			return;
		}
		echo '<table class="widefat striped mo-table"><thead><tr><th>מספר</th><th>לקוח</th><th>סטטוס</th><th>חד־פעמי</th><th>חודשי</th><th>נשלחה</th><th>נפתחה</th><th>נחתמה</th></tr></thead><tbody>';
		foreach ( $orders as $o ) {
			printf(
				'<tr><td><a href="%s"><strong>%s</strong></a>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
				esc_url( self::edit_url( $o->id ) ),
				esc_html( $o->number ),
				$o->version > 1 ? ' <span class="mo-muted">גרסה ' . (int) $o->version . '</span>' : '',
				esc_html( $o->client_label ),
				self::badge( $o->eff_status ),
				esc_html( self::money( $o->totals['once'] ) ),
				esc_html( self::money( $o->totals['monthly'] ) ),
				self::dt( $o->sent_at ),
				self::dt( $o->opened_at ),
				self::dt( $o->signed_at )
			);
		}
		echo '</tbody></table></div>';
	}

	public static function page_new() {
		echo '<div class="wrap mo-wrap"><h1>הזמנה חדשה</h1>';
		self::flash();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="mo-card mo-narrow">';
		wp_nonce_field( 'mo_new' );
		echo '<input type="hidden" name="action" value="mo_new">';
		echo '<p><label class="mo-label" for="client_label">לקוח (כפי שיופיע בפתיח)</label><input class="regular-text mo-full" id="client_label" name="client_label" required placeholder="למשל: משה · עבודות גמר ושיפוץ משרדים"></p>';
		echo '<p><label class="mo-label" for="tpl">מתבנית</label><select id="tpl" name="tpl" class="mo-full"><option value="">הזמנה ריקה</option>';
		foreach ( MO_Store::templates() as $t ) {
			printf( '<option value="%s">%s (%d שירותים)</option>', esc_attr( $t['id'] ), esc_html( $t['name'] ), count( $t['services'] ) );
		}
		echo '</select></p><p class="mo-muted">ההזמנה נוצרת כטיוטה. אפשר לשנות שירותים, מחירים ותנאים לפני השליחה. התבנית עצמה לא משתנה.</p>';
		submit_button( 'יצירת טיוטה' );
		echo '</form></div>';
	}

	private static function page_edit( $id ) {
		$o = MO_Store::get( $id );
		if ( ! $o ) { echo '<div class="wrap"><p>ההזמנה לא נמצאה.</p></div>'; return; }
		$draft   = ( 'draft' === $o->status );
		$preview = wp_nonce_url( home_url( '/o-preview/' . $o->id . '/' ), 'mo_preview_' . $o->id );
		$missing = MO_Store::missing_terms( $o );
		$post    = esc_url( admin_url( 'admin-post.php' ) );

		echo '<div class="wrap mo-wrap">';
		printf( '<h1 class="wp-heading-inline">%s%s</h1> %s <a class="page-title-action" href="%s" target="_blank" rel="noopener">תצוגה כמו אצל הלקוח</a><hr class="wp-header-end">',
			esc_html( $o->number ), $o->version > 1 ? ' · גרסה ' . (int) $o->version : '', self::badge( $o->eff_status ), esc_url( $preview ) );
		self::flash();

		echo '<div class="mo-grid"><div class="mo-main">';

		if ( $draft ) {
			echo '<form method="post" action="' . $post . '" id="moEdit">';
			wp_nonce_field( 'mo_save_' . $o->id );
			echo '<input type="hidden" name="action" value="mo_save"><input type="hidden" name="id" value="' . (int) $o->id . '">';

			echo '<div class="mo-card"><h2>לקוח</h2><p><label class="mo-label" for="client_label">שם הלקוח בפתיח</label><input class="mo-full" id="client_label" name="client_label" value="' . esc_attr( $o->client_label ) . '" required></p>';
			echo '<p class="mo-muted">את הפרטים המשפטיים (שם רשום, מספר עוסק, חותם) ממלא הלקוח בעצמו בקישור.</p></div>';

			echo '<div class="mo-card"><h2>שירותים ומחירים</h2><p class="mo-muted">מחירים לפני מע״מ. הלקוח לא יכול לשנות אותם.</p>';
			echo '<table class="mo-svc-table"><thead><tr><th>שירות</th><th>תיאור ההיקף</th><th>חיוב</th><th>מחיר ₪</th><th></th></tr></thead><tbody id="moSvcRows">';
			foreach ( $o->services as $s ) { self::svc_row( $s ); }
			echo '</tbody></table>';
			echo '<template id="moSvcTpl">'; self::svc_row( array( 'name' => '', 'desc' => '', 'type' => 'once', 'price' => '' ) ); echo '</template>';
			echo '<div class="mo-svc-actions"><button type="button" class="button" id="moAddRow">שורה ריקה</button> <select id="moLib"><option value="">הוספה מהספרייה…</option>';
			foreach ( MO_Store::services_library() as $i => $s ) {
				printf( '<option value="%d">%s · %s%s</option>', (int) $i, esc_html( $s['name'] ), esc_html( self::money( $s['price'] ) ), 'monthly' === $s['type'] ? ' לחודש' : '' );
			}
			echo '</select></div>';
			printf( '<div class="mo-totals" id="moTotals">חד־פעמי: <strong>%s</strong> · חודשי: <strong>%s</strong></div></div>', esc_html( self::money( $o->totals['once'] ) ), esc_html( self::money( $o->totals['monthly'] ) ) );

			echo '<div class="mo-card"><h2>תנאי ההזמנה</h2><p class="mo-muted">תנאי ריק מוצג ללקוח כ"ממתין להשלמה" וחוסם שליחה לחתימה. לא נכנס שום תנאי כברירת מחדל.</p>';
			foreach ( MO_Store::TERM_KEYS as $k => $title ) {
				$v = isset( $o->terms[ $k ] ) ? $o->terms[ $k ] : '';
				printf( '<p><label class="mo-label" for="t_%1$s">%2$s %3$s</label><textarea class="mo-full" rows="3" id="t_%1$s" name="term[%1$s]">%4$s</textarea></p>',
					esc_attr( $k ), esc_html( $title ), '' === trim( $v ) ? '<span class="mo-pend">חסר</span>' : '<span class="mo-ok">הושלם</span>', esc_textarea( $v ) );
			}
			echo '</div>';

			echo '<div class="mo-card"><h2>הבהרות בסיכום</h2><p class="mo-muted">שורה לכל הבהרה.</p><textarea class="mo-full" rows="6" name="notes">' . esc_textarea( implode( "\n", $o->notes ) ) . '</textarea></div>';

			echo '<p class="mo-sticky-save"><button class="button button-primary button-large">שמירת טיוטה</button></p></form>';

			// שמירה כתבנית
			echo '<form method="post" action="' . $post . '" class="mo-card mo-inline">';
			wp_nonce_field( 'mo_save_tpl' );
			echo '<input type="hidden" name="action" value="mo_save_tpl"><input type="hidden" name="from_order" value="' . (int) $o->id . '">';
			echo '<h2>שמירה כתבנית</h2><p class="mo-muted">שומר את השירותים, התנאים וההבהרות של הטיוטה (כפי שנשמרו) כתבנית ללקוחות הבאים.</p>';
			echo '<input name="tpl_name" class="regular-text" placeholder="שם התבנית" required> <button class="button">שמירה כתבנית</button></form>';
		} else {
			self::readonly_doc( $o );
		}

		self::events_box( $o );
		echo '</div><div class="mo-side">';
		self::side_actions( $o, $missing, $post );
		echo '</div></div></div>';
	}

	private static function svc_row( $s ) {
		printf(
			'<tr class="mo-svc-row"><td><input class="mo-full" name="svc_name[]" value="%s" placeholder="שם השירות"></td><td><input class="mo-full" name="svc_desc[]" value="%s" placeholder="מה בדיוק כלול"></td><td><select name="svc_type[]"><option value="once"%s>חד־פעמי</option><option value="monthly"%s>חודשי</option></select></td><td><input class="mo-price" name="svc_price[]" type="number" min="0" step="1" value="%s" dir="ltr"></td><td><button type="button" class="button-link mo-del" aria-label="הסרת שורה">הסרה</button></td></tr>',
			esc_attr( $s['name'] ), esc_attr( $s['desc'] ),
			selected( $s['type'], 'once', false ), selected( $s['type'], 'monthly', false ),
			esc_attr( '' === $s['price'] ? '' : (float) $s['price'] )
		);
	}

	private static function readonly_doc( $o ) {
		$doc = $o->doc_json ? json_decode( $o->doc_json, true ) : MO_Store::document( $o );
		echo '<div class="mo-card"><h2>המסמך הנעול</h2>';
		echo '<p class="mo-muted">ננעל בשליחה. שינוי מסחרי מחייב גרסה חדשה. טביעת מסמך: <code dir="ltr">' . esc_html( substr( (string) $o->doc_hash, 0, 16 ) ) . '…</code></p>';
		echo '<table class="widefat striped"><thead><tr><th>שירות</th><th>חיוב</th><th>מחיר</th></tr></thead><tbody>';
		foreach ( $doc['services'] as $s ) {
			printf( '<tr><td><strong>%s</strong><br><span class="mo-muted">%s</span></td><td>%s</td><td>%s</td></tr>', esc_html( $s['name'] ), esc_html( $s['desc'] ), 'monthly' === $s['type'] ? 'חודשי' : 'חד־פעמי', esc_html( self::money( $s['price'] ) ) );
		}
		printf( '</tbody></table><p class="mo-totals">חד־פעמי: <strong>%s</strong> · חודשי: <strong>%s</strong></p>', esc_html( self::money( $doc['totals']['once'] ) ), esc_html( self::money( $doc['totals']['monthly'] ) ) );
		echo '<h3>תנאים</h3><dl class="mo-terms">';
		foreach ( $doc['terms'] as $t ) {
			printf( '<dt>%s</dt><dd>%s</dd>', esc_html( $t['title'] ), '' === trim( $t['body'] ) ? '<span class="mo-pend">חסר</span>' : nl2br( esc_html( $t['body'] ) ) );
		}
		echo '</dl></div>';

		if ( $o->client ) {
			$labels = array( 'entity' => 'שם משפטי', 'ctype' => 'סוג', 'reg' => 'מספר עוסק / ח.פ.', 'signer' => 'חותם', 'role' => 'תפקיד', 'phone' => 'טלפון', 'email' => 'דוא״ל', 'addr' => 'כתובת' );
			echo '<div class="mo-card"><h2>פרטי הלקוח</h2><table class="form-table mo-kv">';
			foreach ( $labels as $k => $l ) {
				if ( empty( $o->client[ $k ] ) ) { continue; }
				$v = 'ctype' === $k ? ( 'company' === $o->client[ $k ] ? 'חברה בע״מ' : 'עוסק / עצמאי' ) : $o->client[ $k ];
				printf( '<tr><th>%s</th><td>%s</td></tr>', esc_html( $l ), esc_html( $v ) );
			}
			echo '</table></div>';
		}
	}

	private static function events_box( $o ) {
		$names = array(
			'created' => 'נוצרה', 'edited' => 'נערכה', 'sent' => 'ננעלה ונוצר קישור', 'opened' => 'הלקוח פתח את הקישור לראשונה', 'visit' => 'ביקור חוזר של הלקוח',
			'client_details_saved' => 'הלקוח שמר פרטים', 'fix_requested' => 'בקשת תיקון', 'cancelled' => 'בוטלה', 'replaced' => 'הוחלפה בגרסה חדשה',
			'access_renewed' => 'גישה חודשה (קישור חדש)', 'expired_link_visit' => 'ניסיון כניסה לקישור שפג', 'sign_attempt_no_provider' => 'ניסיון חתימה (ספק לא מחובר)',
			'integrity_fail' => 'שגיאת שלמות מסמך', 'link_copied' => 'הקישור הועתק', 'note' => 'הערה',
		);
		$ev = MO_Store::events( $o->id );
		echo '<div class="mo-card"><h2>תיעוד פעולות</h2><ul class="mo-events">';
		foreach ( $ev as $e ) {
			$d = $e->data ? json_decode( $e->data, true ) : array();
			$extra = '';
			if ( ! empty( $d['text'] ) ) { $extra = '<blockquote>' . nl2br( esc_html( $d['text'] ) ) . '</blockquote>'; }
			if ( ! empty( $d['by'] ) ) { $extra = ' <a href="' . esc_url( self::edit_url( (int) $d['by'] ) ) . '">לגרסה החדשה</a>'; }
			printf( '<li><time>%s</time> <span class="mo-actor mo-a-%s">%s</span> %s%s</li>',
				self::dt( $e->created_at ), esc_attr( $e->actor ), 'client' === $e->actor ? 'לקוח' : ( 'admin' === $e->actor ? 'מנהל' : 'מערכת' ),
				esc_html( isset( $names[ $e->type ] ) ? $names[ $e->type ] : $e->type ), $extra );
		}
		echo '</ul></div>';
	}

	private static function side_actions( $o, $missing, $post ) {
		$st = $o->eff_status;
		echo '<div class="mo-card">';
		printf( '<h2>סיכום</h2><p class="mo-big">%s <span class="mo-muted">חד־פעמי</span></p><p class="mo-big">%s <span class="mo-muted">לחודש</span></p><p class="mo-muted">לפני מע״מ</p>', esc_html( self::money( $o->totals['once'] ) ), esc_html( self::money( $o->totals['monthly'] ) ) );
		echo '</div>';

		if ( 'draft' === $o->status ) {
			echo '<div class="mo-card"><h2>שליחה לחתימה</h2>';
			if ( $missing ) {
				echo '<p class="mo-warn">אי אפשר לשלוח עדיין. חסרים ' . count( $missing ) . ' תנאים:</p><ul class="mo-list">';
				foreach ( $missing as $t ) { echo '<li>' . esc_html( $t ) . '</li>'; }
				echo '</ul><p class="mo-muted">אפשר לבדוק בינתיים את העמוד ב"תצוגה כמו אצל הלקוח".</p>';
			} else {
				echo '<form method="post" action="' . $post . '">';
				wp_nonce_field( 'mo_send_' . $o->id );
				echo '<input type="hidden" name="action" value="mo_send"><input type="hidden" name="id" value="' . (int) $o->id . '">';
				echo '<p><label class="mo-label">תוקף הקישור (ימים)</label><input type="number" name="days" min="1" max="90" value="' . (int) get_option( 'mo_days', 14 ) . '" dir="ltr"></p>';
				echo '<p class="mo-muted">השליחה נועלת את המסמך ויוצרת קישור אישי. הקישור לא נשלח אוטומטית, אתה שולח אותו.</p>';
				echo '<button class="button button-primary button-large mo-full">נעילה ויצירת קישור</button></form>';
			}
			echo '</div>';
			return;
		}

		if ( in_array( $o->status, MO_Store::OPEN_STATUSES, true ) ) {
			$link = MO_Store::link_for( $o );
			echo '<div class="mo-card"><h2>הקישור של הלקוח</h2>';
			if ( 'expired' === $st ) { echo '<p class="mo-warn">תוקף הקישור הסתיים. אפשר לחדש גישה.</p>'; }
			printf( '<input class="mo-full mo-link" readonly dir="ltr" value="%s" id="moLink"><p class="mo-muted">בתוקף עד %s</p>', esc_attr( $link ), self::dt( $o->token_expires ) );
			$msg = "היי, מצרף את ההזמנה שסיכמנו.\nעוברים על הפרטים וחותמים מכאן:\n" . $link;
			echo '<p class="mo-btns"><button type="button" class="button button-primary" data-copy="moLink">העתקת הקישור</button> ';
			echo '<a class="button" target="_blank" rel="noopener" href="' . esc_url( 'https://wa.me/?text=' . rawurlencode( $msg ) ) . '">שליחה בוואטסאפ</a> ';
			echo '<a class="button" href="' . esc_url( 'mailto:' . ( ! empty( $o->client['email'] ) ? $o->client['email'] : '' ) . '?subject=' . rawurlencode( 'ההזמנה שלך · ' . $o->number ) . '&body=' . rawurlencode( $msg ) ) . '">שליחה במייל</a></p>';
			echo '</div>';

			echo '<div class="mo-card"><h2>פעולות</h2>';
			self::mini_form( 'mo_renew', $o->id, 'חידוש גישה (קישור חדש)', 'הקישור הנוכחי יפסיק לעבוד.' );
			self::mini_form( 'mo_version', $o->id, 'גרסה חדשה לעריכה', 'הגרסה הנוכחית תסומן כמוחלפת והקישור שלה ייסגר.' );
			self::mini_form( 'mo_cancel', $o->id, 'ביטול ההזמנה', 'הקישור ייסגר מיד. הפעולה מתועדת.', 'mo-danger' );
			echo '</div>';
		} elseif ( in_array( $o->status, array( 'cancelled', 'replaced', 'declined' ), true ) ) {
			echo '<div class="mo-card"><h2>פעולות</h2>';
			self::mini_form( 'mo_version', $o->id, 'יצירת גרסה חדשה מההזמנה הזו', '' );
			echo '</div>';
		}

		echo '<div class="mo-card"><h2>הערה פנימית</h2><form method="post" action="' . $post . '">';
		wp_nonce_field( 'mo_note_' . $o->id );
		echo '<input type="hidden" name="action" value="mo_note"><input type="hidden" name="id" value="' . (int) $o->id . '"><textarea class="mo-full" rows="2" name="text" required></textarea><button class="button">הוספה לתיעוד</button></form></div>';
	}

	private static function mini_form( $action, $id, $label, $help, $class = '' ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="mo-mini" data-confirm="' . esc_attr( $label . '? ' . $help ) . '">';
		wp_nonce_field( $action . '_' . $id );
		printf( '<input type="hidden" name="action" value="%s"><input type="hidden" name="id" value="%d"><button class="button mo-full %s">%s</button>', esc_attr( $action ), (int) $id, esc_attr( $class ), esc_html( $label ) );
		if ( $help ) { echo '<p class="mo-muted">' . esc_html( $help ) . '</p>'; }
		echo '</form>';
	}

	public static function page_templates() {
		$tpls = MO_Store::templates();
		echo '<div class="wrap mo-wrap"><h1>תבניות</h1>';
		self::flash();
		echo '<p class="mo-muted">תבנית נוצרת מטיוטת הזמנה ("שמירה כתבנית" בעמוד ההזמנה). הזמנה שנוצרה מתבנית לא משתנה כשמעדכנים את התבנית.</p>';
		if ( ! $tpls ) { echo '<p>אין תבניות.</p></div>'; return; }
		echo '<table class="widefat striped mo-table"><thead><tr><th>שם</th><th>שירותים</th><th>חד־פעמי</th><th>חודשי</th><th>תנאים מוגדרים</th><th></th></tr></thead><tbody>';
		foreach ( $tpls as $t ) {
			$tot  = MO_Store::totals( $t['services'] );
			$done = count( array_filter( (array) $t['terms'], function ( $x ) { return '' !== trim( (string) $x ); } ) );
			echo '<tr><td><strong>' . esc_html( $t['name'] ) . '</strong></td><td>' . count( $t['services'] ) . '</td><td>' . esc_html( self::money( $tot['once'] ) ) . '</td><td>' . esc_html( self::money( $tot['monthly'] ) ) . '</td><td>' . (int) $done . ' / ' . count( MO_Store::TERM_KEYS ) . '</td><td>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="mo-mini" data-confirm="מחיקת התבנית? הזמנות קיימות לא יושפעו.">';
			wp_nonce_field( 'mo_del_tpl' );
			echo '<input type="hidden" name="action" value="mo_del_tpl"><input type="hidden" name="tid" value="' . esc_attr( $t['id'] ) . '"><button class="button-link mo-danger-link">מחיקה</button></form></td></tr>';
		}
		echo '</tbody></table></div>';
	}

	public static function page_library() {
		echo '<div class="wrap mo-wrap"><h1>ספריית שירותים</h1>';
		self::flash();
		echo '<p class="mo-muted">השירותים שזמינים להוספה מהירה להזמנה. מחיר כאן הוא ברירת מחדל, ובכל הזמנה אפשר להתאים.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="mo-card">';
		wp_nonce_field( 'mo_save_lib' );
		echo '<input type="hidden" name="action" value="mo_save_lib">';
		echo '<table class="mo-svc-table"><thead><tr><th>שירות</th><th>תיאור ההיקף</th><th>חיוב</th><th>מחיר ₪</th><th></th></tr></thead><tbody id="moSvcRows">';
		foreach ( MO_Store::services_library() as $s ) { self::svc_row( $s ); }
		echo '</tbody></table><template id="moSvcTpl">'; self::svc_row( array( 'name' => '', 'desc' => '', 'type' => 'once', 'price' => '' ) ); echo '</template>';
		echo '<p><button type="button" class="button" id="moAddRow">הוספת שירות</button></p>';
		submit_button( 'שמירת הספרייה' );
		echo '</form></div>';
	}

	public static function page_settings() {
		echo '<div class="wrap mo-wrap"><h1>הגדרות הזמנות</h1>';
		self::flash();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="mo-card mo-narrow">';
		wp_nonce_field( 'mo_settings' );
		echo '<input type="hidden" name="action" value="mo_settings">';
		echo '<p><label class="mo-label">מייל להתראות (פתיחה, בקשת תיקון)</label><input class="mo-full" type="email" dir="ltr" name="notify" value="' . esc_attr( get_option( 'mo_notify_email', get_option( 'admin_email' ) ) ) . '"></p>';
		echo '<p><label class="mo-label">תוקף ברירת מחדל לקישור (ימים)</label><input type="number" min="1" max="90" dir="ltr" name="days" value="' . (int) get_option( 'mo_days', 14 ) . '"></p>';
		submit_button( 'שמירה' );
		echo '</form><div class="mo-card mo-narrow"><h2>ספק חתימה</h2>';
		echo get_option( 'mo_provider' ) ? '<p>מחובר.</p>' : '<p class="mo-warn">לא מחובר עדיין. כל עוד אין ספק, כפתור החתימה אצל הלקוח נשאר נעול ולא נוצר שום תהליך חתימה.</p>';
		echo '</div></div>';
	}

	/* ---------------- פעולות ---------------- */

	public static function act_new() {
		self::guard( 'mo_new' );
		$label = sanitize_text_field( wp_unslash( isset( $_POST['client_label'] ) ? $_POST['client_label'] : '' ) );
		$tid   = sanitize_text_field( wp_unslash( isset( $_POST['tpl'] ) ? $_POST['tpl'] : '' ) );
		$data  = array( 'client_label' => $label );
		if ( $tid && ( $t = MO_Store::template( $tid ) ) ) {
			$data['services'] = $t['services'];
			$data['terms']    = array_merge( MO_Store::empty_terms(), (array) $t['terms'] );
			$data['notes']    = $t['notes'];
		} else {
			$data['services'] = array();
		}
		$id = MO_Store::create( $data );
		self::back( self::edit_url( $id ), 'הטיוטה נוצרה.' );
	}

	public static function act_save() {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		self::guard( 'mo_save_' . $id );
		$r = MO_Store::update_draft( $id, array(
			'client_label' => sanitize_text_field( wp_unslash( isset( $_POST['client_label'] ) ? $_POST['client_label'] : '' ) ),
			'services'     => self::read_services(),
			'terms'        => self::read_terms(),
			'notes'        => self::read_notes(),
		) );
		if ( is_wp_error( $r ) ) { self::back( self::edit_url( $id ), $r->get_error_message(), 'error' ); }
		self::back( self::edit_url( $id ), 'הטיוטה נשמרה.' );
	}

	public static function act_send() {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		self::guard( 'mo_send_' . $id );
		$days = isset( $_POST['days'] ) ? max( 1, min( 90, (int) $_POST['days'] ) ) : 14;
		$r    = MO_Store::send( $id, $days );
		if ( is_wp_error( $r ) ) { self::back( self::edit_url( $id ), $r->get_error_message(), 'error' ); }
		self::back( self::edit_url( $id ), 'המסמך ננעל והקישור מוכן. העתק אותו ושלח ללקוח.' );
	}

	public static function act_renew() {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		self::guard( 'mo_renew_' . $id );
		$r = MO_Store::renew( $id, (int) get_option( 'mo_days', 14 ) );
		if ( is_wp_error( $r ) ) { self::back( self::edit_url( $id ), $r->get_error_message(), 'error' ); }
		self::back( self::edit_url( $id ), 'נוצר קישור חדש. הקישור הקודם כבר לא עובד.' );
	}

	public static function act_cancel() {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		self::guard( 'mo_cancel_' . $id );
		$o = MO_Store::get( $id );
		if ( $o && in_array( $o->status, MO_Store::OPEN_STATUSES, true ) ) {
			MO_Store::set_status( $id, 'cancelled' );
		}
		self::back( self::edit_url( $id ), 'ההזמנה בוטלה והקישור נסגר.' );
	}

	public static function act_version() {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		self::guard( 'mo_version_' . $id );
		$r = MO_Store::new_version( $id );
		if ( is_wp_error( $r ) ) { self::back( self::edit_url( $id ), $r->get_error_message(), 'error' ); }
		self::back( self::edit_url( $r ), 'נוצרה גרסה חדשה כטיוטה.' );
	}

	public static function act_note() {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		self::guard( 'mo_note_' . $id );
		$t = trim( sanitize_textarea_field( wp_unslash( isset( $_POST['text'] ) ? $_POST['text'] : '' ) ) );
		if ( $t ) { MO_Store::log( $id, 'note', array( 'text' => mb_substr( $t, 0, 1000 ) ), 'admin' ); }
		self::back( self::edit_url( $id ) );
	}

	public static function act_save_tpl() {
		self::guard( 'mo_save_tpl' );
		$o    = MO_Store::get( isset( $_POST['from_order'] ) ? (int) $_POST['from_order'] : 0 );
		$name = sanitize_text_field( wp_unslash( isset( $_POST['tpl_name'] ) ? $_POST['tpl_name'] : '' ) );
		if ( ! $o || ! $name ) { self::back( admin_url( 'admin.php?page=mo-templates' ), 'חסר שם או הזמנה.', 'error' ); }
		$list   = MO_Store::templates();
		$list[] = array( 'id' => 'tpl_' . wp_generate_password( 8, false, false ), 'name' => $name, 'services' => $o->services, 'terms' => $o->terms, 'notes' => $o->notes );
		MO_Store::save_templates( $list );
		self::back( self::edit_url( $o->id ), 'התבנית "' . esc_html( $name ) . '" נשמרה.' );
	}

	public static function act_del_tpl() {
		self::guard( 'mo_del_tpl' );
		$tid = sanitize_text_field( wp_unslash( isset( $_POST['tid'] ) ? $_POST['tid'] : '' ) );
		MO_Store::save_templates( array_filter( MO_Store::templates(), function ( $t ) use ( $tid ) { return $t['id'] !== $tid; } ) );
		self::back( admin_url( 'admin.php?page=mo-templates' ), 'התבנית נמחקה.' );
	}

	public static function act_save_lib() {
		self::guard( 'mo_save_lib' );
		MO_Store::save_services_library( self::read_services() );
		self::back( admin_url( 'admin.php?page=mo-library' ), 'הספרייה נשמרה.' );
	}

	public static function act_settings() {
		self::guard( 'mo_settings' );
		$email = sanitize_email( wp_unslash( isset( $_POST['notify'] ) ? $_POST['notify'] : '' ) );
		if ( is_email( $email ) ) { update_option( 'mo_notify_email', $email, false ); }
		update_option( 'mo_days', max( 1, min( 90, (int) ( isset( $_POST['days'] ) ? $_POST['days'] : 14 ) ) ), false );
		self::back( admin_url( 'admin.php?page=mo-settings' ), 'נשמר.' );
	}
}
