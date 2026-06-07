<?php
// ============================================================
//  Richiamo Coffee — Customer Profile
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
$allowed = [ROLE_CUSTOMER, ROLE_ADMIN, ROLE_DEVELOPER];
require_once __DIR__ . '/../auth/auth_check.php';

$db    = get_db();
$flash = get_flash();
$error = '';
$success = '';

// Fetch full user record
$user_stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->execute([$current_user['id']]);
$user = $user_stmt->fetch();

// ── Handle profile update ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post('action');

    if ($action === 'update_profile') {
        $name  = trim(post('name'));
        $phone = trim(post('phone'));

        if (strlen($name) < 2) {
            $error = 'Name must be at least 2 characters.';
        } else {
            $db->prepare("UPDATE users SET name = ?, phone = ?, updated_at = NOW() WHERE id = ?")
               ->execute([$name, $phone, $user['id']]);
            $_SESSION['name'] = $name;
            $success = 'Profile updated successfully.';
            // Refresh user
            $user_stmt->execute([$current_user['id']]);
            $user = $user_stmt->fetch();
        }
    }

    if ($action === 'change_password') {
        $current_pw  = post('current_password');
        $new_pw      = post('new_password');
        $confirm_pw  = post('confirm_password');

        if (!verify_password($current_pw, $user['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new_pw) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif (!preg_match('/[A-Z]/', $new_pw)) {
            $error = 'New password must contain at least one uppercase letter.';
        } elseif (!preg_match('/[0-9]/', $new_pw)) {
            $error = 'New password must contain at least one number.';
        } elseif ($new_pw !== $confirm_pw) {
            $error = 'New passwords do not match.';
        } else {
            $db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?")
               ->execute([hash_password($new_pw), $user['id']]);
            $success = 'Password changed successfully.';
        }
    }
}

// ── Loyalty points ────────────────────────────────────────────
$points_total = (int) $db->prepare("SELECT COALESCE(SUM(points),0) FROM loyalty_points WHERE user_id = ?")
    ->execute([$user['id']]) ? $db->query("SELECT COALESCE(SUM(points),0) FROM loyalty_points WHERE user_id = {$user['id']}")->fetchColumn() : 0;

$points_stmt = $db->prepare("SELECT COALESCE(SUM(points),0) AS total FROM loyalty_points WHERE user_id = ?");
$points_stmt->execute([$user['id']]);
$points_total = (int) $points_stmt->fetchColumn();

$points_history = $db->prepare("
    SELECT lp.*, o.order_number
    FROM loyalty_points lp
    LEFT JOIN orders o ON o.id = lp.order_id
    WHERE lp.user_id = ?
    ORDER BY lp.created_at DESC
    LIMIT 10
");
$points_history->execute([$user['id']]);
$points_rows = $points_history->fetchAll();

// ── Order stats ───────────────────────────────────────────────
$stats = $db->prepare("
    SELECT COUNT(*) AS total_orders,
           COALESCE(SUM(total_amount),0) AS total_spent,
           SUM(CASE WHEN order_status='completed' THEN 1 ELSE 0 END) AS completed
    FROM orders WHERE user_id = ?
");
$stats->execute([$user['id']]);
$order_stats = $stats->fetch();

// ── Recent orders ─────────────────────────────────────────────
$recent = $db->prepare("
    SELECT o.*, COUNT(oi.id) AS item_count
    FROM orders o
    LEFT JOIN order_items oi ON oi.order_id = o.id
    WHERE o.user_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT 5
");
$recent->execute([$user['id']]);
$recent_orders = $recent->fetchAll();

// Member since duration
$member_since = new DateTime($user['created_at']);
$now          = new DateTime();
$diff         = $member_since->diff($now);
$member_label = $diff->days < 30
    ? $diff->days . ' days'
    : ($diff->m + $diff->y * 12) . ' months';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Profile — <?= APP_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
    :root{--espresso:#1C0A00;--roast:#3B1A08;--caramel:#C68642;--latte:#D4A96A;--cream:#F5E6C8;--foam:#FDF6EC;}
    *{box-sizing:border-box;}
    body{font-family:'DM Sans',sans-serif;background:#F4F1EC;color:var(--espresso);min-height:100vh;}
    .navbar-rc{background:var(--espresso);padding:.9rem 0;border-bottom:2px solid var(--caramel);}
    .navbar-brand-text{font-family:'Playfair Display',serif;color:var(--cream)!important;font-size:1.3rem;text-decoration:none;}
    .nav-link-rc{color:var(--latte);font-size:.85rem;text-decoration:none;display:flex;align-items:center;gap:.4rem;transition:color .15s;}
    .nav-link-rc:hover{color:var(--cream);}
    .profile-wrap{max-width:900px;margin:2rem auto;padding:0 1rem 3rem;}
    .page-title{font-family:'Playfair Display',serif;font-size:1.6rem;margin-bottom:1.5rem;}

    /* Profile hero card */
    .profile-hero{
      background:linear-gradient(135deg,var(--espresso),var(--roast));
      border-radius:1.25rem;padding:1.75rem;
      display:flex;align-items:center;gap:1.5rem;
      margin-bottom:1.25rem;position:relative;overflow:hidden;
    }
    .profile-hero::before{
      content:'';position:absolute;inset:0;
      background:radial-gradient(ellipse at 80% 50%,rgba(198,134,66,.12) 0%,transparent 60%);
      pointer-events:none;
    }
    .profile-avatar{
      width:72px;height:72px;border-radius:50%;
      background:var(--caramel);
      display:flex;align-items:center;justify-content:center;
      font-family:'Playfair Display',serif;font-size:1.8rem;
      color:var(--espresso);flex-shrink:0;
      border:3px solid rgba(255,255,255,.15);
    }
    .profile-name{font-family:'Playfair Display',serif;font-size:1.25rem;color:var(--cream);}
    .profile-email{font-size:.8rem;color:var(--latte);margin-top:.15rem;}
    .profile-since{font-size:.75rem;color:rgba(245,230,200,.5);margin-top:.25rem;}
    .points-chip{
      margin-left:auto;text-align:center;
      background:rgba(198,134,66,.2);border:1px solid rgba(198,134,66,.3);
      border-radius:1rem;padding:.75rem 1.25rem;flex-shrink:0;
    }
    .points-value{font-family:'Playfair Display',serif;font-size:1.6rem;color:var(--caramel);}
    .points-label{font-size:.7rem;color:var(--latte);margin-top:.1rem;}

    /* Stat chips */
    .stat-row{display:flex;gap:.75rem;margin-bottom:1.25rem;flex-wrap:wrap;}
    .stat-chip{background:#fff;border:1px solid #ede8df;border-radius:.75rem;padding:.9rem 1.1rem;flex:1;min-width:130px;}
    .stat-chip-value{font-family:'Playfair Display',serif;font-size:1.2rem;color:var(--espresso);}
    .stat-chip-label{font-size:.72rem;color:#aaa;text-transform:uppercase;letter-spacing:.4px;margin-top:.15rem;}

    /* Tabs */
    .tab-bar{display:flex;gap:.25rem;background:#fff;border:1px solid #ede8df;border-radius:.75rem;padding:.3rem;margin-bottom:1.25rem;}
    .tab-btn{flex:1;padding:.5rem;border:none;background:transparent;border-radius:.5rem;font-family:'DM Sans',sans-serif;font-size:.85rem;font-weight:500;color:#888;cursor:pointer;transition:all .2s;}
    .tab-btn.active{background:var(--espresso);color:var(--cream);}
    .tab-panel{display:none;}
    .tab-panel.active{display:block;}

    /* Form card */
    .form-card{background:#fff;border-radius:1rem;border:1px solid #ede8df;padding:1.5rem;margin-bottom:1rem;}
    .form-card-title{font-family:'Playfair Display',serif;font-size:1rem;color:var(--espresso);margin-bottom:1.25rem;display:flex;align-items:center;gap:.5rem;}
    .form-card-title i{color:var(--caramel);}
    .form-label-rc{font-size:.73rem;font-weight:500;letter-spacing:.5px;text-transform:uppercase;color:var(--roast);margin-bottom:.4rem;display:block;}
    .input-group-text{background:var(--cream);border:1.5px solid #ddd;border-right:none;color:var(--caramel);}
    .form-control-rc{border:1.5px solid #ddd;border-left:none;background:#fff;color:var(--espresso);font-family:'DM Sans',sans-serif;font-size:.875rem;padding:.6rem .85rem;transition:border-color .2s;}
    .form-control-rc:focus{border-color:var(--caramel);box-shadow:0 0 0 3px rgba(198,134,66,.12);outline:none;}
    .input-group:focus-within .input-group-text{border-color:var(--caramel);}
    .btn-save{background:var(--espresso);color:var(--cream);border:none;border-radius:.65rem;padding:.65rem 1.5rem;font-size:.875rem;font-weight:500;cursor:pointer;transition:background .2s;}
    .btn-save:hover{background:var(--roast);}

    /* Points history */
    .points-row{display:flex;align-items:center;gap:.75rem;padding:.7rem 0;border-bottom:1px solid #f8f5f0;font-size:.85rem;}
    .points-row:last-child{border-bottom:none;}
    .points-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;}
    .points-earn{background:#1D9E75;}
    .points-redeem{background:#E24B4A;}
    .points-badge-earn{background:#EDFAF4;color:#0F6E56;padding:.2rem .55rem;border-radius:2rem;font-size:.72rem;font-weight:500;}
    .points-badge-redeem{background:#FEF0F0;color:#A32D2D;padding:.2rem .55rem;border-radius:2rem;font-size:.72rem;font-weight:500;}

    /* Order history mini */
    .order-mini{display:flex;align-items:center;gap:.75rem;padding:.75rem 0;border-bottom:1px solid #f8f5f0;font-size:.85rem;}
    .order-mini:last-child{border-bottom:none;}
    .order-status-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;}
    .status-pending{background:#E8A045;}
    .status-preparing{background:#3B6DD8;}
    .status-ready{background:#1D9E75;}
    .status-completed{background:#aaa;}
    .status-cancelled{background:#E24B4A;}

    /* Alerts */
    .alert-rc{border-radius:.65rem;padding:.7rem 1rem;font-size:.875rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem;}

    /* Password strength */
    .strength-bar{height:4px;border-radius:2px;background:#eee;margin-top:.35rem;overflow:hidden;}
    .strength-fill{height:100%;width:0%;border-radius:2px;transition:width .3s,background .3s;}
    .pw-req{font-size:.72rem;color:#aaa;display:flex;align-items:center;gap:.35rem;margin-top:.2rem;transition:color .2s;}
    .pw-req.met{color:#1D9E75;}
  </style>
</head>
<body>

<nav class="navbar-rc">
  <div class="container d-flex align-items-center justify-content-between">
    <a href="<?= APP_URL ?>/customer/menu.php" class="navbar-brand-text">☕ <?= APP_NAME ?></a>
    <div style="display:flex;align-items:center;gap:1rem;">
      <a href="menu.php"  class="nav-link-rc"><i class="bi bi-cup-hot"></i> Menu</a>
      <a href="track.php" class="nav-link-rc"><i class="bi bi-receipt"></i> Orders</a>
      <a href="<?= APP_URL ?>/auth/logout.php" class="nav-link-rc"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
  </div>
</nav>

<div class="profile-wrap">

  <!-- Profile hero -->
  <div class="profile-hero">
    <div class="profile-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
    <div>
      <div class="profile-name"><?= clean($user['name']) ?></div>
      <div class="profile-email"><?= clean($user['email']) ?></div>
      <div class="profile-since">Member for <?= $member_label ?></div>
    </div>
    <div class="points-chip">
      <div class="points-value"><?= number_format($points_total) ?></div>
      <div class="points-label">Loyalty points</div>
    </div>
  </div>

  <!-- Stats -->
  <div class="stat-row">
    <div class="stat-chip">
      <div class="stat-chip-value"><?= $order_stats['total_orders'] ?></div>
      <div class="stat-chip-label">Total orders</div>
    </div>
    <div class="stat-chip">
      <div class="stat-chip-value"><?= format_price($order_stats['total_spent']) ?></div>
      <div class="stat-chip-label">Total spent</div>
    </div>
    <div class="stat-chip">
      <div class="stat-chip-value"><?= $order_stats['completed'] ?></div>
      <div class="stat-chip-label">Completed</div>
    </div>
    <div class="stat-chip">
      <div class="stat-chip-value"><?= number_format($points_total) ?></div>
      <div class="stat-chip-label">Points balance</div>
    </div>
  </div>

  <!-- Alerts -->
  <?php if ($error): ?>
    <div class="alert-rc alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?= clean($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert-rc alert alert-success"><i class="bi bi-check-circle"></i> <?= clean($success) ?></div>
  <?php endif; ?>
  <?php if ($flash): ?>
    <div class="alert-rc alert alert-<?= $flash['type'] === 'success' ? 'success' : 'warning' ?>">
      <?= clean($flash['message']) ?>
    </div>
  <?php endif; ?>

  <!-- Tabs -->
  <div class="tab-bar">
    <button class="tab-btn active" onclick="switchTab('profile')"><i class="bi bi-person me-1"></i> Profile</button>
    <button class="tab-btn" onclick="switchTab('password')"><i class="bi bi-lock me-1"></i> Password</button>
    <button class="tab-btn" onclick="switchTab('points')"><i class="bi bi-star me-1"></i> Points</button>
    <button class="tab-btn" onclick="switchTab('orders')"><i class="bi bi-receipt me-1"></i> Orders</button>
  </div>

  <!-- Tab: Profile -->
  <div class="tab-panel active" id="tab-profile">
    <div class="form-card">
      <div class="form-card-title"><i class="bi bi-person-circle"></i> Personal information</div>
      <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_profile">
        <div class="mb-3">
          <label class="form-label-rc">Full name</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input type="text" class="form-control-rc" name="name"
                   value="<?= clean($user['name']) ?>" required minlength="2">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label-rc">Email address</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input type="email" class="form-control-rc"
                   value="<?= clean($user['email']) ?>" disabled
                   style="background:#f8f8f8;color:#aaa;">
          </div>
          <small style="font-size:.72rem;color:#aaa;">Email cannot be changed.</small>
        </div>
        <div class="mb-3">
          <label class="form-label-rc">Phone number</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-phone"></i></span>
            <input type="tel" class="form-control-rc" name="phone"
                   value="<?= clean($user['phone'] ?? '') ?>" placeholder="e.g. 0123456789">
          </div>
        </div>
        <button type="submit" class="btn-save">
          <i class="bi bi-check-lg me-1"></i> Save changes
        </button>
      </form>
    </div>
  </div>

  <!-- Tab: Password -->
  <div class="tab-panel" id="tab-password">
    <div class="form-card">
      <div class="form-card-title"><i class="bi bi-shield-lock"></i> Change password</div>
      <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="change_password">
        <div class="mb-3">
          <label class="form-label-rc">Current password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" class="form-control-rc" name="current_password" required placeholder="Your current password">
            <button type="button" onclick="togglePw('current_password','eye1')"
              style="background:var(--cream);border:1.5px solid #ddd;border-left:none;padding:0 .75rem;cursor:pointer;color:var(--caramel);">
              <i class="bi bi-eye" id="eye1"></i>
            </button>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label-rc">New password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
            <input type="password" class="form-control-rc" name="new_password" id="new_pw"
                   required placeholder="Min. 8 characters" oninput="checkPwStrength(this.value)">
            <button type="button" onclick="togglePw('new_pw','eye2')"
              style="background:var(--cream);border:1.5px solid #ddd;border-left:none;padding:0 .75rem;cursor:pointer;color:var(--caramel);">
              <i class="bi bi-eye" id="eye2"></i>
            </button>
          </div>
          <div class="strength-bar"><div class="strength-fill" id="sw-fill"></div></div>
          <div class="pw-req" id="req-len"><i class="bi bi-circle"></i> At least 8 characters</div>
          <div class="pw-req" id="req-up"><i class="bi bi-circle"></i> One uppercase letter</div>
          <div class="pw-req" id="req-num"><i class="bi bi-circle"></i> One number</div>
        </div>
        <div class="mb-4">
          <label class="form-label-rc">Confirm new password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
            <input type="password" class="form-control-rc" name="confirm_password"
                   required placeholder="Repeat new password" oninput="checkMatch()">
          </div>
          <div id="match-msg" style="font-size:.72rem;margin-top:.25rem;"></div>
        </div>
        <button type="submit" class="btn-save">
          <i class="bi bi-shield-check me-1"></i> Change password
        </button>
      </form>
    </div>
  </div>

  <!-- Tab: Points -->
  <div class="tab-panel" id="tab-points">
    <div class="form-card">
      <div class="form-card-title"><i class="bi bi-star-fill"></i> Loyalty points history</div>
      <div style="background:var(--foam);border-radius:.75rem;padding:1rem;margin-bottom:1.25rem;display:flex;align-items:center;gap:1rem;">
        <div style="font-size:2rem;">⭐</div>
        <div>
          <div style="font-family:'Playfair Display',serif;font-size:1.4rem;color:var(--espresso);"><?= number_format($points_total) ?> points</div>
          <div style="font-size:.78rem;color:#888;">You earn 1 point for every RM1 spent (before SST)</div>
        </div>
      </div>
      <?php if (empty($points_rows)): ?>
        <p style="text-align:center;color:#aaa;font-size:.875rem;padding:1.5rem 0;">No points activity yet. Place your first order to start earning!</p>
      <?php else: ?>
        <?php foreach ($points_rows as $row): $earn = $row['points'] > 0; ?>
          <div class="points-row">
            <span class="points-dot <?= $earn ? 'points-earn' : 'points-redeem' ?>"></span>
            <div style="flex:1;">
              <div style="font-weight:500;"><?= clean($row['description']) ?></div>
              <?php if ($row['order_number']): ?>
                <div style="font-size:.75rem;color:#aaa;"><?= clean($row['order_number']) ?></div>
              <?php endif; ?>
            </div>
            <span class="<?= $earn ? 'points-badge-earn' : 'points-badge-redeem' ?>">
              <?= $earn ? '+' : '' ?><?= $row['points'] ?> pts
            </span>
            <span style="font-size:.75rem;color:#aaa;min-width:80px;text-align:right;">
              <?= date('d M Y', strtotime($row['created_at'])) ?>
            </span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Tab: Orders -->
  <div class="tab-panel" id="tab-orders">
    <div class="form-card">
      <div class="form-card-title"><i class="bi bi-bag-check"></i> Recent orders</div>
      <?php if (empty($recent_orders)): ?>
        <div style="text-align:center;padding:2rem;color:#aaa;">
          <i class="bi bi-bag" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>
          No orders yet.
          <a href="menu.php" style="display:block;margin-top:.75rem;color:var(--caramel);font-size:.875rem;">Browse our menu</a>
        </div>
      <?php else: ?>
        <?php foreach ($recent_orders as $o): ?>
          <div class="order-mini">
            <span class="order-status-dot status-<?= $o['order_status'] ?>"></span>
            <div style="flex:1;">
              <div style="font-weight:600;color:var(--caramel);font-size:.85rem;"><?= clean($o['order_number']) ?></div>
              <div style="font-size:.75rem;color:#aaa;">
                <?= $o['item_count'] ?> item<?= $o['item_count'] > 1 ? 's' : '' ?> &bull;
                <?= $o['order_type'] === 'dine_in' ? '🪑 Dine in' : '🥡 Takeaway' ?> &bull;
                <?= date('d M Y', strtotime($o['created_at'])) ?>
              </div>
            </div>
            <div style="text-align:right;">
              <div style="font-weight:600;font-size:.875rem;"><?= format_price($o['total_amount']) ?></div>
              <div style="font-size:.72rem;color:#aaa;text-transform:capitalize;"><?= $o['order_status'] ?></div>
            </div>
          </div>
        <?php endforeach; ?>
        <div style="margin-top:.75rem;">
          <a href="track.php" style="font-size:.82rem;color:var(--caramel);text-decoration:none;">
            View all orders <i class="bi bi-arrow-right"></i>
          </a>
        </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function switchTab(name) {
  document.querySelectorAll('.tab-btn').forEach((b,i) => {
    b.classList.toggle('active', ['profile','password','points','orders'][i] === name);
  });
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.getElementById('tab-' + name).classList.add('active');
}

function togglePw(id, iconId) {
  const el = document.getElementById(id);
  const ic = document.getElementById(iconId);
  el.type = el.type === 'password' ? 'text' : 'password';
  ic.className = el.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}

function checkPwStrength(val) {
  const fill = document.getElementById('sw-fill');
  const hasLen   = val.length >= 8;
  const hasUpper = /[A-Z]/.test(val);
  const hasNum   = /[0-9]/.test(val);
  const hasSpec  = /[^a-zA-Z0-9]/.test(val);
  const score    = [hasLen, hasUpper, hasNum, hasSpec].filter(Boolean).length;
  const cfg = [
    {w:'0%',bg:'#eee'},{w:'25%',bg:'#E24B4A'},{w:'50%',bg:'#E8A045'},
    {w:'75%',bg:'#3B6DD8'},{w:'100%',bg:'#1D9E75'}
  ][score];
  fill.style.width = cfg.w;
  fill.style.background = cfg.bg;
  setReq('req-len',  hasLen);
  setReq('req-up',   hasUpper);
  setReq('req-num',  hasNum);
}

function setReq(id, met) {
  const el = document.getElementById(id);
  el.classList.toggle('met', met);
  el.querySelector('i').className = met ? 'bi bi-check-circle-fill' : 'bi bi-circle';
}

function checkMatch() {
  const pw  = document.getElementById('new_pw').value;
  const cpw = document.querySelector('[name="confirm_password"]').value;
  const msg = document.getElementById('match-msg');
  if (!cpw) { msg.textContent = ''; return; }
  msg.textContent = pw === cpw ? '✓ Passwords match' : '✗ Passwords do not match';
  msg.style.color = pw === cpw ? '#1D9E75' : '#E24B4A';
}

// Auto-open tab from URL hash
const tabMap = {'#profile':'profile','#password':'password','#points':'points','#orders':'orders'};
if (tabMap[location.hash]) switchTab(tabMap[location.hash]);
</script>
</body>
</html>