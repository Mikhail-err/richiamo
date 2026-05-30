<?php
// ============================================================
//  Richiamo Coffee — Logout
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

destroy_session();
redirect_with_message(APP_URL . '/auth/login.php', 'You have been logged out.', 'info');
