<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    $role = $_SESSION['user_role'];
    if ($role !== 'customer') {
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    }
    // customers just see the homepage — no redirect
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Resurrection Musical Instruments — Davao City</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Barlow:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400;1,700&family=Barlow+Condensed:wght@500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css">
  <style>
    body { overflow-x: hidden; }

    /* ── Promo bar ── */
    .promo-bar {
      background: var(--red);
      color: #fff;
      text-align: center;
      font-family: var(--font-cond);
      font-size: .78rem;
      font-weight: 700;
      letter-spacing: .15em;
      text-transform: uppercase;
      padding: .6rem 1rem;
    }

    /* ── Landing nav ── */
    .landing-nav {
      position: fixed; top: 36px; left: 0; right: 0; z-index: 100;
      padding: 0 2rem;
      height: 64px;
      display: flex; align-items: center; justify-content: space-between;
      background: rgba(255,255,255,.96);
      backdrop-filter: blur(8px);
      border-bottom: 2px solid #111;
    }
    .landing-nav .brand {
      display: flex; align-items: center;
      text-decoration: none;
    }
    .landing-nav .nav-btns { display: flex; gap: .75rem; }

    /* ── Hero ── */
    .hero-full {
      min-height: 100vh;
      display: flex; align-items: center;
      position: relative;
      overflow: hidden;
      background: var(--bg-dark);
      padding-top: 100px;
    }
    .hero-bg-pattern {
      position: absolute; inset: 0;
      background-image:
        repeating-linear-gradient(0deg, transparent, transparent 80px, rgba(255,255,255,.02) 80px, rgba(255,255,255,.02) 81px),
        repeating-linear-gradient(90deg, transparent, transparent 80px, rgba(255,255,255,.02) 80px, rgba(255,255,255,.02) 81px);
    }
    .hero-red-bar {
      display: none; /* replaced by the photo on the right */
    }
    .hero-inner {
      position: relative; z-index: 2;
      max-width: 1280px; margin: 0 auto;
      padding: 4rem 2rem 6rem;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 3rem;
      align-items: center;
      width: 100%;
    }
    .hero-text {}
    .hero-eyebrow {
      font-family: var(--font-cond);
      font-size: .78rem; font-weight: 700; letter-spacing: .25em;
      text-transform: uppercase; color: var(--red);
      margin-bottom: 1.5rem;
      display: inline-flex; align-items: center; gap: .75rem;
    }
    .hero-eyebrow::before { content: ''; width: 32px; height: 2px; background: var(--red); }
    .hero-title {
      font-family: var(--font-display);
      font-size: clamp(5rem, 10vw, 9rem);
      line-height: .9;
      color: #fff;
      margin-bottom: 2rem;
      text-transform: uppercase;
    }
    .hero-title .line2 { color: #fff; display: block; }
    .hero-sub {
      font-size: 1rem; color: rgba(255,255,255,.65);
      max-width: 400px; margin-bottom: 2.5rem;
      line-height: 1.8; font-weight: 300;
    }
    .hero-ctas { display: flex; gap: 1rem; flex-wrap: wrap; }

    .hero-img-col {
      position: relative;
      display: flex; align-items: stretch; justify-content: center;
      /* extend to fill the red bar height */
      margin: -4rem -2rem -6rem 0;
    }
    .hero-guitar-wrap {
      position: relative;
      width: 100%;
      overflow: hidden;
    }
    .hero-guitar-wrap img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: center center;
      display: block;
      min-height: 480px;
    }
    .hero-guitar-label {
      position: absolute; bottom: 0; left: 0; right: 0;
      background: rgba(0,0,0,.55);
      backdrop-filter: blur(4px);
      padding: .75rem 1rem;
      font-family: var(--font-cond);
      font-size: .75rem;
      font-weight: 700;
      letter-spacing: .2em;
      text-transform: uppercase;
      color: #fff;
      text-align: center;
    }

    /* ── Features strip ── */
    .features-strip {
      background: var(--bg-dark);
      border-top: 1px solid rgba(255,255,255,.08);
      padding: 2rem 1.5rem;
    }
    .features-inner {
      max-width: 1280px; margin: 0 auto;
      display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 0;
    }
    .feature-item {
      display: flex; align-items: flex-start; gap: 1rem;
      padding: 1.25rem 1.5rem;
      border-right: 1px solid rgba(255,255,255,.08);
    }
    .feature-item:last-child { border-right: none; }
    .feature-icon { font-size: 1.8rem; line-height: 1; flex-shrink: 0; display: flex; align-items: center; color: rgba(255,255,255,.75); }
    .feature-item h4 {
      font-size: .8rem; font-weight: 700; color: #fff;
      font-family: var(--font-cond); margin-bottom: .2rem;
      text-transform: uppercase; letter-spacing: .08em;
    }
    .feature-item p { font-size: .78rem; color: rgba(255,255,255,.5); margin: 0; }

    /* ── Categories ── */
    .section { padding: 5rem 1.5rem; }
    .section-inner { max-width: 1280px; margin: 0 auto; }

    .section-head {
      display: flex; justify-content: space-between; align-items: flex-end;
      margin-bottom: 2.5rem;
      border-bottom: 2px solid var(--text-0);
      padding-bottom: 1rem;
    }
    .section-head h2 { margin: 0; }
    .section-head a {
      font-family: var(--font-cond);
      font-size: .8rem; font-weight: 700; letter-spacing: .15em;
      text-transform: uppercase; color: var(--text-0);
      text-decoration: underline;
    }

    .categories-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
      gap: 1px;
      background: var(--border);
      border: 1px solid var(--border);
    }
    .cat-card {
      background: var(--bg-0);
      padding: 2rem 1rem 1.5rem;
      text-align: center;
      cursor: pointer;
      transition: all .2s ease;
      text-decoration: none;
      display: block;
    }
    .cat-card:hover {
      background: var(--text-0);
    }
    .cat-card:hover .cat-name { color: #fff; }
    .cat-card:hover .cat-emoji { transform: scale(1.1); }
    .cat-card .cat-emoji {
      font-size: 2.8rem;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: .75rem;
      transition: transform .2s;
      color: var(--text-0);
    }
    .cat-card:hover .cat-emoji { color: #fff; }
    .cat-card .cat-name {
      font-family: var(--font-cond);
      font-size: .85rem; font-weight: 700;
      color: var(--text-0);
      text-transform: uppercase;
      letter-spacing: .08em;
      transition: color .2s;
    }

    /* ── Banner strip ── */
    .banner-strip {
      background: var(--text-0);
      padding: 4rem 1.5rem;
      text-align: center;
      position: relative;
      overflow: hidden;
    }
    .banner-strip::before {
      content: '';
      position: absolute;
      top: 0; bottom: 0; left: 0;
      width: 6px;
      background: var(--red);
    }
    .banner-strip h2 {
      font-size: clamp(3rem, 7vw, 6rem);
      color: #fff;
      margin-bottom: .5rem;
    }
    .banner-strip h2 span { color: var(--red); }
    .banner-strip p { color: rgba(255,255,255,.6); max-width: 480px; margin: 0 auto 2rem; }

    /* ── Services section ── */
    .services-section { background: var(--bg-1); padding: 5rem 1.5rem; }
    .services-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 1px;
      background: var(--border);
      border: 1px solid var(--border);
      margin-top: 2.5rem;
    }
    .service-card {
      background: var(--bg-0);
      padding: 2rem 1.75rem;
      transition: all .2s;
      position: relative;
    }
    .service-card:hover { background: var(--bg-2); }
    .service-card::before {
      content: '';
      position: absolute;
      top: 0; left: 0;
      width: 3px; height: 0;
      background: var(--red);
      transition: height .25s ease;
    }
    .service-card:hover::before { height: 100%; }
    .service-card .s-icon {
      font-size: 2rem;
      margin-bottom: 1.25rem;
      display: flex;
      align-items: center;
      color: var(--red);
    }
    .service-card h4 {
      font-size: 1rem; font-weight: 700;
      color: var(--text-0); margin-bottom: .5rem;
    }
    .service-card p { font-size: .85rem; color: var(--text-2); margin: 0; }

    /* ── CTA band ── */
    .cta-band {
      background: var(--red);
      padding: 5rem 1.5rem;
      text-align: center;
    }
    .cta-band h2 { color: #fff; font-size: clamp(3rem, 6vw, 5rem); margin-bottom: .75rem; }
    .cta-band p { color: rgba(255,255,255,.75); max-width: 480px; margin: 0 auto 2rem; }

    /* ── Footer ── */
    .footer-full {
      background: var(--bg-dark);
      padding: 4rem 1.5rem 2rem;
    }
    .footer-grid {
      max-width: 1280px; margin: 0 auto 2rem;
      display: grid;
      grid-template-columns: 2fr 1fr 1fr;
      gap: 3rem;
    }
    .footer-brand-col .brand-name {
      font-family: var(--font-display);
      font-size: 1.8rem;
      color: #fff;
      margin-bottom: .75rem;
      letter-spacing: .05em;
    }
    .footer-brand-col .brand-name span { color: var(--red); }
    .footer-brand-col p {
      font-size: .85rem; color: rgba(255,255,255,.45);
      max-width: 260px; margin-bottom: 1.25rem;
    }
    .footer-contact-item {
      display: flex; align-items: center; gap: .5rem;
      font-size: .82rem; color: rgba(255,255,255,.45); margin-bottom: .4rem;
      font-family: var(--font-body);
    }
    .footer-col h5 {
      font-family: var(--font-cond);
      font-size: .72rem; font-weight: 700; letter-spacing: .15em;
      text-transform: uppercase; color: rgba(255,255,255,.4);
      margin-bottom: 1rem;
    }
    .footer-col a {
      display: block; font-size: .85rem; color: rgba(255,255,255,.6);
      margin-bottom: .5rem; transition: color .2s;
    }
    .footer-col a:hover { color: #fff; }
    .footer-bottom {
      max-width: 1280px; margin: 0 auto;
      padding-top: 1.5rem;
      border-top: 1px solid rgba(255,255,255,.08);
      display: flex; justify-content: space-between; align-items: center;
      flex-wrap: wrap; gap: 1rem;
    }
    .footer-bottom p { font-size: .78rem; color: rgba(255,255,255,.3); margin: 0; }

    @media (max-width: 900px) {
      .hero-inner { grid-template-columns: 1fr; padding: 3rem 1.5rem 4rem; }
      .hero-red-bar { display: none; }
      .hero-title { font-size: clamp(4rem, 15vw, 7rem); }
      .hero-img-col { display: none; }
      .footer-grid { grid-template-columns: 1fr; gap: 2rem; }
    }
    @media (max-width: 700px) {
      .landing-nav { padding: 0 1rem; }
      .hero-ctas { flex-direction: column; align-items: flex-start; }
      .features-inner { grid-template-columns: 1fr 1fr; }
      .feature-item { border-right: none; border-bottom: 1px solid rgba(255,255,255,.08); }
    }
  </style>
</head>
<body>

<!-- Promo bar -->
<div class="promo-bar" style="position:fixed;top:0;left:0;right:0;z-index:101;">
  FREE SETUP ON ALL GUITARS THIS MONTH &nbsp;·&nbsp; VISIT US IN DAVAO CITY
</div>

<!-- Fixed nav -->
<nav class="landing-nav">
  <div class="brand">
    <img src="https://github.com/bert-28/rmi/blob/main/RMI%20Logo%20(2).png?raw=true" alt="RMI Logo" style="height:44px;width:auto;object-fit:contain;display:block;">
  </div>
  <div class="nav-btns">
    <?php if (is_logged_in()): ?>
      <?php $__u = current_user(); ?>
      <a href="<?= BASE_URL ?>/shop.php" class="btn btn-ghost btn-sm">Shop</a>
      <span style="font-size:.82rem;color:var(--text-2);padding:0 .25rem;display:inline-flex;align-items:center;gap:.35rem"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg><?= h(explode(' ', $__u['name'])[0]) ?></span>
      <a href="<?= BASE_URL ?>/logout.php" class="btn btn-primary btn-sm">Logout</a>
    <?php else: ?>
      <a href="<?= BASE_URL ?>/login.php" class="btn btn-ghost btn-sm">Login</a>
      <a href="<?= BASE_URL ?>/register.php" class="btn btn-primary btn-sm">Sign Up</a>
    <?php endif; ?>
  </div>
</nav>

<!-- Hero -->
<section class="hero-full">
  <div class="hero-bg-pattern"></div>
  <div class="hero-red-bar"></div>
  <div class="hero-inner">
    <div class="hero-text">
      <span class="hero-eyebrow">Davao City, Davao del Sur</span>
      <h1 class="hero-title">
        PLAY<br>
        <span class="line2">YOUR </span>
        BEST.<br>
        
      </h1>
      <p class="hero-sub">
        Premium guitars, amplifiers, and accessories — plus expert repair and setup services. Everything for your sound, all in one place.
      </p>
      <div class="hero-ctas">
        <a href="<?= BASE_URL ?>/shop.php" class="btn btn-primary btn-lg">Shop Now</a>
        <a href="#services" class="btn btn-ghost-inv btn-lg">Our Services</a>
      </div>
    </div>
    <div class="hero-img-col">
      <div class="hero-guitar-wrap">
        <img src="https://my.fender.com/assets/images/connect-login-hero3.jpg" alt="Guitar and Amplifier">
        <div class="hero-guitar-label">Premium Instruments In Stock</div>
      </div>
    </div>
  </div>
</section>

<!-- Features strip -->
<div class="features-strip">
  <div class="features-inner">
    <div class="feature-item">
      <div class="feature-icon"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg></div>
      <div>
        <h4>Expert Repairs</h4>
        <p>Certified luthiers for full setup & restoration</p>
      </div>
    </div>
    <div class="feature-item">
      <div class="feature-icon"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg></div>
      <div>
        <h4>Guitar Lessons</h4>
        <p>One-on-one sessions for all skill levels</p>
      </div>
    </div>
    <div class="feature-item">
      <div class="feature-icon"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4a2 2 0 0 1-2-2V5h4"/><path d="M18 9h2a2 2 0 0 0 2-2V5h-4"/><path d="M12 17v4"/><path d="M8 21h8"/><path d="M6 3h12v8a6 6 0 0 1-12 0z"/></svg></div>
      <div>
        <h4>Top Brands</h4>
        <p>Fender, Gibson, Boss, Ernie Ball & more</p>
      </div>
    </div>
    <div class="feature-item">
      <div class="feature-icon"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m16.5 9.4-9-5.19"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg></div>
      <div>
        <h4>Always In Stock</h4>
        <p>Strings, picks, pedals ready for pickup</p>
      </div>
    </div>
  </div>
</div>

<!-- Categories -->
<section class="section" style="background: var(--bg-0);">
  <div class="section-inner">
    <div class="section-label">Browse</div>
    <div class="section-head">
      <h2>Shop by Category</h2>
      <a href="<?= BASE_URL ?>/shop.php">View All →</a>
    </div>
    <div class="categories-grid">
      <a href="<?= BASE_URL ?>/shop.php?category=Guitar&sort=default" class="cat-card">
        <span class="cat-emoji"><svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m9 9-2 2a4.5 4.5 0 1 0 6 6l2-2"/><path d="M13 6l3-3 3 3-3 3z"/><path d="m10 10 4-4"/><path d="m6 20 3-3"/></svg></span>
        <span class="cat-name">Guitars</span>
      </a>
      <a href="<?= BASE_URL ?>/shop.php?category=Bass&sort=default" class="cat-card">
        <span class="cat-emoji"><svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg></span>
        <span class="cat-name">Bass</span>
      </a>
      <a href="<?= BASE_URL ?>/shop.php?category=Amplifiers&sort=default" class="cat-card">
        <span class="cat-emoji"><svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/></svg></span>
        <span class="cat-name">Amplifiers</span>
      </a>
      <a href="<?= BASE_URL ?>/shop.php?category=Pedal&sort=default" class="cat-card">
        <span class="cat-emoji"><svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="8" r="3"/><path d="M8 5V3"/><path d="M8 11v2"/><circle cx="16" cy="16" r="3"/><path d="M16 13v-2"/><path d="M16 19v2"/><path d="M3 16h5"/><path d="M16 8h5"/></svg></span>
        <span class="cat-name">Pedals</span>
      </a>
      <a href="<?= BASE_URL ?>/shop.php?category=Strings&sort=default" class="cat-card">
        <span class="cat-emoji"><svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h1m16 0h1"/><path d="M4 12c2-4 4-6 8-6s6 2 8 6"/><path d="M4 12c2 4 4 6 8 6s6-2 8-6"/><circle cx="12" cy="12" r="1" fill="currentColor"/></svg></span>
        <span class="cat-name">Pedals</span>
      </a>
      <a href="<?= BASE_URL ?>/shop.php?category=Accessories&sort=default" class="cat-card">
        <span class="cat-emoji"><svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="17" r="3"/><circle cx="16" cy="15" r="3"/><polyline points="9 17 9 5 19 3 19 15"/><line x1="9" y1="9" x2="19" y2="7"/></svg></span>
        <span class="cat-name">Accessories</span>
      </a>
    </div>
  </div>
</section>

<!-- Banner -->
<div class="banner-strip">
  <h2>THE BEST GEAR.<br><span>THE BEST SERVICE.</span></h2>
  <p>Davao's home for serious musicians. Walk in with a problem, walk out ready to play.</p>
  <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap">
    <a href="<?= BASE_URL ?>/register.php" class="btn btn-ghost-inv btn-lg">Create Account</a>
    <a href="#services" class="btn btn-primary btn-lg" style="background:#fff;color:var(--red);border-color:#fff;">Explore Services</a>
  </div>
</div>

<!-- Services -->
<section class="services-section" id="services">
  <div class="section-inner">
    <div class="section-label">What We Do</div>
    <h2>Pro Services</h2>
    <div class="services-grid">
      <div class="service-card">
        <span class="s-icon"><svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg></span>
        <h4>Full Guitar Setup</h4>
        <p>Intonation, action, truss rod, and nut — we get your guitar playing exactly how you want it.</p>
      </div>
      <div class="service-card">
        <span class="s-icon"><svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg></span>
        <h4>Electronics Repair</h4>
        <p>Pickup swaps, wiring harness fixes, jack replacements, and more.</p>
      </div>
      <div class="service-card">
        <span class="s-icon"><svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h1m16 0h1"/><path d="M4 12c2-4 4-6 8-6s6 2 8 6"/><path d="M4 12c2 4 4 6 8 6s6-2 8-6"/><circle cx="12" cy="12" r="1" fill="currentColor"/></svg></span>
        <h4>String Replacement</h4>
        <p>Quick restring with your preferred brand and gauge while you wait.</p>
      </div>
      <div class="service-card">
        <span class="s-icon"><svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.5 4.5L18 9l-4.5 1.5L12 15l-1.5-4.5L6 9l4.5-1.5z"/><path d="M5 3l.75 2.25L8 6l-2.25.75L5 9l-.75-2.25L2 6l2.25-.75z"/><path d="M19 15l.75 2.25L22 18l-2.25.75L19 21l-.75-2.25L16 18l2.25-.75z"/></svg></span>
        <h4>Deep Cleaning</h4>
        <p>Fretboard conditioning, body polish, and hardware cleaning to restore that showroom feel.</p>
      </div>
      <div class="service-card">
        <span class="s-icon"><svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m9 9-2 2a4.5 4.5 0 1 0 6 6l2-2"/><path d="M13 6l3-3 3 3-3 3z"/><path d="m10 10 4-4"/><path d="m6 20 3-3"/></svg></span>
        <h4>Guitar Lessons</h4>
        <p>Learn from experienced players. Beginner to advanced sessions by appointment.</p>
      </div>
      <div class="service-card">
        <span class="s-icon"><svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4.8 2.3A.3.3 0 1 0 5 2H4a2 2 0 0 0-2 2v5a6 6 0 0 0 6 6a6 6 0 0 0 6-6V4a2 2 0 0 0-2-2h-1a.2.2 0 1 0 .3.3"/><path d="M8 15v1a6 6 0 0 0 6 6v0a6 6 0 0 0 6-6v-4"/><circle cx="20" cy="10" r="2"/></svg></span>
        <h4>Free Consultation</h4>
        <p>Not sure what your guitar needs? Bring it in — we'll assess it for free.</p>
      </div>
    </div>
    <div style="text-align:center; margin-top:2.5rem">
      <a href="<?= BASE_URL ?>/appointments.php" class="btn btn-primary btn-lg">Book an Appointment</a>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="cta-band">
  <h2>READY TO PLAY?</h2>
  <p>Join hundreds of musicians in Davao who trust Resurrection for their gear and repairs.</p>
  <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap">
    <a href="<?= BASE_URL ?>/register.php" class="btn btn-lg" style="background:#fff;color:var(--red);border-color:#fff;font-family:var(--font-cond);font-weight:700;letter-spacing:.12em;">Create Account</a>
    <a href="<?= BASE_URL ?>/login.php" class="btn btn-ghost-inv btn-lg">Sign In</a>
  </div>
</section>

<!-- Footer -->
<footer class="footer-full">
  <div class="footer-grid">
    <div class="footer-brand-col">
    <img src="https://github.com/bert-28/rmi/blob/main/RMI%20Logo%20(2).png?raw=true" alt="RMI Logo" style="height:44px;width:auto;object-fit:contain;display:block;">
      <p>Resurrection Musical Instruments — Davao City's home for premium guitars, expert repairs, and everything in between.</p>
      <div class="footer-contact-item"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/></svg> Davao City, Davao del Norte</div>
      <div class="footer-contact-item"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.57 3.38 2 2 0 0 1 3.54 1.18l3 .01a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L7.91 8.96a16 16 0 0 0 6.13 6.13l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg> 09463439660</div>
      <div class="footer-contact-item"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg> rmi@resurrection.ph</div>
    </div>
    <div class="footer-col">
      <h5>Shop</h5>
      <a href="<?= BASE_URL ?>/shop.php?category=Guitar&sort=default">Guitars</a>
      <a href="<?= BASE_URL ?>/shop.php?category=Amplifier&sort=default">Amplifiers</a>
      <a href="<?= BASE_URL ?>/shop.php?category=Pedals&sort=default">Pedals</a>
      <a href="<?= BASE_URL ?>/shop.php?category=Strings&sort=default">Strings</a>
      <a href="<?= BASE_URL ?>/shop.php?category=Accessories&sort=default">Accessories</a>
    </div>
    <div class="footer-col">
      <h5>Services</h5>
      <a href="<?= BASE_URL ?>/register.php">Book Appointment</a>
      <a href="#services">Guitar Setup</a>
      <a href="#services">Repairs</a>
      <a href="#services">Lessons</a>
    </div>
  </div>
  <div class="footer-bottom">
    <p>© <?= date('Y') ?> Resurrection Musical Instruments. All rights reserved.</p>
    <p>Davao City, Davao del Sur, Philippines</p>
  </div>
</footer>

<script src="<?= BASE_URL ?>/js/app.js"></script>
</body>
</html>