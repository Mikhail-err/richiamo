<?php
// ============================================================
//  Richiamo Coffee — Google OAuth Callback
//  Step 2: Google redirects here with ?code=
//  Exchange code → access token → user info → login/register
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

// ── Validate state (CSRF protection) ─────────────────────────
$state = get_param('state');
if (!$state || $state !== ($_SESSION['google_oauth_state'] ?? '')) {
    redirect_with_message(APP_URL . '/auth/login.php', 'Invalid OAuth state. Please try again.', 'warning');
}
unset($_SESSION['google_oauth_state']);

// ── Check for error from Google ───────────────────────────────
if (get_param('error')) {
    redirect_with_message(APP_URL . '/auth/login.php', 'Google sign-in was cancelled.', 'info');
}

$code = get_param('code');
if (!$code) {
    redirect_with_message(APP_URL . '/auth/login.php', 'No authorization code received.', 'warning');
}

// ── Get credentials from session ──────────────────────────────
$client_id     = $_SESSION['195255280900-pcq0vgakmhh1h88ll2f4duhso1t3o6do.apps.googleusercontent.com']     ?? '';
$client_secret = $_SESSION['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'] ?? '';
$redirect_uri  = APP_URL . '/auth/google_callback.php';

if (!$client_id || !$client_secret) {
    redirect_with_message(APP_URL . '/auth/login.php', 'Google OAuth not configured.', 'warning');
}

// ── Exchange code for access token ────────────────────────────
$token_response = google_post('https://oauth2.googleapis.com/token', [
    'code'          => $code,
    'client_id'     => $client_id,
    'client_secret' => $client_secret,
    'redirect_uri'  => $redirect_uri,
    'grant_type'    => 'authorization_code',
]);

if (!$token_response || empty($token_response['access_token'])) {
    redirect_with_message(APP_URL . '/auth/login.php', 'Failed to get access token from Google.', 'warning');
}

$access_token = $token_response['access_token'];

// ── Get user info from Google ─────────────────────────────────
$user_info = google_get('https://www.googleapis.com/oauth2/v2/userinfo', $access_token);

if (!$user_info || empty($user_info['email'])) {
    redirect_with_message(APP_URL . '/auth/login.php', 'Failed to get user info from Google.', 'warning');
}

$google_email = $user_info['email'];
$google_name  = $user_info['name']    ?? $google_email;
$google_id    = $user_info['id']      ?? '';
$verified     = $user_info['verified_email'] ?? false;

if (!$verified) {
    redirect_with_message(APP_URL . '/auth/login.php', 'Your Google account email is not verified.', 'warning');
}

// ── Find or create user ───────────────────────────────────────
$db   = get_db();
$stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$google_email]);
$user = $stmt->fetch();

if ($user) {
    // Existing user — check if active
    if (!$user['is_active']) {
        redirect_with_message(APP_URL . '/auth/login.php', 'Your account has been deactivated.', 'warning');
    }
    // Update last login
    $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
    $user_id = $user['id'];
    $role    = $user['role'];
    $name    = $user['name'];
} else {
    // New user — create customer account (no password needed for Google accounts)
    $db->prepare("
        INSERT INTO users (name, email, password, role, is_active, last_login)
        VALUES (?, ?, '', 'customer', 1, NOW())
    ")->execute([$google_name, $google_email]);
    $user_id = $db->lastInsertId();
    $role    = ROLE_CUSTOMER;
    $name    = $google_name;
}

// ── Create session ────────────────────────────────────────────
create_session_token($user_id, $role);

// ── Redirect based on role ────────────────────────────────────
if ($role === ROLE_ADMIN || $role === ROLE_DEVELOPER) {
    redirect_with_message(APP_URL . '/admin/dashboard.php', 'Welcome back, ' . $name . '!', 'success');
} else {
    redirect_with_message(APP_URL . '/customer/menu.php', 'Welcome, ' . $name . '! ☕', 'success');
}

// ── Helper functions ──────────────────────────────────────────

function google_post(string $url, array $data): array|false {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response = curl_exec($ch);
    $error    = curl_error($ch);
    curl_close($ch);
    if ($error || !$response) return false;
    return json_decode($response, true) ?: false;
}

function google_get(string $url, string $token): array|false {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response = curl_exec($ch);
    $error    = curl_error($ch);
    curl_close($ch);
    if ($error || !$response) return false;
    return json_decode($response, true) ?: false;
}