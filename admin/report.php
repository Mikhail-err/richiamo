<?php
// ============================================================
//  Richiamo Coffee — Admin Sales Report
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
$allowed = [ROLE_ADMIN, ROLE_DEVELOPER];
require_once __DIR__ . '/../auth/auth_check.php';

$db = get_db();

// ── Date filter ───────────────────────────────────────────────
$range     = get_param('range', '7');
$date_from = get_param('from', date('Y-m-d', strtotime('-7 days')));
$date_to   = get_param('to',   date('Y-m-d'));

if ($range !== 'custom') {
    $date_from = date('Y-m-d', strtotime("-{$range} days"));
    $date_to   = date('Y-m-d');
}

$from_dt = $date_from . ' 00:00:00';
$to_dt   = $date_to   . ' 23:59:59';

// ── Summary stats ─────────────────────────────────────────────
$stmt = $db->prepare("
    SELECT
        COUNT(*)                          AS total_orders,
        COALESCE(SUM(total_amount), 0)    AS total_revenue,
        COALESCE(SUM(sst_amount),   0)    AS total_sst,
        COALESCE(SUM(subtotal),     0)    AS total_subtotal,
        COALESCE(AVG(total_amount), 0)    AS avg_order_value,
        SUM(CASE WHEN order_status='cancelled' THEN 1 ELSE 0 END) AS cancelled,
        SUM(CASE WHEN order_status='completed' THEN 1 ELSE 0 END) AS completed,
        SUM(CASE WHEN order_type='dine_in'     THEN 1 ELSE 0 END) AS dine_in,
        SUM(CASE WHEN order_type='takeaway'    THEN 1 ELSE 0 END) AS takeaway,
        SUM(CASE WHEN payment_method='cash'    THEN 1 ELSE 0 END) AS pay_cash,
        SUM(CASE WHEN payment_method='ewallet' THEN 1 ELSE 0 END) AS pay_ewallet,
        SUM(CASE WHEN payment_method='card'    THEN 1 ELSE 0 END) AS pay_card
    FROM orders
    WHERE created_at BETWEEN ? AND ? AND payment_status = 'paid'
");
$stmt->execute([$from_dt, $to_dt]);
$s = $stmt->fetch();

// ── Daily revenue ─────────────────────────────────────────────
$daily_stmt = $db->prepare("
    SELECT DATE(created_at) AS day,
           COUNT(*) AS orders,
           COALESCE(SUM(total_amount),0) AS revenue
    FROM orders
    WHERE created_at BETWEEN ? AND ? AND payment_status='paid'
    GROUP BY DATE(created_at) ORDER BY day ASC
");
$daily_stmt->execute([$from_dt, $to_dt]);
$daily_map = [];
foreach ($daily_stmt->fetchAll() as $r) $daily_map[$r['day']] = $r;

$daily_labels = $daily_revenue = $daily_orders = [];
$cur = new DateTime($date_from);
$end = new DateTime($date_to);
while ($cur <= $end) {
    $d = $cur->format('Y-m-d');
    $daily_labels[]  = $cur->format('d M');
    $daily_revenue[] = isset($daily_map[$d]) ? (float)$daily_map[$d]['revenue'] : 0;
    $daily_orders[]  = isset($daily_map[$d]) ? (int)$daily_map[$d]['orders']    : 0;
    $cur->modify('+1 day');
}

// ── Top items ─────────────────────────────────────────────────
$top_stmt = $db->prepare("
    SELECT mi.name, c.name AS category,
           SUM(oi.quantity) AS units_sold,
           COALESCE(SUM(oi.subtotal),0) AS revenue
    FROM order_items oi
    JOIN orders o      ON o.id  = oi.order_id
    JOIN menu_items mi ON mi.id = oi.menu_item_id
    JOIN categories c  ON c.id  = mi.category_id
    WHERE o.created_at BETWEEN ? AND ? AND o.payment_status='paid'
    GROUP BY oi.menu_item_id ORDER BY units_sold DESC LIMIT 10
");
$top_stmt->execute([$from_dt, $to_dt]);
$top_items = $top_stmt->fetchAll();
$max_rev = max(array_column($top_items, 'revenue') ?: [1]);

// ── By category ───────────────────────────────────────────────
$cat_stmt = $db->prepare("
    SELECT c.name AS category,
           SUM(oi.quantity) AS units_sold,
           COALESCE(SUM(oi.subtotal),0) AS revenue
    FROM order_items oi
    JOIN orders o      ON o.id  = oi.order_id
    JOIN menu_items mi ON mi.id = oi.menu_item_id
    JOIN categories c  ON c.id  = mi.category_id
    WHERE o.created_at BETWEEN ? AND ? AND o.payment_status='paid'
    GROUP BY c.id ORDER BY revenue DESC
");
$cat_stmt->execute([$from_dt, $to_dt]);
$cat_rows = $cat_stmt->fetchAll();

// ── Hourly ────────────────────────────────────────────────────
$hr_stmt = $db->prepare("
    SELECT HOUR(created_at) AS hr, COUNT(*) AS cnt
    FROM orders WHERE created_at BETWEEN ? AND ? AND payment_status='paid'
    GROUP BY HOUR(created_at)
");
$hr_stmt->execute([$from_dt, $to_dt]);
$hr_map = [];
foreach ($hr_stmt->fetchAll() as $r) $hr_map[$r['hr']] = (int)$r['cnt'];
$hr_labels = $hr_data = [];
for ($h = 0; $h < 24; $h++) {
    $hr_labels[] = str_pad($h, 2, '0', STR_PAD_LEFT).':00';
    $hr_data[]   = $hr_map[$h] ?? 0;
}

// ── Recent paid orders ────────────────────────────────────────
$rec_stmt = $db->prepare("
    SELECT o.*, u.name AS customer_name
    FROM orders o LEFT JOIN users u ON u.id = o.user_id
    WHERE o.created_at BETWEEN ? AND ? AND o.payment_status='paid'
    ORDER BY o.created_at DESC LIMIT 15
");
$rec_stmt->execute([$from_dt, $to_dt]);
$recent_orders = $rec_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sales Report — <?= APP_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
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
    .topbar{background:#fff;border-bottom:1px solid #ede8df;padding:.9rem 1.5rem;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;flex-wrap:wrap;gap:.75rem;}
    .topbar-title{font-family:'Playfair Display',serif;font-size:1.2rem;color:var(--espresso);}
    .content{padding:1.5rem;}
    .filter-bar{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;margin-bottom:1.5rem;}
    .range-btn{padding:.4rem .9rem;border-radius:2rem;border:1.5px solid #ddd;background:#fff;font-size:.8rem;font-weight:500;cursor:pointer;color:#666;transition:all .15s;text-decoration:none;}
    .range-btn:hover{border-color:var(--caramel);color:var(--caramel);}
    .range-btn.active{background:var(--espresso);color:var(--cream);border-color:var(--espresso);}
    .date-input{border:1.5px solid #ddd;border-radius:.5rem;padding:.35rem .7rem;font-size:.8rem;font-family:'DM Sans',sans-serif;color:var(--espresso);}
    .btn-apply{background:var(--caramel);color:#fff;border:none;border-radius:.5rem;padding:.4rem .9rem;font-size:.8rem;font-weight:500;cursor:pointer;}
    .stat-card{background:#fff;border-radius:1rem;padding:1.25rem 1.5rem;border:1px solid #ede8df;display:flex;align-items:flex-start;gap:1rem;}
    .stat-icon{width:48px;height:48px;border-radius:.75rem;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;}
    .stat-icon.orange{background:#FEF3E2;color:var(--caramel);}
    .stat-icon.green{background:#EDFAF4;color:#1D9E75;}
    .stat-icon.blue{background:#EEF4FF;color:#3B6DD8;}
    .stat-icon.purple{background:#F3F0FF;color:#7C3AED;}
    .stat-label{font-size:.72rem;color:#888;text-transform:uppercase;letter-spacing:.5px;margin-bottom:.2rem;}
    .stat-value{font-family:'Playfair Display',serif;font-size:1.5rem;color:var(--espresso);line-height:1;}
    .stat-sub{font-size:.73rem;color:#aaa;margin-top:.3rem;}
    .panel{background:#fff;border-radius:1rem;border:1px solid #ede8df;overflow:hidden;margin-bottom:1rem;}
    .panel-header{padding:1rem 1.25rem;border-bottom:1px solid #f0ebe2;display:flex;align-items:center;justify-content:space-between;}
    .panel-title{font-family:'Playfair Display',serif;font-size:1rem;color:var(--espresso);margin:0;}
    .panel-body{padding:1.25rem;}
    .chart-wrap{position:relative;height:240px;}
    .items-table{width:100%;border-collapse:collapse;}
    .items-table th{font-size:.7rem;text-transform:uppercase;letter-spacing:.5px;color:#aaa;font-weight:500;padding:.5rem .75rem;border-bottom:1px solid #f0ebe2;background:#FAFAF8;}
    .items-table td{padding:.7rem .75rem;font-size:.85rem;border-bottom:1px solid #f8f5f0;vertical-align:middle;}
    .items-table tr:last-child td{border-bottom:none;}
    .items-table tr:hover td{background:#FAFAF8;}
    .rev-bar-wrap{display:flex;align-items:center;gap:.6rem;}
    .rev-bar-bg{flex:1;height:6px;background:#f0ebe2;border-radius:3px;overflow:hidden;}
    .rev-bar-fill{height:100%;background:var(--caramel);border-radius:3px;}
    .cat-pill{display:inline-block;background:#EEF4FF;color:#3B6DD8;border-radius:2rem;font-size:.68rem;padding:.15rem .55rem;font-weight:500;}
    .donut-legend{display:flex;flex-direction:column;gap:.5rem;margin-top:.75rem;}
    .legend-row{display:flex;align-items:center;gap:.5rem;font-size:.8rem;}
    .legend-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;}
    .order-row{display:flex;align-items:center;gap:.75rem;padding:.65rem 0;border-bottom:1px solid #f8f5f0;font-size:.85rem;flex-wrap:wrap;}
    .order-row:last-child{border-bottom:none;}
    .btn-print{background:transparent;border:1.5px solid #ddd;border-radius:.5rem;padding:.4rem .9rem;font-size:.8rem;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;color:#666;transition:all .15s;}
    .btn-print:hover{border-color:var(--caramel);color:var(--caramel);}
    @media print{.sidebar,.topbar,.filter-bar,.btn-print{display:none!important;}.main{margin-left:0;}.content{padding:.5rem;}}
  </style>
</head>
<body>

<aside class="sidebar">
  <div class="sidebar-brand">
    <h1>☕ <?= APP_NAME ?></h1>
    <p>Admin Panel</p>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-label">Main</div>
    <a href="dashboard.php" class="nav-link-item"><i class="bi bi-grid-1x2"></i> Dashboard</a>
    <a href="orders.php"    class="nav-link-item"><i class="bi bi-receipt"></i> Orders</a>
    <a href="menu.php"      class="nav-link-item"><i class="bi bi-journal-text"></i> Menu items</a>
    <a href="categories.php"class="nav-link-item"><i class="bi bi-tags"></i> Categories</a>
    <div class="nav-label">Reports</div>
    <a href="reports.php"   class="nav-link-item active"><i class="bi bi-bar-chart"></i> Sales report</a>
    <a href="customers.php" class="nav-link-item"><i class="bi bi-people"></i> Customers</a>
  </nav>
  <div class="sidebar-footer">
    <div class="user-chip">
      <div class="user-avatar"><?= strtoupper(substr($current_user['name'],0,1)) ?></div>
      <div style="flex:1;min-width:0;">
        <div class="user-name"><?= clean($current_user['name']) ?></div>
        <div class="user-role"><?= $current_user['role'] ?></div>
      </div>
      <a href="<?= APP_URL ?>/auth/logout.php" style="color:rgba(255,255,255,.4);font-size:.85rem;text-decoration:none;" title="Logout">
        <i class="bi bi-box-arrow-right"></i>
      </a>
    </div>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <div class="topbar-title">Sales report</div>
    <button class="btn-print" onclick="window.print()">
      <i class="bi bi-printer"></i> Print / Export
    </button>
  </div>

  <div class="content">

    <!-- Date filter bar -->
    <div class="filter-bar">
      <?php foreach(['7'=>'Last 7 days','30'=>'Last 30 days','90'=>'Last 90 days'] as $v=>$l): ?>
        <a href="?range=<?= $v ?>" class="range-btn <?= $range===$v?'active':'' ?>"><?= $l ?></a>
      <?php endforeach; ?>
      <form method="GET" style="display:flex;align-items:center;gap:.5rem;">
        <input type="hidden" name="range" value="custom">
        <input type="date" name="from" class="date-input" value="<?= $date_from ?>" max="<?= date('Y-m-d') ?>">
        <span style="font-size:.8rem;color:#aaa;">to</span>
        <input type="date" name="to"   class="date-input" value="<?= $date_to ?>"   max="<?= date('Y-m-d') ?>">
        <button type="submit" class="btn-apply">Apply</button>
      </form>
      <span style="font-size:.75rem;color:#aaa;">
        <?= date('d M Y', strtotime($date_from)) ?> — <?= date('d M Y', strtotime($date_to)) ?>
      </span>
    </div>

    <!-- Summary cards -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div class="stat-icon green"><i class="bi bi-currency-dollar"></i></div>
          <div>
            <div class="stat-label">Total revenue</div>
            <div class="stat-value"><?= format_price($s['total_revenue']) ?></div>
            <div class="stat-sub">SST collected: <?= format_price($s['total_sst']) ?></div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div class="stat-icon orange"><i class="bi bi-bag-check"></i></div>
          <div>
            <div class="stat-label">Orders paid</div>
            <div class="stat-value"><?= number_format($s['total_orders']) ?></div>
            <div class="stat-sub"><?= $s['cancelled'] ?> cancelled</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div class="stat-icon blue"><i class="bi bi-graph-up"></i></div>
          <div>
            <div class="stat-label">Avg order value</div>
            <div class="stat-value"><?= format_price($s['avg_order_value']) ?></div>
            <div class="stat-sub">per transaction</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div class="stat-icon purple"><i class="bi bi-receipt"></i></div>
          <div>
            <div class="stat-label">Subtotal (ex-SST)</div>
            <div class="stat-value"><?= format_price($s['total_subtotal']) ?></div>
            <div class="stat-sub"><?= $s['completed'] ?> completed</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Revenue chart + breakdown -->
    <div class="row g-3 mb-3">
      <div class="col-lg-8">
        <div class="panel">
          <div class="panel-header">
            <h2 class="panel-title">Daily revenue</h2>
            <button onclick="toggleChart()" class="btn-print" style="font-size:.75rem;padding:.3rem .65rem;">
              <i class="bi bi-arrows-angle-expand"></i> Toggle view
            </button>
          </div>
          <div class="panel-body">
            <div class="chart-wrap"><canvas id="revenueChart"></canvas></div>
          </div>
        </div>
      </div>

      <div class="col-lg-4 d-flex flex-column gap-3">
        <!-- Order type -->
        <div class="panel" style="flex:1;">
          <div class="panel-header"><h2 class="panel-title">Order type</h2></div>
          <div class="panel-body" style="display:flex;align-items:center;gap:1rem;">
            <div style="width:90px;height:90px;flex-shrink:0;"><canvas id="typeChart"></canvas></div>
            <div class="donut-legend">
              <div class="legend-row"><span class="legend-dot" style="background:#1C0A00;"></span><span>Dine in</span><span style="margin-left:auto;font-weight:600;"><?= $s['dine_in'] ?></span></div>
              <div class="legend-row"><span class="legend-dot" style="background:#C68642;"></span><span>Takeaway</span><span style="margin-left:auto;font-weight:600;"><?= $s['takeaway'] ?></span></div>
            </div>
          </div>
        </div>
        <!-- Payment method -->
        <div class="panel" style="flex:1;">
          <div class="panel-header"><h2 class="panel-title">Payment method</h2></div>
          <div class="panel-body" style="display:flex;align-items:center;gap:1rem;">
            <div style="width:90px;height:90px;flex-shrink:0;"><canvas id="payChart"></canvas></div>
            <div class="donut-legend">
              <div class="legend-row"><span class="legend-dot" style="background:#1C0A00;"></span><span>Cash</span><span style="margin-left:auto;font-weight:600;"><?= $s['pay_cash'] ?></span></div>
              <div class="legend-row"><span class="legend-dot" style="background:#C68642;"></span><span>E-Wallet</span><span style="margin-left:auto;font-weight:600;"><?= $s['pay_ewallet'] ?></span></div>
              <div class="legend-row"><span class="legend-dot" style="background:#3B6DD8;"></span><span>Card</span><span style="margin-left:auto;font-weight:600;"><?= $s['pay_card'] ?></span></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Busiest hours -->
    <div class="panel mb-3">
      <div class="panel-header">
        <h2 class="panel-title">Busiest hours</h2>
        <span style="font-size:.75rem;color:#aaa;">Orders by hour of day</span>
      </div>
      <div class="panel-body">
        <div class="chart-wrap" style="height:140px;"><canvas id="hourlyChart"></canvas></div>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <!-- Top items -->
      <div class="col-lg-7">
        <div class="panel">
          <div class="panel-header">
            <h2 class="panel-title">Top selling items</h2>
            <span style="font-size:.75rem;color:#aaa;">by units sold</span>
          </div>
          <?php if (empty($top_items)): ?>
            <div style="text-align:center;padding:2rem;color:#aaa;font-size:.875rem;">No sales data for this period.</div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="items-table">
                <thead><tr><th>#</th><th>Item</th><th>Category</th><th>Units</th><th>Revenue</th></tr></thead>
                <tbody>
                  <?php foreach ($top_items as $i => $item): ?>
                    <tr>
                      <td style="color:#aaa;font-size:.78rem;"><?= $i+1 ?></td>
                      <td style="font-weight:500;"><?= clean($item['name']) ?></td>
                      <td><span class="cat-pill"><?= clean($item['category']) ?></span></td>
                      <td style="font-weight:600;color:var(--caramel);"><?= number_format($item['units_sold']) ?></td>
                      <td>
                        <div class="rev-bar-wrap">
                          <span style="min-width:70px;font-size:.82rem;font-weight:500;"><?= format_price($item['revenue']) ?></span>
                          <div class="rev-bar-bg">
                            <div class="rev-bar-fill" style="width:<?= round(($item['revenue']/$max_rev)*100) ?>%"></div>
                          </div>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- By category -->
      <div class="col-lg-5">
        <div class="panel">
          <div class="panel-header"><h2 class="panel-title">Revenue by category</h2></div>
          <div class="panel-body">
            <?php if (empty($cat_rows)): ?>
              <p style="color:#aaa;font-size:.875rem;text-align:center;">No data.</p>
            <?php else: ?>
              <div class="chart-wrap" style="height:200px;"><canvas id="catChart"></canvas></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent paid orders -->
    <div class="panel">
      <div class="panel-header">
        <h2 class="panel-title">Recent paid orders</h2>
        <a href="orders.php" style="font-size:.78rem;color:var(--caramel);text-decoration:none;">View all</a>
      </div>
      <div style="padding:0 1.25rem;">
        <?php if (empty($recent_orders)): ?>
          <p style="color:#aaa;font-size:.875rem;text-align:center;padding:2rem 0;">No paid orders in this period.</p>
        <?php else: ?>
          <?php foreach ($recent_orders as $o): ?>
            <div class="order-row">
              <span style="font-weight:600;color:var(--caramel);min-width:140px;"><?= clean($o['order_number']) ?></span>
              <span style="flex:1;color:#555;"><?= clean($o['customer_name'] ?? 'Guest') ?></span>
              <span style="font-size:.78rem;color:#aaa;"><?= $o['order_type']==='dine_in'?'🪑 Dine in':'🥡 Takeaway' ?></span>
              <span style="font-size:.78rem;"><?= ucfirst($o['payment_method']) ?></span>
              <span style="font-weight:600;"><?= format_price($o['total_amount']) ?></span>
              <span style="font-size:.75rem;color:#aaa;"><?= date('d M, h:i A', strtotime($o['created_at'])) ?></span>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
Chart.defaults.font.family = "'DM Sans', sans-serif";
Chart.defaults.color = '#aaa';

const labels  = <?= json_encode($daily_labels) ?>;
const revenue = <?= json_encode($daily_revenue) ?>;
const orders  = <?= json_encode($daily_orders) ?>;
let chartType = 'line';

const rCtx = document.getElementById('revenueChart').getContext('2d');
let rChart;

function buildRevChart(type) {
  if (rChart) rChart.destroy();
  rChart = new Chart(rCtx, {
    type,
    data: {
      labels,
      datasets: [
        { label:'Revenue (RM)', data:revenue, borderColor:'#C68642',
          backgroundColor: type==='line'?'rgba(198,134,66,.1)':'rgba(198,134,66,.75)',
          borderWidth:2, fill:true, tension:.4,
          pointBackgroundColor:'#C68642', pointRadius: labels.length>14?2:4, yAxisID:'y' },
        { label:'Orders', data:orders, borderColor:'#1C0A00',
          backgroundColor: type==='line'?'rgba(28,10,0,.06)':'rgba(28,10,0,.6)',
          borderWidth:1.5, fill:false, tension:.4,
          pointRadius: labels.length>14?2:3, yAxisID:'y1' },
      ]
    },
    options: {
      responsive:true, maintainAspectRatio:false,
      interaction:{ mode:'index', intersect:false },
      plugins:{ legend:{ position:'top', labels:{ boxWidth:10, font:{ size:11 } } },
        tooltip:{ callbacks:{ label: ctx => ctx.datasetIndex===0
          ? ' RM '+ctx.parsed.y.toFixed(2) : ' '+ctx.parsed.y+' orders' } } },
      scales:{
        y:  { position:'left',  grid:{ color:'#f0ebe2' }, ticks:{ callback:v=>'RM'+v } },
        y1: { position:'right', grid:{ display:false },   ticks:{ stepSize:1 } },
        x:  { grid:{ display:false } },
      }
    }
  });
}
function toggleChart() { chartType = chartType==='line'?'bar':'line'; buildRevChart(chartType); }
buildRevChart(chartType);

new Chart(document.getElementById('typeChart'),{
  type:'doughnut',
  data:{ labels:['Dine in','Takeaway'],
    datasets:[{ data:[<?= (int)$s['dine_in'] ?>,<?= (int)$s['takeaway'] ?>],
      backgroundColor:['#1C0A00','#C68642'], borderWidth:0 }] },
  options:{ responsive:true, maintainAspectRatio:true, cutout:'70%',
    plugins:{ legend:{ display:false } } }
});

new Chart(document.getElementById('payChart'),{
  type:'doughnut',
  data:{ labels:['Cash','E-Wallet','Card'],
    datasets:[{ data:[<?= (int)$s['pay_cash'] ?>,<?= (int)$s['pay_ewallet'] ?>,<?= (int)$s['pay_card'] ?>],
      backgroundColor:['#1C0A00','#C68642','#3B6DD8'], borderWidth:0 }] },
  options:{ responsive:true, maintainAspectRatio:true, cutout:'70%',
    plugins:{ legend:{ display:false } } }
});

new Chart(document.getElementById('hourlyChart'),{
  type:'bar',
  data:{ labels:<?= json_encode($hr_labels) ?>,
    datasets:[{ label:'Orders', data:<?= json_encode($hr_data) ?>,
      backgroundColor: <?= json_encode($hr_data) ?>.map(v => {
        const max = Math.max(...<?= json_encode($hr_data) ?>);
        const a = max>0 ? 0.2+(v/max)*0.8 : 0.2;
        return `rgba(198,134,66,${a})`;
      }), borderRadius:4 }] },
  options:{ responsive:true, maintainAspectRatio:false,
    plugins:{ legend:{ display:false } },
    scales:{ x:{ grid:{ display:false }, ticks:{ font:{ size:10 } } },
             y:{ grid:{ color:'#f0ebe2' }, ticks:{ stepSize:1 } } } }
});

<?php if (!empty($cat_rows)): ?>
new Chart(document.getElementById('catChart'),{
  type:'bar',
  data:{ labels:<?= json_encode(array_column($cat_rows,'category')) ?>,
    datasets:[{ label:'Revenue (RM)',
      data:<?= json_encode(array_map(fn($r)=>round($r['revenue'],2),$cat_rows)) ?>,
      backgroundColor:['#1C0A00','#C68642','#3B6DD8','#1D9E75','#E8A045'],
      borderRadius:6 }] },
  options:{ responsive:true, maintainAspectRatio:false, indexAxis:'y',
    plugins:{ legend:{ display:false } },
    scales:{ x:{ grid:{ color:'#f0ebe2' }, ticks:{ callback:v=>'RM'+v } },
             y:{ grid:{ display:false } } } }
});
<?php endif; ?>
</script>
</body>
</html>