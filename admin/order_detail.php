<?php
// ============================================================
//  Richiamo Coffee — Admin Order Detail
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
$allowed = [ROLE_ADMIN, ROLE_DEVELOPER];
require_once __DIR__ . '/../auth/auth_check.php';

$db    = get_db();
$flash = get_flash();

$order_id = (int) get_param('id');
if (!$order_id) redirect_with_message(APP_URL . '/admin/orders.php', 'Invalid order.', 'warning');

// Handle status / payment update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post('action');

    if ($action === 'update_status') {
        $status = post('order_status');
        $valid  = ['pending','preparing','ready','completed','cancelled'];
        if (in_array($status, $valid)) {
            $db->prepare("UPDATE orders SET order_status = ?, updated_at = NOW() WHERE id = ?")
               ->execute([$status, $order_id]);
            redirect_with_message(APP_URL . '/admin/order_detail.php?id=' . $order_id, 'Order status updated.', 'success');
        }
    }

    if ($action === 'update_payment') {
        $pstatus = post('payment_status');
        $valid   = ['pending','paid','failed','refunded'];
        if (in_array($pstatus, $valid)) {
            $db->prepare("UPDATE orders SET payment_status = ?, updated_at = NOW() WHERE id = ?")
               ->execute([$pstatus, $order_id]);
            redirect_with_message(APP_URL . '/admin/order_detail.php?id=' . $order_id, 'Payment status updated.', 'success');
        }
    }
}

// Fetch order
$order_stmt = $db->prepare("
    SELECT o.*, u.name AS customer_name, u.email AS customer_email, u.phone AS customer_phone
    FROM orders o
    LEFT JOIN users u ON u.id = o.user_id
    WHERE o.id = ?
");
$order_stmt->execute([$order_id]);
$order = $order_stmt->fetch();

if (!$order) redirect_with_message(APP_URL . '/admin/orders.php', 'Order not found.', 'warning');

// Fetch order items
$items = $db->prepare("
    SELECT oi.*, mi.name AS current_name, mi.is_available
    FROM order_items oi
    LEFT JOIN menu_items mi ON mi.id = oi.menu_item_id
    WHERE oi.order_id = ?
    ORDER BY oi.id
");
$items->execute([$order_id]);
$order_items = $items->fetchAll();

$status_steps  = ['pending','preparing','ready','completed'];
$step_index    = array_search($order['order_status'], $status_steps);
$is_cancelled  = $order['order_status'] === 'cancelled';

$status_colors = [
    'pending'   => ['bg'=>'#FEF3E2','color'=>'#B07A1A'],
    'preparing' => ['bg'=>'#EEF4FF','color'=>'#3B6DD8'],
    'ready'     => ['bg'=>'#EDFAF4','color'=>'#0F6E56'],
    'completed' => ['bg'=>'#F0F0F0','color'=>'#666'],
    'cancelled' => ['bg'=>'#FEF0F0','color'=>'#A32D2D'],
];
$pay_colors = [
    'pending'  => ['bg'=>'#FEF3E2','color'=>'#B07A1A'],
    'paid'     => ['bg'=>'#EDFAF4','color'=>'#0F6E56'],
    'failed'   => ['bg'=>'#FEF0F0','color'=>'#A32D2D'],
    'refunded' => ['bg'=>'#EEF4FF','color'=>'#3B6DD8'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Order <?= clean($order['order_number']) ?> — <?= APP_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
    :root{--espresso:#1C0A00;--roast:#3B1A08;--caramel:#C68642;--latte:#D4A96A;--cream:#F5E6C8;--foam:#FDF6EC;}
    *{box-sizing:border-box;}
    body{font-family:'DM Sans',sans-serif;background:#F4F1EC;margin:0;}
    .sidebar{width:240px;min-height:100vh;background:var(--espresso);position:fixed;top:0;left:0;z-index:200;display:flex;flex-direction:column;}
    .sidebar-brand{padding:1.5rem 1.25rem 1rem;border-bottom:1px solid rgba(255,255,255,.08);}
    .sidebar-brand h1{font-family:'Playfair Display',serif;color:var(--cream);font-size:1.15rem;margin:0;}
    .sidebar-brand p{color:var(--latte);font-size:.72rem;margin:.2rem 0 0;}
    .sidebar-nav{flex:1;padding:1rem 0;}
    .nav-label{font-size:.65rem;letter-spacing:1px;text-transform:uppercase;color:rgba(255,255,255,.3);padding:.75rem 1.25rem .25rem;}
    .nav-link-item{display:flex;align-items:center;gap:.65rem;padding:.6rem 1.25rem;color:rgba(255,255,255,.65);text-decoration:none;font-size:.875rem;border-left:3px solid transparent;transition:all .15s;}
    .nav-link-item:hover{color:var(--cream);background:rgba(255,255,255,.06);}
    .nav-link-item.active{color:var(--cream);background:rgba(198,134,66,.15);border-left-color:var(--caramel);}
    .nav-link-item i{font-size:1rem;min-width:18px;}
    .sidebar-footer{padding:1rem 1.25rem;border-top:1px solid rgba(255,255,255,.08);}
    .user-chip{display:flex;align-items:center;gap:.6rem;}
    .user-avatar{width:32px;height:32px;border-radius:50%;background:var(--caramel);display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:600;color:var(--espresso);flex-shrink:0;}
    .user-name{color:var(--cream);font-size:.8rem;font-weight:500;}
    .user-role{color:var(--latte);font-size:.7rem;text-transform:capitalize;}
    .main{margin-left:240px;min-height:100vh;}
    .topbar{background:#fff;border-bottom:1px solid #ede8df;padding:.9rem 1.5rem;display:flex;align-items:center;gap:.75rem;position:sticky;top:0;z-index:100;}
    .topbar-title{font-family:'Playfair Display',serif;font-size:1.1rem;color:var(--espresso);}
    .btn-back{color:#888;font-size:.82rem;text-decoration:none;display:flex;align-items:center;gap:.35rem;transition:color .15s;}
    .btn-back:hover{color:var(--espresso);}
    .content{padding:1.5rem;}

    /* Order header card */
    .order-header{
      background:linear-gradient(135deg,var(--espresso),var(--roast));
      border-radius:1.25rem;padding:1.5rem;
      display:flex;align-items:flex-start;justify-content:space-between;
      gap:1rem;margin-bottom:1.25rem;flex-wrap:wrap;
    }
    .order-num{font-family:'Playfair Display',serif;font-size:1.4rem;color:var(--cream);}
    .order-meta-item{font-size:.78rem;color:var(--latte);margin-top:.25rem;display:flex;align-items:center;gap:.35rem;}
    .badge-status{padding:.3rem .8rem;border-radius:2rem;font-size:.75rem;font-weight:600;}

    /* Panel */
    .panel{background:#fff;border-radius:1rem;border:1px solid #ede8df;overflow:hidden;margin-bottom:1rem;}
    .panel-header{padding:1rem 1.25rem;border-bottom:1px solid #f0ebe2;display:flex;align-items:center;justify-content:space-between;}
    .panel-title{font-family:'Playfair Display',serif;font-size:.95rem;color:var(--espresso);margin:0;}
    .panel-body{padding:1.25rem;}

    /* Progress tracker */
    .progress-track{display:flex;align-items:flex-start;}
    .progress-step{flex:1;display:flex;flex-direction:column;align-items:center;position:relative;}
    .progress-step:not(:last-child)::after{content:'';position:absolute;top:14px;left:50%;width:100%;height:2px;background:#e5e5e5;z-index:0;}
    .progress-step.done:not(:last-child)::after{background:var(--caramel);}
    .step-dot{width:28px;height:28px;border-radius:50%;border:2px solid #e5e5e5;background:#fff;display:flex;align-items:center;justify-content:center;font-size:.75rem;z-index:1;position:relative;}
    .progress-step.done .step-dot{border-color:var(--caramel);background:var(--caramel);color:#fff;}
    .progress-step.current .step-dot{border-color:var(--espresso);background:var(--espresso);color:var(--cream);}
    .step-label{font-size:.65rem;color:#aaa;margin-top:.4rem;text-align:center;}
    .progress-step.done .step-label,.progress-step.current .step-label{color:var(--espresso);}

    /* Order items */
    .order-item-row{display:flex;align-items:center;gap:.75rem;padding:.75rem 0;border-bottom:1px solid #f8f5f0;}
    .order-item-row:last-child{border-bottom:none;}
    .oi-icon{width:36px;height:36px;border-radius:.5rem;background:var(--foam);display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;}
    .oi-name{font-weight:500;font-size:.875rem;}
    .oi-meta{font-size:.75rem;color:#aaa;}
    .oi-price{font-weight:600;font-size:.875rem;text-align:right;}

    /* Totals */
    .total-row{display:flex;justify-content:space-between;font-size:.85rem;color:#888;margin-bottom:.3rem;}
    .total-final{display:flex;justify-content:space-between;font-size:1rem;font-weight:700;color:var(--espresso);padding-top:.6rem;border-top:1.5px solid #ede8df;margin-top:.4rem;}

    /* Info rows */
    .info-row{display:flex;justify-content:space-between;font-size:.85rem;padding:.45rem 0;border-bottom:1px solid #f8f5f0;}
    .info-row:last-child{border-bottom:none;}
    .info-label{color:#888;}
    .info-value{font-weight:500;color:var(--espresso);}

    /* Action controls */
    .select-rc{border:1.5px solid #ddd;border-radius:.6rem;padding:.5rem .85rem;font-family:'DM Sans',sans-serif;font-size:.85rem;color:var(--espresso);background:#fff;cursor:pointer;}
    .btn-update{background:var(--espresso);color:var(--cream);border:none;border-radius:.6rem;padding:.5rem 1rem;font-size:.82rem;font-weight:500;cursor:pointer;transition:background .2s;}
    .btn-update:hover{background:var(--roast);}

    /* Flash */
    .flash{border-radius:.75rem;padding:.65rem 1rem;font-size:.875rem;margin-bottom:1rem;}

    @media print{
      .sidebar,.topbar,.btn-back,.update-form{display:none!important;}
      .main{margin-left:0;}
    }
  </style>
</head>
<body>

<aside class="sidebar">
  <div class="sidebar-brand">
    <h1>☕ <?= APP_NAME ?></h1><p>Admin Panel</p>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-label">Main</div>
    <a href="dashboard.php"  class="nav-link-item"><i class="bi bi-grid-1x2"></i> Dashboard</a>
    <a href="orders.php"     class="nav-link-item active"><i class="bi bi-receipt"></i> Orders</a>
    <a href="menu.php"       class="nav-link-item"><i class="bi bi-journal-text"></i> Menu items</a>
    <a href="categories.php" class="nav-link-item"><i class="bi bi-tags"></i> Categories</a>
    <div class="nav-label">Reports</div>
    <a href="reports.php"    class="nav-link-item"><i class="bi bi-bar-chart"></i> Sales report</a>
    <a href="customers.php"  class="nav-link-item"><i class="bi bi-people"></i> Customers</a>
  </nav>
  <div class="sidebar-footer">
    <div class="user-chip">
      <div class="user-avatar"><?= strtoupper(substr($current_user['name'],0,1)) ?></div>
      <div style="flex:1;min-width:0;">
        <div class="user-name"><?= clean($current_user['name']) ?></div>
        <div class="user-role"><?= $current_user['role'] ?></div>
      </div>
      <a href="<?= APP_URL ?>/auth/logout.php" style="color:rgba(255,255,255,.4);font-size:.85rem;text-decoration:none;">
        <i class="bi bi-box-arrow-right"></i>
      </a>
    </div>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <a href="orders.php" class="btn-back"><i class="bi bi-arrow-left"></i> Orders</a>
    <span style="color:#ddd;">/</span>
    <div class="topbar-title"><?= clean($order['order_number']) ?></div>
    <button onclick="window.print()" style="margin-left:auto;background:transparent;border:1.5px solid #ddd;border-radius:.5rem;padding:.35rem .75rem;font-size:.78rem;cursor:pointer;color:#666;">
      <i class="bi bi-printer"></i> Print
    </button>
    <a href="<?= APP_URL ?>/admin/print_receipt.php?id=<?= $order_id ?>" target="_blank" style="background:transparent;border:1.5px solid #ddd;border-radius:.5rem;padding:.35rem .75rem;font-size:.78rem;cursor:pointer;color:#666;text-decoration:none;">
      <i class="bi bi-receipt"></i> Receipt
    </a>
  </div>

  <div class="content">

    <?php if ($flash): ?>
      <div class="flash alert alert-<?= $flash['type'] === 'success' ? 'success' : 'warning' ?>">
        <?= clean($flash['message']) ?>
      </div>
    <?php endif; ?>

    <!-- Order header -->
    <div class="order-header">
      <div>
        <div class="order-num"><?= clean($order['order_number']) ?></div>
        <div class="order-meta-item"><i class="bi bi-calendar3"></i><?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></div>
        <div class="order-meta-item">
          <?= $order['order_type'] === 'dine_in'
            ? '<i class="bi bi-table"></i> Dine in — Table ' . clean($order['table_number'])
            : '<i class="bi bi-bag"></i> Takeaway — ' . clean($order['customer_name']) ?>
        </div>
      </div>
      <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.5rem;">
        <?php $sc = $status_colors[$order['order_status']] ?? $status_colors['pending']; ?>
        <span class="badge-status" style="background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;">
          <?= ucfirst($order['order_status']) ?>
        </span>
        <?php $pc = $pay_colors[$order['payment_status']] ?? $pay_colors['pending']; ?>
        <span class="badge-status" style="background:<?= $pc['bg'] ?>;color:<?= $pc['color'] ?>;">
          <?= ucfirst($order['payment_status']) ?> · <?= ucfirst($order['payment_method']) ?>
        </span>
        <span style="font-family:'Playfair Display',serif;font-size:1.3rem;color:var(--cream);">
          <?= format_price($order['total_amount']) ?>
        </span>
      </div>
    </div>

    <div class="row g-3">
      <!-- Left column -->
      <div class="col-lg-8">

        <!-- Order progress -->
        <?php if (!$is_cancelled): ?>
        <div class="panel">
          <div class="panel-header"><h2 class="panel-title">Order progress</h2></div>
          <div class="panel-body">
            <div class="progress-track">
              <?php foreach ($status_steps as $i => $step):
                $done    = $step_index !== false && $i < $step_index;
                $current = $step_index !== false && $i === $step_index;
                $class   = $done ? 'done' : ($current ? 'current' : '');
                $labels  = ['Order received','Preparing','Ready','Completed'];
              ?>
                <div class="progress-step <?= $class ?>">
                  <div class="step-dot">
                    <?php if ($done): ?><i class="bi bi-check" style="font-size:.7rem;"></i>
                    <?php elseif ($current): ?><i class="bi bi-clock" style="font-size:.7rem;"></i>
                    <?php else: ?><?= $i+1 ?>
                    <?php endif; ?>
                  </div>
                  <div class="step-label"><?= $labels[$i] ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Order items -->
        <div class="panel">
          <div class="panel-header">
            <h2 class="panel-title">Order items</h2>
            <span style="font-size:.78rem;color:#aaa;"><?= count($order_items) ?> item<?= count($order_items) > 1 ? 's' : '' ?></span>
          </div>
          <div class="panel-body">
            <?php foreach ($order_items as $item): ?>
              <div class="order-item-row">
                <div class="oi-icon">☕</div>
                <div style="flex:1;">
                  <div class="oi-name"><?= clean($item['item_name']) ?></div>
                  <div class="oi-meta">RM <?= number_format($item['item_price'], 2) ?> each &bull; qty <?= $item['quantity'] ?></div>
                  <?php if ($item['notes']): ?>
                    <div class="oi-meta" style="color:var(--caramel);">
                      <i class="bi bi-pencil"></i> <?= clean($item['notes']) ?>
                    </div>
                  <?php endif; ?>
                </div>
                <div class="oi-price"><?= format_price($item['subtotal']) ?></div>
              </div>
            <?php endforeach; ?>

            <div style="margin-top:1rem;">
              <div class="total-row"><span>Subtotal</span><span><?= format_price($order['subtotal']) ?></span></div>
              <div class="total-row"><span>SST (6%)</span><span><?= format_price($order['sst_amount']) ?></span></div>
              <div class="total-final"><span>Total</span><span><?= format_price($order['total_amount']) ?></span></div>
            </div>
          </div>
        </div>

        <!-- Notes -->
        <?php if ($order['notes']): ?>
        <div class="panel">
          <div class="panel-header"><h2 class="panel-title">Special notes</h2></div>
          <div class="panel-body">
            <p style="font-size:.875rem;color:#555;margin:0;font-style:italic;">"<?= clean($order['notes']) ?>"</p>
          </div>
        </div>
        <?php endif; ?>

      </div>

      <!-- Right column -->
      <div class="col-lg-4 d-flex flex-column gap-3">

        <!-- Update order status -->
        <div class="panel update-form">
          <div class="panel-header"><h2 class="panel-title">Update status</h2></div>
          <div class="panel-body">
            <form method="POST" action="" style="display:flex;flex-direction:column;gap:.75rem;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="update_status">
              <select name="order_status" class="select-rc">
                <?php foreach (['pending','preparing','ready','completed','cancelled'] as $s): ?>
                  <option value="<?= $s ?>" <?= $order['order_status'] === $s ? 'selected' : '' ?>>
                    <?= ucfirst($s) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn-update">Update order status</button>
            </form>
          </div>
        </div>

        <!-- Update payment status -->
        <div class="panel update-form">
          <div class="panel-header"><h2 class="panel-title">Payment status</h2></div>
          <div class="panel-body">
            <form method="POST" action="" style="display:flex;flex-direction:column;gap:.75rem;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="update_payment">
              <select name="payment_status" class="select-rc">
                <?php foreach (['pending','paid','failed','refunded'] as $s): ?>
                  <option value="<?= $s ?>" <?= $order['payment_status'] === $s ? 'selected' : '' ?>>
                    <?= ucfirst($s) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn-update">Update payment</button>
            </form>
          </div>
        </div>

        <!-- Customer info -->
        <div class="panel">
          <div class="panel-header"><h2 class="panel-title">Customer</h2></div>
          <div class="panel-body">
            <div class="info-row">
              <span class="info-label">Name</span>
              <span class="info-value"><?= clean($order['customer_name'] ?? 'Guest') ?></span>
            </div>
            <?php if ($order['customer_email']): ?>
            <div class="info-row">
              <span class="info-label">Email</span>
              <span class="info-value" style="font-size:.8rem;"><?= clean($order['customer_email']) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($order['customer_phone']): ?>
            <div class="info-row">
              <span class="info-label">Phone</span>
              <span class="info-value"><?= clean($order['customer_phone']) ?></span>
            </div>
            <?php endif; ?>
            <div class="info-row">
              <span class="info-label">Order type</span>
              <span class="info-value"><?= $order['order_type'] === 'dine_in' ? '🪑 Dine in' : '🥡 Takeaway' ?></span>
            </div>
            <?php if ($order['table_number']): ?>
            <div class="info-row">
              <span class="info-label">Table</span>
              <span class="info-value"><?= clean($order['table_number']) ?></span>
            </div>
            <?php endif; ?>
            <div class="info-row">
              <span class="info-label">Payment</span>
              <span class="info-value"><?= ucfirst($order['payment_method']) ?></span>
            </div>
            <div class="info-row">
              <span class="info-label">Placed at</span>
              <span class="info-value" style="font-size:.8rem;"><?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></span>
            </div>
            <div class="info-row">
              <span class="info-label">Last updated</span>
              <span class="info-value" style="font-size:.8rem;"><?= date('d M Y, h:i A', strtotime($order['updated_at'])) ?></span>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
