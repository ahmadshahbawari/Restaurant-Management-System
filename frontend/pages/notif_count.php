<?php
/* notif_count.php — Returns unread notification count as plain text integer.
   Called by footer.php JS every 30 seconds. */
require_once __DIR__ . '/../../backend/config/bootstrap.php';
if (!defined('BASE_URL')) { define('BASE_URL', '/RestaurantDB/'); }
require_once __DIR__ . '/../../backend/includes/notifications.php';

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');

if (!isset($_SESSION['user_id'])) { echo 0; exit; }

$uid = (int)$_SESSION['user_id'];
echo countUnreadNotifications($uid);
