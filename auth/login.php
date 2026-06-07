<?php
// ============================================================
//  Richiamo Coffee — Login Page
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

// Redirect if already logged in
if (validate_session()) {
    $role = $_SESSION['role'] ?? '';
    if ($role === ROLE_ADMIN)         redirect_with_message(APP_URL . '/admin/dashboard.php');
    elseif ($role === ROLE_DEVELOPER) redirect_with_message(APP_URL . '/developer/logs.php');
    else                              redirect_with_message(APP_URL . '/customer/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email    = post('email');
    $password = post('password');

    if (empty($email) || empty($password)) {
        $error = 'Please enter your email and password.';
    } else {
        $db   = get_db();
        $stmt = $db->prepare("SELECT id, name, email, password, role FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && verify_password($password, $user['password'])) {
            create_session_token($user['id'], $user['role']);

            // Update last login
            $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

            // Redirect based on role
            if ($user['role'] === ROLE_ADMIN)         redirect_with_message(APP_URL . '/admin/dashboard.php',      'Welcome back, ' . $user['name'] . '!', 'success');
            elseif ($user['role'] === ROLE_DEVELOPER) redirect_with_message(APP_URL . '/developer/logs.php',       'Welcome back, ' . $user['name'] . '!', 'success');
            else                                      redirect_with_message(APP_URL . '/customer/dashboard.php',   'Welcome back, ' . $user['name'] . '! ☕', 'success');
        } else {
            $error = 'Invalid email or password.';
        }
    }
}

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login — <?= APP_NAME ?></title>

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">

  <style>
    :root {
      --espresso:  #1C0A00;
      --roast:     #3B1A08;
      --caramel:   #C68642;
      --latte:     #D4A96A;
      --cream:     #F5E6C8;
      --foam:      #FDF6EC;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--espresso);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
      overflow: hidden;
    }

    /* Subtle background pattern */
    body::before {
      content: '';
      position: fixed; inset: 0;
      background-image:
        radial-gradient(circle at 20% 50%, rgba(198,134,66,.08) 0%, transparent 50%),
        radial-gradient(circle at 80% 20%, rgba(212,169,106,.06) 0%, transparent 40%);
      pointer-events: none;
    }

    .login-wrapper {
      width: 100%;
      max-width: 440px;
      padding: 1.5rem;
      position: relative;
      z-index: 1;
      animation: fadeUp .5s ease both;
    }

    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(20px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    .brand {
      text-align: center;
      margin-bottom: 2rem;
    }

    .brand-logo {
      width: 64px; height: 64px;
      background: var(--caramel);
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 1rem;
      font-size: 1.8rem;
    }

    .brand h1 {
      font-family: 'Playfair Display', serif;
      font-size: 1.8rem;
      color: var(--cream);
      letter-spacing: .5px;
    }

    .brand p {
      font-size: .85rem;
      color: var(--latte);
      margin-top: .25rem;
    }

    .card-login {
      background: var(--foam);
      border-radius: 1.25rem;
      padding: 2.25rem 2rem;
      border: 1px solid rgba(198,134,66,.2);
    }

    .card-login h2 {
      font-family: 'Playfair Display', serif;
      font-size: 1.3rem;
      color: var(--espresso);
      margin-bottom: 1.5rem;
      text-align: center;
    }

    .form-label {
      font-size: .8rem;
      font-weight: 500;
      letter-spacing: .5px;
      text-transform: uppercase;
      color: var(--roast);
      margin-bottom: .4rem;
    }

    .input-group-text {
      background: var(--cream);
      border: 1.5px solid #ddd;
      border-right: none;
      color: var(--caramel);
    }

    .form-control {
      border: 1.5px solid #ddd;
      border-left: none;
      background: #fff;
      color: var(--espresso);
      font-family: 'DM Sans', sans-serif;
      font-size: .9rem;
      padding: .65rem .9rem;
      transition: border-color .2s, box-shadow .2s;
    }

    .form-control:focus {
      border-color: var(--caramel);
      box-shadow: 0 0 0 3px rgba(198,134,66,.15);
      outline: none;
    }

    .input-group:focus-within .input-group-text {
      border-color: var(--caramel);
    }

    .btn-login {
      width: 100%;
      background: var(--espresso);
      color: var(--cream);
      border: none;
      border-radius: .75rem;
      padding: .8rem;
      font-family: 'DM Sans', sans-serif;
      font-size: .95rem;
      font-weight: 500;
      letter-spacing: .5px;
      cursor: pointer;
      transition: background .2s, transform .1s;
      margin-top: .5rem;
    }

    .btn-login:hover  { background: var(--roast); }
    .btn-login:active { transform: scale(.98); }

    .divider {
      display: flex;
      align-items: center;
      gap: .75rem;
      margin: 1.25rem 0;
      color: #aaa;
      font-size: .8rem;
    }
    .divider::before, .divider::after {
      content: ''; flex: 1; height: 1px; background: #e5e5e5;
    }

    .btn-register {
      width: 100%;
      background: transparent;
      color: var(--roast);
      border: 1.5px solid var(--caramel);
      border-radius: .75rem;
      padding: .75rem;
      font-family: 'DM Sans', sans-serif;
      font-size: .9rem;
      cursor: pointer;
      transition: background .2s, color .2s;
      text-decoration: none;
      display: block;
      text-align: center;
    }
    .btn-register:hover {
      background: var(--caramel);
      color: #fff;
    }

    .alert-error {
      background: #fff0f0;
      border: 1px solid #fcc;
      color: #c0392b;
      border-radius: .6rem;
      padding: .65rem .9rem;
      font-size: .85rem;
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      gap: .5rem;
    }

    .alert-success {
      background: #f0fff4;
      border: 1px solid #b2dfdb;
      color: #1a6b4a;
      border-radius: .6rem;
      padding: .65rem .9rem;
      font-size: .85rem;
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      gap: .5rem;
    }

    .footer-note {
      text-align: center;
      margin-top: 1.5rem;
      font-size: .78rem;
      color: var(--latte);
    }

    .toggle-password {
      background: var(--cream);
      border: 1.5px solid #ddd;
      border-left: none;
      color: var(--caramel);
      cursor: pointer;
      padding: 0 .75rem;
    }
  </style>
</head>
<body>

<div class="login-wrapper">
  <!-- Brand -->
  <div class="brand">
    <div class="brand-logo">☕</div>
    <h1><?= APP_NAME ?></h1>
    <p>Crafted with care, served with love</p>
  </div>

  <!-- Card -->
  <div class="card-login">
    <h2>Sign in</h2>

    <?php if ($flash): ?>
      <div class="alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
        <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
        <?= clean($flash['message']) ?>
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert-error">
        <i class="bi bi-exclamation-circle"></i>
        <?= clean($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="">
      <?= csrf_field() ?>

      <div class="mb-3">
        <label class="form-label" for="email">Email address</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-envelope"></i></span>
          <input type="email" class="form-control" id="email" name="email"
                 value="<?= clean(post('email')) ?>"
                 placeholder="you@example.com" required autofocus>
        </div>
      </div>

      <div class="mb-3">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.4rem;">
          <label class="form-label" for="password" style="margin:0;">Password</label>
          <a href="<?= APP_URL ?>/auth/forgot_password.php" style="font-size:.75rem;color:var(--caramel);text-decoration:none;">Forgot password?</a>
        </div>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-lock"></i></span>
          <input type="password" class="form-control" id="password" name="password"
                 placeholder="••••••••" required>
          <button type="button" class="toggle-password" onclick="togglePw()" aria-label="Show password">
            <i class="bi bi-eye" id="pw-icon"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-login">
        <i class="bi bi-box-arrow-in-right me-1"></i> Sign in
      </button>
    </form>

    <div class="divider">or</div>

    <a href="<?= APP_URL ?>/auth/google_redirect.php" style="width:100%;background:#fff;color:#3c4043;border:1.5px solid #ddd;border-radius:.75rem;padding:.75rem;font-family:'DM Sans',sans-serif;font-size:.875rem;font-weight:500;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:.6rem;text-decoration:none;margin-bottom:.75rem;">
      <svg width="18" height="18" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
      Continue with Google
    </a>

    <a href="<?= APP_URL ?>/auth/register.php" class="btn-register">
      <i class="bi bi-person-plus me-1"></i> Create an account
    </a>
  </div>

  <p class="footer-note">&copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved.</p>
</div>

<script>
function togglePw() {
  const input = document.getElementById('password');
  const icon  = document.getElementById('pw-icon');
  if (input.type === 'password') {
    input.type = 'text';
    icon.className = 'bi bi-eye-slash';
  } else {
    input.type = 'password';
    icon.className = 'bi bi-eye';
  }
}
</script>
</body>
</html>
