<?php
// ============================================================
//  Richiamo Coffee — Print Receipt
//  Accessible by admin (any order) or customer (own orders only)
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
$allowed = [ROLE_CUSTOMER, ROLE_ADMIN, ROLE_DEVELOPER];
require_once __DIR__ . '/../auth/auth_check.php';

$db       = get_db();
$order_id = (int) get_param('id');

if (!$order_id) {
    http_response_code(400);
    die('Invalid order ID.');
}

// Fetch order — customers can only see their own
$where = 'o.id = ?';
$params = [$order_id];
if ($current_user['role'] === ROLE_CUSTOMER) {
    $where  .= ' AND o.user_id = ?';
    $params[] = $current_user['id'];
}

$stmt = $db->prepare("
    SELECT o.*, u.name AS customer_name, u.email AS customer_email, u.phone
    FROM orders o
    LEFT JOIN users u ON u.id = o.user_id
    WHERE $where LIMIT 1
");
$stmt->execute($params);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    die('Order not found.');
}

// Fetch items
$items_stmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id");
$items_stmt->execute([$order_id]);
$items = $items_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Receipt <?= clean($order['order_number']) ?> — <?= APP_NAME ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
    :root{--espresso:#1C0A00;--caramel:#C68642;--cream:#F5E6C8;}
    *{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'DM Sans',sans-serif;background:#f5f5f5;color:#1a1a1a;padding:2rem 1rem;}

    .receipt{
      max-width:380px;margin:0 auto;
      background:#fff;
      padding:2rem 1.75rem;
      border-radius:1rem;
      box-shadow:0 4px 24px rgba(0,0,0,.1);
    }

    /* Header */
    .receipt-header{text-align:center;padding-bottom:1.25rem;border-bottom:1px dashed #ddd;margin-bottom:1.25rem;}
    .receipt-logo{font-size:2.5rem;margin-bottom:.5rem;}
    .receipt-brand{font-family:'Playfair Display',serif;font-size:1.3rem;color:var(--espresso);}
    .receipt-tagline{font-size:.72rem;color:#aaa;margin-top:.15rem;}
    .receipt-order-num{font-size:.78rem;color:#888;margin-top:.75rem;}
    .receipt-order-num span{font-weight:600;color:var(--espresso);}

    /* Meta */
    .receipt-meta{display:grid;grid-template-columns:1fr 1fr;gap:.35rem .5rem;font-size:.78rem;margin-bottom:1.25rem;}
    .meta-label{color:#aaa;}
    .meta-value{font-weight:500;text-align:right;}

    /* Items */
    .receipt-items{margin-bottom:1.25rem;}
    .item-row{display:flex;align-items:flex-start;gap:.5rem;padding:.45rem 0;border-bottom:1px solid #f5f5f5;}
    .item-row:last-child{border-bottom:none;}
    .item-qty{min-width:24px;font-size:.78rem;color:#aaa;padding-top:.05rem;}
    .item-name{flex:1;font-size:.82rem;font-weight:500;}
    .item-price{font-size:.82rem;font-weight:500;white-space:nowrap;}

    /* Totals */
    .receipt-totals{border-top:1px dashed #ddd;padding-top:1rem;margin-bottom:1.25rem;}
    .total-row{display:flex;justify-content:space-between;font-size:.82rem;color:#666;margin-bottom:.3rem;}
    .total-final{display:flex;justify-content:space-between;font-size:1rem;font-weight:700;color:var(--espresso);padding-top:.5rem;border-top:1.5px solid #1a1a1a;margin-top:.35rem;}

    /* Payment */
    .payment-badge{display:inline-block;background:#EDFAF4;color:#0F6E56;border-radius:2rem;font-size:.72rem;font-weight:600;padding:.25rem .7rem;margin-top:.5rem;}
    .payment-badge.pending{background:#FEF3E2;color:#B07A1A;}
    .payment-badge.failed{background:#FEF0F0;color:#A32D2D;}

    /* Footer */
    .receipt-footer{text-align:center;border-top:1px dashed #ddd;padding-top:1.25rem;font-size:.72rem;color:#aaa;line-height:1.8;}
    .receipt-footer strong{color:var(--espresso);}

    /* Barcode placeholder */
    .barcode{text-align:center;margin:1rem 0;letter-spacing:.25rem;font-size:.65rem;color:#ccc;}

    /* Print button */
    .print-btn{display:flex;gap:.75rem;justify-content:center;margin-top:1.5rem;}
    .btn-print{background:var(--espresso);color:#fff;border:none;border-radius:.65rem;padding:.65rem 1.5rem;font-size:.875rem;font-weight:500;cursor:pointer;display:flex;align-items:center;gap:.4rem;}
    .btn-back{background:transparent;color:var(--espresso);border:1.5px solid #ddd;border-radius:.65rem;padding:.65rem 1.25rem;font-size:.875rem;cursor:pointer;text-decoration:none;display:flex;align-items:center;gap:.4rem;}

    @media print {
      body{background:#fff;padding:0;}
      .receipt{box-shadow:none;border-radius:0;max-width:100%;}
      .print-btn{display:none!important;}
    }
  </style>
</head>
<body>

<div class="receipt">

  <!-- Header -->
  <div class="receipt-header">
    <div class="receipt-logo">☕</div>
    <div class="receipt-brand"><?= APP_NAME ?></div>
    <div class="receipt-tagline">Crafted with care, served with love</div>
    <div class="receipt-order-num">
      Order <span><?= clean($order['order_number']) ?></span>
    </div>
  </div>

  <!-- Meta info -->
  <div class="receipt-meta">
    <span class="meta-label">Date</span>
    <span class="meta-value"><?= date('d M Y', strtotime($order['created_at'])) ?></span>

    <span class="meta-label">Time</span>
    <span class="meta-value"><?= date('h:i A', strtotime($order['created_at'])) ?></span>

    <span class="meta-label">Customer</span>
    <span class="meta-value"><?= clean($order['customer_name'] ?? 'Guest') ?></span>

    <span class="meta-label">Order type</span>
    <span class="meta-value">
      <?= $order['order_type'] === 'dine_in'
        ? 'Dine in — Table ' . clean($order['table_number'])
        : 'Takeaway' ?>
    </span>

    <span class="meta-label">Payment</span>
    <span class="meta-value"><?= ucfirst($order['payment_method']) ?></span>

    <span class="meta-label">Status</span>
    <span class="meta-value"><?= ucfirst($order['payment_status']) ?></span>
  </div>

  <!-- Items -->
  <div class="receipt-items">
    <?php foreach ($items as $item): ?>
      <div class="item-row">
        <span class="item-qty"><?= $item['quantity'] ?>x</span>
        <span class="item-name"><?= clean($item['item_name']) ?></span>
        <span class="item-price"><?= format_price($item['subtotal']) ?></span>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Totals -->
  <div class="receipt-totals">
    <div class="total-row">
      <span>Subtotal</span>
      <span><?= format_price($order['subtotal']) ?></span>
    </div>
    <div class="total-row">
      <span>SST (6%)</span>
      <span><?= format_price($order['sst_amount']) ?></span>
    </div>
    <div class="total-final">
      <span>TOTAL</span>
      <span><?= format_price($order['total_amount']) ?></span>
    </div>
  </div>

  <!-- Payment status badge -->
  <div style="text-align:center;">
    <span class="payment-badge <?= $order['payment_status'] !== 'paid' ? $order['payment_status'] : '' ?>">
      <?= strtoupper($order['payment_status']) ?>
    </span>
  </div>

  <?php if ($order['notes']): ?>
    <div style="margin-top:1rem;background:#f9f9f7;border-radius:.5rem;padding:.65rem .85rem;font-size:.78rem;color:#555;font-style:italic;">
      Note: <?= clean($order['notes']) ?>
    </div>
  <?php endif; ?>

  <!-- Barcode placeholder -->
  <div class="barcode">
    ||||| ||| || ||| ||||| || ||| || ||||| |||
    <div style="margin-top:.25rem;font-size:.65rem;letter-spacing:0;"><?= clean($order['order_number']) ?></div>
  </div>

  <!-- Footer -->
  <div class="receipt-footer">
    <strong>Thank you for visiting <?= APP_NAME ?>!</strong><br>
    Crafted with care, served with love.<br>
    <?= date('Y') ?> © <?= APP_NAME ?><br>
    <span style="font-size:.65rem;">Retain this receipt for your records.</span>
  </div>

</div>

<!-- Print / back buttons -->
<div class="print-btn">
  <?php
    $back_url = $current_user['role'] === ROLE_CUSTOMER
      ? APP_URL . '/customer/track.php'
      : APP_URL . '/admin/order_detail.php?id=' . $order_id;
  ?>
  <a href="<?= $back_url ?>" class="btn-back">
    <i>←</i> Back
  </a>
  <button class="btn-print" onclick="window.print()">
    🖨️ Print receipt
  </button>
</div>

</body>
</html>
