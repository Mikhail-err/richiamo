<?php
// ============================================================
//  Richiamo Coffee — App Configuration
// ============================================================

// ── Database ─────────────────────────────────────────────────
define('DB_HOST',     'localhost');
define('DB_NAME',     'richiamo_coffee');
define('DB_USER',     'root');          // change in production
define('DB_PASS',     '');              // change in production
define('DB_CHARSET',  'utf8mb4');

// ── App ──────────────────────────────────────────────────────
define('APP_NAME',    'Richiamo Coffee');
define('APP_URL',     'http://localhost/richiamo');
define('APP_VERSION', '1.0.0');

// ── Token / Session ──────────────────────────────────────────
define('TOKEN_EXPIRY_HOURS', 8);        // session token lifetime
define('TOKEN_BYTES',        32);       // bytes for random token
define('SESSION_NAME',       'richiamo_sess');

// ── Roles ────────────────────────────────────────────────────
define('ROLE_CUSTOMER',  'customer');
define('ROLE_ADMIN',     'admin');
define('ROLE_DEVELOPER', 'developer');

// ── Tax ──────────────────────────────────────────────────────
define('SST_RATE', 0.06);              // 6% SST Malaysia

// ── Error display (set false in production) ──────────────────
define('DEBUG_MODE', true);

if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// ── Start session ────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => TOKEN_EXPIRY_HOURS * 3600,
        'path'     => '/',
        'secure'   => false,   // set true when using HTTPS
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}
