<?php
// ============================================================
//  Richiamo Coffee — Forgot Password
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

if (validate_session()) redirect_with_message(APP_URL . '/customer/menu.php');

$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim(post('email'));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $db   = get_db();
        $stmt = $db->prepare("SELECT id, name FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Always show success message (don't reveal if email exists)
        if ($user) {
            // Generate reset token
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour

            // Store in DB — reuse user_sessions table with role='reset'
            // First revoke old reset tokens for this user
            $db->prepare("UPDATE user_sessions SET revoked = 1 WHERE user_id = ? AND role = 'reset'")
               ->execute([$user['id']]);

            $db->prepare("
                INSERT INTO user_sessions (user_id, token, role, expires_at, ip_address)
                VALUES (?, ?, 'reset', ?, ?)
            ")->execute([
                $user['id'],
                hash('sha256', $token),
                $expires,
                $_SERVER['REMOTE_ADDR'] ?? '',
            ]);

            // In production: send email with reset link
            // For XAMPP/development: show the link directly
            $reset_link = APP_URL . '/auth/reset_password.php?token=' . urlencode($token) . '&uid=' . $user['id'];

            if (DEBUG_MODE) {
                // Show link directly in dev mode
                $message = 'dev_link:' . $reset_link;
            } else {
                // TODO: Send email via PHPMailer or mail()
                // mail($email, 'Reset your password', "Click here: $reset_link");
                $message = 'sent';
            }
        } else {
            $message = 'sent'; // Still show success to prevent email enumeration
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Forgot Password — <?= APP_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
    :root{--espresso:#1C0A00;--roast:#3B1A08;--caramel:#C68642;--latte:#D4A96A;--cream:#F5E6C8;--foam:#FDF6EC;}
    *{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'DM Sans',sans-serif;background:var(--espresso);min-height:100vh;display:flex;align-items:center;justify-content:center;}
    body::before{content:'';position:fixed;inset:0;background-image:radial-gradient(circle at 15% 50%,rgba(198,134,66,.08) 0%,transparent 50%);pointer-events:none;}
    .wrapper{width:100%;max-width:420px;padding:1.5rem;position:relative;z-index:1;animation:fadeUp .45s ease both;}
    @keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
    .brand{text-align:center;margin-bottom:1.75rem;}
    .brand-logo{width:60px;height:60px;border-radius:50%;background:var(--caramel);display:inline-flex;align-items:center;justify-content:center;margin-bottom:.9rem;font-size:1.6rem;}
    .brand h1{font-family:'Playfair Display',serif;font-size:1.7rem;color:var(--cream);}
    .card-rc{background:var(--foam);border-radius:1.25rem;padding:2rem 1.75rem;border:1px solid rgba(198,134,66,.2);}
    .card-rc h2{font-family:'Playfair Display',serif;font-size:1.2rem;color:var(--espresso);margin-bottom:.5rem;}
    .card-rc p{font-size:.85rem;color:#888;margin-bottom:1.5rem;line-height:1.6;}
    .form-label-rc{font-size:.73rem;font-weight:500;letter-spacing:.5px;text-transform:uppercase;color:var(--roast);margin-bottom:.4rem;display:block;}
    .input-group-text{background:var(--cream);border:1.5px solid #ddd;border-right:none;color:var(--caramel);}
    .form-control-rc{border:1.5px solid #ddd;border-left:none;background:#fff;color:var(--espresso);font-family:'DM Sans',sans-serif;font-size:.875rem;padding:.6rem .85rem;transition:border-color .2s;border-radius:0 .5rem .5rem 0!important;}
    .form-control-rc:focus{border-color:var(--caramel);box-shadow:0 0 0 3px rgba(198,134,66,.12);outline:none;}
    .input-group:focus-within .input-group-text{border-color:var(--caramel);}
    .btn-rc{width:100%;background:var(--espresso);color:var(--cream);border:none;border-radius:.75rem;padding:.8rem;font-family:'DM Sans',sans-serif;font-size:.95rem;font-weight:500;cursor:pointer;transition:background .2s;margin-top:.5rem;}
    .btn-rc:hover{background:var(--roast);}
    .alert-success-rc{background:#f0fff4;border:1px solid #b2dfdb;color:#1a6b4a;border-radius:.6rem;padding:.85rem 1rem;font-size:.85rem;margin-bottom:1rem;}
    .alert-error-rc{background:#fff0f0;border:1px solid #fcc;color:#c0392b;border-radius:.6rem;padding:.65rem .9rem;font-size:.83rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem;}
    .dev-box{background:#1e1e2e;border-radius:.75rem;padding:1rem;margin-top:1rem;}
    .dev-box p{color:#aaa;font-size:.72rem;margin-bottom:.5rem;}
    .dev-link{color:#C68642;font-size:.78rem;word-break:break-all;text-decoration:none;}
    .dev-link:hover{text-decoration:underline;}
    .back-link{text-align:center;margin-top:1.25rem;font-size:.82rem;color:#888;}
    .back-link a{color:var(--caramel);text-decoration:none;font-weight:500;}
    .footer-note{text-align:center;margin-top:1.25rem;font-size:.75rem;color:var(--latte);}
  </style>
</head>
<body>
<div class="wrapper">
  <div class="brand">
    <div class="brand-logo">☕</div>
    <h1><?= APP_NAME ?></h1>
  </div>
  <div class="card-rc">
    <h2>Forgot password?</h2>
    <p>Enter your email address and we'll send you a link to reset your password.</p>

    <?php if ($error): ?>
      <div class="alert-error-rc"><i class="bi bi-exclamation-circle"></i><?= clean($error) ?></div>
    <?php endif; ?>

    <?php if ($message === 'sent'): ?>
      <div class="alert-success-rc">
        <i class="bi bi-envelope-check me-1"></i>
        <strong>Check your email.</strong> If an account exists for that address, we've sent a password reset link. It expires in 1 hour.
      </div>
      <a href="login.php" class="btn-rc" style="display:block;text-align:center;text-decoration:none;margin-top:0;">
        <i class="bi bi-arrow-left me-1"></i> Back to sign in
      </a>

    <?php elseif (str_starts_with($message, 'dev_link:')): ?>
      <div class="alert-success-rc">
        <i class="bi bi-check-circle me-1"></i> Reset token generated successfully.
      </div>
      <div class="dev-box">
        <p>⚙️ <strong style="color:#C68642;">DEV MODE</strong> — In production this link is sent by email. Click below to reset:</p>
        <?php $link = substr($message, 9); ?>
        <a href="<?= clean($link) ?>" class="dev-link"><?= clean($link) ?></a>
      </div>

    <?php else: ?>
      <form method="POST" action="">
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label-rc">Email address</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input type="email" class="form-control-rc" name="email" placeholder="you@example.com" required autofocus>
          </div>
        </div>
        <button type="submit" class="btn-rc">
          <i class="bi bi-send me-1"></i> Send reset link
        </button>
      </form>
    <?php endif; ?>
  </div>

  <?php if (!$message): ?>
    <div class="back-link"><a href="login.php"><i class="bi bi-arrow-left"></i> Back to sign in</a></div>
  <?php endif; ?>

  <p class="footer-note">&copy; <?= date('Y') ?> <?= APP_NAME ?></p>
</div>
</body>
</html>