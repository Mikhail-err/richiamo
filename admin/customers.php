<?php
// ============================================================
//  Richiamo Coffee — Admin Customers
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
$allowed = [ROLE_ADMIN, ROLE_DEVELOPER];
require_once __DIR__ . '/../auth/auth_check.php';

$db    = get_db();
$flash = get_flash();

// ── Handle toggle active ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (post('action') === 'toggle_active') {
        $uid = (int) post('user_id');
        // Prevent disabling self
        if ($uid !== $current_user['id']) {
            $db->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ? AND role = 'customer'")
               ->execute([$uid]);
        }
        redirect_with_message(APP_URL . '/admin/customers.php', 'Customer status updated.', 'success');
    }
}

// ── Search & filter ───────────────────────────────────────────
$search = get_param('q', '');
$sort   = get_param('sort', 'newest');

$where  = "u.role = 'customer'";
$params = [];

if ($search) {
    $where   .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$order_by = match($sort) {
    'spent'   => 'total_spent DESC',
    'orders'  => 'total_orders DESC',
    'points'  => 'points_balance DESC',
    'name'    => 'u.name ASC',
    default   => 'u.created_at DESC',
};

$stmt = $db->prepare("
    SELECT
        u.*,
        COUNT(DISTINCT o.id)                     AS total_orders,
        COALESCE(SUM(o.total_amount), 0)         AS total_spent,
        COALESCE((
            SELECT SUM(lp.points)
            FROM loyalty_points lp
            WHERE lp.user_id = u.id
        ), 0)                                    AS points_balance,
        MAX(o.created_at)                        AS last_order_at
    FROM users u
    LEFT JOIN orders o ON o.user_id = u.id AND o.payment_status = 'paid'
    WHERE $where
    GROUP BY u.id
    ORDER BY $order_by
");
$stmt->execute($params);
$customers = $stmt->fetchAll();

// ── Summary stats ─────────────────────────────────────────────
$total_customers = count($customers);
$active_count    = count(array_filter($customers, fn($c) => $c['is_active']));
$total_revenue   = array_sum(array_column($customers, 'total_spent'));
$total_points    = array_sum(array_column($customers, 'points_balance'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Customers — <?= APP_NAME ?></title>
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
    .topbar{background:#fff;border-bottom:1px solid #ede8df;padding:.9rem 1.5rem;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;}
    .topbar-title{font-family:'Playfair Display',serif;font-size:1.2rem;color:var(--espresso);}
    .content{padding:1.5rem;}

    /* Stat cards */
    .stat-card{background:#fff;border-radius:1rem;padding:1.1rem 1.25rem;border:1px solid #ede8df;display:flex;align-items:center;gap:.9rem;}
    .stat-icon{width:44px;height:44px;border-radius:.75rem;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0;}
    .stat-icon.orange{background:#FEF3E2;color:var(--caramel);}
    .stat-icon.green{background:#EDFAF4;color:#1D9E75;}
    .stat-icon.blue{background:#EEF4FF;color:#3B6DD8;}
    .stat-icon.purple{background:#F3F0FF;color:#7C3AED;}
    .stat-label{font-size:.7rem;color:#888;text-transform:uppercase;letter-spacing:.4px;}
    .stat-value{font-family:'Playfair Display',serif;font-size:1.3rem;color:var(--espresso);line-height:1.1;}

    /* Toolbar */
    .toolbar{display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;margin-bottom:1.25rem;}
    .search-box{display:flex;align-items:center;gap:.5rem;background:#fff;border:1.5px solid #ddd;border-radius:.65rem;padding:.45rem .85rem;flex:1;min-width:200px;max-width:320px;}
    .search-box input{border:none;outline:none;font-family:'DM Sans',sans-serif;font-size:.875rem;color:var(--espresso);width:100%;background:transparent;}
    .search-box i{color:#aaa;}
    .filter-select{border:1.5px solid #ddd;border-radius:.65rem;padding:.45rem .85rem;font-family:'DM Sans',sans-serif;font-size:.875rem;color:var(--espresso);background:#fff;cursor:pointer;}

    /* Table */
    .card-panel{background:#fff;border-radius:1rem;border:1px solid #ede8df;overflow:hidden;}
    .table-customers{margin:0;width:100%;border-collapse:collapse;}
    .table-customers th{font-size:.7rem;text-transform:uppercase;letter-spacing:.5px;color:#aaa;font-weight:500;padding:.65rem 1rem;background:#FAFAF8;border-bottom:1px solid #f0ebe2;white-space:nowrap;}
    .table-customers td{padding:.8rem 1rem;vertical-align:middle;font-size:.875rem;border-bottom:1px solid #f8f5f0;}
    .table-customers tr:last-child td{border-bottom:none;}
    .table-customers tr:hover td{background:#FAFAF8;}

    /* Customer avatar */
    .cust-avatar{width:34px;height:34px;border-radius:50%;background:var(--cream);display:flex;align-items:center;justify-content:center;font-size:.85rem;font-weight:600;color:var(--roast);flex-shrink:0;}

    /* Badges */
    .badge-active{background:#EDFAF4;color:#0F6E56;padding:.2rem .6rem;border-radius:2rem;font-size:.7rem;font-weight:500;}
    .badge-inactive{background:#F0F0F0;color:#888;padding:.2rem .6rem;border-radius:2rem;font-size:.7rem;font-weight:500;}
    .points-chip{background:#FEF3E2;color:#B07A1A;padding:.2rem .6rem;border-radius:2rem;font-size:.72rem;font-weight:500;display:inline-flex;align-items:center;gap:.25rem;}

    /* Toggle switch */
    .toggle-switch{position:relative;display:inline-block;width:36px;height:20px;}
    .toggle-switch input{opacity:0;width:0;height:0;}
    .toggle-slider{position:absolute;cursor:pointer;inset:0;background:#ddd;border-radius:20px;transition:.2s;}
    .toggle-slider:before{content:'';position:absolute;width:14px;height:14px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.2s;}
    input:checked + .toggle-slider{background:var(--caramel);}
    input:checked + .toggle-slider:before{transform:translateX(16px);}

    /* Detail modal */
    .modal-header{background:var(--espresso);color:var(--cream);}
    .modal-header .btn-close{filter:invert(1);}
    .modal-title{font-family:'Playfair Display',serif;}
    .detail-row{display:flex;justify-content:space-between;font-size:.85rem;padding:.45rem 0;border-bottom:1px solid #f8f5f0;}
    .detail-row:last-child{border-bottom:none;}

    .flash{border-radius:.75rem;padding:.65rem 1rem;font-size:.875rem;margin-bottom:1rem;}
    .empty-state{text-align:center;padding:3rem;color:#aaa;}
    .empty-state i{font-size:2.5rem;display:block;margin-bottom:.75rem;}
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
    <a href="orders.php"     class="nav-link-item"><i class="bi bi-receipt"></i> Orders</a>
    <a href="menu.php"       class="nav-link-item"><i class="bi bi-journal-text"></i> Menu items</a>
    <a href="categories.php" class="nav-link-item"><i class="bi bi-tags"></i> Categories</a>
    <div class="nav-label">Reports</div>
    <a href="reports.php"    class="nav-link-item"><i class="bi bi-bar-chart"></i> Sales report</a>
    <a href="customers.php"  class="nav-link-item active"><i class="bi bi-people"></i> Customers</a>
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
    <div class="topbar-title">Customers</div>
    <span style="font-size:.8rem;color:#888;"><?= $total_customers ?> registered</span>
  </div>

  <div class="content">

    <?php if ($flash): ?>
      <div class="flash alert alert-<?= $flash['type'] === 'success' ? 'success' : 'warning' ?>">
        <?= clean($flash['message']) ?>
      </div>
    <?php endif; ?>

    <!-- Summary -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div class="stat-icon blue"><i class="bi bi-people"></i></div>
          <div>
            <div class="stat-label">Total customers</div>
            <div class="stat-value"><?= $total_customers ?></div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div class="stat-icon green"><i class="bi bi-person-check"></i></div>
          <div>
            <div class="stat-label">Active</div>
            <div class="stat-value"><?= $active_count ?></div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div class="stat-icon orange"><i class="bi bi-currency-dollar"></i></div>
          <div>
            <div class="stat-label">Total revenue</div>
            <div class="stat-value"><?= format_price($total_revenue) ?></div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div class="stat-icon purple"><i class="bi bi-star"></i></div>
          <div>
            <div class="stat-label">Points issued</div>
            <div class="stat-value"><?= number_format($total_points) ?></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
      <form method="GET" action="" style="display:contents;">
        <div class="search-box">
          <i class="bi bi-search"></i>
          <input type="text" name="q" placeholder="Search name, email, phone..." value="<?= clean($search) ?>">
        </div>
        <select name="sort" class="filter-select" onchange="this.form.submit()">
          <option value="newest" <?= $sort==='newest'?'selected':'' ?>>Newest first</option>
          <option value="name"   <?= $sort==='name'  ?'selected':'' ?>>Name A–Z</option>
          <option value="spent"  <?= $sort==='spent' ?'selected':'' ?>>Most spent</option>
          <option value="orders" <?= $sort==='orders'?'selected':'' ?>>Most orders</option>
          <option value="points" <?= $sort==='points'?'selected':'' ?>>Most points</option>
        </select>
        <?php if ($search): ?>
          <a href="customers.php" style="font-size:.8rem;color:#aaa;text-decoration:none;">✕ Clear</a>
        <?php endif; ?>
      </form>
    </div>

    <!-- Table -->
    <div class="card-panel">
      <?php if (empty($customers)): ?>
        <div class="empty-state">
          <i class="bi bi-people"></i>
          <p>No customers found<?= $search ? ' for "'.clean($search).'"' : '' ?>.</p>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table-customers">
            <thead>
              <tr>
                <th></th>
                <th>Customer</th>
                <th>Phone</th>
                <th>Orders</th>
                <th>Total spent</th>
                <th>Points</th>
                <th>Last order</th>
                <th>Joined</th>
                <th>Active</th>
                <th>Details</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($customers as $c): ?>
                <tr>
                  <td>
                    <div class="cust-avatar"><?= strtoupper(substr($c['name'],0,1)) ?></div>
                  </td>
                  <td>
                    <div style="font-weight:500;"><?= clean($c['name']) ?></div>
                    <div style="font-size:.75rem;color:#aaa;"><?= clean($c['email']) ?></div>
                  </td>
                  <td style="color:#888;font-size:.82rem;"><?= clean($c['phone'] ?? '—') ?></td>
                  <td style="font-weight:600;color:var(--caramel);"><?= $c['total_orders'] ?></td>
                  <td style="font-weight:500;"><?= format_price($c['total_spent']) ?></td>
                  <td>
                    <span class="points-chip">
                      <i class="bi bi-star-fill" style="font-size:.6rem;"></i>
                      <?= number_format($c['points_balance']) ?>
                    </span>
                  </td>
                  <td style="font-size:.78rem;color:#aaa;">
                    <?= $c['last_order_at'] ? date('d M Y', strtotime($c['last_order_at'])) : '—' ?>
                  </td>
                  <td style="font-size:.78rem;color:#aaa;">
                    <?= date('d M Y', strtotime($c['created_at'])) ?>
                  </td>
                  <td>
                    <form method="POST" action="" style="display:inline;">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action"  value="toggle_active">
                      <input type="hidden" name="user_id" value="<?= $c['id'] ?>">
                      <label class="toggle-switch" title="<?= $c['is_active'] ? 'Deactivate' : 'Activate' ?>">
                        <input type="checkbox" <?= $c['is_active'] ? 'checked' : '' ?> onchange="this.form.submit()">
                        <span class="toggle-slider"></span>
                      </label>
                    </form>
                  </td>
                  <td>
                    <button class="btn-icon" style="border-radius:.4rem;border:1px solid #e5e5e5;background:#fff;cursor:pointer;width:30px;height:30px;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;transition:all .15s;"
                      onclick="openDetail(
                        '<?= addslashes($c['name']) ?>',
                        '<?= addslashes($c['email']) ?>',
                        '<?= addslashes($c['phone'] ?? '—') ?>',
                        <?= $c['total_orders'] ?>,
                        '<?= format_price($c['total_spent']) ?>',
                        <?= $c['points_balance'] ?>,
                        '<?= date('d M Y', strtotime($c['created_at'])) ?>',
                        '<?= $c['last_order_at'] ? date('d M Y', strtotime($c['last_order_at'])) : '—' ?>'
                      )"
                      title="View details">
                      <i class="bi bi-eye"></i>
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<!-- Customer detail modal -->
<div class="modal fade" id="detailModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content" style="border-radius:1rem;overflow:hidden;border:none;">
      <div class="modal-header">
        <h5 class="modal-title" id="detail-name">Customer</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="padding:1.25rem;">
        <div class="detail-row"><span style="color:#888;">Email</span><span id="d-email" style="font-weight:500;font-size:.82rem;"></span></div>
        <div class="detail-row"><span style="color:#888;">Phone</span><span id="d-phone" style="font-weight:500;"></span></div>
        <div class="detail-row"><span style="color:#888;">Total orders</span><span id="d-orders" style="font-weight:600;color:var(--caramel);"></span></div>
        <div class="detail-row"><span style="color:#888;">Total spent</span><span id="d-spent" style="font-weight:600;"></span></div>
        <div class="detail-row"><span style="color:#888;">Loyalty points</span><span id="d-points" style="font-weight:600;color:#B07A1A;"></span></div>
        <div class="detail-row"><span style="color:#888;">Member since</span><span id="d-joined"></span></div>
        <div class="detail-row"><span style="color:#888;">Last order</span><span id="d-last"></span></div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const detailModal = new bootstrap.Modal(document.getElementById('detailModal'));

function openDetail(name, email, phone, orders, spent, points, joined, last) {
  document.getElementById('detail-name').textContent = name;
  document.getElementById('d-email').textContent     = email;
  document.getElementById('d-phone').textContent     = phone;
  document.getElementById('d-orders').textContent    = orders;
  document.getElementById('d-spent').textContent     = spent;
  document.getElementById('d-points').textContent    = points + ' pts';
  document.getElementById('d-joined').textContent    = joined;
  document.getElementById('d-last').textContent      = last;
  detailModal.show();
}

// Auto-submit search form on Enter
document.querySelector('.search-box input').addEventListener('keydown', e => {
  if (e.key === 'Enter') e.target.closest('form').submit();
});
</script>
</body>
</html>