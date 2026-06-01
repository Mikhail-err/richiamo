<?php
// ============================================================
//  Richiamo Coffee — Menu Item Save Handler (Add / Edit)
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
$allowed = [ROLE_ADMIN, ROLE_DEVELOPER];
require_once __DIR__ . '/../auth/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_message(APP_URL . '/admin/menu.php', '', '');
}

verify_csrf();

$db          = get_db();
$item_id     = (int) post('item_id');       // 0 = new item
$name        = trim(post('name'));
$description = trim(post('description'));
$price       = (float) post('price');
$category_id = (int) post('category_id');
$sort_order  = (int) post('sort_order');

// ── Validate ──────────────────────────────────────────────────
$errors = [];
if (empty($name))        $errors[] = 'Item name is required.';
if ($price <= 0)         $errors[] = 'Price must be greater than 0.';
if ($category_id <= 0)   $errors[] = 'Please select a category.';

// Check category exists
if ($category_id > 0) {
    $cat = $db->prepare("SELECT id FROM categories WHERE id = ? AND is_active = 1");
    $cat->execute([$category_id]);
    if (!$cat->fetch()) $errors[] = 'Invalid category selected.';
}

if (!empty($errors)) {
    redirect_with_message(APP_URL . '/admin/menu.php', implode(' ', $errors), 'danger');
}

// ── Save ──────────────────────────────────────────────────────
if ($item_id === 0) {
    // INSERT new item
    $db->prepare("
        INSERT INTO menu_items (category_id, name, description, price, sort_order, is_available, is_featured)
        VALUES (?, ?, ?, ?, ?, 1, 0)
    ")->execute([$category_id, $name, $description, $price, $sort_order]);

    redirect_with_message(APP_URL . '/admin/menu.php', '"' . $name . '" added to menu successfully.', 'success');
} else {
    // UPDATE existing item
    $check = $db->prepare("SELECT id FROM menu_items WHERE id = ?");
    $check->execute([$item_id]);
    if (!$check->fetch()) {
        redirect_with_message(APP_URL . '/admin/menu.php', 'Item not found.', 'danger');
    }

    $db->prepare("
        UPDATE menu_items
        SET category_id = ?, name = ?, description = ?, price = ?, sort_order = ?, updated_at = NOW()
        WHERE id = ?
    ")->execute([$category_id, $name, $description, $price, $sort_order, $item_id]);

    redirect_with_message(APP_URL . '/admin/menu.php', '"' . $name . '" updated successfully.', 'success');
}
