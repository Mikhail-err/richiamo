<?php
// ============================================================
//  Richiamo Coffee — Google OAuth Redirect
//  Step 1: Build Google auth URL and redirect user there
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

if (validate_session()) redirect_with_message(APP_URL . '/customer/menu.php');

// ── Google OAuth credentials ──────────────────────────────────
// Get these from https://console.cloud.google.com
// APIs & Services → Credentials → Create OAuth 2.0 Client ID
define('GOOGLE_CLIENT_ID',     '195255280900-pcq0vgakmhh1h88ll2f4duhso1t3o6do.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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');
define('GOOGLE_REDIRECT_URI',  APP_URL . '/auth/google_callback.php');

// Store credentials in session for callback
$_SESSION['195255280900-pcq0vgakmhh1h88ll2f4duhso1t3o6do.apps.googleusercontent.com']     = GOOGLE_CLIENT_ID;
$_SESSION['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'] = GOOGLE_CLIENT_SECRET;

// Generate state token to prevent CSRF on the OAuth flow
$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;

// Build Google OAuth URL
$params = http_build_query([
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'access_type'   => 'online',
    'state'         => $state,
    'prompt'        => 'select_account',
]);

$google_auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . $params;

// Redirect to Google
header('Location: ' . $google_auth_url);
exit;