<?php
// ============================================================
//  Richiamo Coffee — Reset Password
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

if (validate_session()) redirect_with_message(APP_URL . '/customer/menu.php');

$db      = get_db();
$error   = '';
$success = false;

$raw_token = get_param('token');
$uid       = (int) get_param('uid');

// Validate token
$valid_token = false;
$token_row   = null;

if ($raw_token && $uid) {
    $hashed = hash('sha256', $raw_token);
    $stmt   = $db->prepare("
        SELECT s.*, u.name, u.email
        FROM user_sessions s
        JOIN users u ON u.id = s.user_id
        WHERE s.user_id  = ?
          AND s.token    = ?
          AND s.role     = 'reset'
          AND s.revoked  = 0
          AND s.expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([$uid, $hashed]);
    $token_row = $stmt->fetch();
    if ($token_row) $valid_token = true;
}

if (!$valid_token && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $error = 'This reset link is invalid or has expired. Please request a new one.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    verify_csrf();
    $new_pw  = post('new_password');
    $confirm = post('confirm_password');

    if (strlen($new_pw) < 8)
        $error = 'Password must be at least 8 characters.';
    elseif (!preg_match('/[A-Z]/', $new_pw))
        $error = 'Password must contain at least one uppercase letter.';
    elseif (!preg_match('/[0-9]/', $new_pw))
        $error = 'Password must contain at least one number.';
    elseif ($new_pw !== $confirm)
        $error = 'Passwords do not match.';
    else {
        // Update password
        $db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?")
           ->execute([hash_password($new_pw), $uid]);

        // Revoke all reset tokens for this user
        $db->prepare("UPDATE user_sessions SET revoked = 1 WHERE user_id = ? AND role = 'reset'")
           ->execute([$uid]);

        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reset Password — <?= APP_NAME ?></title>
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
    .card-rc p{font-size:.85rem;color:#888;margin-bottom:1.5rem;}
    .form-label-rc{font-size:.73rem;font-weight:500;letter-spacing:.5px;text-transform:uppercase;color:var(--roast);margin-bottom:.4rem;display:block;}
    .input-group-text{background:var(--cream);border:1.5px solid #ddd;border-right:none;color:var(--caramel);}
    .form-control-rc{border:1.5px solid #ddd;border-left:none;background:#fff;color:var(--espresso);font-family:'DM Sans',sans-serif;font-size:.875rem;padding:.6rem .85rem;transition:border-color .2s;border-radius:0 .5rem .5rem 0!important;}
    .form-control-rc:focus{border-color:var(--caramel);box-shadow:0 0 0 3px rgba(198,134,66,.12);outline:none;}
    .input-group:focus-within .input-group-text{border-color:var(--caramel);}
    .toggle-pw{background:var(--cream);border:1.5px solid #ddd;border-left:none;border-radius:0 .5rem .5rem 0!important;color:var(--caramel);cursor:pointer;padding:0 .75rem;display:flex;align-items:center;}
    .btn-rc{width:100%;background:var(--espresso);color:var(--cream);border:none;border-radius:.75rem;padding:.8rem;font-family:'DM Sans',sans-serif;font-size:.95rem;font-weight:500;cursor:pointer;transition:background .2s;margin-top:.5rem;}
    .btn-rc:hover{background:var(--roast);}
    .alert-success-rc{background:#f0fff4;border:1px solid #b2dfdb;color:#1a6b4a;border-radius:.6rem;padding:.85rem 1rem;font-size:.85rem;margin-bottom:1rem;}
    .alert-error-rc{background:#fff0f0;border:1px solid #fcc;color:#c0392b;border-radius:.6rem;padding:.65rem .9rem;font-size:.83rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem;}
    .strength-bar{height:4px;border-radius:2px;background:#eee;margin-top:.4rem;overflow:hidden;}
    .strength-fill{height:100%;width:0%;border-radius:2px;transition:width .3s,background .3s;}
    .req{font-size:.72rem;color:#aaa;display:flex;align-items:center;gap:.4rem;margin-top:.2rem;transition:color .2s;}
    .req.met{color:#1D9E75;}
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
    <h2>Reset your password</h2>

    <?php if ($success): ?>
      <div class="alert-success-rc">
        <i class="bi bi-check-circle me-1"></i>
        <strong>Password changed!</strong> You can now sign in with your new password.
      </div>
      <a href="login.php" class="btn-rc" style="display:block;text-align:center;text-decoration:none;margin-top:0;">
        <i class="bi bi-box-arrow-in-right me-1"></i> Sign in now
      </a>

    <?php elseif (!$valid_token): ?>
      <div class="alert-error-rc"><i class="bi bi-exclamation-circle"></i><?= clean($error) ?></div>
      <a href="forgot_password.php" class="btn-rc" style="display:block;text-align:center;text-decoration:none;margin-top:.5rem;">
        Request new reset link
      </a>

    <?php else: ?>
      <p>Hi <strong><?= clean($token_row['name']) ?></strong>. Choose a new password below.</p>

      <?php if ($error): ?>
        <div class="alert-error-rc"><i class="bi bi-exclamation-circle"></i><?= clean($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= clean($raw_token) ?>">
        <input type="hidden" name="uid"   value="<?= $uid ?>">

        <div class="mb-3">
          <label class="form-label-rc">New password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" class="form-control-rc" id="new_pw" name="new_password"
                   placeholder="Min. 8 characters" required oninput="checkStr(this.value)">
            <button type="button" class="toggle-pw" onclick="togglePw('new_pw','e1')"><i class="bi bi-eye" id="e1"></i></button>
          </div>
          <div class="strength-bar"><div class="strength-fill" id="sf"></div></div>
          <div class="req" id="r1"><i class="bi bi-circle"></i> At least 8 characters</div>
          <div class="req" id="r2"><i class="bi bi-circle"></i> One uppercase letter</div>
          <div class="req" id="r3"><i class="bi bi-circle"></i> One number</div>
        </div>

        <div class="mb-3">
          <label class="form-label-rc">Confirm new password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
            <input type="password" class="form-control-rc" name="confirm_password"
                   placeholder="Repeat new password" required>
            <button type="button" class="toggle-pw" onclick="togglePw('confirm_password','e2')"><i class="bi bi-eye" id="e2"></i></button>
          </div>
        </div>

        <button type="submit" class="btn-rc">
          <i class="bi bi-shield-check me-1"></i> Set new password
        </button>
      </form>
    <?php endif; ?>
  </div>
  <p class="footer-note">&copy; <?= date('Y') ?> <?= APP_NAME ?></p>
</div>
<script>
function togglePw(id,iconId){const el=document.getElementById(id);const ic=document.getElementById(iconId);el.type=el.type==='password'?'text':'password';ic.className=el.type==='password'?'bi bi-eye':'bi bi-eye-slash';}
function checkStr(val){const sf=document.getElementById('sf');const h=val.length>=8,u=/[A-Z]/.test(val),n=/[0-9]/.test(val),s=/[^a-zA-Z0-9]/.test(val);const score=[h,u,n,s].filter(Boolean).length;const c=[{w:'0%',bg:'#eee'},{w:'25%',bg:'#E24B4A'},{w:'50%',bg:'#E8A045'},{w:'75%',bg:'#3B6DD8'},{w:'100%',bg:'#1D9E75'}][score];sf.style.width=c.w;sf.style.background=c.bg;setR('r1',h);setR('r2',u);setR('r3',n);}
function setR(id,met){const el=document.getElementById(id);el.classList.toggle('met',met);el.querySelector('i').className=met?'bi bi-check-circle-fill':'bi bi-circle';}
</script>
</body>
</html>