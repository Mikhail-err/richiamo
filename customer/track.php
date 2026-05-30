<?php
// ============================================================
//  Richiamo Coffee — Order Tracking
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
$allowed = [ROLE_CUSTOMER, ROLE_ADMIN, ROLE_DEVELOPER];
require_once __DIR__ . '/../auth/auth_check.php';

$db = get_db();

// Fetch user's orders
$orders = $db->prepare("
    SELECT o.*, COUNT(oi.id) AS item_count
    FROM orders o
    LEFT JOIN order_items oi ON oi.order_id = o.id
    WHERE o.user_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$orders->execute([$current_user['id']]);
$my_orders = $orders->fetchAll();

$status_steps = ['pending', 'preparing', 'ready', 'completed'];
$status_labels = [
    'pending'   => ['label' => 'Order received',   'icon' => 'bi-clock',        'color' => '#B07A1A'],
    'preparing' => ['label' => 'Preparing',         'icon' => 'bi-fire',         'color' => '#3B6DD8'],
    'ready'     => ['label' => 'Ready for pickup',  'icon' => 'bi-check-circle', 'color' => '#0F6E56'],
    'completed' => ['label' => 'Completed',         'icon' => 'bi-bag-check',    'color' => '#666'],
    'cancelled' => ['label' => 'Cancelled',         'icon' => 'bi-x-circle',     'color' => '#A32D2D'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Orders — <?= APP_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
    :root { --espresso:#1C0A00;--roast:#3B1A08;--caramel:#C68642;--latte:#D4A96A;--cream:#F5E6C8;--foam:#FDF6EC; }
    * { box-sizing:border-box; }
    body { font-family:'DM Sans',sans-serif;background:#F4F1EC;color:var(--espresso);min-height:100vh; }
    .navbar-rc { background:var(--espresso);padding:.9rem 0;border-bottom:2px solid var(--caramel); }
    .navbar-brand-text { font-family:'Playfair Display',serif;color:var(--cream)!important;font-size:1.3rem;text-decoration:none; }
    .btn-back { color:var(--latte);font-size:.85rem;text-decoration:none;display:flex;align-items:center;gap:.4rem; }
    .btn-back:hover { color:var(--cream); }
    .track-wrap { max-width:680px;margin:2rem auto;padding:0 1rem 3rem; }
    .page-title { font-family:'Playfair Display',serif;font-size:1.6rem;margin-bottom:1.5rem; }

    .order-card { background:#fff;border-radius:1rem;border:1px solid #ede8df;margin-bottom:1rem;overflow:hidden; }
    .order-card-header {
      padding:1rem 1.25rem;display:flex;align-items:center;gap:.75rem;
      cursor:pointer;border-bottom:1px solid transparent;transition:border-color .2s;
    }
    .order-card-header:hover { border-bottom-color:#f0ebe2; }
    .order-card.open .order-card-header { border-bottom-color:#f0ebe2; }
    .order-num { font-weight:600;color:var(--caramel);font-size:.9rem; }
    .order-meta { font-size:.75rem;color:#aaa;margin-top:.15rem; }
    .order-card-body { padding:1.25rem;display:none; }
    .order-card.open .order-card-body { display:block; }

    /* Progress tracker */
    .progress-track { display:flex;align-items:flex-start;gap:0;margin-bottom:1.25rem; }
    .progress-step { flex:1;display:flex;flex-direction:column;align-items:center;position:relative; }
    .progress-step:not(:last-child)::after {
      content:'';position:absolute;top:14px;left:50%;width:100%;height:2px;
      background:#e5e5e5;z-index:0;
    }
    .progress-step.done:not(:last-child)::after { background:var(--caramel); }
    .step-dot {
      width:28px;height:28px;border-radius:50%;border:2px solid #e5e5e5;
      background:#fff;display:flex;align-items:center;justify-content:center;
      font-size:.75rem;z-index:1;position:relative;transition:all .3s;
    }
    .progress-step.done .step-dot { border-color:var(--caramel);background:var(--caramel);color:#fff; }
    .progress-step.current .step-dot { border-color:var(--espresso);background:var(--espresso);color:var(--cream); }
    .step-label { font-size:.65rem;color:#aaa;margin-top:.4rem;text-align:center; }
    .progress-step.done .step-label,.progress-step.current .step-label { color:var(--espresso); }

    .order-items-mini { margin-bottom:.75rem; }
    .oi-mini { display:flex;justify-content:space-between;font-size:.82rem;padding:.3rem 0;border-bottom:1px solid #f8f5f0; }
    .oi-mini:last-child { border-bottom:none; }
    .total-mini { display:flex;justify-content:space-between;font-size:.9rem;font-weight:600;margin-top:.5rem; }

    .badge-type { font-size:.7rem;padding:.25rem .6rem;border-radius:2rem;font-weight:500; }
    .badge-dine { background:#EEF4FF;color:#3B6DD8; }
    .badge-take { background:#F0FFF4;color:#0F6E56; }
    .badge-cancel { background:#FEF0F0;color:#A32D2D;font-size:.7rem;padding:.25rem .6rem;border-radius:2rem;font-weight:500; }

    .empty-state { text-align:center;padding:3rem 1rem;color:#aaa; }
    .empty-state i { font-size:3rem;display:block;margin-bottom:.75rem; }
    .btn-menu { background:var(--espresso);color:var(--cream);border:none;border-radius:.75rem;padding:.75rem 1.5rem;font-size:.9rem;text-decoration:none;display:inline-flex;align-items:center;gap:.5rem;margin-top:1rem; }

    /* Auto refresh badge */
    .refresh-note { font-size:.75rem;color:#aaa;display:flex;align-items:center;gap:.3rem;margin-bottom:1rem; }
    .dot-live { width:7px;height:7px;border-radius:50%;background:#1D9E75;display:inline-block;animation:pulse 1.5s infinite; }
    @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.3} }
  </style>
</head>
<body>

<nav class="navbar-rc">
  <div class="container d-flex align-items-center justify-content-between">
    <a href="menu.php" class="navbar-brand-text">☕ <?= APP_NAME ?></a>
    <a href="menu.php" class="btn-back"><i class="bi bi-arrow-left"></i> Menu</a>
  </div>
</nav>

<div class="track-wrap">
  <h1 class="page-title">My orders</h1>

  <?php if (empty($my_orders)): ?>
    <div class="empty-state">
      <i class="bi bi-bag"></i>
      <p>You haven't placed any orders yet.</p>
      <a href="menu.php" class="btn-menu"><i class="bi bi-cup-hot"></i> Browse menu</a>
    </div>
  <?php else: ?>
    <div class="refresh-note"><span class="dot-live"></span> Page refreshes every 30 seconds</div>

    <?php foreach ($my_orders as $order):
      $step_index = array_search($order['order_status'], $status_steps);
      $is_cancelled = $order['order_status'] === 'cancelled';

      // Fetch items for this order
      $oi = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
      $oi->execute([$order['id']]);
      $oi_rows = $oi->fetchAll();
    ?>
      <div class="order-card" id="card-<?= $order['id'] ?>">
        <div class="order-card-header" onclick="toggleCard(<?= $order['id'] ?>)">
          <div style="flex:1;">
            <div class="order-num"><?= clean($order['order_number']) ?></div>
            <div class="order-meta">
              <?= date('d M Y, h:i A', strtotime($order['created_at'])) ?> &bull;
              <?= $order['item_count'] ?> item<?= $order['item_count'] > 1 ? 's' : '' ?> &bull;
              <?= format_price($order['total_amount']) ?>
            </div>
          </div>
          <span class="badge-type <?= $order['order_type'] === 'dine_in' ? 'badge-dine' : 'badge-take' ?>">
            <?= $order['order_type'] === 'dine_in' ? '🪑 Dine in' : '🥡 Takeaway' ?>
          </span>
          <?php if ($is_cancelled): ?>
            <span class="badge-cancel">Cancelled</span>
          <?php else: ?>
            <span style="font-size:.75rem;font-weight:600;color:<?= $status_labels[$order['order_status']]['color'] ?? '#888' ?>;">
              <?= $status_labels[$order['order_status']]['label'] ?? ucfirst($order['order_status']) ?>
            </span>
          <?php endif; ?>
          <i class="bi bi-chevron-down" style="color:#aaa;font-size:.8rem;margin-left:.5rem;"></i>
        </div>

        <div class="order-card-body">
          <!-- Progress tracker -->
          <?php if (!$is_cancelled): ?>
          <div class="progress-track">
            <?php foreach ($status_steps as $i => $step):
              $done    = $step_index !== false && $i < $step_index;
              $current = $step_index !== false && $i === $step_index;
              $class   = $done ? 'done' : ($current ? 'current' : '');
            ?>
              <div class="progress-step <?= $class ?>">
                <div class="step-dot">
                  <?php if ($done): ?><i class="bi bi-check" style="font-size:.7rem;"></i>
                  <?php elseif ($current): ?><i class="bi <?= $status_labels[$step]['icon'] ?>" style="font-size:.7rem;"></i>
                  <?php else: ?><?= $i + 1 ?>
                  <?php endif; ?>
                </div>
                <div class="step-label"><?= $status_labels[$step]['label'] ?></div>
              </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <!-- Items -->
          <div class="order-items-mini">
            <?php foreach ($oi_rows as $oi_row): ?>
              <div class="oi-mini">
                <span><?= clean($oi_row['item_name']) ?> x<?= $oi_row['quantity'] ?></span>
                <span><?= format_price($oi_row['subtotal']) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="total-mini">
            <span>Total</span>
            <span><?= format_price($order['total_amount']) ?></span>
          </div>

          <?php if ($order['notes']): ?>
            <p style="font-size:.78rem;color:#888;margin-top:.75rem;margin-bottom:0;">
              <i class="bi bi-pencil"></i> <?= clean($order['notes']) ?>
            </p>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<script>
function toggleCard(id) {
  const card = document.getElementById('card-' + id);
  card.classList.toggle('open');
}

// Auto-open the most recent order
const firstCard = document.querySelector('.order-card');
if (firstCard) firstCard.classList.add('open');

// Auto refresh every 30s to show status updates
setTimeout(() => location.reload(), 30000);
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
