<?php
// ============================================================
//  Richiamo Coffee — Customer Registration
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

if (validate_session()) {
    redirect_with_message(APP_URL . '/customer/menu.php');
}

$error = '';
$old   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $name     = trim(post('name'));
    $email    = trim(post('email'));
    $phone    = trim(post('phone'));
    $password = post('password');
    $confirm  = post('confirm_password');
    $old      = compact('name', 'email', 'phone');

    if (strlen($name) < 2)
        $error = 'Please enter your full name (at least 2 characters).';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $error = 'Please enter a valid email address.';
    elseif (strlen($password) < 8)
        $error = 'Password must be at least 8 characters.';
    elseif (!preg_match('/[A-Z]/', $password))
        $error = 'Password must contain at least one uppercase letter.';
    elseif (!preg_match('/[0-9]/', $password))
        $error = 'Password must contain at least one number.';
    elseif ($password !== $confirm)
        $error = 'Passwords do not match.';
    else {
        $db = get_db();
        $exists = $db->prepare("SELECT id FROM users WHERE email = ?");
        $exists->execute([$email]);
        if ($exists->fetch()) {
            $error = 'An account with this email already exists.';
        } else {
            $db->prepare("
                INSERT INTO users (name, email, phone, password, role, is_active)
                VALUES (?, ?, ?, ?, 'customer', 1)
            ")->execute([$name, $email, $phone, hash_password($password)]);
            $user_id = $db->lastInsertId();
            create_session_token($user_id, ROLE_CUSTOMER);
            redirect_with_message(APP_URL . '/customer/dashboard.php', 'Welcome to Richiamo Coffee, ' . $name . '! ☕', 'success');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Create Account — <?= APP_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
    :root{--espresso:#1C0A00;--roast:#3B1A08;--caramel:#C68642;--latte:#D4A96A;--cream:#F5E6C8;--foam:#FDF6EC;}
    *{box-sizing:border-box;margin:0;padding:0;}
    body{font-family:'DM Sans',sans-serif;background:var(--espresso);min-height:100vh;display:flex;align-items:center;justify-content:center;position:relative;overflow-x:hidden;}
    body::before{content:'';position:fixed;inset:0;background-image:radial-gradient(circle at 15% 50%,rgba(198,134,66,.08) 0%,transparent 50%),radial-gradient(circle at 85% 20%,rgba(212,169,106,.06) 0%,transparent 40%);pointer-events:none;}
    .wrapper{width:100%;max-width:480px;padding:1.5rem;position:relative;z-index:1;animation:fadeUp .45s ease both;}
    @keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
    .brand{text-align:center;margin-bottom:1.75rem;}
    .brand-logo{width:60px;height:60px;border-radius:50%;background:var(--caramel);display:inline-flex;align-items:center;justify-content:center;margin-bottom:.9rem;font-size:1.6rem;}
    .brand h1{font-family:'Playfair Display',serif;font-size:1.7rem;color:var(--cream);}
    .brand p{font-size:.82rem;color:var(--latte);margin-top:.2rem;}
    .card-rc{background:var(--foam);border-radius:1.25rem;padding:2rem 1.75rem;border:1px solid rgba(198,134,66,.2);}
    .card-rc h2{font-family:'Playfair Display',serif;font-size:1.2rem;color:var(--espresso);text-align:center;margin-bottom:1.5rem;}
    .form-label-rc{font-size:.73rem;font-weight:500;letter-spacing:.5px;text-transform:uppercase;color:var(--roast);margin-bottom:.4rem;display:block;}
    .input-group-text{background:var(--cream);border:1.5px solid #ddd;border-right:none;color:var(--caramel);}
    .form-control-rc{border:1.5px solid #ddd;border-left:none;background:#fff;color:var(--espresso);font-family:'DM Sans',sans-serif;font-size:.875rem;padding:.6rem .85rem;transition:border-color .2s,box-shadow .2s;border-radius:0 .5rem .5rem 0!important;}
    .form-control-rc:focus{border-color:var(--caramel);box-shadow:0 0 0 3px rgba(198,134,66,.12);outline:none;}
    .input-group:focus-within .input-group-text{border-color:var(--caramel);}
    .toggle-pw{background:var(--cream);border:1.5px solid #ddd;border-left:none;border-radius:0 .5rem .5rem 0!important;color:var(--caramel);cursor:pointer;padding:0 .75rem;display:flex;align-items:center;}
    .strength-bar{height:4px;border-radius:2px;background:#eee;margin-top:.4rem;overflow:hidden;}
    .strength-fill{height:100%;width:0%;border-radius:2px;transition:width .3s,background .3s;}
    .req{font-size:.72rem;color:#aaa;display:flex;align-items:center;gap:.4rem;margin-top:.2rem;transition:color .2s;}
    .req.met{color:#1D9E75;}
    .btn-register{width:100%;background:var(--espresso);color:var(--cream);border:none;border-radius:.75rem;padding:.8rem;font-family:'DM Sans',sans-serif;font-size:.95rem;font-weight:500;cursor:pointer;transition:background .2s;margin-top:.25rem;}
    .btn-register:hover{background:var(--roast);}
    .divider{display:flex;align-items:center;gap:.75rem;margin:1.1rem 0;color:#aaa;font-size:.78rem;}
    .divider::before,.divider::after{content:'';flex:1;height:1px;background:#e5e5e5;}
    .btn-google{width:100%;background:#fff;color:#3c4043;border:1.5px solid #ddd;border-radius:.75rem;padding:.75rem;font-family:'DM Sans',sans-serif;font-size:.875rem;font-weight:500;cursor:pointer;transition:border-color .2s,box-shadow .2s;display:flex;align-items:center;justify-content:center;gap:.6rem;text-decoration:none;}
    .btn-google:hover{border-color:#aaa;box-shadow:0 2px 8px rgba(0,0,0,.08);color:#3c4043;}
    .btn-google svg{width:18px;height:18px;flex-shrink:0;}
    .login-link{text-align:center;margin-top:1.25rem;font-size:.82rem;color:#888;}
    .login-link a{color:var(--caramel);text-decoration:none;font-weight:500;}
    .alert-error{background:#fff0f0;border:1px solid #fcc;color:#c0392b;border-radius:.6rem;padding:.65rem .9rem;font-size:.83rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem;}
    .footer-note{text-align:center;margin-top:1.25rem;font-size:.75rem;color:var(--latte);}
  </style>
</head>
<body>
<div class="wrapper">
  <div class="brand">
    <div class="brand-logo">☕</div>
    <h1><?= APP_NAME ?></h1>
    <p>Join us for your daily brew</p>
  </div>
  <div class="card-rc">
    <h2>Create your account</h2>
    <?php if ($error): ?>
      <div class="alert-error"><i class="bi bi-exclamation-circle"></i><?= clean($error) ?></div>
    <?php endif; ?>
    <form method="POST" action="">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label-rc">Full name</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-person"></i></span>
          <input type="text" class="form-control-rc" name="name" value="<?= clean($old['name'] ?? '') ?>" placeholder="e.g. Ahmad Fauzi" required>
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label-rc">Email address</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-envelope"></i></span>
          <input type="email" class="form-control-rc" name="email" value="<?= clean($old['email'] ?? '') ?>" placeholder="you@example.com" required>
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label-rc">Phone <span style="font-weight:300;text-transform:none;">(optional)</span></label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-phone"></i></span>
          <input type="tel" class="form-control-rc" name="phone" value="<?= clean($old['phone'] ?? '') ?>" placeholder="e.g. 0123456789">
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label-rc">Password</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-lock"></i></span>
          <input type="password" class="form-control-rc" id="password" name="password" placeholder="Min. 8 characters" required oninput="checkStr(this.value)">
          <button type="button" class="toggle-pw" onclick="togglePw('password','eye1')"><i class="bi bi-eye" id="eye1"></i></button>
        </div>
        <div class="strength-bar"><div class="strength-fill" id="sf"></div></div>
        <div class="req" id="r1"><i class="bi bi-circle"></i> At least 8 characters</div>
        <div class="req" id="r2"><i class="bi bi-circle"></i> One uppercase letter</div>
        <div class="req" id="r3"><i class="bi bi-circle"></i> One number</div>
      </div>
      <div class="mb-3">
        <label class="form-label-rc">Confirm password</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
          <input type="password" class="form-control-rc" name="confirm_password" placeholder="Repeat your password" required oninput="checkMatch()">
          <button type="button" class="toggle-pw" onclick="togglePw('confirm_password','eye2')"><i class="bi bi-eye" id="eye2"></i></button>
        </div>
        <div id="match-msg" style="font-size:.72rem;margin-top:.25rem;"></div>
      </div>
      <button type="submit" class="btn-register"><i class="bi bi-person-plus me-1"></i>Create account</button>
    </form>
    <div class="divider">or</div>
    <a href="<?= APP_URL ?>/auth/google_redirect.php" class="btn-google">
      <svg viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
      Continue with Google
    </a>
    <div class="login-link">Already have an account? <a href="<?= APP_URL ?>/auth/login.php">Sign in</a></div>
  </div>
  <p class="footer-note">&copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved.</p>
</div>
<script>
function togglePw(id,iconId){const el=document.getElementById(id);const ic=document.getElementById(iconId);el.type=el.type==='password'?'text':'password';ic.className=el.type==='password'?'bi bi-eye':'bi bi-eye-slash';}
function checkStr(val){const sf=document.getElementById('sf');const h=val.length>=8,u=/[A-Z]/.test(val),n=/[0-9]/.test(val),s=/[^a-zA-Z0-9]/.test(val);const score=[h,u,n,s].filter(Boolean).length;const c=[{w:'0%',bg:'#eee'},{w:'25%',bg:'#E24B4A'},{w:'50%',bg:'#E8A045'},{w:'75%',bg:'#3B6DD8'},{w:'100%',bg:'#1D9E75'}][score];sf.style.width=c.w;sf.style.background=c.bg;setR('r1',h);setR('r2',u);setR('r3',n);}
function setR(id,met){const el=document.getElementById(id);el.classList.toggle('met',met);el.querySelector('i').className=met?'bi bi-check-circle-fill':'bi bi-circle';}
function checkMatch(){const pw=document.getElementById('password').value;const cpw=document.querySelector('[name="confirm_password"]').value;const msg=document.getElementById('match-msg');if(!cpw){msg.textContent='';return;}msg.textContent=pw===cpw?'✓ Passwords match':'✗ Passwords do not match';msg.style.color=pw===cpw?'#1D9E75':'#E24B4A';}
</script>
</body>
</html>
