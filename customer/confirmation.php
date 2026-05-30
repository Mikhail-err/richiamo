<?php
// ============================================================
//  Richiamo Coffee — Order Confirmation
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
$allowed = [ROLE_CUSTOMER, ROLE_ADMIN, ROLE_DEVELOPER];
require_once __DIR__ . '/../auth/auth_check.php';

$db           = get_db();
$order_number = clean(get_param('order'));
$flash        = get_flash();

if (empty($order_number)) {
    redirect_with_message(APP_URL . '/customer/menu.php', 'Invalid order.', 'warning');
}

// Fetch order
$stmt = $db->prepare("
    SELECT o.*, u.name AS user_name, u.email
    FROM orders o
    LEFT JOIN users u ON u.id = o.user_id
    WHERE o.order_number = ? AND o.user_id = ?
    LIMIT 1
");
$stmt->execute([$order_number, $current_user['id']]);
$order = $stmt->fetch();

if (!$order) {
    redirect_with_message(APP_URL . '/customer/menu.php', 'Order not found.', 'warning');
}

// Fetch order items
$items = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
$items->execute([$order['id']]);
$order_items = $items->fetchAll();

// Points earned
$points = $db->prepare("SELECT points FROM loyalty_points WHERE order_id = ? AND user_id = ?");
$points->execute([$order['id'], $current_user['id']]);
$points_earned = $points->fetchColumn();

// Estimated wait time (minutes)
$wait_time = count($order_items) * 3 + 5;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Order Confirmed — <?= APP_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
    :root { --espresso:#1C0A00;--roast:#3B1A08;--caramel:#C68642;--latte:#D4A96A;--cream:#F5E6C8;--foam:#FDF6EC; }
    * { box-sizing:border-box; }
    body { font-family:'DM Sans',sans-serif;background:#F4F1EC;color:var(--espresso);min-height:100vh; }

    .navbar-rc { background:var(--espresso);padding:.9rem 0;border-bottom:2px solid var(--caramel); }
    .navbar-brand-text { font-family:'Playfair Display',serif;color:var(--cream)!important;font-size:1.3rem;text-decoration:none; }

    .confirm-wrap { max-width:620px;margin:2rem auto;padding:0 1rem 3rem; }

    /* Success banner */
    .success-banner {
      background: var(--espresso);
      border-radius: 1.25rem;
      padding: 2rem;
      text-align: center;
      margin-bottom: 1.25rem;
      position: relative;
      overflow: hidden;
    }
    .success-banner::before {
      content:'';position:absolute;inset:0;
      background:radial-gradient(ellipse at 50% 0%, rgba(198,134,66,.2) 0%, transparent 70%);
      pointer-events:none;
    }
    .check-circle {
      width:64px;height:64px;border-radius:50%;
      background:var(--caramel);
      display:flex;align-items:center;justify-content:center;
      margin:0 auto 1rem;
      font-size:1.8rem;
    }
    .success-title { font-family:'Playfair Display',serif;color:var(--cream);font-size:1.5rem;margin-bottom:.3rem; }
    .success-sub { color:var(--latte);font-size:.875rem; }
    .order-number-tag {
      display:inline-block;
      background:rgba(255,255,255,.1);
      color:var(--cream);
      border-radius:.5rem;
      padding:.4rem 1rem;
      font-size:.95rem;font-weight:600;
      letter-spacing:.5px;
      margin-top:.75rem;
    }

    /* Info cards */
    .info-card { background:#fff;border-radius:1rem;border:1px solid #ede8df;padding:1.25rem;margin-bottom:1rem; }
    .info-card-title { font-family:'Playfair Display',serif;font-size:.95rem;color:var(--espresso);margin-bottom:1rem;display:flex;align-items:center;gap:.5rem; }
    .info-card-title i { color:var(--caramel); }
    .info-row { display:flex;justify-content:space-between;font-size:.875rem;padding:.4rem 0;border-bottom:1px solid #f8f5f0; }
    .info-row:last-child { border-bottom:none; }
    .info-row .label { color:#888; }
    .info-row .value { font-weight:500;color:var(--espresso); }

    /* Order items */
    .order-item-row { display:flex;align-items:center;gap:.75rem;padding:.65rem 0;border-bottom:1px solid #f8f5f0; }
    .order-item-row:last-child { border-bottom:none; }
    .oi-icon { font-size:1.3rem;min-width:28px;text-align:center; }
    .oi-name { flex:1;font-size:.875rem;font-weight:500; }
    .oi-qty { font-size:.78rem;color:#aaa; }
    .oi-price { font-size:.875rem;font-weight:500; }

    /* Totals */
    .total-row { display:flex;justify-content:space-between;font-size:.875rem;color:#888;margin-bottom:.3rem; }
    .total-final { display:flex;justify-content:space-between;font-size:1.05rem;font-weight:600;color:var(--espresso);padding-top:.5rem;border-top:1px solid #ede8df;margin-top:.3rem; }

    /* Timer */
    .wait-banner {
      background:var(--foam);border:1px solid #ede8df;border-radius:1rem;
      padding:1rem 1.25rem;margin-bottom:1rem;
      display:flex;align-items:center;gap:1rem;
    }
    .wait-icon { font-size:2rem; }
    .wait-time { font-family:'Playfair Display',serif;font-size:1.5rem;color:var(--caramel); }
    .wait-label { font-size:.78rem;color:#888; }

    /* Points badge */
    .points-banner {
      background:linear-gradient(135deg,var(--roast),var(--espresso));
      border-radius:1rem;padding:1rem 1.25rem;margin-bottom:1rem;
      display:flex;align-items:center;gap:1rem;color:var(--cream);
    }
    .points-icon { font-size:1.8rem; }

    /* Action buttons */
    .btn-primary-coffee { background:var(--espresso);color:var(--cream);border:none;border-radius:.75rem;padding:.8rem 1.5rem;font-size:.9rem;font-weight:500;text-decoration:none;display:inline-flex;align-items:center;gap:.5rem;transition:background .2s; }
    .btn-primary-coffee:hover { background:var(--roast);color:var(--cream); }
    .btn-outline-coffee { background:transparent;color:var(--espresso);border:1.5px solid var(--caramel);border-radius:.75rem;padding:.75rem 1.5rem;font-size:.9rem;font-weight:500;text-decoration:none;display:inline-flex;align-items:center;gap:.5rem;transition:all .2s; }
    .btn-outline-coffee:hover { background:var(--caramel);color:#fff; }

    @keyframes popIn { from{opacity:0;transform:scale(.9) translateY(20px)} to{opacity:1;transform:scale(1) translateY(0)} }
    .confirm-wrap { animation: popIn .4s ease both; }
  </style>
</head>
<body>

<nav class="navbar-rc">
  <div class="container d-flex align-items-center justify-content-between">
    <a href="menu.php" class="navbar-brand-text">☕ <?= APP_NAME ?></a>
    <a href="track.php" style="color:var(--latte);font-size:.85rem;text-decoration:none;">
      <i class="bi bi-receipt"></i> My orders
    </a>
  </div>
</nav>

<div class="confirm-wrap">

  <!-- Success banner -->
  <div class="success-banner">
    <div class="check-circle">✓</div>
    <div class="success-title">Order placed!</div>
    <div class="success-sub">We've received your order and it's being prepared.</div>
    <div class="order-number-tag"><?= clean($order['order_number']) ?></div>
  </div>

  <!-- Wait time -->
  <div class="wait-banner">
    <div class="wait-icon">⏱️</div>
    <div>
      <div class="wait-time">~<?= $wait_time ?> min</div>
      <div class="wait-label">Estimated wait time</div>
    </div>
    <div style="margin-left:auto;text-align:right;">
      <?php if ($order['order_type'] === 'dine_in'): ?>
        <div style="font-size:.95rem;font-weight:500;">🪑 Table <?= clean($order['table_number']) ?></div>
        <div style="font-size:.75rem;color:#888;">Dine in</div>
      <?php else: ?>
        <div style="font-size:.95rem;font-weight:500;">🥡 <?= clean($order['customer_name']) ?></div>
        <div style="font-size:.75rem;color:#888;">Takeaway</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Loyalty points -->
  <?php if ($points_earned): ?>
  <div class="points-banner">
    <div class="points-icon">⭐</div>
    <div>
      <div style="font-size:.95rem;font-weight:500;">You earned <?= $points_earned ?> loyalty points!</div>
      <div style="font-size:.78rem;color:var(--latte);">Points are added to your account automatically.</div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Order items -->
  <div class="info-card">
    <div class="info-card-title"><i class="bi bi-bag"></i> Order items</div>
    <?php foreach ($order_items as $item): ?>
      <div class="order-item-row">
        <span class="oi-icon">☕</span>
        <div style="flex:1;">
          <div class="oi-name"><?= clean($item['item_name']) ?></div>
          <div class="oi-qty">x<?= $item['quantity'] ?></div>
        </div>
        <span class="oi-price"><?= format_price($item['subtotal']) ?></span>
      </div>
    <?php endforeach; ?>

    <div style="margin-top:1rem;">
      <div class="total-row"><span>Subtotal</span><span><?= format_price($order['subtotal']) ?></span></div>
      <div class="total-row"><span>SST (6%)</span><span><?= format_price($order['sst_amount']) ?></span></div>
      <div class="total-final"><span>Total paid</span><span><?= format_price($order['total_amount']) ?></span></div>
    </div>
  </div>

  <!-- Order details -->
  <div class="info-card">
    <div class="info-card-title"><i class="bi bi-info-circle"></i> Order details</div>
    <div class="info-row"><span class="label">Order number</span><span class="value"><?= clean($order['order_number']) ?></span></div>
    <div class="info-row"><span class="label">Date & time</span><span class="value"><?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></span></div>
    <div class="info-row"><span class="label">Payment</span><span class="value"><?= ucfirst($order['payment_method']) ?></span></div>
    <div class="info-row"><span class="label">Status</span><span class="value" style="color:var(--caramel);"><?= ucfirst($order['order_status']) ?></span></div>
    <?php if ($order['notes']): ?>
      <div class="info-row"><span class="label">Notes</span><span class="value"><?= clean($order['notes']) ?></span></div>
    <?php endif; ?>
  </div>

  <!-- Action buttons -->
  <div class="d-flex gap-2 flex-wrap">
    <a href="track.php" class="btn-primary-coffee">
      <i class="bi bi-clock-history"></i> Track order
    </a>
    <a href="menu.php" class="btn-outline-coffee">
      <i class="bi bi-cup-hot"></i> Order again
    </a>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
