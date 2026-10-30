<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** משתנים: $cal, $ap (בעמוד ניהול פגישה) */
$is_manage = (bool) $ap;
$site      = 'https://muladigital.co.il/';
?><!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo esc_html( $cal ? $cal->name : 'קביעת פגישה' ); ?> | Mula Digital</title>
  <meta name="robots" content="noindex, nofollow" />
  <meta name="theme-color" content="#0B0B0F" />
  <base href="<?php echo esc_url( $site ); ?>" />
  <link rel="icon" type="image/png" href="assets/favicon.png" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Assistant:wght@400;500;600;700&family=Heebo:wght@500;700;800;900&display=swap" rel="stylesheet" />
  <?php echo MOS_Booking::shell( 'style' ); // phpcs:ignore ?>
  <link rel="stylesheet" href="assets/mega.css?v=4" />
  <link rel="stylesheet" href="<?php echo esc_url( MOS_URL . 'assets/booking.css?v=' . MOS_VERSION ); ?>" />
</head>
<body>
  <?php echo MOS_Booking::shell( 'header' ); // phpcs:ignore ?>

  <section class="bk-hero">
    <div class="bk-in">
      <?php if ( ! $cal ) : ?>
        <h1>היומן לא נמצא</h1><p>אפשר לדבר איתי ישירות: <a href="tel:0508249824"><bdi>050-824-9824</bdi></a></p>
      <?php elseif ( $is_manage ) : ?>
        <p class="bk-eye">ניהול פגישה</p>
        <h1><?php echo esc_html( $cal->name ); ?></h1>
      <?php else : ?>
        <p class="bk-eye">משה מולה · Mula Digital</p>
        <h1><span>בואו נדבר</span><span class="bk-grad">על העסק שלכם.</span></h1>
        <p class="bk-sub"><?php echo esc_html( $cal->description ); ?></p>
        <ul class="bk-meta">
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg><?php echo (int) $cal->duration; ?> דקות</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg><?php echo esc_html( $cal->location ); ?></li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>בלי התחייבות</li>
        </ul>
      <?php endif; ?>
    </div>
  </section>

  <main class="bk-body">
    <div class="bk-in">
    <?php if ( $cal && $is_manage ) : ?>
      <div class="bk-card bk-manage">
        <?php if ( 'booked' === $ap->status ) : ?>
          <p class="bk-label">הפגישה שלך</p>
          <p class="bk-when"><?php echo esc_html( MOS_Booking::local( strtotime( $ap->start_at . ' UTC' ) ) ); ?></p>
          <p class="bk-muted"><?php echo esc_html( $cal->location ); ?></p>
          <div class="bk-actions">
            <a class="btn btn-primary" href="<?php echo esc_url( home_url( '/book/' . $cal->slug ) ); ?>">קביעת מועד אחר</a>
            <button class="btn btn-ghost" id="bkCancel" data-token="<?php echo esc_attr( $ap->token ); ?>">ביטול הפגישה</button>
          </div>
          <p class="bk-muted bk-small">כשקובעים מועד אחר, כדאי לבטל כאן את הקודם.</p>
        <?php else : ?>
          <p class="bk-when">הפגישה בוטלה.</p>
          <a class="btn btn-primary" href="<?php echo esc_url( home_url( '/book/' . $cal->slug ) ); ?>">קביעת פגישה חדשה</a>
        <?php endif; ?>
      </div>
    <?php elseif ( $cal ) : ?>
      <div class="bk-card" id="bk">
        <div class="bk-steps" aria-hidden="true"><span class="on">1. יום</span><span>2. שעה</span><span>3. פרטים</span></div>

        <section class="bk-pane" data-pane="day">
          <h2>באיזה יום נוח לכם?</h2>
          <div class="bk-days" id="bkDays"><div class="bk-skel"></div><div class="bk-skel"></div><div class="bk-skel"></div><div class="bk-skel"></div></div>
          <p class="bk-muted bk-small">השעות מוצגות לפי שעון ישראל.</p>
        </section>

        <section class="bk-pane" data-pane="time" hidden>
          <button class="bk-back" data-back="day">→ חזרה לימים</button>
          <h2 id="bkDayTitle">בחרו שעה</h2>
          <div class="bk-times" id="bkTimes"></div>
        </section>

        <section class="bk-pane" data-pane="form" hidden>
          <button class="bk-back" data-back="time">→ חזרה לשעות</button>
          <h2>כמעט סיימנו</h2>
          <p class="bk-chosen" id="bkChosen"></p>
          <form id="bkForm" novalidate>
            <div class="bk-field"><label for="bkName">שם מלא</label><input id="bkName" name="name" autocomplete="name" required></div>
            <div class="bk-row">
              <div class="bk-field"><label for="bkPhone">טלפון</label><input id="bkPhone" name="phone" type="tel" inputmode="tel" autocomplete="tel" dir="ltr" required></div>
              <div class="bk-field"><label for="bkEmail">מייל <span class="bk-muted">(לאישור ותזכורת)</span></label><input id="bkEmail" name="email" type="email" inputmode="email" autocomplete="email" dir="ltr"></div>
            </div>
            <div class="bk-field"><label for="bkNote">במה העסק עוסק, ומה הכי בוער? <span class="bk-muted">(לא חובה)</span></label><textarea id="bkNote" name="note" rows="3"></textarea></div>
            <input type="text" name="website" tabindex="-1" autocomplete="off" class="bk-hp" aria-hidden="true">
            <p class="bk-err" id="bkErr" role="alert" hidden></p>
            <button class="btn btn-primary bk-submit" type="submit">קביעת השיחה</button>
          </form>
        </section>

        <section class="bk-pane bk-done" data-pane="done" hidden>
          <div class="bk-check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></div>
          <h2>קבענו.</h2>
          <p class="bk-when" id="bkDoneWhen"></p>
          <p class="bk-muted">אישור עם הזמנה ליומן נשלח למייל. אם משהו משתנה, יש שם קישור לשינוי.</p>
        </section>
      </div>
    <?php endif; ?>
    </div>
  </main>

  <?php echo MOS_Booking::shell( 'footer' ); // phpcs:ignore ?>
  <?php echo MOS_Booking::shell( 'script' ); // phpcs:ignore ?>
  <script>window.MOSB = <?php echo wp_json_encode( array( 'api' => esc_url_raw( rest_url( 'mos/v1/book/' ) ), 'slug' => $cal ? $cal->slug : '' ) ); ?>;</script>
  <script src="<?php echo esc_url( MOS_URL . 'assets/booking.js?v=' . MOS_VERSION ); ?>"></script>
  <script src="assets/mega.js?v=5" defer></script>
</body>
</html>
