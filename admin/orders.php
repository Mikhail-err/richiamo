<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
$allowed = [ROLE_ADMIN, ROLE_DEVELOPER];
require_once __DIR__ . '/../auth/auth_check.php';
$db = get_db();

$orders = $db->query("
    SELECT o.*, u.name AS customer_name
    FROM orders o
    LEFT JOIN users u ON u.id = o.user_id
    ORDER BY o.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Orders — <?= APP_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
  <style>
    :root { --espresso:#1C0A00;--roast:#3B1A08;--caramel:#C68642;--latte:#D4A96A;--cream:#F5E6C8; }
    body { font-family:'DM Sans',sans-serif; background:#F4F1EC; margin:0; }
    .sidebar { width:240px;min-height:100vh;background:var(--espresso);position:fixed;top:0;left:0;z-index:200; }
    .sidebar-brand { padding:1.5rem 1.25rem 1rem;border-bottom:1px solid rgba(255,255,255,.08); }
    .sidebar-brand h1 { font-family:'Playfair Display',serif;color:var(--cream);font-size:1.15rem;margin:0; }
    .sidebar-brand p { color:var(--latte);font-size:.72rem;margin:.2rem 0 0; }
    .sidebar-nav { flex:1;padding:1rem 0; }
    .nav-label { font-size:.65rem;letter-spacing:1px;text-transform:uppercase;color:rgba(255,255,255,.3);padding:.75rem 1.25rem .25rem; }
    .nav-link-item { display:flex;align-items:center;gap:.65rem;padding:.6rem 1.25rem;color:rgba(255,255,255,.65);text-decoration:none;font-size:.875rem;border-left:3px solid transparent;transition:all .15s; }
    .nav-link-item:hover { color:var(--cream);background:rgba(255,255,255,.06); }
    .nav-link-item.active { color:var(--cream);background:rgba(198,134,66,.15);border-left-color:var(--caramel); }
    .main { margin-left:240px; }
    .topbar { background:#fff;border-bottom:1px solid #ede8df;padding:.9rem 1.5rem;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100; }
    .topbar-title { font-family:'Playfair Display',serif;font-size:1.2rem;color:var(--espresso); }
    .content { padding:1.5rem; }
    .card-panel { background:#fff;border-radius:1rem;border:1px solid #ede8df;overflow:hidden; }
    .card-panel-header { padding:1rem 1.25rem;border-bottom:1px solid #f0ebe2;display:flex;align-items:center;justify-content:space-between; }
    .card-panel-title { font-family:'Playfair Display',serif;font-size:1rem;color:var(--espresso);margin:0; }
    .table-clean { margin:0; }
    .table-clean th { font-size:.72rem;text-transform:uppercase;letter-spacing:.5px;color:#aaa;font-weight:500;border-bottom:1px solid #f0ebe2;padding:.65rem 1.25rem;background:#FAFAF8; }
    .table-clean td { padding:.75rem 1.25rem;vertical-align:middle;font-size:.875rem;border-bottom:1px solid #f8f5f0; }
    .table-clean tr:last-child td { border-bottom:none; }
    .badge-status { padding:.3rem .7rem;border-radius:2rem;font-size:.72rem;font-weight:500; }
    .badge-pending { background:#FEF3E2;color:#B07A1A; }
    .badge-preparing { background:#EEF4FF;color:#3B6DD8; }
    .badge-ready { background:#EDFAF4;color:#0F6E56; }
    .badge-completed { background:#F0F0F0;color:#666; }
    .badge-cancelled { background:#FEF0F0;color:#A32D2D; }
    .badge-paid { background:#EDFAF4;color:#0F6E56; }
    .badge-pending-pay { background:#FEF3E2;color:#B07A1A; }

    .btn-sm-action { padding:.3rem .65rem;border-radius:.4rem;font-size:.75rem;border:1px solid #ddd;background:#fff;cursor:pointer;text-decoration:none;color:var(--espresso);transition:all .15s; }
    .btn-sm-action:hover { border-color:var(--caramel);color:var(--caramel); }

    select.status-select { border:1px solid #ddd;border-radius:.4rem;padding:.25rem .5rem;font-size:.78rem;cursor:pointer; }
  </style>
</head>
<body>
<aside class="sidebar" style="display:flex;flex-direction:column;">
  <div class="sidebar-brand">
    <h1>☕ <?= APP_NAME ?></h1>
    <p>Admin Panel</p>
  </div>
  <nav class="sidebar-nav" style="flex:1;">
    <div class="nav-label">Main</div>
    <a href="dashboard.php" class="nav-link-item"><i class="bi bi-grid-1x2"></i> Dashboard</a>
    <a href="orders.php" class="nav-link-item active"><i class="bi bi-receipt"></i> Orders</a>
    <a href="menu.php" class="nav-link-item"><i class="bi bi-journal-text"></i> Menu items</a>
    <a href="categories.php" class="nav-link-item"><i class="bi bi-tags"></i> Categories</a>
    <div class="nav-label">Reports</div>
    <a href="reports.php" class="nav-link-item"><i class="bi bi-bar-chart"></i> Sales report</a>
    <a href="customers.php" class="nav-link-item"><i class="bi bi-people"></i> Customers</a>
  </nav>
  <div style="padding:1rem 1.25rem;border-top:1px solid rgba(255,255,255,.08);">
    <a href="<?= APP_URL ?>/auth/logout.php" style="color:rgba(255,255,255,.4);font-size:.85rem;text-decoration:none;">
      <i class="bi bi-box-arrow-right"></i> Logout
    </a>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <div class="topbar-title">Orders</div>
    <div style="font-size:.8rem;color:#888;"><?= count($orders) ?> total orders</div>
  </div>
  <div class="content">
    <div class="card-panel">
      <div class="card-panel-header">
        <h2 class="card-panel-title">All orders</h2>
      </div>
      <div class="table-responsive">
        <table class="table table-clean">
          <thead>
            <tr>
              <th>Order #</th><th>Customer</th><th>Type</th>
              <th>Total</th><th>Payment</th><th>Status</th>
              <th>Date</th><th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($orders)): ?>
              <tr><td colspan="8" class="text-center text-muted py-4">No orders yet</td></tr>
            <?php else: ?>
              <?php foreach ($orders as $order): ?>
              <tr>
                <td style="font-weight:500;color:var(--caramel);"><?= clean($order['order_number']) ?></td>
                <td><?= clean($order['customer_name'] ?? 'Guest') ?></td>
                <td style="font-size:.8rem;">
                  <?= $order['order_type']==='dine_in' ? '🪑 Table '.$order['table_number'] : '🥡 Takeaway' ?>
                </td>
                <td style="font-weight:500;"><?= format_price($order['total_amount']) ?></td>
                <td><span class="badge-status badge-<?= $order['payment_status']==='paid'?'paid':'pending-pay' ?>"><?= ucfirst($order['payment_status']) ?></span></td>
                <td>
                  <form method="POST" action="update_order_status.php" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                    <select name="status" class="status-select" onchange="this.form.submit()">
                      <?php foreach (['pending','preparing','ready','completed','cancelled'] as $s): ?>
                        <option value="<?= $s ?>" <?= $order['order_status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </form>
                </td>
                <td style="color:#aaa;font-size:.78rem;"><?= date('d M, h:i A', strtotime($order['created_at'])) ?></td>
                <td><a href="order_detail.php?id=<?= $order['id'] ?>" class="btn-sm-action">View</a></td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
