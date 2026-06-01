<?php
// ============================================================
//  Richiamo Coffee — Admin Categories Management
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
$allowed = [ROLE_ADMIN, ROLE_DEVELOPER];
require_once __DIR__ . '/../auth/auth_check.php';

$db    = get_db();
$flash = get_flash();
$error = '';

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post('action');

    if ($action === 'save') {
        $cat_id     = (int) post('cat_id');
        $name       = trim(post('name'));
        $sort_order = (int) post('sort_order');

        if (empty($name)) {
            $error = 'Category name is required.';
        } else {
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
            if ($cat_id === 0) {
                $db->prepare("INSERT INTO categories (name, slug, sort_order) VALUES (?,?,?)")
                   ->execute([$name, $slug, $sort_order]);
                redirect_with_message(APP_URL . '/admin/categories.php', 'Category added.', 'success');
            } else {
                $db->prepare("UPDATE categories SET name=?, slug=?, sort_order=? WHERE id=?")
                   ->execute([$name, $slug, $sort_order, $cat_id]);
                redirect_with_message(APP_URL . '/admin/categories.php', 'Category updated.', 'success');
            }
        }
    }

    if ($action === 'toggle') {
        $id = (int) post('cat_id');
        $db->prepare("UPDATE categories SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
        redirect_with_message(APP_URL . '/admin/categories.php', 'Category visibility updated.', 'success');
    }

    if ($action === 'delete') {
        $id = (int) post('cat_id');
        $item_count = $db->prepare("SELECT COUNT(*) FROM menu_items WHERE category_id = ?");
        $item_count->execute([$id]);
        if ($item_count->fetchColumn() > 0) {
            redirect_with_message(APP_URL . '/admin/categories.php', 'Cannot delete — category has menu items.', 'warning');
        } else {
            $db->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
            redirect_with_message(APP_URL . '/admin/categories.php', 'Category deleted.', 'success');
        }
    }
}

$categories = $db->query("
    SELECT c.*, COUNT(m.id) AS item_count
    FROM categories c
    LEFT JOIN menu_items m ON m.category_id = c.id
    GROUP BY c.id
    ORDER BY c.sort_order
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Categories — <?= APP_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
  <style>
    :root { --espresso:#1C0A00;--roast:#3B1A08;--caramel:#C68642;--latte:#D4A96A;--cream:#F5E6C8;--foam:#FDF6EC; }
    * { box-sizing:border-box; }
    body { font-family:'DM Sans',sans-serif;background:#F4F1EC;margin:0; }
    .sidebar { width:240px;min-height:100vh;background:var(--espresso);position:fixed;top:0;left:0;z-index:200;display:flex;flex-direction:column; }
    .sidebar-brand { padding:1.5rem 1.25rem 1rem;border-bottom:1px solid rgba(255,255,255,.08); }
    .sidebar-brand h1 { font-family:'Playfair Display',serif;color:var(--cream);font-size:1.15rem;margin:0; }
    .sidebar-brand p { color:var(--latte);font-size:.72rem;margin:.2rem 0 0; }
    .sidebar-nav { flex:1;padding:1rem 0; }
    .nav-label { font-size:.65rem;letter-spacing:1px;text-transform:uppercase;color:rgba(255,255,255,.3);padding:.75rem 1.25rem .25rem; }
    .nav-link-item { display:flex;align-items:center;gap:.65rem;padding:.6rem 1.25rem;color:rgba(255,255,255,.65);text-decoration:none;font-size:.875rem;border-left:3px solid transparent;transition:all .15s; }
    .nav-link-item:hover { color:var(--cream);background:rgba(255,255,255,.06); }
    .nav-link-item.active { color:var(--cream);background:rgba(198,134,66,.15);border-left-color:var(--caramel); }
    .sidebar-footer { padding:1rem 1.25rem;border-top:1px solid rgba(255,255,255,.08); }
    .main { margin-left:240px;min-height:100vh; }
    .topbar { background:#fff;border-bottom:1px solid #ede8df;padding:.9rem 1.5rem;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100; }
    .topbar-title { font-family:'Playfair Display',serif;font-size:1.2rem;color:var(--espresso); }
    .content { padding:1.5rem;max-width:700px; }
    .card-panel { background:#fff;border-radius:1rem;border:1px solid #ede8df;overflow:hidden;margin-bottom:1rem; }
    .card-panel-header { padding:1rem 1.25rem;border-bottom:1px solid #f0ebe2;display:flex;align-items:center;justify-content:space-between; }
    .card-panel-title { font-family:'Playfair Display',serif;font-size:1rem;color:var(--espresso);margin:0; }
    .table-menu { margin:0;width:100%;border-collapse:collapse; }
    .table-menu th { font-size:.72rem;text-transform:uppercase;letter-spacing:.5px;color:#aaa;font-weight:500;padding:.65rem 1rem;background:#FAFAF8;border-bottom:1px solid #f0ebe2; }
    .table-menu td { padding:.75rem 1rem;vertical-align:middle;font-size:.875rem;border-bottom:1px solid #f8f5f0; }
    .table-menu tr:last-child td { border-bottom:none; }
    .btn-icon { width:30px;height:30px;border-radius:.4rem;border:1px solid #e5e5e5;background:#fff;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;transition:all .15s; }
    .btn-icon:hover { border-color:var(--caramel);color:var(--caramel); }
    .btn-icon.danger:hover { border-color:#E24B4A;color:#E24B4A; }
    .toggle-switch { position:relative;display:inline-block;width:36px;height:20px; }
    .toggle-switch input { opacity:0;width:0;height:0; }
    .toggle-slider { position:absolute;cursor:pointer;inset:0;background:#ddd;border-radius:20px;transition:.2s; }
    .toggle-slider:before { content:'';position:absolute;width:14px;height:14px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.2s; }
    input:checked + .toggle-slider { background:var(--caramel); }
    input:checked + .toggle-slider:before { transform:translateX(16px); }
    .btn-add-item { background:var(--espresso);color:var(--cream);border:none;border-radius:.65rem;padding:.45rem .9rem;font-size:.8rem;font-weight:500;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem; }
    .btn-add-item:hover { background:var(--roast); }
    .form-label-rc { font-size:.75rem;font-weight:500;letter-spacing:.5px;text-transform:uppercase;color:var(--roast);margin-bottom:.4rem;display:block; }
    .form-control-rc { width:100%;border:1.5px solid #ddd;border-radius:.65rem;padding:.6rem .9rem;font-family:'DM Sans',sans-serif;font-size:.875rem;color:var(--espresso);transition:border-color .2s;background:#fff; }
    .form-control-rc:focus { outline:none;border-color:var(--caramel);box-shadow:0 0 0 3px rgba(198,134,66,.12); }
    .btn-save { background:var(--espresso);color:var(--cream);border:none;border-radius:.65rem;padding:.6rem 1.25rem;font-size:.875rem;font-weight:500;cursor:pointer; }
    .flash { border-radius:.75rem;padding:.65rem 1rem;font-size:.875rem;margin-bottom:1rem; }
    .modal-header { background:var(--espresso);color:var(--cream); }
    .modal-header .btn-close { filter:invert(1); }
    .modal-title { font-family:'Playfair Display',serif; }
    .btn-cancel-modal { background:transparent;color:#888;border:1.5px solid #ddd;border-radius:.65rem;padding:.6rem 1.25rem;font-size:.875rem;cursor:pointer; }
  </style>
</head>
<body>
<aside class="sidebar">
  <div class="sidebar-brand">
    <h1>☕ <?= APP_NAME ?></h1><p>Admin Panel</p>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-label">Main</div>
    <a href="dashboard.php" class="nav-link-item"><i class="bi bi-grid-1x2"></i> Dashboard</a>
    <a href="orders.php" class="nav-link-item"><i class="bi bi-receipt"></i> Orders</a>
    <a href="menu.php" class="nav-link-item"><i class="bi bi-journal-text"></i> Menu items</a>
    <a href="categories.php" class="nav-link-item active"><i class="bi bi-tags"></i> Categories</a>
    <div class="nav-label">Reports</div>
    <a href="reports.php" class="nav-link-item"><i class="bi bi-bar-chart"></i> Sales report</a>
    <a href="customers.php" class="nav-link-item"><i class="bi bi-people"></i> Customers</a>
  </nav>
  <div class="sidebar-footer">
    <a href="<?= APP_URL ?>/auth/logout.php" style="color:rgba(255,255,255,.4);font-size:.85rem;text-decoration:none;">
      <i class="bi bi-box-arrow-right"></i> Logout
    </a>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <div class="topbar-title">Categories</div>
  </div>
  <div class="content">
    <?php if ($flash): ?>
      <div class="flash alert alert-<?= $flash['type'] === 'success' ? 'success' : 'warning' ?>">
        <?= clean($flash['message']) ?>
      </div>
    <?php endif; ?>

    <div class="card-panel">
      <div class="card-panel-header">
        <h2 class="card-panel-title">All categories</h2>
        <button class="btn-add-item" onclick="openCatModal()">
          <i class="bi bi-plus-lg"></i> Add category
        </button>
      </div>
      <table class="table-menu">
        <thead>
          <tr><th>Name</th><th>Slug</th><th>Items</th><th>Sort</th><th>Active</th><th style="text-align:right;">Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($categories as $cat): ?>
          <tr>
            <td style="font-weight:500;"><?= clean($cat['name']) ?></td>
            <td style="color:#aaa;font-size:.8rem;"><?= clean($cat['slug']) ?></td>
            <td><?= $cat['item_count'] ?></td>
            <td><?= $cat['sort_order'] ?></td>
            <td>
              <form method="POST" action="" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="cat_id" value="<?= $cat['id'] ?>">
                <label class="toggle-switch">
                  <input type="checkbox" <?= $cat['is_active'] ? 'checked' : '' ?> onchange="this.form.submit()">
                  <span class="toggle-slider"></span>
                </label>
              </form>
            </td>
            <td>
              <div style="display:flex;gap:.4rem;justify-content:flex-end;">
                <button class="btn-icon" onclick="openCatModal(<?= $cat['id'] ?>,'<?= addslashes($cat['name']) ?>',<?= $cat['sort_order'] ?>)">
                  <i class="bi bi-pencil"></i>
                </button>
                <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Delete this category?')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="cat_id" value="<?= $cat['id'] ?>">
                  <button type="submit" class="btn-icon danger"><i class="bi bi-trash3"></i></button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Category Modal -->
<div class="modal fade" id="catModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content" style="border-radius:1rem;overflow:hidden;border:none;">
      <div class="modal-header">
        <h5 class="modal-title" id="cat-modal-title">Add category</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="cat_id" id="cat-id" value="0">
        <div class="modal-body" style="padding:1.25rem;">
          <div class="mb-3">
            <label class="form-label-rc">Category name *</label>
            <input type="text" class="form-control-rc" id="cat-name" name="name" placeholder="e.g. Iced Drinks" required>
          </div>
          <div>
            <label class="form-label-rc">Sort order</label>
            <input type="number" class="form-control-rc" id="cat-sort" name="sort_order" value="0" min="0">
          </div>
        </div>
        <div class="modal-footer" style="padding:1rem 1.25rem;gap:.5rem;">
          <button type="button" class="btn-cancel-modal" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-save">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const catModal = new bootstrap.Modal(document.getElementById('catModal'));
function openCatModal(id=0, name='', sort=0) {
  document.getElementById('cat-modal-title').textContent = id ? 'Edit category' : 'Add category';
  document.getElementById('cat-id').value   = id;
  document.getElementById('cat-name').value = name;
  document.getElementById('cat-sort').value = sort;
  catModal.show();
}
</script>
</body>
</html>
