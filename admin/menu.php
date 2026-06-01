<?php
// ============================================================
//  Richiamo Coffee — Admin Menu Management
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
$allowed = [ROLE_ADMIN, ROLE_DEVELOPER];
require_once __DIR__ . '/../auth/auth_check.php';

$db    = get_db();
$flash = get_flash();

// ── Handle DELETE ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'delete') {
    verify_csrf();
    $id = (int) post('item_id');
    $used = $db->prepare("SELECT COUNT(*) FROM order_items WHERE menu_item_id = ?");
    $used->execute([$id]);
    if ($used->fetchColumn() > 0) {
        $db->prepare("UPDATE menu_items SET is_available = 0 WHERE id = ?")->execute([$id]);
        redirect_with_message(APP_URL . '/admin/menu.php', 'Item hidden from menu (has existing orders).', 'warning');
    } else {
        $db->prepare("DELETE FROM menu_items WHERE id = ?")->execute([$id]);
        redirect_with_message(APP_URL . '/admin/menu.php', 'Menu item deleted successfully.', 'success');
    }
}

// ── Handle TOGGLE availability ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'toggle') {
    verify_csrf();
    $id = (int) post('item_id');
    $db->prepare("UPDATE menu_items SET is_available = NOT is_available WHERE id = ?")->execute([$id]);
    redirect_with_message(APP_URL . '/admin/menu.php', 'Item availability updated.', 'success');
}

// ── Handle TOGGLE featured ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'toggle_featured') {
    verify_csrf();
    $id = (int) post('item_id');
    $db->prepare("UPDATE menu_items SET is_featured = NOT is_featured WHERE id = ?")->execute([$id]);
    redirect_with_message(APP_URL . '/admin/menu.php', 'Featured status updated.', 'success');
}

// ── Fetch data ────────────────────────────────────────────────
$categories = $db->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order")->fetchAll();
$filter_cat = get_param('cat', 'all');
$search     = get_param('q', '');
$where  = '1=1';
$params = [];
if ($filter_cat !== 'all') { $where .= ' AND c.id = ?'; $params[] = (int) $filter_cat; }
if ($search !== '')         { $where .= ' AND m.name LIKE ?'; $params[] = '%' . $search . '%'; }

$stmt = $db->prepare("
    SELECT m.*, c.name AS category_name
    FROM menu_items m
    JOIN categories c ON c.id = m.category_id
    WHERE $where
    ORDER BY c.sort_order, m.sort_order, m.name
");
$stmt->execute($params);
$items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Menu Management — <?= APP_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
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
    .nav-link-item i { font-size:1rem;min-width:18px; }
    .sidebar-footer { padding:1rem 1.25rem;border-top:1px solid rgba(255,255,255,.08); }
    .user-chip { display:flex;align-items:center;gap:.6rem; }
    .user-avatar { width:32px;height:32px;border-radius:50%;background:var(--caramel);display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:600;color:var(--espresso);flex-shrink:0; }
    .user-name { color:var(--cream);font-size:.8rem;font-weight:500; }
    .user-role { color:var(--latte);font-size:.7rem;text-transform:capitalize; }
    .main { margin-left:240px;min-height:100vh; }
    .topbar { background:#fff;border-bottom:1px solid #ede8df;padding:.9rem 1.5rem;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100; }
    .topbar-title { font-family:'Playfair Display',serif;font-size:1.2rem;color:var(--espresso); }
    .content { padding:1.5rem; }
    .toolbar { display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;margin-bottom:1.25rem; }
    .search-box { display:flex;align-items:center;gap:.5rem;background:#fff;border:1.5px solid #ddd;border-radius:.65rem;padding:.45rem .85rem;flex:1;min-width:200px;max-width:320px; }
    .search-box input { border:none;outline:none;font-family:'DM Sans',sans-serif;font-size:.875rem;color:var(--espresso);width:100%;background:transparent; }
    .search-box i { color:#aaa; }
    .filter-select { border:1.5px solid #ddd;border-radius:.65rem;padding:.45rem .85rem;font-family:'DM Sans',sans-serif;font-size:.875rem;color:var(--espresso);background:#fff;cursor:pointer; }
    .btn-add-item { background:var(--espresso);color:var(--cream);border:none;border-radius:.65rem;padding:.5rem 1rem;font-size:.875rem;font-weight:500;cursor:pointer;display:flex;align-items:center;gap:.4rem;text-decoration:none;white-space:nowrap; }
    .btn-add-item:hover { background:var(--roast);color:var(--cream); }
    .card-panel { background:#fff;border-radius:1rem;border:1px solid #ede8df;overflow:hidden; }
    .table-menu { margin:0;width:100%;border-collapse:collapse; }
    .table-menu th { font-size:.72rem;text-transform:uppercase;letter-spacing:.5px;color:#aaa;font-weight:500;padding:.65rem 1rem;background:#FAFAF8;border-bottom:1px solid #f0ebe2;white-space:nowrap; }
    .table-menu td { padding:.8rem 1rem;vertical-align:middle;font-size:.875rem;border-bottom:1px solid #f8f5f0; }
    .table-menu tr:last-child td { border-bottom:none; }
    .table-menu tr:hover td { background:#FAFAF8; }
    .item-icon-cell { width:40px;height:40px;border-radius:.6rem;background:var(--foam);display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0; }
    .item-name { font-weight:500;color:var(--espresso); }
    .item-desc { font-size:.75rem;color:#aaa;margin-top:.1rem;max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
    .badge-cat { background:#EEF4FF;color:#3B6DD8;padding:.2rem .55rem;border-radius:2rem;font-size:.68rem;font-weight:500; }
    .btn-icon { width:30px;height:30px;border-radius:.4rem;border:1px solid #e5e5e5;background:#fff;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;transition:all .15s;text-decoration:none; }
    .btn-icon:hover { border-color:var(--caramel);color:var(--caramel); }
    .btn-icon.danger:hover { border-color:#E24B4A;color:#E24B4A; }
    .toggle-switch { position:relative;display:inline-block;width:36px;height:20px; }
    .toggle-switch input { opacity:0;width:0;height:0; }
    .toggle-slider { position:absolute;cursor:pointer;inset:0;background:#ddd;border-radius:20px;transition:.2s; }
    .toggle-slider:before { content:'';position:absolute;width:14px;height:14px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.2s; }
    input:checked + .toggle-slider { background:var(--caramel); }
    input:checked + .toggle-slider:before { transform:translateX(16px); }
    .flash { border-radius:.75rem;padding:.65rem 1rem;font-size:.875rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem; }
    .empty-state { text-align:center;padding:3rem;color:#aaa; }
    .empty-state i { font-size:2.5rem;display:block;margin-bottom:.75rem; }
    .modal-header { background:var(--espresso);color:var(--cream); }
    .modal-title { font-family:'Playfair Display',serif; }
    .modal-header .btn-close { filter:invert(1); }
    .form-label-rc { font-size:.75rem;font-weight:500;letter-spacing:.5px;text-transform:uppercase;color:var(--roast);margin-bottom:.4rem;display:block; }
    .form-control-rc { width:100%;border:1.5px solid #ddd;border-radius:.65rem;padding:.6rem .9rem;font-family:'DM Sans',sans-serif;font-size:.875rem;color:var(--espresso);transition:border-color .2s;background:#fff; }
    .form-control-rc:focus { outline:none;border-color:var(--caramel);box-shadow:0 0 0 3px rgba(198,134,66,.12); }
    .btn-save { background:var(--espresso);color:var(--cream);border:none;border-radius:.65rem;padding:.65rem 1.5rem;font-size:.875rem;font-weight:500;cursor:pointer;transition:background .2s; }
    .btn-save:hover { background:var(--roast); }
    .btn-cancel-modal { background:transparent;color:#888;border:1.5px solid #ddd;border-radius:.65rem;padding:.65rem 1.25rem;font-size:.875rem;cursor:pointer; }
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
    <a href="orders.php" class="nav-link-item"><i class="bi bi-receipt"></i> Orders</a>
    <a href="menu.php" class="nav-link-item active"><i class="bi bi-journal-text"></i> Menu items</a>
    <a href="categories.php" class="nav-link-item"><i class="bi bi-tags"></i> Categories</a>
    <div class="nav-label">Reports</div>
    <a href="reports.php" class="nav-link-item"><i class="bi bi-bar-chart"></i> Sales report</a>
    <a href="customers.php" class="nav-link-item"><i class="bi bi-people"></i> Customers</a>
  </nav>
  <div class="sidebar-footer">
    <div class="user-chip">
      <div class="user-avatar"><?= strtoupper(substr($current_user['name'], 0, 1)) ?></div>
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
    <div class="topbar-title">Menu management</div>
    <span style="font-size:.8rem;color:#888;"><?= count($items) ?> item<?= count($items) !== 1 ? 's' : '' ?></span>
  </div>

  <div class="content">
    <?php if ($flash): ?>
      <div class="flash alert alert-<?= $flash['type'] === 'success' ? 'success' : 'warning' ?>">
        <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
        <?= clean($flash['message']) ?>
      </div>
    <?php endif; ?>

    <div class="toolbar">
      <form method="GET" action="" style="display:contents;">
        <div class="search-box">
          <i class="bi bi-search"></i>
          <input type="text" name="q" placeholder="Search items..." value="<?= clean($search) ?>">
        </div>
        <select name="cat" class="filter-select" onchange="this.form.submit()">
          <option value="all" <?= $filter_cat === 'all' ? 'selected' : '' ?>>All categories</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>" <?= $filter_cat == $cat['id'] ? 'selected' : '' ?>>
              <?= clean($cat['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if ($search): ?>
          <a href="menu.php" style="font-size:.8rem;color:#aaa;text-decoration:none;">✕ Clear</a>
        <?php endif; ?>
      </form>
      <div style="margin-left:auto;">
        <a href="#" class="btn-add-item" onclick="openAddModal(); return false;">
          <i class="bi bi-plus-lg"></i> Add item
        </a>
      </div>
    </div>

    <div class="card-panel">
      <?php if (empty($items)): ?>
        <div class="empty-state">
          <i class="bi bi-journal-x"></i>
          <p>No menu items found.</p>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table-menu">
            <thead>
              <tr>
                <th></th><th>Item</th><th>Category</th>
                <th>Price</th><th>Available</th><th>Featured</th>
                <th style="text-align:right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($items as $item):
                $cat_icons = ['Espresso'=>'☕','Cold Brew'=>'🧊','Seasonal'=>'🌿','Non-Coffee'=>'🍵','Food'=>'🥐'];
                $icon = $cat_icons[$item['category_name']] ?? '☕';
              ?>
              <tr>
                <td><div class="item-icon-cell"><?= $icon ?></div></td>
                <td>
                  <div class="item-name"><?= clean($item['name']) ?></div>
                  <div class="item-desc"><?= clean($item['description'] ?? '—') ?></div>
                </td>
                <td><span class="badge-cat"><?= clean($item['category_name']) ?></span></td>
                <td style="font-weight:500;font-family:'Playfair Display',serif;"><?= format_price($item['price']) ?></td>
                <td>
                  <form method="POST" action="" style="display:inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                    <label class="toggle-switch">
                      <input type="checkbox" <?= $item['is_available'] ? 'checked' : '' ?> onchange="this.form.submit()">
                      <span class="toggle-slider"></span>
                    </label>
                  </form>
                </td>
                <td>
                  <form method="POST" action="" style="display:inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle_featured">
                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                    <label class="toggle-switch">
                      <input type="checkbox" <?= $item['is_featured'] ? 'checked' : '' ?> onchange="this.form.submit()">
                      <span class="toggle-slider"></span>
                    </label>
                  </form>
                </td>
                <td>
                  <div style="display:flex;gap:.4rem;justify-content:flex-end;">
                    <button class="btn-icon" title="Edit"
                      onclick="openEditModal(<?= $item['id'] ?>,'<?= addslashes($item['name']) ?>','<?= addslashes($item['description'] ?? '') ?>',<?= $item['price'] ?>,<?= $item['category_id'] ?>,<?= $item['sort_order'] ?>)">
                      <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn-icon danger" title="Delete"
                      onclick="confirmDelete(<?= $item['id'] ?>,'<?= addslashes($item['name']) ?>')">
                      <i class="bi bi-trash3"></i>
                    </button>
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
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="itemModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:1rem;overflow:hidden;border:none;">
      <div class="modal-header">
        <h5 class="modal-title" id="modal-title">Add menu item</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="menu_save.php" id="item-form">
        <?= csrf_field() ?>
        <input type="hidden" name="item_id" id="modal-item-id" value="">
        <div class="modal-body" style="padding:1.5rem;">
          <div class="mb-3">
            <label class="form-label-rc">Item name *</label>
            <input type="text" class="form-control-rc" id="modal-name" name="name" placeholder="e.g. Caramel Latte" required>
          </div>
          <div class="mb-3">
            <label class="form-label-rc">Description</label>
            <textarea class="form-control-rc" id="modal-desc" name="description" rows="2" placeholder="Brief description"></textarea>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label-rc">Price (RM) *</label>
              <input type="number" class="form-control-rc" id="modal-price" name="price" step="0.50" min="0.50" placeholder="0.00" required>
            </div>
            <div class="col-6">
              <label class="form-label-rc">Category *</label>
              <select class="form-control-rc" id="modal-cat" name="category_id" required>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?= $cat['id'] ?>"><?= clean($cat['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="mb-1">
            <label class="form-label-rc">Sort order</label>
            <input type="number" class="form-control-rc" id="modal-sort" name="sort_order" value="0" min="0">
            <small style="color:#aaa;font-size:.72rem;">Lower = shown first within category</small>
          </div>
        </div>
        <div class="modal-footer" style="border-top:1px solid #f0ebe2;padding:1rem 1.5rem;gap:.5rem;">
          <button type="button" class="btn-cancel-modal" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-save" id="modal-save-btn">Save item</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content" style="border-radius:1rem;overflow:hidden;border:none;">
      <div class="modal-header" style="background:#FEF0F0;color:#A32D2D;border-bottom:1px solid #fcc;">
        <h5 class="modal-title" style="font-family:'DM Sans',sans-serif;font-size:.95rem;">Delete item?</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="padding:1.25rem;">
        <p style="font-size:.875rem;color:#555;margin:0;">
          Are you sure you want to delete <strong id="delete-item-name"></strong>? This cannot be undone.
        </p>
      </div>
      <div class="modal-footer" style="border-top:1px solid #f0ebe2;padding:1rem 1.25rem;gap:.5rem;">
        <button type="button" class="btn-cancel-modal" data-bs-dismiss="modal">Cancel</button>
        <form method="POST" action="" style="display:inline;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="item_id" id="delete-item-id">
          <button type="submit" style="background:#E24B4A;color:#fff;border:none;border-radius:.65rem;padding:.6rem 1.25rem;font-size:.875rem;cursor:pointer;">Delete</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const itemModal   = new bootstrap.Modal(document.getElementById('itemModal'));
const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));

function openAddModal() {
  document.getElementById('modal-title').textContent    = 'Add menu item';
  document.getElementById('modal-save-btn').textContent = 'Add item';
  document.getElementById('modal-item-id').value = '';
  document.getElementById('item-form').reset();
  itemModal.show();
}

function openEditModal(id, name, desc, price, catId, sortOrder) {
  document.getElementById('modal-title').textContent    = 'Edit menu item';
  document.getElementById('modal-save-btn').textContent = 'Save changes';
  document.getElementById('modal-item-id').value = id;
  document.getElementById('modal-name').value    = name;
  document.getElementById('modal-desc').value    = desc;
  document.getElementById('modal-price').value   = price;
  document.getElementById('modal-cat').value     = catId;
  document.getElementById('modal-sort').value    = sortOrder;
  itemModal.show();
}

function confirmDelete(id, name) {
  document.getElementById('delete-item-id').value      = id;
  document.getElementById('delete-item-name').textContent = name;
  deleteModal.show();
}

<?php if (get_param('action') === 'add'): ?>openAddModal();<?php endif; ?>
</script>
</body>
</html>
