<?php
// ============================================================
//  Richiamo Coffee — Helper Functions
// ============================================================

require_once __DIR__ . '/db.php';

// ── Token helpers ─────────────────────────────────────────────

/**
 * Generate a cryptographically secure token string.
 */
function generate_token(): string {
    return bin2hex(random_bytes(TOKEN_BYTES));
}

/**
 * Store a new token in the DB and in $_SESSION.
 * Call this right after a successful login.
 */
function create_session_token(int $user_id, string $role): string {
    $db    = get_db();
    $token = generate_token();
    $expires = date('Y-m-d H:i:s', time() + TOKEN_EXPIRY_HOURS * 3600);

    // Revoke any previous active tokens for this user
    $db->prepare("UPDATE user_sessions SET revoked = 1 WHERE user_id = ? AND revoked = 0")
       ->execute([$user_id]);

    // Insert new token
    $db->prepare("
        INSERT INTO user_sessions (user_id, token, role, expires_at, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?, ?)
    ")->execute([
        $user_id,
        hash('sha256', $token),   // store hashed — never raw
        $role,
        $expires,
        $_SERVER['REMOTE_ADDR']  ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? '',
    ]);

    // Keep raw token in session (never in URL)
    $_SESSION['auth_token'] = $token;
    $_SESSION['user_id']    = $user_id;
    $_SESSION['role']       = $role;

    return $token;
}

/**
 * Validate the current session token.
 * Returns user row on success, false on failure.
 */
function validate_session(): array|false {
    if (empty($_SESSION['auth_token']) || empty($_SESSION['user_id'])) {
        return false;
    }

    $db          = get_db();
    $hashed      = hash('sha256', $_SESSION['auth_token']);
    $now         = date('Y-m-d H:i:s');

    $stmt = $db->prepare("
        SELECT s.id AS session_id, s.role, s.expires_at,
               u.id, u.name, u.email
        FROM   user_sessions s
        JOIN   users u ON u.id = s.user_id
        WHERE  s.user_id  = ?
          AND  s.token    = ?
          AND  s.revoked  = 0
          AND  s.expires_at > ?
        LIMIT  1
    ");
    $stmt->execute([$_SESSION['user_id'], $hashed, $now]);
    return $stmt->fetch() ?: false;
}

/**
 * Destroy session and revoke DB token.
 */
function destroy_session(): void {
    if (!empty($_SESSION['auth_token']) && !empty($_SESSION['user_id'])) {
        $db     = get_db();
        $hashed = hash('sha256', $_SESSION['auth_token']);
        $db->prepare("UPDATE user_sessions SET revoked = 1 WHERE user_id = ? AND token = ?")
           ->execute([$_SESSION['user_id'], $hashed]);
    }

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']
        );
    }
    session_destroy();
}

// ── Auth helpers ──────────────────────────────────────────────

/**
 * Require a valid session; redirect to login if not found.
 * Optionally restrict to specific roles.
 *
 * Usage:
 *   require_auth();                          // any logged-in user
 *   require_auth([ROLE_ADMIN]);              // admin only
 *   require_auth([ROLE_ADMIN, ROLE_DEV]);    // admin or developer
 */
function require_auth(array $allowed_roles = []): array {
    $user = validate_session();

    if (!$user) {
        redirect_with_message(APP_URL . '/auth/login.php', 'Please log in to continue.', 'warning');
    }

    if (!empty($allowed_roles) && !in_array($user['role'], $allowed_roles, true)) {
        http_response_code(403);
        include __DIR__ . '/../auth/403.php';
        exit;
    }

    return $user;
}

/**
 * Redirect to a URL with an optional flash message.
 */
function redirect_with_message(string $url, string $message = '', string $type = 'info'): never {
    if ($message) {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
    }
    header('Location: ' . $url);
    exit;
}

/**
 * Retrieve and clear a flash message from session.
 */
function get_flash(): array|null {
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ── Input helpers ─────────────────────────────────────────────

function clean(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function post(string $key, string $default = ''): string {
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}

function get_param(string $key, string $default = ''): string {
    return isset($_GET[$key]) ? trim($_GET[$key]) : $default;
}

// ── Password helpers ──────────────────────────────────────────

function hash_password(string $plain): string {
    return password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verify_password(string $plain, string $hash): bool {
    return password_verify($plain, $hash);
}

// ── Price helpers ─────────────────────────────────────────────

function format_price(float $amount): string {
    return 'RM ' . number_format($amount, 2);
}

function calculate_sst(float $subtotal): float {
    return round($subtotal * SST_RATE, 2);
}

function calculate_total(float $subtotal): float {
    return round($subtotal + calculate_sst($subtotal), 2);
}

// ── CSRF helpers ──────────────────────────────────────────────

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf(): void {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        http_response_code(419);
        die('CSRF token mismatch. Please go back and try again.');
    }
}
