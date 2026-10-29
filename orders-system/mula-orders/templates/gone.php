<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
?><!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo esc_html( $gone['title'] ); ?> | Mula Digital</title>
  <meta name="robots" content="noindex, nofollow" />
  <meta name="referrer" content="no-referrer" />
  <base href="<?php echo esc_url( MO_SITE ); ?>" />
  <link rel="icon" type="image/png" href="assets/favicon.png" />
  <link href="https://fonts.googleapis.com/css2?family=Assistant:wght@400;600&family=Heebo:wght@700;900&display=swap" rel="stylesheet" />
  <?php echo MO_Public::shell( 'style' ); // phpcs:ignore ?>
  <link rel="stylesheet" href="assets/mega.css?v=4" />
  <link rel="stylesheet" href="<?php echo esc_url( MO_URL . 'assets/order.css?v=' . MO_VERSION ); ?>" />
</head>
<body>
  <?php echo MO_Public::shell( 'header' ); // phpcs:ignore ?>
  <section class="c-hero o-hero" style="min-height:62vh;align-items:center">
    <div class="c-hero-in">
      <h1 class="o-title"><span><?php echo esc_html( $gone['title'] ); ?></span></h1>
      <?php if ( $gone['text'] ) : ?><p class="c-sub"><?php echo esc_html( $gone['text'] ); ?></p><?php endif; ?>
      <p style="margin-top:26px;display:flex;gap:10px;flex-wrap:wrap">
        <a class="btn btn-primary" href="https://wa.me/972508249824" target="_blank" rel="noopener">וואטסאפ למשה</a>
        <a class="btn btn-ghost" href="tel:0508249824"><bdi>050-824-9824</bdi></a>
      </p>
    </div>
  </section>
  <?php echo MO_Public::shell( 'footer' ); // phpcs:ignore ?>
  <?php echo MO_Public::shell( 'script' ); // phpcs:ignore ?>
  <script src="assets/mega.js?v=5" defer></script>
</body>
</html>
