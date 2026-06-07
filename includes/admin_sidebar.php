<?php
// ============================================================
//  Richiamo Coffee — Shared Admin Sidebar
//  Usage: require_once __DIR__ . '/../includes/admin_sidebar.php';
//  Set $active_page before including, e.g. $active_page = 'orders';
// ============================================================
$active_page = $active_page ?? '';

// Count pending orders for badge
$_pending = 0;
try {
    $_db = get_db();
    $_pending = (int) $_db->query("SELECT COUNT(*) FROM orders WHERE order_status = 'pending'")->fetchColumn();
} catch(Exception $e) {}
?>
<aside class="rc-sidebar">
  <div class="rc-sidebar-brand">
    <a href="<?= APP_URL ?>/admin/dashboard.php" style="text-decoration:none;">
      <h1>☕ <?= APP_NAME ?></h1>
      <p>Admin Panel</p>
    </a>
  </div>

  <nav class="rc-sidebar-nav">
    <div class="rc-nav-label">Main</div>

    <a href="<?= APP_URL ?>/admin/dashboard.php"
       class="rc-nav-link <?= $active_page==='dashboard'?'active':'' ?>">
      <i class="bi bi-grid-1x2"></i> Dashboard
    </a>

    <a href="<?= APP_URL ?>/admin/orders.php"
       class="rc-nav-link <?= $active_page==='orders'?'active':'' ?>">
      <i class="bi bi-receipt"></i> Orders
      <?php if ($_pending > 0): ?>
        <span class="rc-nav-badge"><?= $_pending ?></span>
      <?php endif; ?>
    </a>

    <a href="<?= APP_URL ?>/admin/menu.php"
       class="rc-nav-link <?= $active_page==='menu'?'active':'' ?>">
      <i class="bi bi-journal-text"></i> Menu items
    </a>

    <a href="<?= APP_URL ?>/admin/categories.php"
       class="rc-nav-link <?= $active_page==='categories'?'active':'' ?>">
      <i class="bi bi-tags"></i> Categories
    </a>

    <a href="<?= APP_URL ?>/admin/notifications.php"
       class="rc-nav-link <?= $active_page==='notifications'?'active':'' ?>">
      <i class="bi bi-bell"></i> Notifications
      <?php if ($_pending > 0): ?>
        <span class="rc-nav-badge"><?= $_pending ?></span>
      <?php endif; ?>
    </a>

    <div class="rc-nav-label">Reports</div>

    <a href="<?= APP_URL ?>/admin/reports.php"
       class="rc-nav-link <?= $active_page==='reports'?'active':'' ?>">
      <i class="bi bi-bar-chart"></i> Sales report
    </a>

    <a href="<?= APP_URL ?>/admin/customers.php"
       class="rc-nav-link <?= $active_page==='customers'?'active':'' ?>">
      <i class="bi bi-people"></i> Customers
    </a>

    <?php if (isset($current_user) && $current_user['role'] === ROLE_DEVELOPER): ?>
    <div class="rc-nav-label">Developer</div>

    <a href="<?= APP_URL ?>/admin/users.php"
       class="rc-nav-link <?= $active_page==='users'?'active':'' ?>">
      <i class="bi bi-shield-lock"></i> User management
    </a>

    <a href="<?= APP_URL ?>/developer/logs.php"
       class="rc-nav-link <?= $active_page==='logs'?'active':'' ?>">
      <i class="bi bi-terminal"></i> System logs
    </a>
    <?php endif; ?>
  </nav>

  <div class="rc-sidebar-footer">
    <div class="rc-user-chip">
      <div class="rc-user-avatar">
        <?= strtoupper(substr($current_user['name'] ?? 'A', 0, 1)) ?>
      </div>
      <div style="flex:1;min-width:0;">
        <div class="rc-user-name"><?= clean($current_user['name'] ?? 'Admin') ?></div>
        <div class="rc-user-role"><?= $current_user['role'] ?? '' ?></div>
      </div>
      <a href="<?= APP_URL ?>/auth/logout.php"
         title="Logout"
         style="color:rgba(255,255,255,.4);font-size:.85rem;text-decoration:none;transition:color .15s;"
         onmouseover="this.style.color='#f87171'"
         onmouseout="this.style.color='rgba(255,255,255,.4)'">
        <i class="bi bi-box-arrow-right"></i>
      </a>
    </div>
  </div>
</aside>

<style>
  .rc-sidebar{width:240px;min-height:100vh;background:#1C0A00;position:fixed;top:0;left:0;z-index:200;display:flex;flex-direction:column;}
  .rc-sidebar-brand{padding:1.5rem 1.25rem 1rem;border-bottom:1px solid rgba(255,255,255,.08);}
  .rc-sidebar-brand h1{font-family:'Playfair Display',serif;color:#F5E6C8;font-size:1.15rem;margin:0;}
  .rc-sidebar-brand p{color:#D4A96A;font-size:.72rem;margin:.2rem 0 0;}
  .rc-sidebar-nav{flex:1;padding:1rem 0;overflow-y:auto;}
  .rc-nav-label{font-size:.65rem;letter-spacing:1px;text-transform:uppercase;color:rgba(255,255,255,.3);padding:.75rem 1.25rem .25rem;}
  .rc-nav-link{display:flex;align-items:center;gap:.65rem;padding:.6rem 1.25rem;color:rgba(255,255,255,.65);text-decoration:none;font-size:.875rem;border-left:3px solid transparent;transition:all .15s;position:relative;}
  .rc-nav-link:hover{color:#F5E6C8;background:rgba(255,255,255,.06);}
  .rc-nav-link.active{color:#F5E6C8;background:rgba(198,134,66,.15);border-left-color:#C68642;}
  .rc-nav-link i{font-size:1rem;min-width:18px;}
  .rc-nav-badge{margin-left:auto;background:#C68642;color:#1C0A00;border-radius:10px;font-size:.65rem;font-weight:700;padding:.1rem .5rem;min-width:18px;text-align:center;}
  .rc-sidebar-footer{padding:1rem 1.25rem;border-top:1px solid rgba(255,255,255,.08);}
  .rc-user-chip{display:flex;align-items:center;gap:.6rem;}
  .rc-user-avatar{width:32px;height:32px;border-radius:50%;background:#C68642;display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:600;color:#1C0A00;flex-shrink:0;}
  .rc-user-name{color:#F5E6C8;font-size:.8rem;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .rc-user-role{color:#D4A96A;font-size:.7rem;text-transform:capitalize;}
</style>
