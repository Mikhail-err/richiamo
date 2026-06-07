<?php
// ============================================================
//  Richiamo Coffee — Back & Home Button Include
//  Usage: require_once __DIR__ . '/../includes/nav_buttons.php';
//  Optional: set $back_url and $back_label before including.
//
//  Examples:
//    require_once __DIR__ . '/../includes/nav_buttons.php';
//    // or with custom back:
//    $back_url   = APP_URL . '/admin/orders.php';
//    $back_label = 'Orders';
//    require_once __DIR__ . '/../includes/nav_buttons.php';
// ============================================================

// Auto-detect home URL based on role
$_role     = $_SESSION['role'] ?? '';
$_home_url = match($_role) {
    'admin', 'developer' => APP_URL . '/admin/dashboard.php',
    'customer'           => APP_URL . '/customer/dashboard.php',
    default              => APP_URL . '/index.php',
};

// Back URL: use provided $back_url, or fall back to browser history
$_back_url   = $back_url   ?? null;
$_back_label = $back_label ?? 'Back';

// Reset for next use
$back_url   = null;
$back_label = null;
?>

<div class="rc-nav-buttons">
  <?php if ($_back_url): ?>
    <a href="<?= htmlspecialchars($_back_url) ?>" class="rc-btn-back">
      <i class="bi bi-arrow-left"></i>
      <?= htmlspecialchars($_back_label) ?>
    </a>
  <?php else: ?>
    <button onclick="history.back()" class="rc-btn-back">
      <i class="bi bi-arrow-left"></i> Back
    </button>
  <?php endif; ?>

  <a href="<?= $_home_url ?>" class="rc-btn-home" title="Go to home">
    <i class="bi bi-house-fill"></i> Home
  </a>
</div>

<style>
  .rc-nav-buttons {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    margin-bottom: 1.25rem;
  }

  .rc-btn-back,
  .rc-btn-home {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .45rem .9rem;
    border-radius: .6rem;
    font-family: 'DM Sans', sans-serif;
    font-size: .82rem;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    transition: all .15s;
    border: 1.5px solid #ddd;
    background: #fff;
    color: #555;
    line-height: 1;
  }

  .rc-btn-back:hover {
    border-color: #C68642;
    color: #C68642;
    background: #fff;
  }

  .rc-btn-home {
    border-color: #1C0A00;
    background: #1C0A00;
    color: #F5E6C8;
  }

  .rc-btn-home:hover {
    background: #3B1A08;
    border-color: #3B1A08;
    color: #F5E6C8;
  }
</style>
