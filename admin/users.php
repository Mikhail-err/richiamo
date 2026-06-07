<?php
// ============================================================
//  Richiamo Coffee — Admin User Management
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
$allowed = [ROLE_DEVELOPER]; // Only developer can manage roles
require_once __DIR__ . '/../auth/auth_check.php';

$db    = get_db();
$flash = get_flash();
$error = '';

// ── Handle POST actions ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action  = post('action');
    $user_id = (int) post('user_id');

    // Prevent modifying yourself
    $self = ($user_id === $current_user['id']);

    if ($action === 'toggle_active' && !$self) {
        $db->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?")->execute([$user_id]);
        redirect_with_message(APP_URL . '/admin/users.php', 'User status updated.', 'success');
    }

    if ($action === 'update_role' && !$self) {
        $new_role = post('role');
        if (in_array($new_role, ['customer', 'admin', 'developer'])) {
            $db->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$new_role, $user_id]);
            // Revoke all active sessions for that user so they re-login with new role
            $db->prepare("UPDATE user_sessions SET revoked = 1 WHERE user_id = ? AND revoked = 0")->execute([$user_id]);
            redirect_with_message(APP_URL . '/admin/users.php', 'Role updated. User sessions revoked — they must log in again.', 'success');
        }
    }

    if ($action === 'add_staff') {
        $name     = trim(post('name'));
        $email    = trim(post('email'));
        $role     = post('role');
        $password = post('password');

        if (strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            $error = 'Please fill all fields correctly. Password must be at least 8 characters.';
        } elseif (!in_array($role, ['admin', 'developer'])) {
            $error = 'Invalid role selected.';
        } else {
            $exists = $db->prepare("SELECT id FROM users WHERE email = ?");
            $exists->execute([$email]);
            if ($exists->fetch()) {
                $error = 'An account with this email already exists.';
            } else {
                $db->prepare("INSERT INTO users (name, email, password, role, is_active) VALUES (?,?,?,?,1)")
                   ->execute([$name, $email, hash_password($password), $role]);
                redirect_with_message(APP_URL . '/admin/users.php', "Staff account created for $name.", 'success');
            }
        }
    }

    if ($action === 'delete' && !$self) {
        // Only delete if user has no orders
        $has_orders = $db->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
        $has_orders->execute([$user_id]);
        if ($has_orders->fetchColumn() > 0) {
            redirect_with_message(APP_URL . '/admin/users.php', 'Cannot delete — user has order history. Deactivate instead.', 'warning');
        } else {
            $db->prepare("DELETE FROM user_sessions WHERE user_id = ?")->execute([$user_id]);
            $db->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
            redirect_with_message(APP_URL . '/admin/users.php', 'User deleted.', 'success');
        }
    }
}

// ── Fetch all users ───────────────────────────────────────────
$search  = get_param('q', '');
$role_f  = get_param('role', 'all');
$where   = '1=1';
$params  = [];

if ($search) {
    $where .= ' AND (name LIKE ? OR email LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($role_f !== 'all') {
    $where .= ' AND role = ?';
    $params[] = $role_f;
}

$stmt = $db->prepare("
    SELECT u.*,
           COUNT(DISTINCT o.id) AS order_count
    FROM users u
    LEFT JOIN orders o ON o.user_id = u.id
    WHERE $where
    GROUP BY u.id
    ORDER BY FIELD(u.role,'developer','admin','customer'), u.created_at DESC
");
$stmt->execute($params);
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>User Management — <?= APP_NAME ?></title>
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
    .toolbar{display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;margin-bottom:1.25rem;}
    .search-box{display:flex;align-items:center;gap:.5rem;background:#fff;border:1.5px solid #ddd;border-radius:.65rem;padding:.45rem .85rem;flex:1;min-width:200px;max-width:300px;}
    .search-box input{border:none;outline:none;font-family:'DM Sans',sans-serif;font-size:.875rem;color:var(--espresso);width:100%;background:transparent;}
    .filter-select{border:1.5px solid #ddd;border-radius:.65rem;padding:.45rem .85rem;font-family:'DM Sans',sans-serif;font-size:.875rem;color:var(--espresso);background:#fff;cursor:pointer;}
    .btn-add{background:var(--espresso);color:var(--cream);border:none;border-radius:.65rem;padding:.5rem 1rem;font-size:.875rem;font-weight:500;cursor:pointer;display:flex;align-items:center;gap:.4rem;margin-left:auto;}
    .btn-add:hover{background:var(--roast);}
    .card-panel{background:#fff;border-radius:1rem;border:1px solid #ede8df;overflow:hidden;}
    .table-users{margin:0;width:100%;border-collapse:collapse;}
    .table-users th{font-size:.7rem;text-transform:uppercase;letter-spacing:.5px;color:#aaa;font-weight:500;padding:.65rem 1rem;background:#FAFAF8;border-bottom:1px solid #f0ebe2;white-space:nowrap;}
    .table-users td{padding:.8rem 1rem;vertical-align:middle;font-size:.875rem;border-bottom:1px solid #f8f5f0;}
    .table-users tr:last-child td{border-bottom:none;}
    .table-users tr:hover td{background:#FAFAF8;}
    .u-avatar{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.85rem;font-weight:600;flex-shrink:0;}
    .role-developer{background:#F3F0FF;color:#7C3AED;}
    .role-admin{background:#FEF3E2;color:#B07A1A;}
    .role-customer{background:#EEF4FF;color:#3B6DD8;}
    .badge-role{padding:.25rem .65rem;border-radius:2rem;font-size:.7rem;font-weight:600;}
    .badge-active{background:#EDFAF4;color:#0F6E56;padding:.2rem .55rem;border-radius:2rem;font-size:.7rem;font-weight:500;}
    .badge-inactive{background:#F0F0F0;color:#888;padding:.2rem .55rem;border-radius:2rem;font-size:.7rem;font-weight:500;}
    .toggle-switch{position:relative;display:inline-block;width:36px;height:20px;}
    .toggle-switch input{opacity:0;width:0;height:0;}
    .toggle-slider{position:absolute;cursor:pointer;inset:0;background:#ddd;border-radius:20px;transition:.2s;}
    .toggle-slider:before{content:'';position:absolute;width:14px;height:14px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.2s;}
    input:checked + .toggle-slider{background:var(--caramel);}
    input:checked + .toggle-slider:before{transform:translateX(16px);}
    .btn-icon{width:30px;height:30px;border-radius:.4rem;border:1px solid #e5e5e5;background:#fff;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;transition:all .15s;}
    .btn-icon:hover{border-color:var(--caramel);color:var(--caramel);}
    .btn-icon.danger:hover{border-color:#E24B4A;color:#E24B4A;}
    .you-tag{background:var(--caramel);color:var(--espresso);border-radius:2rem;font-size:.62rem;font-weight:700;padding:.1rem .45rem;margin-left:.35rem;}
    .flash{border-radius:.75rem;padding:.65rem 1rem;font-size:.875rem;margin-bottom:1rem;}
    .modal-header{background:var(--espresso);color:var(--cream);}
    .modal-header .btn-close{filter:invert(1);}
    .modal-title{font-family:'Playfair Display',serif;}
    .form-label-rc{font-size:.73rem;font-weight:500;letter-spacing:.5px;text-transform:uppercase;color:var(--roast);margin-bottom:.4rem;display:block;}
    .form-control-rc{width:100%;border:1.5px solid #ddd;border-radius:.65rem;padding:.6rem .9rem;font-family:'DM Sans',sans-serif;font-size:.875rem;color:var(--espresso);transition:border-color .2s;background:#fff;}
    .form-control-rc:focus{outline:none;border-color:var(--caramel);box-shadow:0 0 0 3px rgba(198,134,66,.12);}
    .btn-save{background:var(--espresso);color:var(--cream);border:none;border-radius:.65rem;padding:.65rem 1.5rem;font-size:.875rem;font-weight:500;cursor:pointer;}
    .btn-cancel-modal{background:transparent;color:#888;border:1.5px solid #ddd;border-radius:.65rem;padding:.65rem 1.25rem;font-size:.875rem;cursor:pointer;}
    .dev-notice{background:#F3F0FF;border:1px solid #d8d0f9;border-radius:.75rem;padding:.75rem 1rem;font-size:.82rem;color:#7C3AED;margin-bottom:1.25rem;display:flex;align-items:center;gap:.5rem;}
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
    <a href="customers.php"  class="nav-link-item"><i class="bi bi-people"></i> Customers</a>
    <div class="nav-label">Developer</div>
    <a href="users.php"      class="nav-link-item active"><i class="bi bi-shield-lock"></i> User management</a>
    <a href="../developer/logs.php" class="nav-link-item"><i class="bi bi-terminal"></i> System logs</a>
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
    <div class="topbar-title">User management</div>
    <span style="font-size:.8rem;color:#888;"><?= count($users) ?> users</span>
  </div>

  <div class="content">

    <div class="dev-notice">
      <i class="bi bi-shield-lock-fill"></i>
      <span><strong>Developer only.</strong> Role changes take effect on next login. Changing a role revokes all active sessions for that user.</span>
    </div>

    <?php if ($flash): ?>
      <div class="flash alert alert-<?= $flash['type'] === 'success' ? 'success' : 'warning' ?>">
        <?= clean($flash['message']) ?>
      </div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="flash alert alert-danger"><?= clean($error) ?></div>
    <?php endif; ?>

    <!-- Toolbar -->
    <div class="toolbar">
      <form method="GET" style="display:contents;">
        <div class="search-box">
          <i class="bi bi-search"></i>
          <input type="text" name="q" placeholder="Search name or email..." value="<?= clean($search) ?>">
        </div>
        <select name="role" class="filter-select" onchange="this.form.submit()">
          <option value="all"       <?= $role_f==='all'       ?'selected':'' ?>>All roles</option>
          <option value="developer" <?= $role_f==='developer' ?'selected':'' ?>>Developer</option>
          <option value="admin"     <?= $role_f==='admin'     ?'selected':'' ?>>Admin</option>
          <option value="customer"  <?= $role_f==='customer'  ?'selected':'' ?>>Customer</option>
        </select>
      </form>
      <button class="btn-add" onclick="openAddModal()">
        <i class="bi bi-plus-lg"></i> Add staff account
      </button>
    </div>

    <!-- Table -->
    <div class="card-panel">
      <div class="table-responsive">
        <table class="table-users">
          <thead>
            <tr>
              <th></th><th>User</th><th>Role</th><th>Orders</th>
              <th>Joined</th><th>Last login</th><th>Active</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u):
              $is_self  = $u['id'] === $current_user['id'];
              $role_cls = 'role-' . $u['role'];
            ?>
            <tr>
              <td>
                <div class="u-avatar <?= $role_cls ?>">
                  <?= strtoupper(substr($u['name'],0,1)) ?>
                </div>
              </td>
              <td>
                <div style="font-weight:500;">
                  <?= clean($u['name']) ?>
                  <?php if ($is_self): ?><span class="you-tag">You</span><?php endif; ?>
                </div>
                <div style="font-size:.75rem;color:#aaa;"><?= clean($u['email']) ?></div>
              </td>
              <td>
                <?php if ($is_self): ?>
                  <span class="badge-role <?= $role_cls ?>"><?= ucfirst($u['role']) ?></span>
                <?php else: ?>
                  <form method="POST" action="" style="display:inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action"  value="update_role">
                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    <select name="role" class="filter-select" style="padding:.25rem .6rem;font-size:.78rem;" onchange="this.form.submit()">
                      <option value="customer"  <?= $u['role']==='customer' ?'selected':'' ?>>Customer</option>
                      <option value="admin"     <?= $u['role']==='admin'    ?'selected':'' ?>>Admin</option>
                      <option value="developer" <?= $u['role']==='developer'?'selected':'' ?>>Developer</option>
                    </select>
                  </form>
                <?php endif; ?>
              </td>
              <td style="font-weight:600;color:var(--caramel);"><?= $u['order_count'] ?></td>
              <td style="font-size:.78rem;color:#aaa;"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
              <td style="font-size:.78rem;color:#aaa;">
                <?= $u['last_login'] ? date('d M Y', strtotime($u['last_login'])) : '—' ?>
              </td>
              <td>
                <?php if ($is_self): ?>
                  <span class="badge-active">Active</span>
                <?php else: ?>
                  <form method="POST" action="" style="display:inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action"  value="toggle_active">
                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    <label class="toggle-switch">
                      <input type="checkbox" <?= $u['is_active']?'checked':'' ?> onchange="this.form.submit()">
                      <span class="toggle-slider"></span>
                    </label>
                  </form>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!$is_self): ?>
                  <form method="POST" action="" style="display:inline;"
                        onsubmit="return confirm('Delete <?= addslashes($u['name']) ?>? This cannot be undone.')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action"  value="delete">
                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    <button type="submit" class="btn-icon danger" title="Delete user">
                      <i class="bi bi-trash3"></i>
                    </button>
                  </form>
                <?php else: ?>
                  <span style="font-size:.75rem;color:#ddd;">—</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Add Staff Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:1rem;overflow:hidden;border:none;">
      <div class="modal-header">
        <h5 class="modal-title">Add staff account</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_staff">
        <div class="modal-body" style="padding:1.5rem;">
          <div class="mb-3">
            <label class="form-label-rc">Full name *</label>
            <input type="text" class="form-control-rc" name="name" placeholder="e.g. Siti Aisyah" required>
          </div>
          <div class="mb-3">
            <label class="form-label-rc">Email address *</label>
            <input type="email" class="form-control-rc" name="email" placeholder="staff@richiamo.my" required>
          </div>
          <div class="mb-3">
            <label class="form-label-rc">Role *</label>
            <select class="form-control-rc" name="role" required>
              <option value="admin">Admin — manage menu, orders, reports</option>
              <option value="developer">Developer — full system access</option>
            </select>
          </div>
          <div class="mb-1">
            <label class="form-label-rc">Password *</label>
            <input type="password" class="form-control-rc" name="password" placeholder="Min. 8 characters" required minlength="8">
            <small style="color:#aaa;font-size:.72rem;">Staff should change this on first login.</small>
          </div>
        </div>
        <div class="modal-footer" style="padding:1rem 1.5rem;gap:.5rem;">
          <button type="button" class="btn-cancel-modal" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-save"><i class="bi bi-person-plus me-1"></i>Create account</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const addModal = new bootstrap.Modal(document.getElementById('addModal'));
function openAddModal() { addModal.show(); }
</script>
</body>
</html>