<?php
// ============================================================
//  Richiamo Coffee — Notifications (Admin)
//  Also serves as AJAX polling endpoint for live order alerts
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
$allowed = [ROLE_ADMIN, ROLE_DEVELOPER];
require_once __DIR__ . '/../auth/auth_check.php';

$db = get_db();

// ── AJAX: return new orders since timestamp ────────────────────
if (isset($_GET['poll'])) {
    header('Content-Type: application/json');
    $since = get_param('since', date('Y-m-d H:i:s', time() - 30));

    $new_orders = $db->prepare("
        SELECT o.id, o.order_number, o.order_type, o.table_number,
               o.customer_name, o.total_amount, o.payment_method,
               o.created_at, u.name AS user_name
        FROM orders o
        LEFT JOIN users u ON u.id = o.user_id
        WHERE o.created_at > ? AND o.order_status = 'pending'
        ORDER BY o.created_at DESC
        LIMIT 10
    ");
    $new_orders->execute([$since]);

    $pending_count = $db->query("SELECT COUNT(*) FROM orders WHERE order_status = 'pending'")->fetchColumn();

    echo json_encode([
        'orders'        => $new_orders->fetchAll(),
        'pending_count' => (int) $pending_count,
        'timestamp'     => date('Y-m-d H:i:s'),
    ]);
    exit;
}

// ── Full page: fetch all recent notifications ──────────────────
$recent_orders = $db->query("
    SELECT o.*, u.name AS user_name
    FROM orders o
    LEFT JOIN users u ON u.id = o.user_id
    WHERE o.order_status IN ('pending','preparing')
    ORDER BY o.created_at DESC
    LIMIT 20
")->fetchAll();

$today_stats = $db->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN order_status='pending'   THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN order_status='preparing' THEN 1 ELSE 0 END) AS preparing,
        SUM(CASE WHEN order_status='ready'     THEN 1 ELSE 0 END) AS ready
    FROM orders
    WHERE DATE(created_at) = CURDATE()
")->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Notifications — <?= APP_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
    :root{--espresso:#1C0A00;--roast:#3B1A08;--caramel:#C68642;--latte:#D4A96A;--cream:#F5E6C8;}
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

    /* Live indicator */
    .live-badge{display:inline-flex;align-items:center;gap:.4rem;background:#EDFAF4;color:#0F6E56;border-radius:2rem;padding:.3rem .75rem;font-size:.75rem;font-weight:500;}
    .live-dot{width:7px;height:7px;border-radius:50%;background:#1D9E75;animation:pulse 1.5s infinite;}
    @keyframes pulse{0%,100%{opacity:1}50%{opacity:.3}}

    /* Stats row */
    .stat-chip{background:#fff;border:1px solid #ede8df;border-radius:.75rem;padding:.85rem 1.1rem;text-align:center;}
    .stat-chip-val{font-family:'Playfair Display',serif;font-size:1.4rem;color:var(--espresso);}
    .stat-chip-lbl{font-size:.7rem;color:#aaa;text-transform:uppercase;letter-spacing:.4px;margin-top:.1rem;}

    /* Order notification card */
    .notif-card{background:#fff;border:1px solid #ede8df;border-radius:.85rem;padding:1rem 1.1rem;margin-bottom:.65rem;display:flex;align-items:center;gap:.9rem;transition:border-color .2s;}
    .notif-card:hover{border-color:var(--caramel);}
    .notif-card.new-order{border-left:3px solid var(--caramel);animation:slideIn .4s ease;}
    @keyframes slideIn{from{opacity:0;transform:translateX(-12px)}to{opacity:1;transform:translateX(0)}}
    .notif-icon{width:40px;height:40px;border-radius:.65rem;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;}
    .notif-num{font-weight:600;font-size:.9rem;color:var(--caramel);}
    .notif-meta{font-size:.75rem;color:#aaa;margin-top:.15rem;}
    .notif-time{font-size:.72rem;color:#aaa;margin-left:auto;white-space:nowrap;}

    /* Status badges */
    .badge-pending{background:#FEF3E2;color:#B07A1A;padding:.2rem .6rem;border-radius:2rem;font-size:.7rem;font-weight:500;}
    .badge-preparing{background:#EEF4FF;color:#3B6DD8;padding:.2rem .6rem;border-radius:2rem;font-size:.7rem;font-weight:500;}

    /* Sound toggle */
    .sound-btn{background:transparent;border:1.5px solid #ddd;border-radius:.5rem;padding:.35rem .75rem;font-size:.78rem;cursor:pointer;display:flex;align-items:center;gap:.35rem;color:#666;}
    .sound-btn:hover{border-color:var(--caramel);color:var(--caramel);}

    /* Empty */
    .empty-notif{text-align:center;padding:3rem;color:#aaa;}
    .empty-notif i{font-size:2.5rem;display:block;margin-bottom:.75rem;}

    /* New order banner (toast) */
    #new-order-banner{position:fixed;top:1rem;right:1rem;background:var(--espresso);color:var(--cream);border-radius:.85rem;padding:1rem 1.25rem;max-width:320px;box-shadow:0 8px 24px rgba(0,0,0,.25);z-index:9999;display:none;animation:slideDown .4s ease;}
    @keyframes slideDown{from{opacity:0;transform:translateY(-12px)}to{opacity:1;transform:translateY(0)}}
    .banner-title{font-weight:600;margin-bottom:.2rem;display:flex;align-items:center;gap:.4rem;}
    .banner-sub{font-size:.78rem;color:var(--latte);}
    .banner-close{position:absolute;top:.6rem;right:.6rem;background:none;border:none;color:rgba(255,255,255,.5);cursor:pointer;font-size:1rem;}
  </style>
</head>
<body>

<aside class="sidebar">
  <div class="sidebar-brand">
    <h1>☕ <?= APP_NAME ?></h1><p>Admin Panel</p>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-label">Main</div>
    <a href="dashboard.php"     class="nav-link-item"><i class="bi bi-grid-1x2"></i> Dashboard</a>
    <a href="orders.php"        class="nav-link-item"><i class="bi bi-receipt"></i> Orders</a>
    <a href="menu.php"          class="nav-link-item"><i class="bi bi-journal-text"></i> Menu items</a>
    <a href="categories.php"    class="nav-link-item"><i class="bi bi-tags"></i> Categories</a>
    <div class="nav-label">Reports</div>
    <a href="reports.php"       class="nav-link-item"><i class="bi bi-bar-chart"></i> Sales report</a>
    <a href="customers.php"     class="nav-link-item"><i class="bi bi-people"></i> Customers</a>
    <a href="notifications.php" class="nav-link-item active"><i class="bi bi-bell"></i> Notifications</a>
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
    <div class="topbar-title">Notifications</div>
    <div style="display:flex;align-items:center;gap:.75rem;">
      <span class="live-badge"><span class="live-dot"></span> Live updates on</span>
      <button class="sound-btn" id="sound-btn" onclick="toggleSound()">
        <i class="bi bi-volume-up" id="sound-icon"></i> Sound
      </button>
    </div>
  </div>

  <div class="content">

    <!-- Today's pipeline -->
    <div class="row g-3 mb-4">
      <?php
        $chips = [
          ['val'=>$today_stats['total'],    'label'=>"Today's orders",  'color'=>'var(--caramel)'],
          ['val'=>$today_stats['pending'],  'label'=>'Pending',         'color'=>'#B07A1A'],
          ['val'=>$today_stats['preparing'],'label'=>'Preparing',       'color'=>'#3B6DD8'],
          ['val'=>$today_stats['ready'],    'label'=>'Ready',           'color'=>'#0F6E56'],
        ];
        foreach ($chips as $c):
      ?>
        <div class="col-6 col-md-3">
          <div class="stat-chip">
            <div class="stat-chip-val" style="color:<?= $c['color'] ?>;"><?= $c['val'] ?></div>
            <div class="stat-chip-lbl"><?= $c['label'] ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Live order list -->
    <div id="orders-list">
      <?php if (empty($recent_orders)): ?>
        <div class="empty-notif">
          <i class="bi bi-bell-slash"></i>
          <p>No pending or active orders right now.</p>
          <p style="font-size:.78rem;">New orders will appear here automatically.</p>
        </div>
      <?php else: ?>
        <?php foreach ($recent_orders as $o): ?>
          <div class="notif-card" id="order-<?= $o['id'] ?>">
            <div class="notif-icon" style="background:<?= $o['order_status']==='pending'?'#FEF3E2':'#EEF4FF' ?>;">
              <?= $o['order_status']==='pending' ? '🔔' : '🔥' ?>
            </div>
            <div style="flex:1;min-width:0;">
              <div class="notif-num">
                <?= clean($o['order_number']) ?>
                <span class="badge-<?= $o['order_status'] ?> ms-1"><?= ucfirst($o['order_status']) ?></span>
              </div>
              <div class="notif-meta">
                <?= clean($o['user_name'] ?? $o['customer_name'] ?? 'Guest') ?> &bull;
                <?= $o['order_type']==='dine_in'?'🪑 Table '.$o['table_number']:'🥡 Takeaway' ?> &bull;
                <?= format_price($o['total_amount']) ?>
              </div>
            </div>
            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.4rem;">
              <span class="notif-time"><?= date('h:i A', strtotime($o['created_at'])) ?></span>
              <a href="order_detail.php?id=<?= $o['id'] ?>" style="font-size:.75rem;color:var(--caramel);text-decoration:none;font-weight:500;">View →</a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div>
</div>

<!-- New order toast banner -->
<div id="new-order-banner">
  <button class="banner-close" onclick="document.getElementById('new-order-banner').style.display='none'">✕</button>
  <div class="banner-title"><span>🔔</span> New order received!</div>
  <div class="banner-sub" id="banner-sub">Loading...</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let lastTimestamp = '<?= date('Y-m-d H:i:s') ?>';
let soundEnabled  = localStorage.getItem('rc_sound') !== 'off';
let knownOrderIds = new Set([<?= implode(',', array_column($recent_orders, 'id')) ?>]);

updateSoundBtn();

function toggleSound() {
  soundEnabled = !soundEnabled;
  localStorage.setItem('rc_sound', soundEnabled ? 'on' : 'off');
  updateSoundBtn();
}

function updateSoundBtn() {
  document.getElementById('sound-icon').className = soundEnabled ? 'bi bi-volume-up' : 'bi bi-volume-mute';
}

function playBeep() {
  if (!soundEnabled) return;
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.frequency.value = 880;
    osc.type = 'sine';
    gain.gain.setValueAtTime(0.3, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
    osc.start(ctx.currentTime);
    osc.stop(ctx.currentTime + 0.4);
  } catch(e) {}
}

function showBanner(order) {
  const banner = document.getElementById('new-order-banner');
  document.getElementById('banner-sub').textContent =
    order.order_number + ' — ' +
    (order.order_type === 'dine_in' ? 'Table ' + order.table_number : 'Takeaway') +
    ' — RM ' + parseFloat(order.total_amount).toFixed(2);
  banner.style.display = 'block';
  setTimeout(() => banner.style.display = 'none', 6000);
}

function addOrderCard(order) {
  const list = document.getElementById('orders-list');
  // Remove empty state if present
  const empty = list.querySelector('.empty-notif');
  if (empty) empty.remove();

  const card = document.createElement('div');
  card.className = 'notif-card new-order';
  card.id = 'order-' + order.id;
  card.innerHTML = `
    <div class="notif-icon" style="background:#FEF3E2;">🔔</div>
    <div style="flex:1;min-width:0;">
      <div class="notif-num">
        ${order.order_number}
        <span class="badge-pending ms-1">Pending</span>
      </div>
      <div class="notif-meta">
        ${order.user_name || order.customer_name || 'Guest'} &bull;
        ${order.order_type === 'dine_in' ? '🪑 Table ' + order.table_number : '🥡 Takeaway'} &bull;
        RM ${parseFloat(order.total_amount).toFixed(2)}
      </div>
    </div>
    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.4rem;">
      <span class="notif-time">Just now</span>
      <a href="order_detail.php?id=${order.id}" style="font-size:.75rem;color:var(--caramel);text-decoration:none;font-weight:500;">View →</a>
    </div>
  `;
  list.insertBefore(card, list.firstChild);
}

// ── Poll for new orders every 10 seconds ──────────────────────
async function pollOrders() {
  try {
    const res  = await fetch(`notifications.php?poll=1&since=${encodeURIComponent(lastTimestamp)}`);
    const data = await res.json();

    data.orders.forEach(order => {
      if (!knownOrderIds.has(order.id)) {
        knownOrderIds.add(order.id);
        addOrderCard(order);
        showBanner(order);
        playBeep();
        // Update browser tab title
        document.title = `🔔 New order — ${order.order_number}`;
        setTimeout(() => document.title = 'Notifications — <?= APP_NAME ?>', 5000);
      }
    });

    lastTimestamp = data.timestamp;
  } catch(e) {
    console.log('Poll error:', e);
  }
}

// Poll every 10 seconds
setInterval(pollOrders, 10000);
</script>
</body>
</html>
