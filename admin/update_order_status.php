<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
$allowed = [ROLE_ADMIN, ROLE_DEVELOPER];
require_once __DIR__ . '/../auth/auth_check.php';
verify_csrf();

$order_id = (int) post('order_id');
$status   = post('status');
$allowed_statuses = ['pending','preparing','ready','completed','cancelled'];

if ($order_id && in_array($status, $allowed_statuses)) {
    $db = get_db();
    $db->prepare("UPDATE orders SET order_status = ? WHERE id = ?")
       ->execute([$status, $order_id]);
}

redirect_with_message(APP_URL . '/admin/orders.php', 'Order status updated.', 'success');
