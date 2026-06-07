<?php
// ============================================================
//  Richiamo Coffee — Developer Panel / System Logs
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
$allowed = [ROLE_DEVELOPER];
require_once __DIR__ . '/../auth/auth_check.php';

$db = get_db();

$user_count    = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$order_count   = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$menu_count    = $db->query("SELECT COUNT(*) FROM menu_items WHERE is_available=1")->fetchColumn();
$session_count = $db->query("SELECT COUNT(*) FROM user_sessions WHERE revoked=0 AND expires_at > NOW()")->fetchColumn();
$pending_count = $db->query("SELECT COUNT(*) FROM orders WHERE order_status='pending'")->fetchColumn();

$sessions = $db->query("
    SELECT s.*, u.name, u.email, u.role
    FROM user_sessions s JOIN users u ON u.id = s.user_id
    WHERE s.revoked=0 AND s.expires_at > NOW()
    ORDER BY s.created_at DESC
")->fetchAll();

$recent_errors = [];
$error_log_path = ini_get('error_log');
if ($error_log_path && file_exists($error_log_path)) {
    $lines = array_reverse(array_slice(file($error_log_path), -30));
    $recent_errors = array_slice($lines, 0, 20);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>System Logs — <?= APP_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
    :root{--espresso:#1C0A00;--roast:#3B1A08;--caramel:#C68642;--latte:#D4A96A;--cream:#F5E6C8;}
    *{box-sizing:border-box;}
    body{font-family:'DM Sans',sans-serif;background:#0D1117;color:#c9d1d9;margin:0;}

    /* ── Sidebar ─────────────────────────────────────── */
    .sidebar{width:240px;min-height:100vh;background:#161b22;position:fixed;top:0;left:0;z-index:200;display:flex;flex-direction:column;border-right:1px solid #30363d;}
    .sidebar-brand{padding:1.5rem 1.25rem 1rem;border-bottom:1px solid #30363d;}
    .sidebar-brand h1{font-family:'Playfair Display',serif;color:var(--cream);font-size:1.15rem;margin:0;}
    .sidebar-brand p{color:#6e7681;font-size:.72rem;margin:.2rem 0 0;}
    .sidebar-nav{flex:1;padding:1rem 0;overflow-y:auto;}
    .nav-label{font-size:.65rem;letter-spacing:1px;text-transform:uppercase;color:#6e7681;padding:.75rem 1.25rem .25rem;}
    .nav-link-item{display:flex;align-items:center;gap:.65rem;padding:.6rem 1.25rem;color:rgba(201,209,217,.65);text-decoration:none;font-size:.875rem;border-left:3px solid transparent;transition:all .15s;}
    .nav-link-item:hover{color:#c9d1d9;background:rgba(255,255,255,.04);}
    .nav-link-item.active{color:var(--caramel);background:rgba(198,134,66,.1);border-left-color:var(--caramel);}
    .nav-link-item i{font-size:1rem;min-width:18px;}
    .nav-badge{margin-left:auto;background:var(--caramel);color:var(--espresso);border-radius:10px;font-size:.65rem;font-weight:700;padding:.1rem .5rem;}
    .sidebar-footer{padding:1rem 1.25rem;border-top:1px solid #30363d;}
    .user-chip{display:flex;align-items:center;gap:.6rem;}
    .user-avatar{width:32px;height:32px;border-radius:50%;background:var(--caramel);display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:600;color:var(--espresso);flex-shrink:0;}
    .user-name{color:var(--cream);font-size:.8rem;font-weight:500;}
    .user-role{color:#6e7681;font-size:.7rem;text-transform:capitalize;}

    /* ── Main ────────────────────────────────────────── */
    .main{margin-left:240px;min-height:100vh;}
    .topbar{background:#161b22;border-bottom:1px solid #30363d;padding:.9rem 1.5rem;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;}
    .topbar-title{font-family:'Playfair Display',serif;font-size:1.2rem;color:var(--cream);}
    .content{padding:1.5rem;max-width:960px;}

    /* ── Dev cards ───────────────────────────────────── */
    .dev-card{background:#161b22;border:1px solid #30363d;border-radius:.85rem;padding:1.25rem;margin-bottom:1rem;}
    .dev-card h2{font-family:'Playfair Display',serif;font-size:.95rem;color:var(--caramel);margin-bottom:1rem;display:flex;align-items:center;gap:.5rem;}
    .stat-row{display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:0;}
    .stat-box{background:#0D1117;border:1px solid #30363d;border-radius:.6rem;padding:.75rem 1rem;flex:1;min-width:110px;}
    .stat-label{font-size:.68rem;color:#6e7681;text-transform:uppercase;letter-spacing:.5px;}
    .stat-value{font-size:1.3rem;font-weight:600;color:var(--cream);margin-top:.15rem;}
    pre{background:#0D1117;border:1px solid #30363d;border-radius:.5rem;padding:1rem;font-size:.78rem;color:#8b949e;overflow-x:auto;max-height:300px;overflow-y:auto;margin:0;}
    .badge-role{display:inline-block;border-radius:.3rem;font-size:.68rem;padding:.12rem .45rem;font-weight:600;}
    .badge-developer{background:rgba(124,58,237,.2);color:#a78bfa;}
    .badge-admin{background:rgba(198,134,66,.2);color:var(--caramel);}
    .badge-customer{background:rgba(59,109,216,.2);color:#60a5fa;}
    .badge-reset{background:rgba(249,115,22,.2);color:#fb923c;}
    .session-row{display:flex;align-items:center;gap:.75rem;padding:.6rem 0;border-bottom:1px solid #21262d;font-size:.82rem;}
    .session-row:last-child{border-bottom:none;}
    .session-avatar{width:28px;height:28px;border-radius:50%;background:rgba(198,134,66,.2);display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:600;color:var(--caramel);flex-shrink:0;}
    .env-row{display:flex;justify-content:space-between;padding:.4rem 0;border-bottom:1px solid #21262d;font-size:.82rem;}
    .env-row:last-child{border-bottom:none;}
    .env-key{color:#6e7681;font-family:monospace;}
    .env-val{color:#c9d1d9;font-family:monospace;}
    .env-val.ok{color:#3fb950;}
    .env-val.warn{color:#d29922;}
    .link-btn{color:var(--caramel);text-decoration:none;font-size:.8rem;font-weight:500;border:1px solid rgba(198,134,66,.3);border-radius:.4rem;padding:.25rem .65rem;transition:all .15s;}
    .link-btn:hover{background:rgba(198,134,66,.1);color:var(--caramel);}
    .live-dot{width:7px;height:7px;border-radius:50%;background:#3fb950;display:inline-block;margin-right:.35rem;animation:pulse 1.5s infinite;}
    @keyframes pulse{0%,100%{opacity:1}50%{opacity:.3}}
  </style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <h1>☕ <?= APP_NAME ?></h1>
    <p>Developer Panel</p>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-label">Admin</div>
    <a href="<?= APP_URL ?>/admin/dashboard.php"  class="nav-link-item"><i class="bi bi-grid-1x2"></i> Dashboard</a>
    <a href="<?= APP_URL ?>/admin/orders.php"      class="nav-link-item">
      <i class="bi bi-receipt"></i> Orders
      <?php if ($pending_count > 0): ?>
        <span class="nav-badge"><?= $pending_count ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= APP_URL ?>/admin/menu.php"        class="nav-link-item"><i class="bi bi-journal-text"></i> Menu items</a>
    <a href="<?= APP_URL ?>/admin/categories.php"  class="nav-link-item"><i class="bi bi-tags"></i> Categories</a>
    <a href="<?= APP_URL ?>/admin/customers.php"   class="nav-link-item"><i class="bi bi-people"></i> Customers</a>
    <a href="<?= APP_URL ?>/admin/reports.php"     class="nav-link-item"><i class="bi bi-bar-chart"></i> Sales report</a>
    <a href="<?= APP_URL ?>/admin/notifications.php" class="nav-link-item"><i class="bi bi-bell"></i> Notifications</a>

    <div class="nav-label">Developer</div>
    <a href="<?= APP_URL ?>/developer/logs.php"    class="nav-link-item active"><i class="bi bi-terminal"></i> System logs</a>
    <a href="<?= APP_URL ?>/admin/users.php"       class="nav-link-item"><i class="bi bi-shield-lock"></i> User management</a>
    <a href="http://localhost/phpmyadmin"           class="nav-link-item" target="_blank"><i class="bi bi-database"></i> phpMyAdmin <i class="bi bi-box-arrow-up-right" style="font-size:.65rem;margin-left:auto;"></i></a>
  </nav>

  <div class="sidebar-footer">
    <div class="user-chip">
      <div class="user-avatar"><?= strtoupper(substr($current_user['name'],0,1)) ?></div>
      <div style="flex:1;min-width:0;">
        <div class="user-name"><?= clean($current_user['name']) ?></div>
        <div class="user-role"><?= $current_user['role'] ?></div>
      </div>
      <a href="<?= APP_URL ?>/auth/logout.php"
         style="color:rgba(201,209,217,.4);font-size:.85rem;text-decoration:none;transition:color .15s;"
         onmouseover="this.style.color='#f87171'"
         onmouseout="this.style.color='rgba(201,209,217,.4)'"
         title="Logout">
        <i class="bi bi-box-arrow-right"></i>
      </a>
    </div>
  </div>
</aside>

<!-- Main -->
<div class="main">
  <div class="topbar">
    <div class="topbar-title">System logs</div>
    <div style="font-size:.78rem;color:#6e7681;">
      <span class="live-dot"></span>
      PHP <?= PHP_VERSION ?> &bull; <?= date('d M Y, h:i A') ?>
    </div>
  </div>

  <div class="content">

    <!-- Stats overview -->
    <div class="dev-card">
      <h2><i class="bi bi-activity"></i> System overview</h2>
      <div class="stat-row">
        <div class="stat-box"><div class="stat-label">Users</div><div class="stat-value"><?= $user_count ?></div></div>
        <div class="stat-box"><div class="stat-label">Orders</div><div class="stat-value"><?= $order_count ?></div></div>
        <div class="stat-box"><div class="stat-label">Menu items</div><div class="stat-value"><?= $menu_count ?></div></div>
        <div class="stat-box"><div class="stat-label">Active sessions</div><div class="stat-value"><?= $session_count ?></div></div>
        <div class="stat-box"><div class="stat-label">Pending orders</div><div class="stat-value" style="color:<?= $pending_count>0?'#d29922':'#3fb950' ?>;"><?= $pending_count ?></div></div>
      </div>
    </div>

    <!-- Environment -->
    <div class="dev-card">
      <h2><i class="bi bi-gear"></i> Environment</h2>
      <div>
        <div class="env-row"><span class="env-key">APP_URL</span><span class="env-val"><?= APP_URL ?></span></div>
        <div class="env-row"><span class="env-key">APP_VERSION</span><span class="env-val"><?= APP_VERSION ?></span></div>
        <div class="env-row"><span class="env-key">DB_HOST</span><span class="env-val"><?= DB_HOST ?></span></div>
        <div class="env-row"><span class="env-key">DB_NAME</span><span class="env-val"><?= DB_NAME ?></span></div>
        <div class="env-row"><span class="env-key">DEBUG_MODE</span><span class="env-val <?= DEBUG_MODE?'warn':'ok' ?>"><?= DEBUG_MODE?'true (disable in production)':'false ✓' ?></span></div>
        <div class="env-row"><span class="env-key">TOKEN_EXPIRY</span><span class="env-val"><?= TOKEN_EXPIRY_HOURS ?> hours</span></div>
        <div class="env-row"><span class="env-key">SST_RATE</span><span class="env-val"><?= SST_RATE * 100 ?>%</span></div>
        <div class="env-row"><span class="env-key">PHP_VERSION</span><span class="env-val ok"><?= PHP_VERSION ?></span></div>
        <div class="env-row"><span class="env-key">SERVER_SOFTWARE</span><span class="env-val"><?= $_SERVER['SERVER_SOFTWARE'] ?? 'N/A' ?></span></div>
        <div class="env-row"><span class="env-key">SESSION_NAME</span><span class="env-val"><?= SESSION_NAME ?></span></div>
      </div>
    </div>

    <!-- Active sessions -->
    <div class="dev-card">
      <h2><i class="bi bi-people"></i> Active sessions (<?= count($sessions) ?>)</h2>
      <?php if (empty($sessions)): ?>
        <p style="color:#6e7681;font-size:.82rem;">No active sessions.</p>
      <?php else: ?>
        <?php foreach ($sessions as $sess): ?>
          <div class="session-row">
            <div class="session-avatar"><?= strtoupper(substr($sess['name'],0,1)) ?></div>
            <div style="flex:1;min-width:0;">
              <div style="font-weight:500;color:#c9d1d9;"><?= clean($sess['name']) ?></div>
              <div style="font-size:.72rem;color:#6e7681;"><?= clean($sess['email']) ?> &bull; IP: <?= clean($sess['ip_address'] ?? 'N/A') ?></div>
            </div>
            <span class="badge-role badge-<?= $sess['role'] ?>"><?= $sess['role'] ?></span>
            <span style="font-size:.72rem;color:#6e7681;white-space:nowrap;margin-left:.5rem;">
              Exp: <?= date('h:i A', strtotime($sess['expires_at'])) ?>
            </span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- PHP extensions -->
    <div class="dev-card">
      <h2><i class="bi bi-puzzle"></i> PHP extensions</h2>
      <div style="display:flex;flex-wrap:wrap;gap:.4rem;">
        <?php
          $needed = ['pdo','pdo_mysql','curl','json','mbstring','openssl','session'];
          foreach ($needed as $ext):
            $loaded = extension_loaded($ext);
        ?>
          <span style="background:<?= $loaded?'rgba(63,185,80,.15)':'rgba(248,81,73,.15)' ?>;color:<?= $loaded?'#3fb950':'#f85149' ?>;border-radius:.35rem;padding:.2rem .6rem;font-size:.72rem;font-family:monospace;">
            <?= $loaded?'✓':'✗' ?> <?= $ext ?>
          </span>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- PHP error log -->
    <div class="dev-card">
      <h2><i class="bi bi-bug"></i> PHP error log
        <span style="font-size:.72rem;color:#6e7681;font-weight:400;margin-left:.5rem;">
          <?= $error_log_path ?: 'Path not configured' ?>
        </span>
      </h2>
      <?php if (!empty($recent_errors)): ?>
        <pre><?php foreach ($recent_errors as $line) echo htmlspecialchars($line); ?></pre>
      <?php else: ?>
        <p style="color:#3fb950;font-size:.82rem;">✓ No recent errors found<?= !$error_log_path ? ' (error_log path not set in php.ini)' : '' ?>.</p>
      <?php endif; ?>
    </div>

    <!-- Quick actions -->
    <div class="dev-card">
      <h2><i class="bi bi-lightning"></i> Quick actions</h2>
      <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
        <a href="http://localhost/phpmyadmin" target="_blank" class="link-btn">
          <i class="bi bi-database"></i> phpMyAdmin
        </a>
        <a href="<?= APP_URL ?>/admin/users.php" class="link-btn">
          <i class="bi bi-shield-lock"></i> User management
        </a>
        <a href="<?= APP_URL ?>/admin/reports.php" class="link-btn">
          <i class="bi bi-bar-chart"></i> Sales report
        </a>
        <a href="<?= APP_URL ?>/database_update.sql" class="link-btn" onclick="return confirm('This will download the SQL file.')">
          <i class="bi bi-download"></i> DB update SQL
        </a>
      </div>
    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
