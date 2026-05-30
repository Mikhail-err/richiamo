<?php
// ============================================================
//  Richiamo Coffee — Auth Check
//  Include at the top of every protected page.
//
//  Usage:
//    $allowed = [ROLE_ADMIN];          // optional role restriction
//    require_once __DIR__ . '/../auth/auth_check.php';
//
//  $current_user is available after this include.
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

// $allowed may be set by the calling page before including this file.
// If not set, any authenticated user is allowed.
$allowed = $allowed ?? [];

$current_user = require_auth($allowed);
