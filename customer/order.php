<?php
// ============================================================
//  Richiamo Coffee — Checkout Page
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
$allowed = [ROLE_CUSTOMER, ROLE_ADMIN, ROLE_DEVELOPER];
require_once __DIR__ . '/../auth/auth_check.php';

$db    = get_db();
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $order_type    = post('order_type');
    $table_number  = post('table_number');
    $customer_name = post('customer_name');
    $payment_method= post('payment_method');
    $notes         = post('notes');
    $items_json    = post('items_json');

    $items = json_decode($items_json, true);

    // Validate
    if (empty($items)) {
        $error = 'Your cart is empty. Please add items before checking out.';
    } elseif (!in_array($order_type, ['dine_in', 'takeaway'])) {
        $error = 'Please select an order type.';
    } elseif ($order_type === 'dine_in' && empty($table_number)) {
        $error = 'Please enter your table number.';
    } elseif ($order_type === 'takeaway' && empty($customer_name)) {
        $error = 'Please enter your name for pickup.';
    } elseif (!in_array($payment_method, ['cash', 'ewallet', 'card'])) {
        $error = 'Please select a payment method.';
    } else {
        // Calculate totals from DB prices (never trust client-side prices)
        $subtotal = 0;
        $validated_items = [];

        foreach ($items as $item) {
            $id  = (int) $item['id'];
            $qty = max(1, (int) $item['qty']);

            $menu_item = $db->prepare("SELECT id, name, price FROM menu_items WHERE id = ? AND is_available = 1");
            $menu_item->execute([$id]);
            $row = $menu_item->fetch();

            if ($row) {
                $line_total = $row['price'] * $qty;
                $subtotal  += $line_total;
                $validated_items[] = [
                    'id'       => $row['id'],
                    'name'     => $row['name'],
                    'price'    => $row['price'],
                    'qty'      => $qty,
                    'subtotal' => $line_total,
                ];
            }
        }

        if (empty($validated_items)) {
            $error = 'No valid items found. Please try again.';
        } else {
            $sst_amount   = calculate_sst($subtotal);
            $total_amount = calculate_total($subtotal);

            // Generate order number: RC-YYYYMMDD-XXXX
            $date_part  = date('Ymd');
            $count_today = $db->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()")->fetchColumn();
            $order_number = 'RC-' . $date_part . '-' . str_pad($count_today + 1, 4, '0', STR_PAD_LEFT);

            // Insert order
            $stmt = $db->prepare("
                INSERT INTO orders
                  (order_number, user_id, order_type, table_number, customer_name,
                   subtotal, sst_amount, total_amount, payment_method, payment_status,
                   order_status, notes)
                VALUES (?,?,?,?,?,?,?,?,?,'pending','pending',?)
            ");
            $stmt->execute([
                $order_number,
                $current_user['id'],
                $order_type,
                $order_type === 'dine_in' ? $table_number : null,
                $order_type === 'takeaway' ? $customer_name : $current_user['name'],
                $subtotal,
                $sst_amount,
                $total_amount,
                $payment_method,
                $notes,
            ]);

            $order_id = $db->lastInsertId();

            // Insert order items
            $item_stmt = $db->prepare("
                INSERT INTO order_items (order_id, menu_item_id, item_name, item_price, quantity, subtotal)
                VALUES (?,?,?,?,?,?)
            ");
            foreach ($validated_items as $vi) {
                $item_stmt->execute([
                    $order_id, $vi['id'], $vi['name'],
                    $vi['price'], $vi['qty'], $vi['subtotal'],
                ]);
            }

            // Award loyalty points (1 point per RM spent, rounded)
            $points_earned = (int) floor($subtotal);
            if ($points_earned > 0) {
                $db->prepare("
                    INSERT INTO loyalty_points (user_id, order_id, points, description)
                    VALUES (?, ?, ?, ?)
                ")->execute([
                    $current_user['id'], $order_id,
                    $points_earned,
                    'Earned from order ' . $order_number,
                ]);
            }

            // Redirect to confirmation
            redirect_with_message(
                APP_URL . '/customer/confirmation.php?order=' . urlencode($order_number),
                'Order placed successfully!',
                'success'
            );
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Checkout — <?= APP_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
    :root {
      --espresso: #1C0A00; --roast: #3B1A08;
      --caramel: #C68642;  --latte: #D4A96A;
      --cream: #F5E6C8;    --foam: #FDF6EC;
    }
    * { box-sizing: border-box; }
    body { font-family: 'DM Sans', sans-serif; background: #F4F1EC; color: var(--espresso); min-height: 100vh; }

    /* Navbar */
    .navbar-rc { background: var(--espresso); padding: .9rem 0; border-bottom: 2px solid var(--caramel); }
    .navbar-brand-text { font-family: 'Playfair Display', serif; color: var(--cream) !important; font-size: 1.3rem; text-decoration: none; }
    .btn-back { color: var(--latte); font-size: .85rem; text-decoration: none; display: flex; align-items: center; gap: .4rem; transition: color .15s; }
    .btn-back:hover { color: var(--cream); }

    /* Layout */
    .checkout-wrap { max-width: 960px; margin: 2rem auto; padding: 0 1rem 3rem; }
    .page-title { font-family: 'Playfair Display', serif; font-size: 1.6rem; color: var(--espresso); margin-bottom: 1.5rem; }

    /* Form card */
    .form-card { background: #fff; border-radius: 1rem; border: 1px solid #ede8df; padding: 1.5rem; margin-bottom: 1rem; }
    .form-card-title {
      font-family: 'Playfair Display', serif; font-size: 1rem;
      color: var(--espresso); margin-bottom: 1.25rem;
      display: flex; align-items: center; gap: .5rem;
    }
    .form-card-title i { color: var(--caramel); }

    /* Order type toggle */
    .order-type-wrap { display: flex; gap: .75rem; }
    .order-type-btn {
      flex: 1; border: 2px solid #e0dbd4; border-radius: .75rem;
      padding: 1rem; text-align: center; cursor: pointer;
      transition: all .2s; background: #fff; position: relative;
    }
    .order-type-btn:hover { border-color: var(--caramel); }
    .order-type-btn.selected { border-color: var(--espresso); background: var(--foam); }
    .order-type-btn input { position: absolute; opacity: 0; }
    .order-type-btn .ot-icon { font-size: 1.8rem; display: block; margin-bottom: .4rem; }
    .order-type-btn .ot-label { font-size: .85rem; font-weight: 500; color: var(--espresso); }
    .order-type-btn .ot-sub { font-size: .72rem; color: #999; }

    /* Payment method */
    .payment-wrap { display: flex; gap: .75rem; flex-wrap: wrap; }
    .payment-btn {
      flex: 1; min-width: 120px; border: 2px solid #e0dbd4; border-radius: .75rem;
      padding: .85rem 1rem; cursor: pointer; transition: all .2s;
      background: #fff; display: flex; align-items: center; gap: .65rem;
      position: relative;
    }
    .payment-btn:hover { border-color: var(--caramel); }
    .payment-btn.selected { border-color: var(--espresso); background: var(--foam); }
    .payment-btn input { position: absolute; opacity: 0; }
    .payment-btn i { font-size: 1.2rem; color: var(--caramel); }
    .payment-btn .pm-label { font-size: .85rem; font-weight: 500; }
    .payment-btn .pm-sub { font-size: .7rem; color: #999; }

    /* Form controls */
    .form-label-rc { font-size: .75rem; font-weight: 500; letter-spacing: .5px; text-transform: uppercase; color: var(--roast); margin-bottom: .4rem; display: block; }
    .form-control-rc {
      width: 100%; border: 1.5px solid #ddd; border-radius: .65rem;
      padding: .65rem .9rem; font-family: 'DM Sans', sans-serif;
      font-size: .9rem; color: var(--espresso); transition: border-color .2s;
      background: #fff;
    }
    .form-control-rc:focus { outline: none; border-color: var(--caramel); box-shadow: 0 0 0 3px rgba(198,134,66,.12); }

    /* Order summary */
    .summary-card { background: var(--espresso); border-radius: 1rem; padding: 1.5rem; color: var(--cream); position: sticky; top: 1rem; }
    .summary-title { font-family: 'Playfair Display', serif; font-size: 1.1rem; margin-bottom: 1.25rem; color: var(--cream); }
    .summary-item { display: flex; align-items: flex-start; gap: .65rem; padding: .65rem 0; border-bottom: 1px solid rgba(255,255,255,.08); }
    .summary-item:last-of-type { border-bottom: none; }
    .summary-item-icon { font-size: 1.2rem; min-width: 28px; }
    .summary-item-name { font-size: .875rem; flex: 1; }
    .summary-item-qty { font-size: .75rem; color: var(--latte); }
    .summary-item-price { font-size: .875rem; font-weight: 500; white-space: nowrap; }
    .summary-divider { border: none; border-top: 1px solid rgba(255,255,255,.12); margin: .75rem 0; }
    .summary-row { display: flex; justify-content: space-between; font-size: .85rem; color: var(--latte); margin-bottom: .35rem; }
    .summary-total-row { display: flex; justify-content: space-between; font-size: 1.1rem; font-weight: 600; color: var(--cream); margin-top: .5rem; }
    .empty-summary { text-align: center; padding: 2rem 0; color: var(--latte); font-size: .875rem; }

    .btn-place-order {
      width: 100%; margin-top: 1.25rem;
      background: var(--caramel); color: var(--espresso);
      border: none; border-radius: .75rem; padding: .9rem;
      font-family: 'DM Sans', sans-serif; font-size: .95rem; font-weight: 600;
      cursor: pointer; transition: background .2s, transform .1s;
      display: flex; align-items: center; justify-content: center; gap: .5rem;
    }
    .btn-place-order:hover { background: var(--latte); }
    .btn-place-order:active { transform: scale(.98); }
    .btn-place-order:disabled { opacity: .5; cursor: not-allowed; }

    /* Alert */
    .alert-rc { background: #fff0f0; border: 1px solid #fcc; color: #c0392b; border-radius: .65rem; padding: .75rem 1rem; font-size: .875rem; margin-bottom: 1rem; display: flex; align-items: center; gap: .5rem; }

    /* Conditional fields */
    #field-table, #field-name { transition: all .2s; }
  </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar-rc">
  <div class="container d-flex align-items-center justify-content-between">
    <a href="menu.php" class="navbar-brand-text">☕ <?= APP_NAME ?></a>
    <a href="menu.php" class="btn-back"><i class="bi bi-arrow-left"></i> Back to menu</a>
  </div>
</nav>

<div class="checkout-wrap">
  <h1 class="page-title">Checkout</h1>

  <?php if ($error): ?>
    <div class="alert-rc"><i class="bi bi-exclamation-circle"></i> <?= clean($error) ?></div>
  <?php endif; ?>

  <form method="POST" action="" id="checkout-form">
    <?= csrf_field() ?>
    <input type="hidden" name="items_json" id="items_json">

    <div class="row g-3">
      <!-- Left: form -->
      <div class="col-lg-7">

        <!-- Order type -->
        <div class="form-card">
          <div class="form-card-title"><i class="bi bi-shop"></i> How would you like your order?</div>
          <div class="order-type-wrap">
            <label class="order-type-btn selected" id="btn-dine" onclick="selectType('dine_in')">
              <input type="radio" name="order_type" value="dine_in" checked>
              <span class="ot-icon">🪑</span>
              <span class="ot-label">Dine in</span>
              <span class="ot-sub">Served to your table</span>
            </label>
            <label class="order-type-btn" id="btn-take" onclick="selectType('takeaway')">
              <input type="radio" name="order_type" value="takeaway">
              <span class="ot-icon">🥡</span>
              <span class="ot-label">Takeaway</span>
              <span class="ot-sub">Pick up at counter</span>
            </label>
          </div>

          <div class="mt-3" id="field-table">
            <label class="form-label-rc" for="table_number">Table number</label>
            <input type="text" class="form-control-rc" id="table_number" name="table_number"
                   placeholder="e.g. A1, 05, B3" value="<?= clean(post('table_number')) ?>">
          </div>

          <div class="mt-3" id="field-name" style="display:none;">
            <label class="form-label-rc" for="customer_name">Your name (for pickup)</label>
            <input type="text" class="form-control-rc" id="customer_name" name="customer_name"
                   placeholder="e.g. Ahmad" value="<?= clean(post('customer_name', $current_user['name'])) ?>">
          </div>
        </div>

        <!-- Payment method -->
        <div class="form-card">
          <div class="form-card-title"><i class="bi bi-credit-card"></i> Payment method</div>
          <div class="payment-wrap">
            <label class="payment-btn selected" onclick="selectPayment(this)">
              <input type="radio" name="payment_method" value="cash" checked>
              <i class="bi bi-cash-stack"></i>
              <div><div class="pm-label">Cash</div><div class="pm-sub">Pay at counter</div></div>
            </label>
            <label class="payment-btn" onclick="selectPayment(this)">
              <input type="radio" name="payment_method" value="ewallet">
              <i class="bi bi-phone"></i>
              <div><div class="pm-label">E-Wallet</div><div class="pm-sub">TnG / GrabPay</div></div>
            </label>
            <label class="payment-btn" onclick="selectPayment(this)">
              <input type="radio" name="payment_method" value="card">
              <i class="bi bi-credit-card-2-front"></i>
              <div><div class="pm-label">Card</div><div class="pm-sub">Debit / Credit</div></div>
            </label>
          </div>
        </div>

        <!-- Special notes -->
        <div class="form-card">
          <div class="form-card-title"><i class="bi bi-pencil"></i> Special requests <span style="font-weight:300;font-size:.8rem;color:#aaa;">(optional)</span></div>
          <textarea class="form-control-rc" name="notes" rows="3"
                    placeholder="e.g. Less sugar, extra hot, no ice..."><?= clean(post('notes')) ?></textarea>
        </div>

      </div>

      <!-- Right: order summary -->
      <div class="col-lg-5">
        <div class="summary-card">
          <div class="summary-title">Your order</div>
          <div id="summary-items">
            <div class="empty-summary"><i class="bi bi-bag" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>Your cart is empty</div>
          </div>
          <hr class="summary-divider">
          <div class="summary-row"><span>Subtotal</span><span id="sum-sub">RM 0.00</span></div>
          <div class="summary-row"><span>SST (6%)</span><span id="sum-tax">RM 0.00</span></div>
          <div class="summary-total-row"><span>Total</span><span id="sum-total">RM 0.00</span></div>
          <button type="submit" class="btn-place-order" id="btn-submit" disabled>
            <i class="bi bi-bag-check"></i> Place order
          </button>
          <p style="text-align:center;font-size:.72rem;color:var(--latte);margin-top:.75rem;margin-bottom:0;">
            <i class="bi bi-shield-check"></i> Secured with session token
          </p>
        </div>
      </div>
    </div>
  </form>
</div>

<script>
// ── Load cart from sessionStorage ─────────────────────────────
const cart = JSON.parse(sessionStorage.getItem('richiamo_cart') || '[]');
const icons = { espresso:'☕','cold-brew':'🧊',seasonal:'🌿','non-coffee':'🍵',food:'🥐' };

function renderSummary() {
  const container  = document.getElementById('summary-items');
  const btnSubmit  = document.getElementById('btn-submit');
  const itemsInput = document.getElementById('items_json');

  if (!cart.length) {
    container.innerHTML = '<div class="empty-summary"><i class="bi bi-bag" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>Your cart is empty</div>';
    btnSubmit.disabled = true;
    document.getElementById('sum-sub').textContent   = 'RM 0.00';
    document.getElementById('sum-tax').textContent   = 'RM 0.00';
    document.getElementById('sum-total').textContent = 'RM 0.00';
    itemsInput.value = '[]';
    return;
  }

  container.innerHTML = cart.map(item => `
    <div class="summary-item">
      <span class="summary-item-icon">${icons[item.category] || '☕'}</span>
      <div style="flex:1;">
        <div class="summary-item-name">${item.name}</div>
        <div class="summary-item-qty">x${item.qty}</div>
      </div>
      <span class="summary-item-price">RM ${(item.price * item.qty).toFixed(2)}</span>
    </div>
  `).join('');

  const subtotal = cart.reduce((s, i) => s + i.price * i.qty, 0);
  const tax      = subtotal * 0.06;
  const total    = subtotal + tax;

  document.getElementById('sum-sub').textContent   = 'RM ' + subtotal.toFixed(2);
  document.getElementById('sum-tax').textContent   = 'RM ' + tax.toFixed(2);
  document.getElementById('sum-total').textContent = 'RM ' + total.toFixed(2);

  itemsInput.value  = JSON.stringify(cart.map(i => ({ id: i.id, qty: i.qty })));
  btnSubmit.disabled = false;
}

// ── Order type toggle ──────────────────────────────────────────
function selectType(type) {
  document.getElementById('btn-dine').classList.toggle('selected', type === 'dine_in');
  document.getElementById('btn-take').classList.toggle('selected', type === 'takeaway');
  document.getElementById('field-table').style.display = type === 'dine_in'  ? '' : 'none';
  document.getElementById('field-name').style.display  = type === 'takeaway' ? '' : 'none';
}

// ── Payment method toggle ──────────────────────────────────────
function selectPayment(el) {
  document.querySelectorAll('.payment-btn').forEach(b => b.classList.remove('selected'));
  el.classList.add('selected');
}

// ── Form submit: clear cart on success ─────────────────────────
document.getElementById('checkout-form').addEventListener('submit', function() {
  // Cart will be cleared after redirect to confirmation
  sessionStorage.removeItem('richiamo_cart');
});

renderSummary();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
