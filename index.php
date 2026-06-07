<?php
// ============================================================
//  Richiamo Coffee — Public Landing Page
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

// If already logged in, redirect to their dashboard
$user = validate_session();
if ($user) {
    if ($user['role'] === ROLE_ADMIN || $user['role'] === ROLE_DEVELOPER)
        redirect_with_message(APP_URL . '/admin/dashboard.php');
    else
        redirect_with_message(APP_URL . '/customer/menu.php');
}

// Fetch featured menu items for the showcase section
$db = get_db();
$featured = $db->query("
    SELECT m.*, c.name AS category_name
    FROM menu_items m
    JOIN categories c ON c.id = m.category_id
    WHERE m.is_featured = 1 AND m.is_available = 1
    ORDER BY RAND()
    LIMIT 6
")->fetchAll();

// Fetch categories for menu preview
$categories = $db->query("
    SELECT c.*, COUNT(m.id) AS item_count
    FROM categories c
    LEFT JOIN menu_items m ON m.category_id = c.id AND m.is_available = 1
    WHERE c.is_active = 1
    GROUP BY c.id
    ORDER BY c.sort_order
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Richiamo Coffee — Order your favourite coffee online. Skip the queue, earn loyalty points.">
  <title><?= APP_NAME ?> — Crafted with care, served with love</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
    :root {
      --espresso: #1C0A00;
      --roast:    #3B1A08;
      --caramel:  #C68642;
      --latte:    #D4A96A;
      --cream:    #F5E6C8;
      --foam:     #FDF6EC;
      --surface:  #FEFAF4;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }
    body { font-family: 'DM Sans', sans-serif; background: var(--surface); color: var(--espresso); overflow-x: hidden; }

    /* ── Navbar ─────────────────────────────────────────────── */
    .navbar-rc {
      position: fixed; top: 0; left: 0; right: 0; z-index: 999;
      background: transparent;
      padding: 1.25rem 0;
      transition: background .3s, padding .3s, box-shadow .3s;
    }
    .navbar-rc.scrolled {
      background: var(--espresso);
      padding: .75rem 0;
      box-shadow: 0 2px 20px rgba(0,0,0,.3);
    }
    .nav-brand {
      font-family: 'Playfair Display', serif;
      color: var(--cream) !important;
      font-size: 1.3rem;
      text-decoration: none;
      display: flex; align-items: center; gap: .5rem;
    }
    .nav-links { display: flex; align-items: center; gap: .25rem; }
    .nav-link-rc {
      color: rgba(255,255,255,.75);
      text-decoration: none;
      font-size: .875rem;
      padding: .4rem .85rem;
      border-radius: 2rem;
      transition: all .2s;
    }
    .nav-link-rc:hover { color: var(--cream); background: rgba(255,255,255,.1); }
    .nav-cta {
      background: var(--caramel);
      color: var(--espresso) !important;
      font-weight: 600;
      border-radius: 2rem;
      padding: .45rem 1.1rem;
      font-size: .875rem;
      text-decoration: none;
      transition: background .2s, transform .1s;
    }
    .nav-cta:hover { background: var(--latte); transform: scale(1.03); }

    /* Mobile nav toggle */
    .nav-toggler {
      background: none; border: none; cursor: pointer;
      color: var(--cream); font-size: 1.4rem; padding: 0;
      display: none;
    }
    @media (max-width: 768px) {
      .nav-links { display: none; }
      .nav-links.open {
        display: flex; flex-direction: column;
        position: absolute; top: 100%; left: 0; right: 0;
        background: var(--espresso);
        padding: 1rem 1.5rem 1.5rem;
        gap: .25rem;
        border-top: 1px solid rgba(255,255,255,.08);
      }
      .nav-toggler { display: block; }
    }

    /* ── Hero ───────────────────────────────────────────────── */
    .hero {
      min-height: 100vh;
      background: linear-gradient(160deg, var(--espresso) 0%, var(--roast) 60%, #5C2D0E 100%);
      display: flex; align-items: center;
      position: relative; overflow: hidden;
    }
    .hero::before {
      content: '';
      position: absolute; inset: 0;
      background:
        radial-gradient(ellipse at 20% 50%, rgba(198,134,66,.12) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 20%, rgba(212,169,106,.08) 0%, transparent 40%),
        radial-gradient(ellipse at 60% 80%, rgba(92,45,14,.4) 0%, transparent 50%);
      pointer-events: none;
    }

    /* Floating coffee rings decoration */
    .hero-ring {
      position: absolute; border-radius: 50%;
      border: 1px solid rgba(198,134,66,.12);
      pointer-events: none;
    }
    .ring-1 { width: 400px; height: 400px; top: -100px; right: -80px; }
    .ring-2 { width: 600px; height: 600px; top: -200px; right: -200px; }
    .ring-3 { width: 200px; height: 200px; bottom: 80px; left: -60px; }

    .hero-content { position: relative; z-index: 1; padding-top: 80px; }
    .hero-eyebrow {
      display: inline-flex; align-items: center; gap: .5rem;
      background: rgba(198,134,66,.15);
      border: 1px solid rgba(198,134,66,.3);
      color: var(--latte);
      border-radius: 2rem; padding: .3rem .9rem;
      font-size: .78rem; font-weight: 500;
      letter-spacing: .5px; text-transform: uppercase;
      margin-bottom: 1.5rem;
    }
    .hero-title {
      font-family: 'Playfair Display', serif;
      font-size: clamp(2.5rem, 6vw, 4.5rem);
      color: var(--cream);
      line-height: 1.08;
      margin-bottom: 1.25rem;
    }
    .hero-title em { color: var(--caramel); font-style: italic; }
    .hero-subtitle {
      color: rgba(245,230,200,.65);
      font-size: 1.05rem;
      line-height: 1.7;
      max-width: 480px;
      margin-bottom: 2rem;
    }
    .hero-actions { display: flex; gap: .75rem; flex-wrap: wrap; }
    .btn-hero-primary {
      background: var(--caramel); color: var(--espresso);
      border: none; border-radius: 3rem;
      padding: .85rem 2rem; font-size: .95rem; font-weight: 600;
      text-decoration: none; display: inline-flex; align-items: center; gap: .5rem;
      transition: all .2s; cursor: pointer;
    }
    .btn-hero-primary:hover { background: var(--latte); transform: translateY(-2px); color: var(--espresso); box-shadow: 0 8px 24px rgba(198,134,66,.35); }
    .btn-hero-secondary {
      background: transparent; color: var(--cream);
      border: 1.5px solid rgba(255,255,255,.3); border-radius: 3rem;
      padding: .85rem 2rem; font-size: .95rem; font-weight: 500;
      text-decoration: none; display: inline-flex; align-items: center; gap: .5rem;
      transition: all .2s;
    }
    .btn-hero-secondary:hover { border-color: var(--cream); color: var(--cream); background: rgba(255,255,255,.08); }

    /* Hero stats */
    .hero-stats {
      display: flex; gap: 2.5rem; margin-top: 3rem; flex-wrap: wrap;
    }
    .hero-stat-value {
      font-family: 'Playfair Display', serif;
      font-size: 1.6rem; color: var(--cream);
    }
    .hero-stat-label { font-size: .75rem; color: var(--latte); margin-top: .1rem; }

    /* Hero visual (right side) */
    .hero-visual {
      position: relative; z-index: 1;
      display: flex; align-items: center; justify-content: center;
      padding: 80px 0 40px;
    }
    .coffee-cup-wrap {
      width: 320px; height: 320px;
      background: rgba(198,134,66,.08);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 10rem;
      border: 1px solid rgba(198,134,66,.15);
      animation: float 4s ease-in-out infinite;
      position: relative;
    }
    @keyframes float {
      0%,100% { transform: translateY(0); }
      50%      { transform: translateY(-16px); }
    }
    .floating-tag {
      position: absolute;
      background: var(--foam); border-radius: .75rem;
      padding: .6rem .9rem; font-size: .78rem; font-weight: 500;
      color: var(--espresso); box-shadow: 0 4px 16px rgba(0,0,0,.12);
      display: flex; align-items: center; gap: .4rem;
      animation: float 4s ease-in-out infinite;
    }
    .tag-1 { top: 30px;  right: -20px; animation-delay: .5s; }
    .tag-2 { bottom: 60px; left: -30px; animation-delay: 1s; }
    .tag-3 { top: 120px; left: -50px;  animation-delay: 1.5s; }

    /* ── Section shared ─────────────────────────────────────── */
    section { padding: 5rem 0; }
    .section-eyebrow {
      font-size: .75rem; font-weight: 500; letter-spacing: 1.5px;
      text-transform: uppercase; color: var(--caramel);
      margin-bottom: .6rem;
    }
    .section-title {
      font-family: 'Playfair Display', serif;
      font-size: clamp(1.6rem, 3vw, 2.25rem);
      color: var(--espresso); line-height: 1.2;
      margin-bottom: 1rem;
    }
    .section-title em { font-style: italic; color: var(--caramel); }
    .section-sub { color: #888; font-size: .95rem; line-height: 1.7; max-width: 520px; }

    /* ── How it works ───────────────────────────────────────── */
    .steps-section { background: var(--espresso); }
    .step-card {
      text-align: center; padding: 1.5rem 1rem;
    }
    .step-num {
      width: 52px; height: 52px; border-radius: 50%;
      background: rgba(198,134,66,.15);
      border: 1px solid rgba(198,134,66,.3);
      display: inline-flex; align-items: center; justify-content: center;
      font-family: 'Playfair Display', serif;
      font-size: 1.3rem; color: var(--caramel);
      margin-bottom: 1rem;
    }
    .step-title { font-weight: 600; color: var(--cream); margin-bottom: .4rem; font-size: .95rem; }
    .step-desc  { font-size: .82rem; color: rgba(245,230,200,.55); line-height: 1.6; }
    .step-connector {
      display: flex; align-items: center; justify-content: center;
      padding-top: 26px; color: rgba(198,134,66,.3); font-size: 1.2rem;
    }

    /* ── Featured menu ──────────────────────────────────────── */
    .menu-card {
      background: #fff; border: 1px solid #ede8df;
      border-radius: 1rem; overflow: hidden;
      transition: transform .2s, box-shadow .2s, border-color .2s;
      cursor: pointer; height: 100%;
    }
    .menu-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 12px 32px rgba(28,10,0,.1);
      border-color: var(--caramel);
    }
    .menu-card-img {
      height: 130px; background: var(--foam);
      display: flex; align-items: center; justify-content: center;
      font-size: 3.5rem; position: relative;
    }
    .featured-badge {
      position: absolute; top: .6rem; left: .6rem;
      background: var(--caramel); color: #fff;
      font-size: .62rem; font-weight: 600; letter-spacing: .5px;
      text-transform: uppercase; padding: .2rem .55rem; border-radius: 1rem;
    }
    .menu-card-body { padding: .9rem 1rem 1rem; }
    .menu-card-name { font-weight: 600; font-size: .9rem; color: var(--espresso); margin-bottom: .2rem; }
    .menu-card-desc { font-size: .75rem; color: #aaa; margin-bottom: .65rem; line-height: 1.4; }
    .menu-card-foot { display: flex; align-items: center; justify-content: space-between; }
    .menu-card-price { font-family: 'Playfair Display', serif; font-size: 1rem; color: var(--roast); }
    .btn-order-now {
      background: var(--espresso); color: var(--cream);
      border: none; border-radius: 2rem; padding: .3rem .8rem;
      font-size: .75rem; cursor: pointer; text-decoration: none;
      transition: background .2s;
    }
    .btn-order-now:hover { background: var(--caramel); color: var(--espresso); }

    /* ── Category chips ─────────────────────────────────────── */
    .cat-chip {
      display: flex; flex-direction: column; align-items: center;
      background: #fff; border: 1px solid #ede8df;
      border-radius: 1rem; padding: 1.25rem 1rem;
      text-decoration: none; transition: all .2s;
      cursor: pointer;
    }
    .cat-chip:hover {
      border-color: var(--caramel);
      box-shadow: 0 4px 16px rgba(198,134,66,.15);
      transform: translateY(-3px);
    }
    .cat-chip-icon { font-size: 2rem; margin-bottom: .5rem; }
    .cat-chip-name { font-size: .8rem; font-weight: 500; color: var(--espresso); }
    .cat-chip-count { font-size: .7rem; color: #aaa; margin-top: .1rem; }

    /* ── Loyalty / perks ────────────────────────────────────── */
    .perks-section { background: var(--foam); }
    .perk-card {
      background: #fff; border: 1px solid #ede8df;
      border-radius: 1rem; padding: 1.5rem;
      height: 100%; transition: box-shadow .2s;
    }
    .perk-card:hover { box-shadow: 0 6px 20px rgba(28,10,0,.07); }
    .perk-icon {
      width: 48px; height: 48px; border-radius: .75rem;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.3rem; margin-bottom: 1rem;
    }
    .perk-title { font-weight: 600; font-size: .95rem; color: var(--espresso); margin-bottom: .4rem; }
    .perk-desc  { font-size: .82rem; color: #888; line-height: 1.6; }

    /* ── Testimonials ───────────────────────────────────────── */
    .testimonial-card {
      background: #fff; border: 1px solid #ede8df;
      border-radius: 1rem; padding: 1.5rem;
    }
    .stars { color: var(--caramel); font-size: .85rem; margin-bottom: .75rem; }
    .quote { font-size: .875rem; color: #555; line-height: 1.7; margin-bottom: 1rem; font-style: italic; }
    .reviewer { display: flex; align-items: center; gap: .65rem; }
    .reviewer-avatar {
      width: 36px; height: 36px; border-radius: 50%;
      background: var(--cream); display: flex; align-items: center;
      justify-content: center; font-size: .9rem; font-weight: 600;
      color: var(--roast); flex-shrink: 0;
    }
    .reviewer-name { font-size: .82rem; font-weight: 600; color: var(--espresso); }
    .reviewer-sub  { font-size: .72rem; color: #aaa; }

    /* ── CTA banner ─────────────────────────────────────────── */
    .cta-banner {
      background: linear-gradient(135deg, var(--espresso), var(--roast));
      border-radius: 1.5rem; padding: 3rem 2rem;
      text-align: center; position: relative; overflow: hidden;
    }
    .cta-banner::before {
      content: '';
      position: absolute; inset: 0;
      background: radial-gradient(ellipse at 50% 0%, rgba(198,134,66,.15) 0%, transparent 60%);
      pointer-events: none;
    }
    .cta-title { font-family: 'Playfair Display', serif; font-size: clamp(1.5rem,3vw,2rem); color: var(--cream); margin-bottom: .75rem; }
    .cta-sub   { color: var(--latte); font-size: .9rem; margin-bottom: 1.75rem; }

    /* ── Footer ─────────────────────────────────────────────── */
    footer {
      background: var(--espresso);
      padding: 3rem 0 1.5rem;
      color: rgba(245,230,200,.55);
    }
    .footer-brand { font-family: 'Playfair Display', serif; font-size: 1.3rem; color: var(--cream); margin-bottom: .5rem; }
    .footer-tagline { font-size: .8rem; color: var(--latte); }
    .footer-heading { font-size: .72rem; text-transform: uppercase; letter-spacing: 1px; color: var(--latte); font-weight: 600; margin-bottom: .75rem; }
    .footer-link { display: block; font-size: .82rem; color: rgba(245,230,200,.55); text-decoration: none; margin-bottom: .35rem; transition: color .15s; }
    .footer-link:hover { color: var(--cream); }
    .footer-divider { border-color: rgba(255,255,255,.08); margin: 1.5rem 0; }
    .footer-bottom { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .5rem; font-size: .75rem; }

    /* ── Scroll animations ──────────────────────────────────── */
    .reveal { opacity: 0; transform: translateY(24px); transition: opacity .6s ease, transform .6s ease; }
    .reveal.visible { opacity: 1; transform: translateY(0); }
    .reveal-delay-1 { transition-delay: .1s; }
    .reveal-delay-2 { transition-delay: .2s; }
    .reveal-delay-3 { transition-delay: .3s; }
    .reveal-delay-4 { transition-delay: .4s; }
  </style>
</head>
<body>

<!-- ── Navbar ─────────────────────────────────────────────────── -->
<nav class="navbar-rc" id="navbar">
  <div class="container d-flex align-items-center justify-content-between">
    <a href="<?= APP_URL ?>" class="nav-brand">☕ <?= APP_NAME ?></a>

    <button class="nav-toggler" onclick="toggleNav()" aria-label="Menu">
      <i class="bi bi-list" id="nav-icon"></i>
    </button>

    <div class="nav-links" id="nav-links">
      <a href="#menu"     class="nav-link-rc">Menu</a>
      <a href="#how"      class="nav-link-rc">How it works</a>
      <a href="#perks"    class="nav-link-rc">Perks</a>
      <a href="<?= APP_URL ?>/auth/login.php"    class="nav-link-rc">Sign in</a>
      <a href="<?= APP_URL ?>/auth/register.php" class="nav-cta ms-2">Order now</a>
    </div>
  </div>
</nav>

<!-- ── Hero ───────────────────────────────────────────────────── -->
<section class="hero">
  <div class="hero-ring ring-1"></div>
  <div class="hero-ring ring-2"></div>
  <div class="hero-ring ring-3"></div>

  <div class="container">
    <div class="row align-items-center">
      <div class="col-lg-6 hero-content">
        <div class="hero-eyebrow">
          <i class="bi bi-geo-alt-fill"></i> Now serving in Malaysia
        </div>
        <h1 class="hero-title">
          Good coffee,<br><em>great moments.</em>
        </h1>
        <p class="hero-subtitle">
          Order your favourite brew ahead of time, skip the queue,
          and earn loyalty points with every cup. Richiamo Coffee —
          crafted with care, served with love.
        </p>
        <div class="hero-actions">
          <a href="<?= APP_URL ?>/auth/register.php" class="btn-hero-primary">
            <i class="bi bi-cup-hot-fill"></i> Start ordering
          </a>
          <a href="#menu" class="btn-hero-secondary">
            <i class="bi bi-journal-text"></i> See our menu
          </a>
        </div>
        <div class="hero-stats">
          <div>
            <div class="hero-stat-value">50+</div>
            <div class="hero-stat-label">Menu items</div>
          </div>
          <div>
            <div class="hero-stat-value">5 min</div>
            <div class="hero-stat-label">Avg wait time</div>
          </div>
          <div>
            <div class="hero-stat-value">100%</div>
            <div class="hero-stat-label">Freshly brewed</div>
          </div>
        </div>
      </div>

      <div class="col-lg-6 hero-visual">
        <div class="coffee-cup-wrap">
          ☕
          <div class="floating-tag tag-1">
            <i class="bi bi-star-fill" style="color:var(--caramel);"></i>
            Earn loyalty points
          </div>
          <div class="floating-tag tag-2">
            🥡 Takeaway ready
          </div>
          <div class="floating-tag tag-3">
            <i class="bi bi-clock" style="color:var(--caramel);"></i>
            Skip the queue
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ── How it works ─────────────────────────────────────────── -->
<section class="steps-section" id="how">
  <div class="container">
    <div class="text-center mb-5 reveal">
      <div class="section-eyebrow" style="color:var(--latte);">Simple & fast</div>
      <h2 class="section-title" style="color:var(--cream);">How it <em>works</em></h2>
      <p class="section-sub" style="color:rgba(245,230,200,.55);margin:0 auto;">
        Three easy steps to your perfect cup.
      </p>
    </div>
    <div class="row align-items-center justify-content-center g-0">
      <?php
        $steps = [
          ['num'=>'1','icon'=>'bi-person-plus','title'=>'Create account','desc'=>'Sign up for free in under a minute. No credit card required.'],
          ['num'=>'2','icon'=>'bi-journal-text','title'=>'Browse & order','desc'=>'Pick your favourite items, customise your order, and choose dine-in or takeaway.'],
          ['num'=>'3','icon'=>'bi-cup-hot-fill','title'=>'Enjoy your brew','desc'=>'Skip the queue. Your order is prepared fresh and ready when you arrive.'],
        ];
        foreach ($steps as $i => $step):
      ?>
        <div class="col-md-3 reveal reveal-delay-<?= $i+1 ?>">
          <div class="step-card">
            <div class="step-num"><?= $step['num'] ?></div>
            <div class="step-title"><?= $step['title'] ?></div>
            <div class="step-desc"><?= $step['desc'] ?></div>
          </div>
        </div>
        <?php if ($i < count($steps)-1): ?>
          <div class="col-md-1 d-none d-md-flex step-connector reveal reveal-delay-<?= $i+1 ?>">
            <i class="bi bi-arrow-right"></i>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ── Menu categories ───────────────────────────────────────── -->
<section style="background:var(--surface);padding:4rem 0 2rem;">
  <div class="container">
    <div class="row align-items-end mb-4 reveal">
      <div class="col">
        <div class="section-eyebrow">What we serve</div>
        <h2 class="section-title">Our <em>categories</em></h2>
      </div>
      <div class="col-auto">
        <a href="<?= APP_URL ?>/auth/register.php" style="font-size:.85rem;color:var(--caramel);text-decoration:none;font-weight:500;">
          View full menu <i class="bi bi-arrow-right"></i>
        </a>
      </div>
    </div>
    <div class="row g-3">
      <?php
        $cat_icons = ['Espresso'=>'☕','Cold Brew'=>'🧊','Seasonal'=>'🌿','Non-Coffee'=>'🍵','Food'=>'🥐'];
        foreach ($categories as $i => $cat):
      ?>
        <div class="col-6 col-sm-4 col-md-3 col-lg-2 reveal reveal-delay-<?= min($i+1,4) ?>">
          <a href="<?= APP_URL ?>/auth/register.php" class="cat-chip text-decoration-none">
            <span class="cat-chip-icon"><?= $cat_icons[$cat['name']] ?? '☕' ?></span>
            <span class="cat-chip-name"><?= clean($cat['name']) ?></span>
            <span class="cat-chip-count"><?= $cat['item_count'] ?> items</span>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ── Featured items ────────────────────────────────────────── -->
<?php if (!empty($featured)): ?>
<section id="menu" style="background:var(--surface);padding:2rem 0 5rem;">
  <div class="container">
    <div class="text-center mb-4 reveal">
      <div class="section-eyebrow">Fan favourites</div>
      <h2 class="section-title">Featured <em>drinks</em></h2>
      <p class="section-sub mx-auto">Our most-loved brews, handpicked by our baristas.</p>
    </div>
    <div class="row row-cols-2 row-cols-md-3 row-cols-lg-6 g-3">
      <?php foreach ($featured as $i => $item):
        $icon = $cat_icons[$item['category_name']] ?? '☕';
      ?>
        <div class="col reveal reveal-delay-<?= min($i % 4 + 1, 4) ?>">
          <div class="menu-card">
            <div class="menu-card-img">
              <span class="featured-badge">Featured</span>
              <?= $icon ?>
            </div>
            <div class="menu-card-body">
              <div class="menu-card-name"><?= clean($item['name']) ?></div>
              <div class="menu-card-desc"><?= clean($item['description'] ?? '') ?></div>
              <div class="menu-card-foot">
                <span class="menu-card-price">RM <?= number_format($item['price'], 2) ?></span>
                <a href="<?= APP_URL ?>/auth/register.php" class="btn-order-now">Order</a>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="text-center mt-4 reveal">
      <a href="<?= APP_URL ?>/auth/register.php" class="btn-hero-primary" style="display:inline-flex;">
        <i class="bi bi-grid"></i> View full menu
      </a>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ── Perks / loyalty ───────────────────────────────────────── -->
<section class="perks-section" id="perks">
  <div class="container">
    <div class="text-center mb-5 reveal">
      <div class="section-eyebrow">Why Richiamo</div>
      <h2 class="section-title">Perks of being a <em>member</em></h2>
      <p class="section-sub mx-auto">Sign up free and unlock these benefits from your very first order.</p>
    </div>
    <div class="row g-3">
      <?php
        $perks = [
          ['icon'=>'⭐','bg'=>'#FEF3E2','title'=>'Loyalty points',      'desc'=>'Earn 1 point for every RM1 spent. Redeem points for free drinks and discounts.'],
          ['icon'=>'⚡','bg'=>'#EDFAF4','title'=>'Skip the queue',       'desc'=>'Order ahead on your phone and your coffee will be ready when you walk in.'],
          ['icon'=>'🎯','bg'=>'#EEF4FF','title'=>'Personalised menu',    'desc'=>'We remember your favourites and suggest new drinks based on your taste.'],
          ['icon'=>'🔔','bg'=>'#F3F0FF','title'=>'Order tracking',       'desc'=>'Real-time status updates — know exactly when your order is being prepared.'],
          ['icon'=>'🥡','bg'=>'#FEF3E2','title'=>'Dine in or takeaway',  'desc'=>'Flexible ordering for your lifestyle. Eat in at your table or grab and go.'],
          ['icon'=>'🔒','bg'=>'#EDFAF4','title'=>'Secure & private',     'desc'=>'Your data is protected with encrypted sessions and secure token authentication.'],
        ];
        foreach ($perks as $i => $perk):
      ?>
        <div class="col-md-6 col-lg-4 reveal reveal-delay-<?= min($i % 3 + 1, 4) ?>">
          <div class="perk-card">
            <div class="perk-icon" style="background:<?= $perk['bg'] ?>;"><?= $perk['icon'] ?></div>
            <div class="perk-title"><?= $perk['title'] ?></div>
            <div class="perk-desc"><?= $perk['desc'] ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ── Testimonials ──────────────────────────────────────────── -->
<section style="background:var(--surface);">
  <div class="container">
    <div class="text-center mb-5 reveal">
      <div class="section-eyebrow">What people say</div>
      <h2 class="section-title">Loved by coffee <em>fans</em></h2>
    </div>
    <div class="row g-3">
      <?php
        $reviews = [
          ['name'=>'Amirah S.','loc'=>'Kuala Lumpur',   'rating'=>5,'text'=>'The pandan latte is absolutely incredible. I order it every morning through the app — so convenient!'],
          ['name'=>'Raj K.',   'loc'=>'Petaling Jaya',  'rating'=>5,'text'=>'Love the loyalty points system. I already redeemed my first free drink after just two weeks of ordering.'],
          ['name'=>'Liyana M.','loc'=>'Shah Alam',      'rating'=>5,'text'=>'The gula melaka cold brew is something else. Skip the queue feature alone is worth signing up for.'],
        ];
        foreach ($reviews as $i => $r):
      ?>
        <div class="col-md-4 reveal reveal-delay-<?= $i+1 ?>">
          <div class="testimonial-card">
            <div class="stars">
              <?= str_repeat('<i class="bi bi-star-fill"></i>', $r['rating']) ?>
            </div>
            <p class="quote">"<?= $r['text'] ?>"</p>
            <div class="reviewer">
              <div class="reviewer-avatar"><?= $r['name'][0] ?></div>
              <div>
                <div class="reviewer-name"><?= $r['name'] ?></div>
                <div class="reviewer-sub"><?= $r['loc'] ?></div>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ── CTA banner ────────────────────────────────────────────── -->
<section style="background:var(--surface);padding:2rem 0 5rem;">
  <div class="container reveal">
    <div class="cta-banner">
      <div style="position:relative;z-index:1;">
        <div class="hero-eyebrow" style="display:inline-flex;margin-bottom:1rem;">
          ☕ Start your coffee journey today
        </div>
        <h2 class="cta-title">Your perfect cup is <em style="color:var(--caramel);">one tap away</em></h2>
        <p class="cta-sub">Join thousands of coffee lovers. Free to sign up, no commitment.</p>
        <div style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap;">
          <a href="<?= APP_URL ?>/auth/register.php" class="btn-hero-primary">
            <i class="bi bi-person-plus"></i> Create free account
          </a>
          <a href="<?= APP_URL ?>/auth/login.php" class="btn-hero-secondary">
            <i class="bi bi-box-arrow-in-right"></i> Sign in
          </a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ── Footer ─────────────────────────────────────────────────── -->
<footer>
  <div class="container">
    <div class="row g-4 mb-4">
      <div class="col-lg-4">
        <div class="footer-brand">☕ <?= APP_NAME ?></div>
        <div class="footer-tagline">Crafted with care, served with love.</div>
        <p style="font-size:.8rem;color:rgba(245,230,200,.4);margin-top:.75rem;line-height:1.7;">
          A modern coffee ordering experience built for Malaysia's coffee culture.
        </p>
      </div>
      <div class="col-6 col-lg-2">
        <div class="footer-heading">Order</div>
        <a href="<?= APP_URL ?>/auth/register.php" class="footer-link">Create account</a>
        <a href="<?= APP_URL ?>/auth/login.php"    class="footer-link">Sign in</a>
        <a href="#menu"                            class="footer-link">Our menu</a>
      </div>
      <div class="col-6 col-lg-2">
        <div class="footer-heading">Discover</div>
        <a href="#how"   class="footer-link">How it works</a>
        <a href="#perks" class="footer-link">Loyalty perks</a>
        <a href="#menu"  class="footer-link">Featured drinks</a>
      </div>
      <div class="col-6 col-lg-2">
        <div class="footer-heading">Categories</div>
        <?php foreach ($categories as $cat): ?>
          <a href="<?= APP_URL ?>/auth/register.php" class="footer-link"><?= clean($cat['name']) ?></a>
        <?php endforeach; ?>
      </div>
      <div class="col-6 col-lg-2">
        <div class="footer-heading">Contact</div>
        <span class="footer-link">📍 Malaysia</span>
        <span class="footer-link">📧 hello@richiamo.my</span>
        <span class="footer-link">📞 +60 3-XXXX XXXX</span>
      </div>
    </div>
    <hr class="footer-divider">
    <div class="footer-bottom">
      <span>&copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved.</span>
      <span style="color:rgba(245,230,200,.3);">Built with ☕ &amp; PHP</span>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Navbar scroll effect ───────────────────────────────────────
const navbar = document.getElementById('navbar');
window.addEventListener('scroll', () => {
  navbar.classList.toggle('scrolled', window.scrollY > 60);
});

// ── Mobile nav toggle ──────────────────────────────────────────
function toggleNav() {
  const links = document.getElementById('nav-links');
  const icon  = document.getElementById('nav-icon');
  links.classList.toggle('open');
  icon.className = links.classList.contains('open') ? 'bi bi-x' : 'bi bi-list';
}

// ── Scroll reveal ──────────────────────────────────────────────
const observer = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      e.target.classList.add('visible');
      observer.unobserve(e.target);
    }
  });
}, { threshold: 0.12 });

document.querySelectorAll('.reveal').forEach(el => observer.observe(el));

// ── Smooth close mobile nav on link click ─────────────────────
document.querySelectorAll('#nav-links a').forEach(a => {
  a.addEventListener('click', () => {
    document.getElementById('nav-links').classList.remove('open');
    document.getElementById('nav-icon').className = 'bi bi-list';
  });
});
</script>
</body>
</html>