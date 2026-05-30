<?php
// ============================================================
//  Richiamo Coffee — Customer Menu
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
$allowed = [ROLE_CUSTOMER, ROLE_ADMIN, ROLE_DEVELOPER];
require_once __DIR__ . '/../auth/auth_check.php';

$db   = get_db();
$flash = get_flash();

// Fetch categories
$categories = $db->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order")->fetchAll();

// Fetch all available items grouped by category
$items_stmt = $db->query("
    SELECT m.*, c.name AS category_name, c.slug AS category_slug
    FROM menu_items m
    JOIN categories c ON c.id = m.category_id
    WHERE m.is_available = 1
    ORDER BY c.sort_order, m.sort_order, m.name
");
$all_items = $items_stmt->fetchAll();

// Group by category slug
$menu = [];
foreach ($all_items as $item) {
    $menu[$item['category_slug']][] = $item;
}

// Loyalty points balance
$points = 0;
if ($current_user['role'] === ROLE_CUSTOMER) {
    $pts = $db->prepare("SELECT COALESCE(SUM(points),0) AS total FROM loyalty_points WHERE user_id = ?");
    $pts->execute([$current_user['id']]);
    $points = (int) $pts->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Menu — <?= APP_NAME ?></title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">

  <style>
    :root {
      --espresso:  #1C0A00;
      --roast:     #3B1A08;
      --caramel:   #C68642;
      --latte:     #D4A96A;
      --cream:     #F5E6C8;
      --foam:      #FDF6EC;
      --surface:   #FEFAF4;
    }

    * { box-sizing: border-box; }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--surface);
      color: var(--espresso);
      min-height: 100vh;
    }

    /* ── Navbar ─────────────────────────────────────── */
    .navbar-richiamo {
      background: var(--espresso);
      padding: .9rem 0;
      position: sticky; top: 0; z-index: 100;
      border-bottom: 2px solid var(--caramel);
    }

    .navbar-brand-text {
      font-family: 'Playfair Display', serif;
      color: var(--cream) !important;
      font-size: 1.4rem;
      letter-spacing: .5px;
    }

    .nav-user {
      display: flex; align-items: center; gap: .75rem;
      color: var(--latte);
      font-size: .85rem;
    }

    .points-badge {
      background: var(--caramel);
      color: var(--espresso);
      border-radius: 2rem;
      padding: .25rem .75rem;
      font-size: .75rem;
      font-weight: 500;
    }

    .cart-btn {
      background: var(--caramel);
      color: var(--espresso);
      border: none;
      border-radius: .6rem;
      padding: .45rem .9rem;
      font-size: .85rem;
      font-weight: 500;
      cursor: pointer;
      position: relative;
      transition: background .2s;
      text-decoration: none;
      display: inline-flex; align-items: center; gap: .4rem;
    }
    .cart-btn:hover { background: var(--latte); }
    .cart-count {
      background: var(--espresso);
      color: var(--cream);
      border-radius: 50%;
      width: 18px; height: 18px;
      font-size: .65rem;
      font-weight: 700;
      display: inline-flex; align-items: center; justify-content: center;
    }

    /* ── Hero strip ─────────────────────────────────── */
    .hero-strip {
      background: var(--roast);
      padding: 2.5rem 0 2rem;
      position: relative;
      overflow: hidden;
    }
    .hero-strip::before {
      content: '';
      position: absolute; inset: 0;
      background: radial-gradient(ellipse at 30% 50%, rgba(198,134,66,.15) 0%, transparent 60%);
      pointer-events: none;
    }
    .hero-title {
      font-family: 'Playfair Display', serif;
      font-size: clamp(1.8rem, 4vw, 2.8rem);
      color: var(--cream);
      line-height: 1.1;
    }
    .hero-title em { color: var(--caramel); font-style: italic; }
    .hero-sub {
      color: var(--latte);
      font-size: .9rem;
      margin-top: .5rem;
    }

    /* ── Category tabs ──────────────────────────────── */
    .cat-nav {
      background: #fff;
      border-bottom: 1px solid #ede8df;
      position: sticky; top: 62px; z-index: 90;
    }
    .cat-nav-inner {
      display: flex; gap: .25rem;
      overflow-x: auto;
      padding: .6rem 1rem;
      scrollbar-width: none;
    }
    .cat-nav-inner::-webkit-scrollbar { display: none; }
    .cat-tab {
      white-space: nowrap;
      padding: .45rem 1.1rem;
      border-radius: 2rem;
      border: 1.5px solid transparent;
      font-size: .85rem;
      font-weight: 500;
      cursor: pointer;
      background: transparent;
      color: #888;
      transition: all .2s;
      text-decoration: none;
    }
    .cat-tab:hover  { color: var(--caramel); border-color: var(--caramel); }
    .cat-tab.active { background: var(--espresso); color: var(--cream); border-color: var(--espresso); }

    /* ── Menu grid ──────────────────────────────────── */
    .menu-section { padding: 2.5rem 0 4rem; }

    .section-title {
      font-family: 'Playfair Display', serif;
      font-size: 1.4rem;
      color: var(--espresso);
      margin-bottom: 1.25rem;
      display: flex; align-items: center; gap: .75rem;
    }
    .section-title::after {
      content: '';
      flex: 1; height: 1px;
      background: linear-gradient(to right, #ddd, transparent);
    }

    .menu-card {
      background: #fff;
      border: 1px solid #ede8df;
      border-radius: 1rem;
      overflow: hidden;
      transition: transform .2s, box-shadow .2s;
      cursor: pointer;
      height: 100%;
    }
    .menu-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 8px 24px rgba(28,10,0,.1);
      border-color: var(--caramel);
    }

    .card-img-wrap {
      background: var(--foam);
      display: flex; align-items: center; justify-content: center;
      height: 130px;
      font-size: 3rem;
      position: relative;
    }

    .featured-badge {
      position: absolute; top: .6rem; left: .6rem;
      background: var(--caramel);
      color: #fff;
      font-size: .65rem;
      font-weight: 600;
      letter-spacing: .5px;
      text-transform: uppercase;
      padding: .2rem .55rem;
      border-radius: 1rem;
    }

    .card-body-custom {
      padding: .9rem 1rem 1rem;
    }

    .card-item-name {
      font-weight: 500;
      font-size: .95rem;
      color: var(--espresso);
      margin-bottom: .2rem;
    }

    .card-item-desc {
      font-size: .78rem;
      color: #888;
      margin-bottom: .65rem;
      line-height: 1.4;
      min-height: 2.2em;
    }

    .card-footer-row {
      display: flex; align-items: center; justify-content: space-between;
    }

    .item-price {
      font-family: 'Playfair Display', serif;
      font-size: 1.05rem;
      color: var(--roast);
    }

    .btn-add {
      width: 32px; height: 32px;
      background: var(--espresso);
      color: var(--cream);
      border: none; border-radius: 50%;
      font-size: 1.2rem;
      line-height: 1;
      cursor: pointer;
      transition: background .2s, transform .1s;
      display: flex; align-items: center; justify-content: center;
    }
    .btn-add:hover  { background: var(--caramel); }
    .btn-add:active { transform: scale(.9); }

    /* ── Cart sidebar (offcanvas style) ─────────────── */
    .offcanvas-header { background: var(--espresso); color: var(--cream); }
    .offcanvas-header .btn-close { filter: invert(1); }
    .offcanvas-title { font-family: 'Playfair Display', serif; }

    .cart-item-row {
      display: flex; align-items: center; gap: .75rem;
      padding: .75rem 0;
      border-bottom: 1px solid #f0ebe2;
    }
    .cart-item-icon { font-size: 1.5rem; min-width: 36px; text-align: center; }
    .cart-item-info { flex: 1; }
    .cart-item-name { font-size: .9rem; font-weight: 500; color: var(--espresso); }
    .cart-item-price { font-size: .8rem; color: #888; }
    .qty-row { display: flex; align-items: center; gap: .4rem; margin-top: .3rem; }
    .qty-btn {
      width: 22px; height: 22px;
      border: 1px solid #ddd; background: #fff;
      border-radius: 50%; cursor: pointer; font-size: .9rem;
      display: flex; align-items: center; justify-content: center;
      transition: background .15s;
    }
    .qty-btn:hover { background: var(--cream); }
    .qty-num { font-size: .85rem; font-weight: 500; min-width: 18px; text-align: center; color: var(--espresso); }

    .cart-summary {
      background: var(--foam);
      border-top: 1px solid #ede8df;
      padding: 1rem;
    }
    .summary-line { display: flex; justify-content: space-between; font-size: .85rem; color: #666; margin-bottom: .3rem; }
    .summary-total { display: flex; justify-content: space-between; font-size: 1rem; font-weight: 600; color: var(--espresso); padding-top: .5rem; border-top: 1px solid #ddd; margin-top: .3rem; }

    .btn-checkout {
      width: 100%;
      background: var(--espresso);
      color: var(--cream);
      border: none; border-radius: .75rem;
      padding: .85rem;
      font-family: 'DM Sans', sans-serif;
      font-size: .95rem; font-weight: 500;
      cursor: pointer; margin-top: .75rem;
      transition: background .2s;
      text-decoration: none; display: block; text-align: center;
    }
    .btn-checkout:hover { background: var(--roast); color: var(--cream); }

    .empty-cart { text-align: center; padding: 3rem 1rem; color: #bbb; }
    .empty-cart i { font-size: 2.5rem; display: block; margin-bottom: .75rem; }

    .flash-alert {
      border-radius: .75rem;
      padding: .65rem 1rem;
      margin-bottom: 1rem;
      font-size: .875rem;
      display: flex; align-items: center; gap: .5rem;
    }

    /* ── Footer ─────────────────────────────────────── */
    .site-footer {
      background: var(--espresso);
      color: var(--latte);
      text-align: center;
      padding: 1.5rem;
      font-size: .8rem;
    }
  </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar-richiamo">
  <div class="container d-flex align-items-center justify-content-between">
    <a href="menu.php" class="navbar-brand-text text-decoration-none">
      ☕ <?= APP_NAME ?>
    </a>
    <div class="nav-user">
      <span>Hi, <?= clean($current_user['name']) ?></span>
      <?php if ($points > 0): ?>
        <span class="points-badge"><i class="bi bi-star-fill me-1"></i><?= $points ?> pts</span>
      <?php endif; ?>
      <button class="cart-btn" data-bs-toggle="offcanvas" data-bs-target="#cartSidebar">
        <i class="bi bi-bag"></i> Cart
        <span class="cart-count" id="cart-count">0</span>
      </button>
      <a href="track.php" class="cart-btn" style="background:transparent;color:var(--latte);border:1px solid rgba(255,255,255,.2);">
        <i class="bi bi-receipt"></i>
      </a>
      <a href="<?= APP_URL ?>/auth/logout.php" class="cart-btn" style="background:transparent;color:var(--latte);border:1px solid rgba(255,255,255,.2);">
        <i class="bi bi-box-arrow-right"></i>
      </a>
    </div>
  </div>
</nav>

<!-- Hero -->
<div class="hero-strip">
  <div class="container">
    <h1 class="hero-title">Good coffee,<br><em>great moments.</em></h1>
    <p class="hero-sub">Fresh brews, crafted for you — order ahead &amp; skip the queue.</p>
  </div>
</div>

<!-- Category tabs -->
<div class="cat-nav">
  <div class="cat-nav-inner">
    <a href="#all" class="cat-tab active" data-filter="all">All items</a>
    <?php foreach ($categories as $cat): ?>
      <a href="#<?= $cat['slug'] ?>" class="cat-tab" data-filter="<?= $cat['slug'] ?>">
        <?= clean($cat['name']) ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- Menu -->
<div class="menu-section">
  <div class="container">

    <?php if ($flash): ?>
      <div class="flash-alert alert alert-<?= $flash['type'] === 'success' ? 'success' : 'warning' ?>">
        <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle' : 'info-circle' ?>"></i>
        <?= clean($flash['message']) ?>
      </div>
    <?php endif; ?>

    <?php foreach ($categories as $cat): ?>
      <?php if (empty($menu[$cat['slug']])) continue; ?>
      <div class="category-section mb-5" data-category="<?= $cat['slug'] ?>">
        <h2 class="section-title" id="<?= $cat['slug'] ?>">
          <?= clean($cat['name']) ?>
        </h2>
        <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 g-3">
          <?php foreach ($menu[$cat['slug']] as $item): ?>
            <div class="col">
              <div class="menu-card"
                   onclick="addToCart(<?= $item['id'] ?>, '<?= addslashes($item['name']) ?>', <?= $item['price'] ?>, '<?= $cat['slug'] ?>')">
                <div class="card-img-wrap">
                  <?php if ($item['is_featured']): ?>
                    <span class="featured-badge">Featured</span>
                  <?php endif; ?>
                  <?php
                    $icons = ['espresso'=>'☕','cold-brew'=>'🧊','seasonal'=>'🌿','non-coffee'=>'🍵','food'=>'🥐'];
                    echo $icons[$cat['slug']] ?? '☕';
                  ?>
                </div>
                <div class="card-body-custom">
                  <div class="card-item-name"><?= clean($item['name']) ?></div>
                  <div class="card-item-desc"><?= clean($item['description'] ?? '') ?></div>
                  <div class="card-footer-row">
                    <span class="item-price">RM <?= number_format($item['price'], 2) ?></span>
                    <button class="btn-add" aria-label="Add <?= clean($item['name']) ?>">+</button>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>

  </div>
</div>

<!-- Cart Sidebar -->
<div class="offcanvas offcanvas-end" id="cartSidebar" tabindex="-1">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title">Your order</h5>
    <button class="btn-close" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body p-0" style="display:flex;flex-direction:column;height:100%;">
    <div id="cart-items-list" style="flex:1;overflow-y:auto;padding:1rem;">
      <div class="empty-cart" id="cart-empty">
        <i class="bi bi-bag"></i>
        <p>No items yet.<br>Add something from the menu!</p>
      </div>
    </div>
    <div class="cart-summary" id="cart-summary" style="display:none;">
      <div class="summary-line"><span>Subtotal</span><span id="sum-subtotal">RM 0.00</span></div>
      <div class="summary-line"><span>SST (6%)</span><span id="sum-tax">RM 0.00</span></div>
      <div class="summary-total"><span>Total</span><span id="sum-total">RM 0.00</span></div>
      <a href="order.php" class="btn-checkout" id="btn-checkout" onclick="saveCartToSession()">
        <i class="bi bi-bag-check me-1"></i> Proceed to checkout
      </a>
    </div>
  </div>
</div>

<!-- Footer -->
<footer class="site-footer">
  &copy; <?= date('Y') ?> <?= APP_NAME ?> &mdash; Crafted with care.
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Cart state ────────────────────────────────────
let cart = JSON.parse(sessionStorage.getItem('richiamo_cart') || '[]');
renderCart();

function addToCart(id, name, price, category) {
  const existing = cart.find(i => i.id === id);
  if (existing) { existing.qty++; }
  else { cart.push({ id, name, price, qty: 1, category }); }
  saveCart();
  renderCart();
  // Flash the cart button
  const btn = document.querySelector('.cart-btn');
  btn.style.background = '#1C0A00';
  setTimeout(() => btn.style.background = '', 300);
}

function changeQty(id, delta) {
  const idx = cart.findIndex(i => i.id === id);
  if (idx < 0) return;
  cart[idx].qty += delta;
  if (cart[idx].qty <= 0) cart.splice(idx, 1);
  saveCart();
  renderCart();
}

function saveCart() {
  sessionStorage.setItem('richiamo_cart', JSON.stringify(cart));
}

function saveCartToSession() {
  // Cart is already in sessionStorage; order.php will read it
}

function renderCart() {
  const list    = document.getElementById('cart-items-list');
  const empty   = document.getElementById('cart-empty');
  const summary = document.getElementById('cart-summary');
  const count   = document.getElementById('cart-count');

  const totalQty = cart.reduce((s, i) => s + i.qty, 0);
  count.textContent = totalQty;

  if (!cart.length) {
    empty.style.display = '';
    summary.style.display = 'none';
    list.innerHTML = '';
    list.appendChild(empty);
    return;
  }

  empty.style.display = 'none';
  summary.style.display = '';

  const icons = { espresso:'☕', 'cold-brew':'🧊', seasonal:'🌿', 'non-coffee':'🍵', food:'🥐' };

  list.innerHTML = cart.map(item => `
    <div class="cart-item-row">
      <div class="cart-item-icon">${icons[item.category] || '☕'}</div>
      <div class="cart-item-info">
        <div class="cart-item-name">${item.name}</div>
        <div class="cart-item-price">RM ${(item.price * item.qty).toFixed(2)}</div>
        <div class="qty-row">
          <button class="qty-btn" onclick="changeQty(${item.id}, -1)">−</button>
          <span class="qty-num">${item.qty}</span>
          <button class="qty-btn" onclick="changeQty(${item.id}, 1)">+</button>
        </div>
      </div>
    </div>
  `).join('');

  const subtotal = cart.reduce((s, i) => s + i.price * i.qty, 0);
  const tax      = subtotal * 0.06;
  const total    = subtotal + tax;

  document.getElementById('sum-subtotal').textContent = 'RM ' + subtotal.toFixed(2);
  document.getElementById('sum-tax').textContent      = 'RM ' + tax.toFixed(2);
  document.getElementById('sum-total').textContent    = 'RM ' + total.toFixed(2);
}

// ── Category filter ───────────────────────────────
document.querySelectorAll('.cat-tab').forEach(tab => {
  tab.addEventListener('click', e => {
    e.preventDefault();
    document.querySelectorAll('.cat-tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    const filter = tab.dataset.filter;
    document.querySelectorAll('.category-section').forEach(sec => {
      sec.style.display = (filter === 'all' || sec.dataset.category === filter) ? '' : 'none';
    });
    if (filter !== 'all') {
      document.getElementById(filter)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  });
});
</script>
</body>
</html>
