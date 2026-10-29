<?php
/**
 * עמוד ההזמנה ללקוח.
 * משתנים: $o, $doc, $mode ('client'|'preview'), $token, $missing, $provider, $rest
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$is_prev = ( 'preview' === $mode );
$status  = $o->eff_status;
$open    = in_array( $status, MO_Store::OPEN_STATUSES, true ) && ! $is_prev;
$signed  = ( 'signed' === $status );
$c       = is_array( $o->client ) ? $o->client : array();
$cv      = function ( $k ) use ( $c ) { return isset( $c[ $k ] ) ? esc_attr( $c[ $k ] ) : ''; };
$money   = function ( $n ) { return '₪' . number_format( (float) $n, 0, '.', ',' ); };
$once    = array_values( array_filter( $doc['services'], function ( $s ) { return 'monthly' !== ( isset( $s['type'] ) ? $s['type'] : 'once' ); } ) );
$monthly = array_values( array_filter( $doc['services'], function ( $s ) { return 'monthly' === ( isset( $s['type'] ) ? $s['type'] : 'once' ); } ) );
$n_miss  = count( $missing );
$labels  = array(
	'draft' => 'טיוטה', 'sent' => 'ממתינה לבדיקה וחתימה', 'opened' => 'ממתינה לבדיקה וחתימה', 'fix_requested' => 'בקשת התיקון התקבלה',
	'signed' => 'נחתמה', 'declined' => 'נדחתה', 'cancelled' => 'בוטלה', 'replaced' => 'הוחלפה בגרסה חדשה', 'expired' => 'פג תוקף',
);
$client_ready = ! empty( $c['entity'] ) && ! empty( $c['email'] );
$svc_icon = array(
	'once'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 2 7l10 5 10-5-10-5z"/><path d="m2 17 10 5 10-5"/><path d="m2 12 10 5 10-5"/></svg>',
	'monthly' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/></svg>',
);
$arrow = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>';

$render_svc = function ( $s ) use ( $money, $svc_icon ) {
	$type = ( isset( $s['type'] ) && 'monthly' === $s['type'] ) ? 'monthly' : 'once';
	?>
	<div class="o-svc">
		<div class="o-svc-ic"><?php echo $svc_icon[ $type ]; // phpcs:ignore ?></div>
		<div class="o-svc-tx"><h4><?php echo esc_html( $s['name'] ); ?></h4><?php if ( ! empty( $s['desc'] ) ) : ?><p><?php echo esc_html( $s['desc'] ); ?></p><?php endif; ?></div>
		<div class="o-svc-pr"><bdi><?php echo esc_html( $money( $s['price'] ) ); ?></bdi><?php if ( 'monthly' === $type ) : ?><small>לחודש</small><?php endif; ?></div>
	</div>
	<?php
};
?><!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>ההזמנה שלך | Mula Digital</title>
  <meta name="robots" content="noindex, nofollow, noarchive" />
  <meta name="referrer" content="no-referrer" />
  <meta name="theme-color" content="#0B0B0F" />
  <base href="<?php echo esc_url( MO_SITE ); ?>" />
  <link rel="icon" type="image/png" href="assets/favicon.png" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Assistant:wght@400;500;600;700&family=Heebo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <?php echo MO_Public::shell( 'style' ); // phpcs:ignore ?>
  <link rel="stylesheet" href="assets/mega.css?v=4" />
  <link rel="stylesheet" href="assets/mega-wow.css?v=5" />
  <link rel="stylesheet" href="<?php echo esc_url( MO_URL . 'assets/order.css?v=' . MO_VERSION ); ?>" />
</head>
<body>
<?php if ( $is_prev ) : ?>
  <div class="o-admin-bar">תצוגת מנהל · כך הלקוח יראה את ההזמנה · <?php echo esc_html( MO_Store::STATUSES[ $o->status ] ); ?></div>
<?php endif; ?>
  <?php echo MO_Public::shell( 'header' ); // phpcs:ignore ?>

  <section class="c-hero o-hero">
    <div class="c-hero-ov"></div>
    <div class="c-hero-in">
      <h1 class="o-title"><span>ההזמנה שלך.</span><span class="gradient-text">הצעד הבא בעסק.</span></h1>
      <p class="c-sub">כל השירותים והתנאים שסיכמנו, במקום אחד. עוברים על הפרטים וחותמים בנוחות.</p>
      <dl class="o-meta">
        <div><dt>לקוח</dt><dd><?php echo esc_html( $doc['client'] ); ?></dd></div>
        <div><dt>מספר הזמנה</dt><dd><bdi class="num"><?php echo esc_html( $doc['number'] ); ?><?php echo $doc['version'] > 1 ? esc_html( ' · גרסה ' . $doc['version'] ) : ''; ?></bdi></dd></div>
        <div><dt>סטטוס</dt><dd><span class="o-status"><i<?php echo $signed ? ' style="background:#19B36B;box-shadow:0 0 0 4px rgba(25,179,107,.18)"' : ''; ?>></i><?php echo esc_html( isset( $labels[ $status ] ) ? $labels[ $status ] : $status ); ?></span></dd></div>
      </dl>
    </div>
  </section>

  <main id="order-main" class="o-body">
    <div class="wrap">

      <ol class="o-steps" aria-label="שלבי ההזמנה">
        <li class="is-active" data-go="1"><button type="button"><span class="o-n">1</span><span class="o-l">השירותים</span></button></li>
        <li data-go="2"><button type="button"><span class="o-n">2</span><span class="o-l">פרטי הלקוח</span></button></li>
        <li data-go="3"><button type="button"><span class="o-n">3</span><span class="o-l">תנאי ההזמנה</span></button></li>
        <li data-go="4"><button type="button"><span class="o-n">4</span><span class="o-l">אישור וחתימה</span></button></li>
      </ol>
      <div class="o-progress" aria-hidden="true"><span id="oBar"></span></div>

      <div class="o-grid">
        <aside class="o-sum" aria-label="סיכום ההזמנה">
          <div class="o-sum-card">
            <p class="o-sum-h">סיכום ההזמנה</p>
            <div class="o-tot">
              <?php if ( $once ) : ?>
              <div class="o-tot-row">
                <span class="o-tot-l"><span class="o-chip once">חד־פעמי</span>הקמה</span>
                <span class="o-tot-v"><bdi><?php echo esc_html( $money( $doc['totals']['once'] ) ); ?></bdi></span>
              </div>
              <?php endif; if ( $monthly ) : ?>
              <div class="o-tot-row">
                <span class="o-tot-l"><span class="o-chip monthly">חודשי</span>שירותים שוטפים</span>
                <span class="o-tot-v"><bdi><?php echo esc_html( $money( $doc['totals']['monthly'] ) ); ?></bdi><small>לחודש</small></span>
              </div>
              <?php endif; ?>
            </div>
            <p class="o-vat">כל המחירים לפני מע״מ</p>
            <?php if ( $doc['notes'] ) : ?>
            <button class="o-sum-more" type="button" aria-expanded="false" aria-controls="oNotes">הבהרות חשובות
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <ul class="o-notes" id="oNotes">
              <?php foreach ( $doc['notes'] as $n ) : ?><li><?php echo esc_html( $n ); ?></li><?php endforeach; ?>
            </ul>
            <?php endif; ?>
          </div>
          <div class="o-help">
            <span>שאלה על ההזמנה?</span>
            <a href="tel:0508249824"><bdi>050-824-9824</bdi></a>
          </div>
        </aside>

        <div class="o-panels">

          <!-- STEP 1 -->
          <section class="o-panel is-active" data-step="1" aria-labelledby="h1s">
            <header class="o-ph">
              <p class="o-kicker">שלב 1 מתוך 4</p>
              <h2 id="h1s">השירותים שסיכמנו</h2>
              <p>זה ההיקף שדיברנו עליו. משהו לא מדויק? אפשר לבקש תיקון ונעדכן לפני החתימה.</p>
            </header>
            <?php if ( $once ) : ?>
              <h3 class="o-group"><span class="o-chip once">חד־פעמי</span>הקמה</h3>
              <?php foreach ( $once as $s ) { $render_svc( $s ); } ?>
            <?php endif; if ( $monthly ) : ?>
              <h3 class="o-group"><span class="o-chip monthly">חודשי</span>שירותים שוטפים</h3>
              <?php foreach ( $monthly as $s ) { $render_svc( $s ); } ?>
            <?php endif; ?>
            <?php if ( $open ) : ?>
            <div class="o-fix">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
              <span>משהו חסר או לא מדויק?</span>
              <button type="button" class="o-link" data-fix>בקשת תיקון</button>
            </div>
            <?php endif; ?>
            <div class="o-nav">
              <button type="button" class="btn btn-primary o-next" data-next="2">המשך לפרטי הלקוח <?php echo $arrow; // phpcs:ignore ?></button>
            </div>
          </section>

          <!-- STEP 2 -->
          <section class="o-panel" data-step="2" aria-labelledby="h2s" hidden>
            <header class="o-ph">
              <p class="o-kicker">שלב 2 מתוך 4</p>
              <h2 id="h2s">פרטי הלקוח</h2>
              <p>הפרטים יופיעו במסמך החתום. ממלאים בדיוק כמו ברישום העסק.</p>
            </header>
            <form class="o-form" id="oForm" novalidate autocomplete="on">
              <fieldset class="o-type">
                <legend>חותמים בתור</legend>
                <label><input type="radio" name="ctype" value="osek" <?php checked( ( isset( $c['ctype'] ) ? $c['ctype'] : 'osek' ) !== 'company' ); ?>><span>עוסק / עצמאי</span></label>
                <label><input type="radio" name="ctype" value="company" <?php checked( isset( $c['ctype'] ) && 'company' === $c['ctype'] ); ?>><span>חברה בע״מ</span></label>
              </fieldset>
              <div class="field" data-f="entity">
                <label for="fEntity">שם משפטי מלא / שם הישות המתקשרת<span class="req">*</span></label>
                <input id="fEntity" name="entity" type="text" autocomplete="organization" maxlength="200" value="<?php echo $cv( 'entity' ); // phpcs:ignore ?>" required>
                <p class="o-err" hidden>צריך את השם כפי שהוא רשום.</p>
              </div>
              <div class="field-row">
                <div class="field" data-f="reg">
                  <label for="fReg"><span data-osek>מספר עוסק</span><span data-company hidden>מספר חברה (ח.פ.)</span><span class="req">*</span></label>
                  <input id="fReg" name="reg" type="text" inputmode="numeric" pattern="[0-9]{9}" maxlength="9" dir="ltr" placeholder=" " value="<?php echo $cv( 'reg' ); // phpcs:ignore ?>" required>
                  <p class="o-err" hidden>9 ספרות, בלי מקפים.</p>
                </div>
                <div class="field" data-f="signer">
                  <label for="fSigner">שם איש הקשר והחותם<span class="req">*</span></label>
                  <input id="fSigner" name="signer" type="text" autocomplete="name" maxlength="200" value="<?php echo $cv( 'signer' ); // phpcs:ignore ?>" required>
                  <p class="o-err" hidden>מי חותם על ההזמנה?</p>
                </div>
              </div>
              <div class="field" data-f="role" data-company hidden>
                <label for="fRole">תפקיד וסמכות חתימה<span class="req">*</span></label>
                <input id="fRole" name="role" type="text" maxlength="200" placeholder="למשל: מנכ״ל, מורשה חתימה" value="<?php echo $cv( 'role' ); // phpcs:ignore ?>">
                <p class="o-err" hidden>בחתימה בשם חברה צריך לציין תפקיד.</p>
              </div>
              <div class="field-row">
                <div class="field" data-f="phone">
                  <label for="fPhone">טלפון<span class="req">*</span></label>
                  <input id="fPhone" name="phone" type="tel" inputmode="tel" autocomplete="tel" dir="ltr" placeholder=" " maxlength="15" value="<?php echo $cv( 'phone' ); // phpcs:ignore ?>" required>
                  <p class="o-err" hidden>מספר טלפון תקין, למשל 050-0000000.</p>
                </div>
                <div class="field" data-f="email">
                  <label for="fEmail">דוא״ל לקבלת המסמכים<span class="req">*</span></label>
                  <input id="fEmail" name="email" type="email" inputmode="email" autocomplete="email" dir="ltr" placeholder=" " maxlength="200" value="<?php echo $cv( 'email' ); // phpcs:ignore ?>" required>
                  <p class="o-err" hidden>לכאן יישלח העותק החתום.</p>
                </div>
              </div>
              <div class="field" data-f="addr">
                <label for="fAddr">כתובת למסמך<span class="req">*</span></label>
                <input id="fAddr" name="addr" type="text" autocomplete="street-address" maxlength="200" value="<?php echo $cv( 'addr' ); // phpcs:ignore ?>" required>
                <p class="o-err" hidden>רחוב, מספר ועיר.</p>
              </div>
              <p class="o-fine">לא מבקשים כאן פרטי בנק או אשראי.</p>
            </form>
            <div class="o-nav">
              <button type="button" class="btn btn-ghost o-prev" data-prev="1">חזרה</button>
              <button type="button" class="btn btn-primary o-next" data-next="3" data-save>המשך לתנאים <?php echo $arrow; // phpcs:ignore ?></button>
            </div>
          </section>

          <!-- STEP 3 -->
          <section class="o-panel" data-step="3" aria-labelledby="h3s" hidden>
            <header class="o-ph">
              <p class="o-kicker">שלב 3 מתוך 4</p>
              <h2 id="h3s">תנאי ההזמנה</h2>
              <p>כל תנאי בנפרד ובשפה פשוטה.</p>
            </header>
            <?php if ( $n_miss ) : ?>
            <div class="o-missing" role="status">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
              <div><strong><?php echo 1 === $n_miss ? 'תנאי אחד עדיין לא סוכם.' : esc_html( $n_miss . ' תנאים עדיין לא סוכמו.' ); ?></strong> ההזמנה לא תישלח לחתימה עד שכולם יושלמו.</div>
            </div>
            <?php endif; ?>
            <div class="o-terms">
              <?php $i = 0; foreach ( $doc['terms'] as $t ) : $i++; $has = '' !== trim( $t['body'] ); ?>
              <details class="o-term"<?php echo 1 === $i && $has ? ' open' : ''; ?>>
                <summary><span class="o-tn"><?php echo esc_html( sprintf( '%02d', $i ) ); ?></span><?php echo esc_html( $t['title'] ); ?><?php echo $has ? '' : '<span class="o-pend">ממתין להשלמה</span>'; ?></summary>
                <div><?php echo $has ? esc_html( $t['body'] ) : 'יושלם לפי הסיכום בפועל.'; ?></div>
              </details>
              <?php endforeach; ?>
            </div>
            <div class="o-nav">
              <button type="button" class="btn btn-ghost o-prev" data-prev="2">חזרה</button>
              <button type="button" class="btn btn-primary o-next" data-next="4">המשך לאישור <?php echo $arrow; // phpcs:ignore ?></button>
            </div>
          </section>

          <!-- STEP 4 -->
          <section class="o-panel" data-step="4" aria-labelledby="h4s" hidden>
            <header class="o-ph">
              <p class="o-kicker">שלב 4 מתוך 4</p>
              <h2 id="h4s">אישור וחתימה</h2>
              <p>בודקים את המסמך המלא, מאשרים וחותמים.</p>
            </header>
            <?php if ( $signed ) : ?>
              <div class="o-locked"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg><div><strong>ההזמנה נחתמה.</strong> עותק חתום נשלח לדוא״ל שמסרתם.</div></div>
            <?php elseif ( ! $open && ! $is_prev ) : ?>
              <div class="o-missing"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg><div>ההזמנה אינה פתוחה לחתימה.</div></div>
            <?php endif; ?>

            <div class="o-review">
              <div class="o-rv"><span>הצדדים</span><p>Mula Digital · <span id="rvEntity" class="<?php echo $client_ready ? '' : 'o-empty'; ?>"><?php echo $client_ready ? esc_html( $c['entity'] ) : 'פרטי הלקוח טרם הושלמו'; ?></span></p><button type="button" class="o-link" data-go-btn="2">עריכה</button></div>
              <div class="o-rv"><span>שירותים</span><p><?php echo esc_html( count( $once ) . ' חד־פעמיים · ' . count( $monthly ) . ' חודשיים' ); ?></p><button type="button" class="o-link" data-go-btn="1">צפייה</button></div>
              <div class="o-rv"><span>תנאים</span><p class="<?php echo $n_miss ? 'o-empty' : ''; ?>"><?php echo $n_miss ? esc_html( $n_miss . ' תנאים ממתינים להשלמה' ) : 'כל התנאים סוכמו'; ?></p><button type="button" class="o-link" data-go-btn="3">צפייה</button></div>
            </div>

            <div class="o-doc">
              <div class="o-doc-bar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
                <span>המסמך המלא לחתימה</span>
              </div>
              <div class="o-doc-slot" id="oDocSlot">
                <p><strong>כאן ייטען רכיב החתימה.</strong></p>
                <p>המסמך הנעול, זיהוי החותם והחתימה עצמה יתבצעו מתוך העמוד.</p>
              </div>
            </div>

            <?php if ( ! $signed ) : ?>
            <div class="o-consent">
              <label><input type="checkbox" id="c1"><span>קראתי את המסמך המלא ואני מסכים לתנאי ההזמנה.</span></label>
              <label><input type="checkbox" id="c2"><span>אני מסכים לחתום באופן אלקטרוני ולקבל עותק חתום לדוא״ל.</span></label>
            </div>
            <div class="o-nav o-nav-sign">
              <button type="button" class="btn btn-ghost o-prev" data-prev="3">חזרה</button>
              <button type="button" class="btn btn-primary o-sign" disabled aria-describedby="signWhy">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                מעבר לחתימה</button>
            </div>
            <p class="o-why" id="signWhy"></p>
            <?php endif; ?>
          </section>
        </div>
      </div>
    </div>

    <?php if ( $open ) : ?>
    <dialog class="o-dlg" id="oDlg" aria-labelledby="dlgH">
      <form method="dialog" id="oFixForm">
        <h3 id="dlgH">בקשת תיקון</h3>
        <p>כתבו מה חסר או לא מדויק. ההזמנה לא משתנה עד שנעדכן ונשלח גרסה חדשה.</p>
        <div class="field"><label for="dlgT">מה לתקן?</label><textarea id="dlgT" rows="4" maxlength="2000"></textarea></div>
        <div class="o-dlg-act"><button type="button" class="btn btn-ghost" data-close>ביטול</button><button type="button" class="btn btn-primary" id="oFixSend">שליחה לצוות</button></div>
      </form>
    </dialog>
    <?php endif; ?>
    <div class="o-toast" id="oToast" role="status" aria-live="polite"></div>
  </main>

  <?php echo MO_Public::shell( 'footer' ); // phpcs:ignore ?>
  <?php echo MO_Public::shell( 'script' ); // phpcs:ignore ?>
  <script>
  window.MO = <?php echo wp_json_encode( array(
	'rest'     => $rest,
	'token'    => $is_prev ? '' : $token,
	'open'     => $open,
	'preview'  => $is_prev,
	'missing'  => $n_miss,
	'provider' => (bool) $provider,
	'saved'    => $client_ready,
  ) ); ?>;
  </script>
  <script src="<?php echo esc_url( MO_URL . 'assets/order.js?v=' . MO_VERSION ); ?>"></script>
  <script src="assets/mega.js?v=5" defer></script>
</body>
</html>
