<?php
/**
 * bootstrap.php — First file included on every page.
 *
 * Loads in this exact order (no circular deps):
 *  1. ob_start()       — buffer so redirect() always works
 *  2. BASE_URL         — needed by everything
 *  3. session          — session_start() + requireLogin() + currentUser()
 *  4. db               — getDBConnection() needed by settings/lang
 *  5. helpers          — h(), money(), setFlash(), redirect(), paginate()
 *  6. settings         — getSetting(), saveSettings()   [needs db]
 *  7. lang             — t(), isRtl(), saveUserPreference()  [needs db]
 */

/* 1  Output buffer */
if (!ob_get_level()) {
    ob_start();
}

/* 2  Base URL */
if (!defined('BASE_URL')) {
    define('BASE_URL', '/RestaurantDB/');
}

/* 3  Session  */
require_once __DIR__ . '/session.php';

/* 4  Database */
require_once __DIR__ . '/db.php';

/* 5  Helpers  */
require_once __DIR__ . '/../includes/helpers.php';

/* 6  Settings */
require_once __DIR__ . '/../includes/settings.php';

/* 7  Language */
require_once __DIR__ . '/../includes/lang.php';
