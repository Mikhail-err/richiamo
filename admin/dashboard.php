<?php
// ============================================================
//  Richiamo Coffee — Admin Dashboard
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
$allowed = [ROLE_ADMIN, ROLE_DEVELOPER];
require_once __DIR__ . '/../auth/auth_check.php';

$db    = get_db();
$flash = get_flash();

// ── Stats ─────────────────────────────────────────────────────
$today = date('Y-m-d');

$total_orders   = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$today_orders   = $db->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at)='$today'")->fetchColumn();
$today_revenue  = $db->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE DATE(created_at)='$today' AND payment_status='paid'")->fetchColumn();
$total_revenue  = $db->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE payment_status='paid'")->fetchColumn();
$total_menu     = $db->query("SELECT COUNT(*) FROM menu_items WHERE is_available=1")->fetchColumn();
$total_customers= $db->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();
$pending_orders = $db->query("SELECT COUNT(*) FROM orders WHERE order_status='pending'")->fetchColumn();
$preparing      = $db->query("SELECT COUNT(*) FROM orders WHERE order_status='preparing'")->fetchColumn();

// ── Recent orders ──────────────────────────────────────────────
$recent = $db->query("
    SELECT o.*, u.name AS customer_name
    FROM orders o
    LEFT JOIN users u ON u.id = o.user_id
    ORDER BY o.created_at DESC
    LIMIT 8
")->fetchAll();

// ── Popular items ──────────────────────────────────────────────
$popular = $db->query("
    SELECT mi.name, SUM(oi.quantity) AS total_sold, SUM(oi.subtotal) AS revenue
    FROM order_items oi
    JOIN menu_items mi ON mi.id = oi.menu_item_id
    GROUP BY oi.menu_item_id
    ORDER BY total_sold DESC
    LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard — <?= APP_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
    :root {
      --espresso: #1C0A00;
      --roast:    #3B1A08;
      --caramel:  #C68642;
      --latte:    #D4A96A;
      --cream:    #F5E6C8;
      --foam:     #FDF6EC;
    }
    body { font-family: 'DM Sans', sans-serif; background: #F4F1EC; margin: 0; }

    /* Sidebar */
    .sidebar {
      width: 240px; min-height: 100vh;
      background: var(--espresso);
      position: fixed; top: 0; left: 0;
      display: flex; flex-direction: column;
      z-index: 200;
    }
    .sidebar-brand {
      padding: 1.5rem 1.25rem 1rem;
      border-bottom: 1px solid rgba(255,255,255,.08);
    }
    .sidebar-brand h1 {
      font-family: 'Playfair Display', serif;
      color: var(--cream); font-size: 1.15rem; margin: 0;
    }
    .sidebar-brand p { color: var(--latte); font-size: .72rem; margin: .2rem 0 0; }
    .sidebar-nav { flex: 1; padding: 1rem 0; }
    .nav-label {
      font-size: .65rem; letter-spacing: 1px; text-transform: uppercase;
      color: rgba(255,255,255,.3); padding: .75rem 1.25rem .25rem;
    }
    .nav-link-item {
      display: flex; align-items: center; gap: .65rem;
      padding: .6rem 1.25rem;
      color: rgba(255,255,255,.65);
      text-decoration: none; font-size: .875rem;
      border-left: 3px solid transparent;
      transition: all .15s;
    }
    .nav-link-item:hover { color: var(--cream); background: rgba(255,255,255,.06); }
    .nav-link-item.active {
      color: var(--cream);
      background: rgba(198,134,66,.15);
      border-left-color: var(--caramel);
    }
    .nav-link-item i { font-size: 1rem; min-width: 18px; }
    .sidebar-footer {
      padding: 1rem 1.25rem;
      border-top: 1px solid rgba(255,255,255,.08);
    }
    .user-chip {
      display: flex; align-items: center; gap: .6rem;
    }
    .user-avatar {
      width: 32px; height: 32px; border-radius: 50%;
      background: var(--caramel);
      display: flex; align-items: center; justify-content: center;
      font-size: .8rem; font-weight: 600; color: var(--espresso);
      flex-shrink: 0;
    }
    .user-info { flex: 1; min-width: 0; }
    .user-name { color: var(--cream); font-size: .8rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .user-role { color: var(--latte); font-size: .7rem; text-transform: capitalize; }
    .btn-logout {
      color: rgba(255,255,255,.4); font-size: .85rem;
      text-decoration: none; transition: color .15s;
    }
    .btn-logout:hover { color: #f87171; }

    /* Main content */
    .main { margin-left: 240px; min-height: 100vh; }
    .topbar {
      background: #fff; border-bottom: 1px solid #ede8df;
      padding: .9rem 1.5rem;
      display: flex; align-items: center; justify-content: space-between;
      position: sticky; top: 0; z-index: 100;
    }
    .topbar-title { font-family: 'Playfair Display', serif; font-size: 1.2rem; color: var(--espresso); }
    .topbar-date { font-size: .8rem; color: #888; }
    .content { padding: 1.5rem; }

    /* Stat cards */
    .stat-card {
      background: #fff; border-radius: 1rem;
      padding: 1.25rem 1.5rem;
      border: 1px solid #ede8df;
      display: flex; align-items: flex-start; gap: 1rem;
    }
    .stat-icon {
      width: 48px; height: 48px; border-radius: .75rem;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.3rem; flex-shrink: 0;
    }
    .stat-icon.orange { background: #FEF3E2; color: var(--caramel); }
    .stat-icon.green  { background: #EDFAF4; color: #1D9E75; }
    .stat-icon.blue   { background: #EEF4FF; color: #3B6DD8; }
    .stat-icon.red    { background: #FEF0F0; color: #E24B4A; }
    .stat-label { font-size: .75rem; color: #888; margin-bottom: .2rem; text-transform: uppercase; letter-spacing: .5px; }
    .stat-value { font-family: 'Playfair Display', serif; font-size: 1.6rem; color: var(--espresso); line-height: 1; }
    .stat-sub { font-size: .75rem; color: #aaa; margin-top: .3rem; }

    /* Cards */
    .card-panel {
      background: #fff; border-radius: 1rem;
      border: 1px solid #ede8df; overflow: hidden;
    }
    .card-panel-header {
      padding: 1rem 1.25rem;
      border-bottom: 1px solid #f0ebe2;
      display: flex; align-items: center; justify-content: space-between;
    }
    .card-panel-title {
      font-family: 'Playfair Display', serif;
      font-size: 1rem; color: var(--espresso); margin: 0;
    }

    /* Table */
    .table-clean { margin: 0; }
    .table-clean th {
      font-size: .72rem; text-transform: uppercase; letter-spacing: .5px;
      color: #aaa; font-weight: 500; border-bottom: 1px solid #f0ebe2;
      padding: .65rem 1.25rem; background: #FAFAF8;
    }
    .table-clean td { padding: .75rem 1.25rem; vertical-align: middle; font-size: .875rem; border-bottom: 1px solid #f8f5f0; }
    .table-clean tr:last-child td { border-bottom: none; }

    /* Badges */
    .badge-status {
      padding: .3rem .7rem; border-radius: 2rem; font-size: .72rem; font-weight: 500;
    }
    .badge-pending   { background: #FEF3E2; color: #B07A1A; }
    .badge-preparing { background: #EEF4FF; color: #3B6DD8; }
    .badge-ready     { background: #EDFAF4; color: #0F6E56; }
    .badge-completed { background: #F0F0F0; color: #666; }
    .badge-cancelled { background: #FEF0F0; color: #A32D2D; }

    .badge-paid    { background: #EDFAF4; color: #0F6E56; }
    .badge-unpaid  { background: #FEF3E2; color: #B07A1A; }
    .badge-failed  { background: #FEF0F0; color: #A32D2D; }

    /* Quick action buttons */
    .btn-action {
      display: inline-flex; align-items: center; gap: .4rem;
      padding: .45rem .9rem; border-radius: .5rem;
      font-size: .8rem; font-weight: 500; text-decoration: none;
      transition: all .15s; border: none; cursor: pointer;
    }
    .btn-primary-coffee {
      background: var(--espresso); color: var(--cream);
    }
    .btn-primary-coffee:hover { background: var(--roast); color: var(--cream); }
    .btn-outline-coffee {
      background: transparent; color: var(--espresso);
      border: 1px solid #ddd;
    }
    .btn-outline-coffee:hover { border-color: var(--caramel); color: var(--caramel); }

    /* Flash */
    .flash { border-radius: .75rem; padding: .65rem 1rem; font-size: .875rem; margin-bottom: 1rem; }
  </style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <h1>☕ <?= APP_NAME ?></h1>
    <p>Admin Panel</p>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-label">Main</div>
    <a href="dashboard.php" class="nav-link-item active">
      <i class="bi bi-grid-1x2"></i> Dashboard
    </a>
    <a href="orders.php" class="nav-link-item">
      <i class="bi bi-receipt"></i> Orders
      <?php if ($pending_orders > 0): ?>
        <span class="ms-auto badge" style="background:var(--caramel);color:var(--espresso);font-size:.65rem;"><?= $pending_orders ?></span>
      <?php endif; ?>
    </a>
    <a href="menu.php" class="nav-link-item">
      <i class="bi bi-journal-text"></i> Menu items
    </a>
    <a href="categories.php" class="nav-link-item">
      <i class="bi bi-tags"></i> Categories
    </a>

    <div class="nav-label">Reports</div>
    <a href="reports.php" class="nav-link-item">
      <i class="bi bi-bar-chart"></i> Sales report
    </a>
    <a href="customers.php" class="nav-link-item">
      <i class="bi bi-people"></i> Customers
    </a>

    <?php if ($current_user['role'] === ROLE_DEVELOPER): ?>
    <div class="nav-label">Developer</div>
    <a href="../developer/logs.php" class="nav-link-item">
      <i class="bi bi-terminal"></i> System logs
    </a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    <div class="user-chip">
      <div class="user-avatar"><?= strtoupper(substr($current_user['name'], 0, 1)) ?></div>
      <div class="user-info">
        <div class="user-name"><?= clean($current_user['name']) ?></div>
        <div class="user-role"><?= $current_user['role'] ?></div>
      </div>
      <a href="<?= APP_URL ?>/auth/logout.php" class="btn-logout" title="Logout">
        <i class="bi bi-box-arrow-right"></i>
      </a>
    </div>
  </div>
</aside>

<!-- Main -->
<div class="main">
  <!-- Topbar -->
  <div class="topbar">
    <div class="topbar-title">Dashboard</div>
    <div class="topbar-date"><i class="bi bi-calendar3 me-1"></i><?= date('l, d M Y') ?></div>
  </div>

  <div class="content">

    <?php if ($flash): ?>
      <div class="flash alert alert-<?= $flash['type'] === 'success' ? 'success' : 'warning' ?>">
        <?= clean($flash['message']) ?>
      </div>
    <?php endif; ?>

    <!-- Stat cards -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div class="stat-icon orange"><i class="bi bi-bag"></i></div>
          <div>
            <div class="stat-label">Today's orders</div>
            <div class="stat-value"><?= $today_orders ?></div>
            <div class="stat-sub"><?= $total_orders ?> total all time</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div class="stat-icon green"><i class="bi bi-currency-dollar"></i></div>
          <div>
            <div class="stat-label">Today's revenue</div>
            <div class="stat-value"><?= format_price($today_revenue) ?></div>
            <div class="stat-sub"><?= format_price($total_revenue) ?> all time</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div class="stat-icon blue"><i class="bi bi-clock-history"></i></div>
          <div>
            <div class="stat-label">Pending orders</div>
            <div class="stat-value"><?= $pending_orders ?></div>
            <div class="stat-sub"><?= $preparing ?> currently preparing</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div class="stat-icon red"><i class="bi bi-people"></i></div>
          <div>
            <div class="stat-label">Customers</div>
            <div class="stat-value"><?= $total_customers ?></div>
            <div class="stat-sub"><?= $total_menu ?> menu items active</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick actions -->
    <div class="mb-4 d-flex gap-2 flex-wrap">
      <a href="orders.php" class="btn-action btn-primary-coffee">
        <i class="bi bi-receipt"></i> View all orders
      </a>
      <a href="menu.php?action=add" class="btn-action btn-outline-coffee">
        <i class="bi bi-plus-circle"></i> Add menu item
      </a>
      <a href="reports.php" class="btn-action btn-outline-coffee">
        <i class="bi bi-bar-chart"></i> Sales report
      </a>
    </div>

    <div class="row g-3">
      <!-- Recent orders -->
      <div class="col-lg-8">
        <div class="card-panel">
          <div class="card-panel-header">
            <h2 class="card-panel-title">Recent orders</h2>
            <a href="orders.php" class="btn-action btn-outline-coffee" style="padding:.3rem .75rem;">View all</a>
          </div>
          <div class="table-responsive">
            <table class="table table-clean">
              <thead>
                <tr>
                  <th>Order #</th>
                  <th>Customer</th>
                  <th>Type</th>
                  <th>Total</th>
                  <th>Payment</th>
                  <th>Status</th>
                  <th>Time</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($recent)): ?>
                  <tr><td colspan="7" class="text-center text-muted py-4">No orders yet</td></tr>
                <?php else: ?>
                  <?php foreach ($recent as $order): ?>
                    <tr>
                      <td><a href="orders.php?id=<?= $order['id'] ?>" style="color:var(--caramel);font-weight:500;"><?= clean($order['order_number']) ?></a></td>
                      <td><?= clean($order['customer_name'] ?? $order['customer_name'] ?? 'Guest') ?></td>
                      <td>
                        <?php if ($order['order_type'] === 'dine_in'): ?>
                          <span style="font-size:.78rem;">🪑 Table <?= clean($order['table_number'] ?? '-') ?></span>
                        <?php else: ?>
                          <span style="font-size:.78rem;">🥡 Takeaway</span>
                        <?php endif; ?>
                      </td>
                      <td style="font-weight:500;"><?= format_price($order['total_amount']) ?></td>
                      <td>
                        <span class="badge-status badge-<?= $order['payment_status'] === 'paid' ? 'paid' : ($order['payment_status'] === 'failed' ? 'failed' : 'unpaid') ?>">
                          <?= ucfirst($order['payment_status']) ?>
                        </span>
                      </td>
                      <td>
                        <span class="badge-status badge-<?= $order['order_status'] ?>">
                          <?= ucfirst($order['order_status']) ?>
                        </span>
                      </td>
                      <td style="color:#aaa;font-size:.78rem;"><?= date('h:i A', strtotime($order['created_at'])) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Popular items + Order status summary -->
      <div class="col-lg-4 d-flex flex-column gap-3">
        <!-- Popular items -->
        <div class="card-panel">
          <div class="card-panel-header">
            <h2 class="card-panel-title">Popular items</h2>
          </div>
          <div style="padding:.5rem 0;">
            <?php if (empty($popular)): ?>
              <p class="text-center text-muted py-3" style="font-size:.85rem;">No sales data yet</p>
            <?php else: ?>
              <?php foreach ($popular as $i => $item): ?>
                <div style="display:flex;align-items:center;gap:.75rem;padding:.6rem 1.25rem;">
                  <span style="width:22px;height:22px;background:var(--foam);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:600;color:var(--caramel);flex-shrink:0;"><?= $i+1 ?></span>
                  <div style="flex:1;min-width:0;">
                    <div style="font-size:.85rem;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= clean($item['name']) ?></div>
                    <div style="font-size:.72rem;color:#aaa;"><?= $item['total_sold'] ?> sold</div>
                  </div>
                  <span style="font-size:.8rem;font-weight:500;color:var(--roast);"><?= format_price($item['revenue']) ?></span>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- Order status -->
        <div class="card-panel">
          <div class="card-panel-header">
            <h2 class="card-panel-title">Order pipeline</h2>
          </div>
          <div style="padding:.75rem 1.25rem;">
            <?php
              $statuses = [
                'pending'   => ['label'=>'Pending',   'color'=>'#B07A1A','bg'=>'#FEF3E2','icon'=>'bi-clock'],
                'preparing' => ['label'=>'Preparing', 'color'=>'#3B6DD8','bg'=>'#EEF4FF','icon'=>'bi-fire'],
                'ready'     => ['label'=>'Ready',     'color'=>'#0F6E56','bg'=>'#EDFAF4','icon'=>'bi-check-circle'],
                'completed' => ['label'=>'Completed', 'color'=>'#666',   'bg'=>'#F0F0F0','icon'=>'bi-bag-check'],
              ];
              foreach ($statuses as $key => $s):
                $count = $db->query("SELECT COUNT(*) FROM orders WHERE order_status='$key'")->fetchColumn();
            ?>
              <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.6rem;">
                <div style="width:32px;height:32px;background:<?= $s['bg'] ?>;border-radius:.5rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                  <i class="bi <?= $s['icon'] ?>" style="color:<?= $s['color'] ?>;font-size:.9rem;"></i>
                </div>
                <span style="flex:1;font-size:.85rem;"><?= $s['label'] ?></span>
                <span style="font-weight:600;color:<?= $s['color'] ?>;font-size:.95rem;"><?= $count ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /content -->
</div><!-- /main -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
