<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
$allowed = [ROLE_DEVELOPER];
require_once __DIR__ . '/../auth/auth_check.php';
$db = get_db();

$user_count  = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$order_count = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$session_count = $db->query("SELECT COUNT(*) FROM user_sessions WHERE revoked=0 AND expires_at > NOW()")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Developer — <?= APP_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
  <style>
    :root { --espresso:#1C0A00;--caramel:#C68642;--latte:#D4A96A;--cream:#F5E6C8; }
    body { font-family:'DM Sans',sans-serif;background:#0D1117;color:#c9d1d9;margin:0; }
    .topbar { background:#161b22;border-bottom:1px solid #30363d;padding:1rem 1.5rem;display:flex;align-items:center;justify-content:space-between; }
    .topbar-title { font-family:'Playfair Display',serif;font-size:1.1rem;color:var(--cream); }
    .content { padding:1.5rem;max-width:900px;margin:0 auto; }
    .dev-card { background:#161b22;border:1px solid #30363d;border-radius:.75rem;padding:1.25rem;margin-bottom:1rem; }
    .dev-card h2 { font-family:'Playfair Display',serif;font-size:1rem;color:var(--caramel);margin-bottom:1rem; }
    .stat-row { display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1rem; }
    .stat-box { background:#0D1117;border:1px solid #30363d;border-radius:.5rem;padding:.75rem 1rem;flex:1;min-width:120px; }
    .stat-box .label { font-size:.7rem;color:#6e7681;text-transform:uppercase;letter-spacing:.5px; }
    .stat-box .value { font-size:1.4rem;font-weight:600;color:var(--cream); }
    pre { background:#0D1117;border:1px solid #30363d;border-radius:.5rem;padding:1rem;font-size:.8rem;color:#8b949e;overflow-x:auto; }
    .badge-dev { background:var(--caramel);color:var(--espresso);border-radius:.3rem;font-size:.7rem;padding:.15rem .5rem;font-weight:600; }
    a { color:var(--caramel); }
  </style>
</head>
<body>
<div class="topbar">
  <div class="topbar-title">☕ Developer Panel</div>
  <div style="display:flex;gap:1rem;align-items:center;font-size:.8rem;">
    <span style="color:#6e7681;">Logged in as <?= clean($current_user['name']) ?></span>
    <a href="<?= APP_URL ?>/admin/dashboard.php">Admin</a>
    <a href="<?= APP_URL ?>/auth/logout.php" style="color:#f87171;">Logout</a>
  </div>
</div>

<div class="content">
  <div class="dev-card">
    <h2>System overview</h2>
    <div class="stat-row">
      <div class="stat-box"><div class="label">Users</div><div class="value"><?= $user_count ?></div></div>
      <div class="stat-box"><div class="label">Orders</div><div class="value"><?= $order_count ?></div></div>
      <div class="stat-box"><div class="label">Active sessions</div><div class="value"><?= $session_count ?></div></div>
      <div class="stat-box"><div class="label">PHP version</div><div class="value" style="font-size:1rem;"><?= PHP_VERSION ?></div></div>
    </div>
  </div>

  <div class="dev-card">
    <h2>Environment</h2>
    <pre><?php
      echo "APP_URL:    " . APP_URL . "\n";
      echo "APP_VER:    " . APP_VERSION . "\n";
      echo "DB_HOST:    " . DB_HOST . "\n";
      echo "DB_NAME:    " . DB_NAME . "\n";
      echo "DEBUG_MODE: " . (DEBUG_MODE ? 'true' : 'false') . "\n";
      echo "TOKEN_EXP:  " . TOKEN_EXPIRY_HOURS . " hours\n";
      echo "SST_RATE:   " . (SST_RATE * 100) . "%\n";
    ?></pre>
  </div>

  <div class="dev-card">
    <h2>Active sessions</h2>
    <?php
      $sessions = $db->query("
        SELECT s.*, u.name, u.email, u.role
        FROM user_sessions s JOIN users u ON u.id = s.user_id
        WHERE s.revoked=0 AND s.expires_at > NOW()
        ORDER BY s.created_at DESC
      ")->fetchAll();
    ?>
    <pre><?php foreach ($sessions as $s): ?>
[<?= $s['role'] ?>] <?= $s['email'] ?> — <?= $s['name'] ?>
  IP: <?= $s['ip_address'] ?>   Expires: <?= $s['expires_at'] ?>
<?php endforeach; if (empty($sessions)) echo "No active sessions."; ?></pre>
  </div>

  <div class="dev-card">
    <h2>Quick DB tools</h2>
    <p style="font-size:.85rem;color:#6e7681;">Use phpMyAdmin for full DB access: <a href="http://localhost/phpmyadmin" target="_blank">localhost/phpmyadmin</a></p>
  </div>
</div>
</body>
</html>
