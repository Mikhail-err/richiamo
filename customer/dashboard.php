<?php
// ============================================================
//  Richiamo Coffee — Customer Dashboard
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
$allowed = [ROLE_CUSTOMER, ROLE_ADMIN, ROLE_DEVELOPER];
require_once __DIR__ . '/../auth/auth_check.php';

$db    = get_db();
$flash = get_flash();

// ── Stats ─────────────────────────────────────────────────────
$stats = $db->prepare("
    SELECT
        COUNT(*)                                    AS total_orders,
        COALESCE(SUM(total_amount),0)               AS total_spent,
        SUM(CASE WHEN order_status='completed' THEN 1 ELSE 0 END) AS completed,
        SUM(CASE WHEN order_status IN ('pending','preparing','ready') THEN 1 ELSE 0 END) AS active_orders
    FROM orders WHERE user_id = ?
");
$stats->execute([$current_user['id']]);
$s = $stats->fetch();

// ── Loyalty points ────────────────────────────────────────────
$pts_stmt = $db->prepare("SELECT COALESCE(SUM(points),0) FROM loyalty_points WHERE user_id = ?");
$pts_stmt->execute([$current_user['id']]);
$points = (int) $pts_stmt->fetchColumn();

// ── Active orders ─────────────────────────────────────────────
$active_stmt = $db->prepare("
    SELECT o.*, COUNT(oi.id) AS item_count
    FROM orders o
    LEFT JOIN order_items oi ON oi.order_id = o.id
    WHERE o.user_id = ? AND o.order_status IN ('pending','preparing','ready')
    GROUP BY o.id ORDER BY o.created_at DESC
");
$active_stmt->execute([$current_user['id']]);
$active_orders = $active_stmt->fetchAll();

// ── Recent orders ─────────────────────────────────────────────
$recent_stmt = $db->prepare("
    SELECT o.*, COUNT(oi.id) AS item_count
    FROM orders o
    LEFT JOIN order_items oi ON oi.order_id = o.id
    WHERE o.user_id = ? AND o.order_status NOT IN ('pending','preparing','ready')
    GROUP BY o.id ORDER BY o.created_at DESC LIMIT 5
");
$recent_stmt->execute([$current_user['id']]);
$recent_orders = $recent_stmt->fetchAll();

// ── Last order items (for quick reorder) ─────────────────────
$last_order = $db->prepare("
    SELECT o.id FROM orders o
    WHERE o.user_id = ? AND o.order_status = 'completed'
    ORDER BY o.created_at DESC LIMIT 1
");
$last_order->execute([$current_user['id']]);
$last_order_id = $last_order->fetchColumn();
$reorder_items = [];
if ($last_order_id) {
    $ri = $db->prepare("
        SELECT oi.*, mi.is_available, mi.price AS current_price
        FROM order_items oi
        JOIN menu_items mi ON mi.id = oi.menu_item_id
        WHERE oi.order_id = ?
    ");
    $ri->execute([$last_order_id]);
    $reorder_items = $ri->fetchAll();
}

// ── Featured menu ─────────────────────────────────────────────
$featured = $db->query("
    SELECT m.*, c.name AS cat_name, c.slug AS cat_slug
    FROM menu_items m JOIN categories c ON c.id = m.category_id
    WHERE m.is_featured = 1 AND m.is_available = 1
    ORDER BY RAND() LIMIT 4
")->fetchAll();

$status_meta = [
    'pending'   => ['label'=>'Order received', 'icon'=>'bi-clock',        'color'=>'#B07A1A', 'bg'=>'#FEF3E2'],
    'preparing' => ['label'=>'Preparing',      'icon'=>'bi-fire',         'color'=>'#3B6DD8', 'bg'=>'#EEF4FF'],
    'ready'     => ['label'=>'Ready!',         'icon'=>'bi-check-circle', 'color'=>'#0F6E56', 'bg'=>'#EDFAF4'],
];
$cat_icons = ['espresso'=>'☕','cold-brew'=>'🧊','seasonal'=>'🌿','non-coffee'=>'🍵','food'=>'🥐'];

// Time-based greeting
$hour = (int) date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Home — <?= APP_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
    :root{--espresso:#1C0A00;--roast:#3B1A08;--caramel:#C68642;--latte:#D4A96A;--cream:#F5E6C8;--foam:#FDF6EC;}
    *{box-sizing:border-box;}
    body{font-family:'DM Sans',sans-serif;background:#F4F1EC;color:var(--espresso);min-height:100vh;}

    /* Navbar */
    .navbar-rc{background:var(--espresso);padding:.9rem 0;border-bottom:2px solid var(--caramel);position:sticky;top:0;z-index:100;}
    .nav-brand{font-family:'Playfair Display',serif;color:var(--cream)!important;font-size:1.3rem;text-decoration:none;}
    .nav-links{display:flex;align-items:center;gap:.25rem;}
    .nav-link-rc{color:rgba(255,255,255,.7);text-decoration:none;font-size:.85rem;padding:.4rem .75rem;border-radius:2rem;transition:all .15s;}
    .nav-link-rc:hover{color:var(--cream);background:rgba(255,255,255,.1);}
    .nav-link-rc.active{color:var(--cream);background:rgba(198,134,66,.2);}
    .nav-cart-btn{background:var(--caramel);color:var(--espresso)!important;border-radius:2rem;padding:.4rem .9rem;font-size:.85rem;font-weight:600;text-decoration:none;display:flex;align-items:center;gap:.4rem;}
    .nav-cart-btn:hover{background:var(--latte);}
    .nav-avatar{width:30px;height:30px;border-radius:50%;background:rgba(198,134,66,.3);display:flex;align-items:center;justify-content:center;color:var(--cream);font-size:.8rem;font-weight:600;text-decoration:none;}

    /* Hero welcome strip */
    .welcome-strip{
      background:linear-gradient(135deg,var(--espresso) 0%,var(--roast) 100%);
      padding:2rem 0;position:relative;overflow:hidden;
    }
    .welcome-strip::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 80% 50%,rgba(198,134,66,.12) 0%,transparent 60%);pointer-events:none;}
    .welcome-title{font-family:'Playfair Display',serif;font-size:clamp(1.4rem,3vw,1.9rem);color:var(--cream);}
    .welcome-title em{color:var(--caramel);font-style:italic;}
    .welcome-sub{color:rgba(245,230,200,.6);font-size:.875rem;margin-top:.3rem;}
    .points-pill{background:rgba(198,134,66,.2);border:1px solid rgba(198,134,66,.35);border-radius:2rem;padding:.4rem 1rem;display:inline-flex;align-items:center;gap:.5rem;color:var(--latte);font-size:.82rem;margin-top:.75rem;}
    .points-pill strong{color:var(--caramel);}

    /* Quick actions */
    .quick-action{background:#fff;border:1px solid #ede8df;border-radius:1rem;padding:1.1rem;text-align:center;text-decoration:none;color:var(--espresso);transition:all .2s;display:flex;flex-direction:column;align-items:center;gap:.5rem;}
    .quick-action:hover{border-color:var(--caramel);transform:translateY(-2px);box-shadow:0 6px 18px rgba(28,10,0,.08);color:var(--espresso);}
    .qa-icon{width:44px;height:44px;border-radius:.75rem;display:flex;align-items:center;justify-content:center;font-size:1.2rem;}
    .qa-label{font-size:.78rem;font-weight:500;}

    /* Section */
    .section-title{font-family:'Playfair Display',serif;font-size:1.1rem;color:var(--espresso);margin-bottom:1rem;display:flex;align-items:center;justify-content:space-between;}
    .section-title a{font-family:'DM Sans',sans-serif;font-size:.8rem;color:var(--caramel);text-decoration:none;font-weight:500;}

    /* Active order cards */
    .active-card{border-radius:1rem;padding:1.1rem 1.25rem;margin-bottom:.75rem;border:1px solid;}
    .pulse-dot{width:8px;height:8px;border-radius:50%;display:inline-block;animation:pulse 1.5s infinite;}
    @keyframes pulse{0%,100%{opacity:1}50%{opacity:.3}}

    /* Progress bar */
    .order-progress{display:flex;align-items:center;gap:0;margin-top:.85rem;}
    .prog-step{flex:1;display:flex;flex-direction:column;align-items:center;position:relative;}
    .prog-step:not(:last-child)::after{content:'';position:absolute;top:10px;left:50%;width:100%;height:2px;background:#e5e5e5;z-index:0;}
    .prog-step.done:not(:last-child)::after,.prog-step.current:not(:last-child)::after{background:var(--caramel);}
    .prog-dot{width:20px;height:20px;border-radius:50%;border:2px solid #e5e5e5;background:#fff;z-index:1;position:relative;display:flex;align-items:center;justify-content:center;font-size:.6rem;}
    .prog-step.done .prog-dot{border-color:var(--caramel);background:var(--caramel);color:#fff;}
    .prog-step.current .prog-dot{border-color:var(--espresso);background:var(--espresso);color:var(--cream);}
    .prog-label{font-size:.6rem;color:#aaa;margin-top:.3rem;text-align:center;}
    .prog-step.done .prog-label,.prog-step.current .prog-label{color:var(--espresso);}

    /* Recent orders */
    .order-row-mini{display:flex;align-items:center;gap:.75rem;padding:.7rem 0;border-bottom:1px solid #f0ebe2;font-size:.85rem;}
    .order-row-mini:last-child{border-bottom:none;}
    .status-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;}

    /* Featured cards */
    .feat-card{background:#fff;border:1px solid #ede8df;border-radius:1rem;overflow:hidden;transition:all .2s;cursor:pointer;height:100%;}
    .feat-card:hover{transform:translateY(-3px);box-shadow:0 8px 20px rgba(28,10,0,.09);border-color:var(--caramel);}
    .feat-img{height:90px;background:var(--foam);display:flex;align-items:center;justify-content:center;font-size:2.5rem;}
    .feat-body{padding:.75rem;}
    .feat-name{font-weight:500;font-size:.85rem;margin-bottom:.2rem;}
    .feat-price{font-family:'Playfair Display',serif;font-size:.9rem;color:var(--roast);}
    .btn-add-feat{background:var(--espresso);color:var(--cream);border:none;border-radius:2rem;padding:.25rem .65rem;font-size:.72rem;cursor:pointer;transition:background .15s;}
    .btn-add-feat:hover{background:var(--caramel);color:var(--espresso);}

    /* Reorder card */
    .reorder-card{background:#fff;border:1px solid #ede8df;border-radius:1rem;padding:1.1rem 1.25rem;}
    .reorder-item{display:flex;align-items:center;gap:.65rem;padding:.5rem 0;border-bottom:1px solid #f8f5f0;font-size:.85rem;}
    .reorder-item:last-child{border-bottom:none;}
    .btn-reorder{background:var(--espresso);color:var(--cream);border:none;border-radius:.65rem;padding:.55rem 1.1rem;font-size:.82rem;font-weight:500;cursor:pointer;transition:background .2s;margin-top:.75rem;}
    .btn-reorder:hover{background:var(--caramel);color:var(--espresso);}

    /* Flash */
    .flash{border-radius:.75rem;padding:.65rem 1rem;font-size:.875rem;margin-bottom:1rem;}

    /* Empty state */
    .empty{text-align:center;padding:2rem 1rem;color:#aaa;font-size:.875rem;}
    .empty i{font-size:2rem;display:block;margin-bottom:.5rem;}

    .main-wrap{max-width:960px;margin:0 auto;padding:1.5rem 1rem 3rem;}
  </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar-rc">
  <div class="container d-flex align-items-center justify-content-between">
    <a href="dashboard.php" class="nav-brand">☕ <?= APP_NAME ?></a>
    <div class="nav-links">
      <a href="dashboard.php" class="nav-link-rc active">Home</a>
      <a href="menu.php"      class="nav-link-rc">Menu</a>
      <a href="track.php"     class="nav-link-rc">Orders</a>
      <a href="profile.php"   class="nav-link-rc">Profile</a>
      <a href="menu.php"      class="nav-cart-btn ms-2"><i class="bi bi-cup-hot-fill"></i> Order now</a>
      <a href="profile.php"   class="nav-avatar ms-2"><?= strtoupper(substr($current_user['name'],0,1)) ?></a>
    </div>
  </div>
</nav>

<!-- Welcome strip -->
<div class="welcome-strip">
  <div class="container">
    <div class="row align-items-center">
      <div class="col">
        <div class="welcome-title"><?= $greeting ?>, <em><?= clean(explode(' ', $current_user['name'])[0]) ?></em> ☕</div>
        <div class="welcome-sub">
          <?php if ($s['active_orders'] > 0): ?>
            You have <?= $s['active_orders'] ?> active order<?= $s['active_orders'] > 1 ? 's' : '' ?> in progress.
          <?php else: ?>
            Ready for your next brew? What are you having today?
          <?php endif; ?>
        </div>
        <div class="points-pill">
          <i class="bi bi-star-fill" style="font-size:.7rem;color:var(--caramel);"></i>
          <strong><?= number_format($points) ?></strong> loyalty points
        </div>
      </div>
      <div class="col-auto d-none d-md-block" style="font-size:4rem;opacity:.3;">☕</div>
    </div>
  </div>
</div>

<div class="main-wrap">

  <?php if ($flash): ?>
    <div class="flash alert alert-<?= $flash['type']==='success'?'success':'warning' ?>">
      <?= clean($flash['message']) ?>
    </div>
  <?php endif; ?>

  <!-- Quick actions -->
  <div class="row g-2 mb-4">
    <?php
      $actions = [
        ['href'=>'menu.php',    'icon'=>'bi-journal-text','bg'=>'#FEF3E2','color'=>'#B07A1A','label'=>'Browse menu'],
        ['href'=>'track.php',   'icon'=>'bi-receipt',     'bg'=>'#EEF4FF','color'=>'#3B6DD8','label'=>'My orders'],
        ['href'=>'profile.php#points','icon'=>'bi-star',  'bg'=>'#EDFAF4','color'=>'#0F6E56','label'=>'My points'],
        ['href'=>'profile.php', 'icon'=>'bi-person-circle','bg'=>'#F3F0FF','color'=>'#7C3AED','label'=>'Profile'],
      ];
      foreach ($actions as $a):
    ?>
      <div class="col-6 col-md-3">
        <a href="<?= $a['href'] ?>" class="quick-action">
          <div class="qa-icon" style="background:<?= $a['bg'] ?>;color:<?= $a['color'] ?>;">
            <i class="bi <?= $a['icon'] ?>"></i>
          </div>
          <span class="qa-label"><?= $a['label'] ?></span>
        </a>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if (!empty($active_orders)): ?>
  <!-- Active orders -->
  <div class="mb-4">
    <div class="section-title">
      <span><span class="pulse-dot me-2" style="background:#1D9E75;"></span>Active orders</span>
      <a href="track.php">View all</a>
    </div>
    <?php
      $steps = ['pending','preparing','ready','completed'];
      foreach ($active_orders as $o):
        $meta      = $status_meta[$o['order_status']] ?? $status_meta['pending'];
        $step_idx  = array_search($o['order_status'], $steps);
    ?>
      <div class="active-card" style="background:<?= $meta['bg'] ?>;border-color:<?= $meta['color'] ?>30;">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div>
            <div style="font-weight:600;font-size:.9rem;"><?= clean($o['order_number']) ?></div>
            <div style="font-size:.75rem;color:#888;">
              <?= $o['item_count'] ?> item<?= $o['item_count']>1?'s':'' ?> &bull;
              <?= $o['order_type']==='dine_in'?'🪑 Table '.$o['table_number']:'🥡 Takeaway' ?>
            </div>
          </div>
          <div style="text-align:right;">
            <div style="font-weight:600;color:<?= $meta['color'] ?>;display:flex;align-items:center;gap:.4rem;">
              <i class="bi <?= $meta['icon'] ?>"></i> <?= $meta['label'] ?>
            </div>
            <div style="font-size:.78rem;color:#888;"><?= format_price($o['total_amount']) ?></div>
          </div>
        </div>
        <!-- Mini progress -->
        <div class="order-progress">
          <?php foreach (['pending','preparing','ready','completed'] as $i => $step):
            $done    = $step_idx !== false && $i < $step_idx;
            $current = $step_idx !== false && $i === $step_idx;
          ?>
            <div class="prog-step <?= $done?'done':($current?'current':'') ?>">
              <div class="prog-dot">
                <?= $done ? '<i class="bi bi-check"></i>' : ($current ? '<i class="bi bi-circle-fill" style="font-size:.4rem;"></i>' : '') ?>
              </div>
              <div class="prog-label"><?= ['Received','Preparing','Ready','Done'][$i] ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="row g-3">

    <!-- Left: Featured + Reorder -->
    <div class="col-lg-8">

      <!-- Featured items -->
      <?php if (!empty($featured)): ?>
      <div class="mb-4">
        <div class="section-title">
          <span>⭐ Featured today</span>
          <a href="menu.php">Full menu</a>
        </div>
        <div class="row row-cols-2 row-cols-md-4 g-2">
          <?php foreach ($featured as $item): ?>
            <div class="col">
              <div class="feat-card" onclick="addToCart(<?= $item['id'] ?>,'<?= addslashes($item['name']) ?>',<?= $item['price'] ?>,'<?= $item['cat_slug'] ?>')">
                <div class="feat-img"><?= $cat_icons[$item['cat_slug']] ?? '☕' ?></div>
                <div class="feat-body">
                  <div class="feat-name"><?= clean($item['name']) ?></div>
                  <div class="d-flex align-items-center justify-content-between mt-1">
                    <span class="feat-price">RM <?= number_format($item['price'],2) ?></span>
                    <button class="btn-add-feat" onclick="event.stopPropagation();addToCart(<?= $item['id'] ?>,'<?= addslashes($item['name']) ?>',<?= $item['price'] ?>,'<?= $item['cat_slug'] ?>')">+</button>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Reorder -->
      <?php if (!empty($reorder_items)): ?>
      <div class="mb-4">
        <div class="section-title"><span>🔄 Reorder last order</span></div>
        <div class="reorder-card">
          <?php foreach ($reorder_items as $ri): ?>
            <div class="reorder-item">
              <span style="font-size:1.2rem;"><?= $cat_icons['espresso'] ?></span>
              <span style="flex:1;font-weight:500;"><?= clean($ri['item_name']) ?></span>
              <span style="color:#aaa;font-size:.78rem;">x<?= $ri['quantity'] ?></span>
              <span style="font-weight:500;"><?= format_price($ri['item_price']) ?></span>
            </div>
          <?php endforeach; ?>
          <button class="btn-reorder" onclick="reorderAll()">
            <i class="bi bi-bag-plus me-1"></i> Add all to cart
          </button>
        </div>
      </div>
      <?php endif; ?>

    </div>

    <!-- Right: Recent orders + Stats -->
    <div class="col-lg-4">

      <!-- Stats card -->
      <div style="background:#fff;border:1px solid #ede8df;border-radius:1rem;padding:1.1rem 1.25rem;margin-bottom:1rem;">
        <div class="section-title" style="margin-bottom:.75rem;"><span>My stats</span></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
          <?php
            $stat_items = [
              ['val'=>$s['total_orders'],              'label'=>'Total orders',  'color'=>'#B07A1A','bg'=>'#FEF3E2','icon'=>'bi-bag'],
              ['val'=>format_price($s['total_spent']), 'label'=>'Total spent',   'color'=>'#0F6E56','bg'=>'#EDFAF4','icon'=>'bi-currency-dollar'],
              ['val'=>$s['completed'],                 'label'=>'Completed',     'color'=>'#3B6DD8','bg'=>'#EEF4FF','icon'=>'bi-check-circle'],
              ['val'=>number_format($points).' pts',   'label'=>'Loyalty pts',   'color'=>'#7C3AED','bg'=>'#F3F0FF','icon'=>'bi-star'],
            ];
            foreach ($stat_items as $si):
          ?>
            <div style="background:<?= $si['bg'] ?>;border-radius:.65rem;padding:.75rem;">
              <div style="font-size:.65rem;color:<?= $si['color'] ?>;text-transform:uppercase;letter-spacing:.5px;"><?= $si['label'] ?></div>
              <div style="font-family:'Playfair Display',serif;font-size:1.1rem;color:var(--espresso);margin-top:.15rem;"><?= $si['val'] ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Recent orders -->
      <div style="background:#fff;border:1px solid #ede8df;border-radius:1rem;padding:1.1rem 1.25rem;">
        <div class="section-title"><span>Recent orders</span><a href="track.php">All</a></div>
        <?php if (empty($recent_orders)): ?>
          <div class="empty"><i class="bi bi-bag"></i>No completed orders yet.</div>
        <?php else: ?>
          <?php foreach ($recent_orders as $o): ?>
            <div class="order-row-mini">
              <span class="status-dot" style="background:<?= $o['order_status']==='completed'?'#aaa':'#E24B4A' ?>;"></span>
              <div style="flex:1;">
                <div style="font-weight:600;color:var(--caramel);font-size:.82rem;"><?= clean($o['order_number']) ?></div>
                <div style="font-size:.72rem;color:#aaa;"><?= date('d M Y', strtotime($o['created_at'])) ?></div>
              </div>
              <span style="font-weight:600;font-size:.82rem;"><?= format_price($o['total_amount']) ?></span>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>

<!-- Cart toast notification -->
<div id="cart-toast" style="position:fixed;bottom:1.5rem;right:1.5rem;background:var(--espresso);color:var(--cream);border-radius:.75rem;padding:.75rem 1.25rem;font-size:.85rem;display:none;align-items:center;gap:.65rem;box-shadow:0 8px 24px rgba(0,0,0,.2);z-index:9999;animation:slideUp .3s ease;">
  <i class="bi bi-bag-check-fill" style="color:var(--caramel);"></i>
  <span id="toast-msg">Added to cart!</span>
  <a href="order.php" style="color:var(--caramel);font-weight:600;text-decoration:none;margin-left:.5rem;">Checkout →</a>
</div>

<style>
@keyframes slideUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let cart = JSON.parse(sessionStorage.getItem('richiamo_cart') || '[]');

function addToCart(id, name, price, category) {
  const existing = cart.find(i => i.id === id);
  if (existing) existing.qty++;
  else cart.push({ id, name, price, qty: 1, category });
  sessionStorage.setItem('richiamo_cart', JSON.stringify(cart));
  showToast(name + ' added to cart!');
}

function showToast(msg) {
  const toast = document.getElementById('cart-toast');
  document.getElementById('toast-msg').textContent = msg;
  toast.style.display = 'flex';
  clearTimeout(window._toastTimer);
  window._toastTimer = setTimeout(() => toast.style.display = 'none', 3000);
}

function reorderAll() {
  const items = <?= json_encode(array_map(fn($r) => [
    'id'       => $r['menu_item_id'],
    'name'     => $r['item_name'],
    'price'    => (float)$r['current_price'],
    'qty'      => (int)$r['quantity'],
    'category' => 'espresso',
  ], $reorder_items)) ?>;
  items.forEach(item => {
    const existing = cart.find(i => i.id === item.id);
    if (existing) existing.qty += item.qty;
    else cart.push(item);
  });
  sessionStorage.setItem('richiamo_cart', JSON.stringify(cart));
  showToast('All items added to cart!');
}

// Auto-refresh if there are active orders
<?php if ($s['active_orders'] > 0): ?>
setTimeout(() => location.reload(), 30000);
<?php endif; ?>
</script>
</body>
</html>
