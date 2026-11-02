<?php
if (!defined('BASE_URL')) { define('BASE_URL', '/RestaurantDB/'); }

/* bootstrap loads: ob_start, session, db, helpers, settings, lang */
require_once __DIR__ . '/../../backend/config/bootstrap.php';
require_once __DIR__ . '/../../backend/includes/rbac.php';
require_once __DIR__ . '/../../backend/includes/notifications.php';
requireLogin();

/*  Color helpers  */
if (!function_exists('adjustBrightness')) {
    function adjustBrightness(string $hex, int $steps): string {
        $hex = ltrim($hex,'#');
        if (strlen($hex)===3) $hex=$hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        $r=max(0,min(255,hexdec(substr($hex,0,2))+$steps));
        $g=max(0,min(255,hexdec(substr($hex,2,2))+$steps));
        $b=max(0,min(255,hexdec(substr($hex,4,2))+$steps));
        return sprintf('#%02x%02x%02x',$r,$g,$b);
    }
}
if (!function_exists('hexToRgb')) {
    function hexToRgb(string $hex): string {
        $hex=ltrim($hex,'#');
        if(strlen($hex)===3) $hex=$hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        return hexdec(substr($hex,0,2)).','.hexdec(substr($hex,2,2)).','.hexdec(substr($hex,4,2));
    }
}

/*  Handle theme/language toggle via GET  */
if (isset($_GET['theme']) || isset($_GET['setlang'])) {
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if (isset($_GET['theme'])) {
        $_SESSION['theme'] = ($_GET['theme']==='dark') ? 'dark' : 'light';
    }
    if (isset($_GET['setlang'])) {
        $allowed = array('en','ps','fa');
        $_SESSION['lang'] = in_array($_GET['setlang'],$allowed) ? $_GET['setlang'] : 'en';
    }
    /* Save to DB if user logged in */
    if ($uid > 0) {
        try { saveUserPreference($uid, $_SESSION['theme']??'light', $_SESSION['lang']??'en'); }
        catch(Throwable $e) { /* table may not exist yet */ }
    }
    $p = $_GET; unset($p['theme'],$p['setlang']);
    $qs = http_build_query($p);
    while(ob_get_level()){ob_end_clean();}
    header('Location: '.$_SERVER['PHP_SELF'].($qs?'?'.$qs:''));
    exit;
}

/*  Notification actions via GET  */
if (isset($_GET['notif_action'])) {
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $nid = (int)($_GET['notif_id'] ?? 0);
    if ($_GET['notif_action'] === 'read'  && $nid) markNotificationRead($nid,$uid);
    if ($_GET['notif_action'] === 'delete'&& $nid) deleteNotification($nid,$uid);
    if ($_GET['notif_action'] === 'clear_all')     clearAllNotifications($uid);
    $p = $_GET; unset($p['notif_action'],$p['notif_id']);
    $qs = http_build_query($p);
    while(ob_get_level()){ob_end_clean();}
    header('Location: '.$_SERVER['PHP_SELF'].($qs?'?'.$qs:''));
    exit;
}

/*  Settings & state  */
$primaryColor = getSetting('primary_color','#c0392b');
$sidebarColor = getSetting('sidebar_color','#1a252f');
$restName     = getSetting('restaurant_name','Zam Zam Restaurant');
$logoPath     = getSetting('logo_path','');
$fontFamily   = getSetting('font_family','Segoe UI');
$sidebarWidth = (int)getSetting('sidebar_width','210');
if ($sidebarWidth < 140 || $sidebarWidth > 320) { $sidebarWidth = 210; }
$fontSize     = (int)getSetting('font_size','14');
if ($fontSize < 10 || $fontSize > 22) { $fontSize = 14; }
$theme        = $_SESSION['theme'] ?? 'light';
$lang         = $_SESSION['lang']  ?? 'en';
$rtl          = isRtl();
$dir          = $rtl ? 'rtl' : 'ltr';
$uid          = (int)($_SESSION['user_id'] ?? 0);

/*  Notifications  */
$notifications  = array();
$notifCount     = 0;
try {
    $notifications = getUnreadNotifications($uid);
    $notifCount    = count($notifications);
} catch(Throwable $e) { /* table may not exist yet */ }

/*  Flash  */
$flash = getFlash();
/* Also store flash as toast (picked up by footer.php) */
if (!empty($flash)) {
    $_SESSION['toast_type'] = $flash['type'];
    $_SESSION['toast_msg']  = $flash['msg'];
}

/*  Avatar for current user  */
$avatarUrl     = getSetting('avatar_user_'.$uid, '');
$sessionAvatar = $avatarUrl ?: ($_SESSION['avatar'] ?? '');
if ($sessionAvatar && !isset($_SESSION['avatar'])) { $_SESSION['avatar'] = $sessionAvatar; }

/*  Sidebar menu  */
$sidebarMenu = getSidebarMenu();
$currentPage = basename($_SERVER['PHP_SELF']);

/*  Toggle URLs  */
$toggleTheme = ($theme==='dark') ? 'light' : 'dark';
$toggleIcon  = ($theme==='dark') ? 'fa-sun' : 'fa-moon';
$toggleTitle = ($theme==='dark') ? t('set_light_mode') : t('set_dark_mode');
$qBase       = $_GET; unset($qBase['theme'],$qBase['setlang']);
$toggleUrl   = $_SERVER['PHP_SELF'].'?'.http_build_query(array_merge($qBase,['theme'=>$toggleTheme]));

function langUrl(string $l): string {
    $q = $_GET; unset($q['setlang'],$q['theme']);
    return $_SERVER['PHP_SELF'].'?'.http_build_query(array_merge($q,['setlang'=>$l]));
}
?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>" dir="<?= $dir ?>" data-theme="<?= h($theme) ?>" data-lang="<?= h($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle??'Dashboard') ?> &mdash; <?= h($restName) ?></title>
    <!-- Bootstrap  -->
    <link href="<?= BASE_URL ?>frontend/assets/vendors/bootstrap/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome  -->
    <link href="<?= BASE_URL ?>frontend/assets/vendors/fontawesome/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>frontend/assets/css/style.css">
    <style>
        :root {
            --primary:     <?= h($primaryColor) ?>;
            --primary-dk:  <?= h(adjustBrightness($primaryColor,-25)) ?>;
            --primary-rgb: <?= hexToRgb($primaryColor) ?>;
            --sidebar-bg:  <?= h($sidebarColor) ?>;
            --sidebar-w:   <?= $sidebarWidth ?>px;
        }
        body {
            font-family: '<?= h($fontFamily) ?>','Segoe UI',system-ui,sans-serif;
            font-size: <?= $fontSize ?>px;
        }
        <?php if ($rtl): ?>
        /* RTL overrides */
        .sidebar { left:auto; right:0; }
        .main-content { margin-left:0; margin-right:var(--sidebar-w); }
        .sidebar-nav a { border-left:none; border-right:3px solid transparent; }
        .sidebar-nav a.active { border-right-color:var(--primary); }
        .topbar { direction:rtl; }
        .page-content { direction:rtl; }
        .ms-auto { margin-left:0!important; margin-right:auto!important; }
        .me-1,.me-2 { margin-right:0!important; margin-left:.25rem!important; }
        <?php endif; ?>
    </style>
</head>
<body>
<!-- Apply sidebar state instantly before paint to prevent flicker -->
<script>
(function(){
    var c=localStorage.getItem('rdb_sidebar_collapsed');
    if(c==='1'){document.body.classList.add('sidebar-collapsed');}
})();
</script>
<div class="app-wrapper">

<!--  SIDEBAR  -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <?php if ($logoPath && file_exists($_SERVER['DOCUMENT_ROOT'].$logoPath)): ?>
        <img src="<?= h($logoPath) ?>" alt="Logo"
             style="width:34px;height:34px;border-radius:10px;object-fit:cover;flex-shrink:0;">
        <?php else: ?>
        <div class="brand-logo">
            <i class="fa-solid fa-utensils" style="color:#fff;font-size:14px;"></i>
        </div>
        <?php endif; ?>
        <div class="sidebar-brand-text">
            <h2 title="<?= h($restName) ?>"><?= h($restName) ?></h2>
            <p><?= t('login_subtitle') ?></p>
        </div>
    </div>

    <nav class="sidebar-nav">
    <?php foreach ($sidebarMenu as $item): ?>
        <?php if (isset($item['section'])): ?>
        <div class="nav-section"><?= h($item['section']) ?></div>
        <?php else: ?>
        <a href="<?= BASE_URL ?>frontend/pages/<?= h($item['page']) ?>"
           class="<?= ($currentPage===$item['page']) ? 'active' : '' ?>">
            <i class="fa-solid <?= h($item['icon']) ?>"></i>
            <span><?= h($item['label']) ?></span>
        </a>
        <?php endif; ?>
    <?php endforeach; ?>
    </nav>
</aside>

<!--  MAIN  -->
<main class="main-content">

    <!-- TOPBAR -->
    <div class="topbar">
        <!-- Sidebar toggle button -->
        <button class="sidebar-toggle-btn" id="sidebarToggle"
                title="Toggle Sidebar"
                style="background:none;border:none;cursor:pointer;
                       padding:8px 10px 8px 4px;color:var(--text);
                       font-size:18px;display:flex;align-items:center;flex-shrink:0;">
            <i class="fa-solid fa-bars" id="sidebarToggleIcon"></i>
        </button>
        <div class="topbar-title">
            <i class="fa-solid fa-utensils" style="color:var(--primary);font-size:13px;"></i>
            <span><?= h($pageTitle??'Dashboard') ?></span>
        </div>

        <div class="topbar-right">

            <!-- Date -->
            <span class="d-none d-lg-flex align-items-center gap-1"
                  style="font-size:12px;color:var(--text-muted);">
                <i class="fa-regular fa-calendar"></i> <?= date('D, d M Y') ?>
            </span>

            <!-- Theme toggle -->
            <a href="<?= h($toggleUrl) ?>" class="topbar-icon-btn" title="<?= h($toggleTitle) ?>">
                <i class="fa-solid <?= $toggleIcon ?>"></i>
            </a>

            <!-- Language selector -->
            <div class="dropdown">
                <button class="topbar-icon-btn dropdown-toggle"
                        style="width:auto;padding:0 10px;font-size:12px;font-weight:600;gap:4px;"
                        type="button" data-bs-toggle="dropdown">
                    <?php $langLabels=array('en'=>'EN','ps'=>'پښتو','fa'=>'دری'); ?>
                    <i class="fa-solid fa-globe" style="font-size:13px;"></i>
                    <?= $langLabels[$lang] ?? 'EN' ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" style="min-width:140px;">
                    <li><a class="dropdown-item <?= $lang==='en'?'active':'' ?>"
                           href="<?= h(langUrl('en')) ?>">
                        <i class="fa-solid fa-check me-2 <?= $lang!=='en'?'invisible':'' ?>"></i>English
                    </a></li>
                    <li><a class="dropdown-item <?= $lang==='ps'?'active':'' ?>"
                           href="<?= h(langUrl('ps')) ?>">
                        <i class="fa-solid fa-check me-2 <?= $lang!=='ps'?'invisible':'' ?>"></i>پښتو
                    </a></li>
                    <li><a class="dropdown-item <?= $lang==='fa'?'active':'' ?>"
                           href="<?= h(langUrl('fa')) ?>">
                        <i class="fa-solid fa-check me-2 <?= $lang!=='fa'?'invisible':'' ?>"></i>دری
                    </a></li>
                </ul>
            </div>

            <!-- Notifications -->
            <div class="dropdown">
                <button class="topbar-icon-btn position-relative" type="button"
                        data-bs-toggle="dropdown" aria-expanded="false"
                        title="<?= t('notif_title') ?>">
                    <i class="fa-solid fa-bell"></i>
                    <?php if ($notifCount > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill"
                          style="background:var(--primary);font-size:9px;padding:2px 5px;">
                        <?= min($notifCount,99) ?>
                    </span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end notif-menu" style="min-width:320px;max-height:400px;overflow-y:auto;">
                    <div class="d-flex align-items-center justify-content-between px-3 py-2"
                         style="border-bottom:1px solid var(--border);">
                        <strong style="font-size:13px;">
                            <i class="fa-solid fa-bell me-1" style="color:var(--primary);"></i>
                            <?= t('notif_title') ?>
                            <?php if ($notifCount>0): ?>
                            <span class="badge ms-1" style="background:var(--primary);font-size:10px;"><?= $notifCount ?></span>
                            <?php endif; ?>
                        </strong>
                        <?php if ($notifCount>0): ?>
                        <a href="?notif_action=clear_all" class="btn btn-sm"
                           style="font-size:11px;color:var(--text-muted);padding:2px 8px;border:1px solid var(--border);">
                            <i class="fa-solid fa-trash-can me-1"></i><?= t('notif_clear_all') ?>
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php if (empty($notifications)): ?>
                    <div style="text-align:center;padding:24px 16px;color:var(--text-muted);font-size:13px;">
                        <i class="fa-regular fa-bell-slash" style="font-size:28px;opacity:.3;display:block;margin-bottom:8px;"></i>
                        <?= t('notif_empty') ?>
                    </div>
                    <?php else: ?>
                    <?php foreach ($notifications as $n): ?>
                    <?php $nColors=array('info'=>'#2980b9','success'=>'#27ae60','warning'=>'#f39c12','danger'=>'#e74c3c'); ?>
                    <div class="d-flex align-items-start gap-2 px-3 py-2 notif-item"
                         style="border-bottom:1px solid var(--border);">
                        <div style="width:8px;height:8px;border-radius:50%;background:<?= $nColors[$n['TYPE']]??'#2980b9' ?>;flex-shrink:0;margin-top:6px;"></div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:13px;font-weight:600;color:var(--text);"><?= h($n['TITLE']) ?></div>
                            <?php if (!empty($n['MESSAGE'])): ?>
                            <div style="font-size:12px;color:var(--text-muted);margin-top:2px;"><?= h($n['MESSAGE']) ?></div>
                            <?php endif; ?>
                            <div style="font-size:11px;color:var(--text-muted);margin-top:3px;">
                                <i class="fa-regular fa-clock me-1"></i><?= h($n['CREATED_AT']) ?>
                            </div>
                        </div>
                        <a href="?notif_action=delete&notif_id=<?= (int)$n['NOTIF_ID'] ?>"
                           class="topbar-icon-btn" style="width:24px;height:24px;font-size:11px;flex-shrink:0;"
                           title="Remove">
                            <i class="fa-solid fa-xmark"></i>
                        </a>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- User profile dropdown -->
            <div class="dropdown">
                <button class="user-btn dropdown-toggle" type="button"
                        id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <?php if ($sessionAvatar && file_exists($_SERVER['DOCUMENT_ROOT'].$sessionAvatar)): ?>
                    <img src="<?= h($sessionAvatar) ?>?v=<?= time() ?>"
                         style="width:30px;height:30px;border-radius:50%;object-fit:cover;flex-shrink:0;"
                         alt="Avatar">
                    <?php else: ?>
                    <div class="user-avatar">
                        <?= strtoupper(substr($_SESSION['username']??'U',0,1)) ?>
                    </div>
                    <?php endif; ?>
                    <div class="d-none d-sm-block text-start">
                        <div style="font-size:13px;font-weight:600;line-height:1.2;">
                            <?= h($_SESSION['username']??'') ?>
                        </div>
                        <div style="font-size:11px;color:var(--text-muted);line-height:1;">
                            <?= h($_SESSION['role']??'Staff') ?>
                        </div>
                    </div>
                    <i class="fa-solid fa-chevron-down" style="font-size:10px;color:var(--text-muted);"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end user-dropdown-menu"
                    aria-labelledby="userDropdown">
                    <li class="dropdown-header-custom">
                        <div class="user-avatar" style="width:40px;height:40px;font-size:17px;margin:0 auto 8px;">
                            <?= strtoupper(substr($_SESSION['username']??'U',0,1)) ?>
                        </div>
                        <div style="font-weight:700;font-size:13px;">
                            <?= h($_SESSION['full_name']??$_SESSION['username']??'') ?>
                        </div>
                        <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">
                            <i class="fa-solid fa-shield-halved me-1" style="color:var(--primary);"></i>
                            <?= h($_SESSION['role']??'Staff') ?>
                        </div>
                    </li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <?php if (canAccess('settings')): ?>
                    <li>
                        <a class="dropdown-item"
                           href="<?= BASE_URL ?>frontend/pages/settings.php">
                            <i class="fa-solid fa-sliders me-2" style="width:16px;color:var(--primary);"></i>
                            <?= t('btn_settings') ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li>
                        <a class="dropdown-item"
                           href="<?= BASE_URL ?>frontend/pages/profile.php">
                            <i class="fa-solid fa-user-pen me-2" style="width:16px;color:var(--primary);"></i>
                            <?= t('btn_profile') ?>
                        </a>
                    </li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
                        <form method="POST"
                              action="<?= BASE_URL ?>frontend/pages/logout.php"
                              style="margin:0;">
                            <button type="submit" class="dropdown-item text-danger">
                                <i class="fa-solid fa-right-from-bracket me-2" style="width:16px;"></i>
                                <?= t('btn_logout') ?>
                            </button>
                        </form>
                    </li>
                </ul>
            </div>

        </div><!-- /.topbar-right -->
    </div><!-- /.topbar -->

    <div class="page-content">
