<?php
/**
 * index.php — Login page (entry point)
 * Uses bootstrap.php for all core dependencies.
 */
require_once __DIR__ . '/backend/config/bootstrap.php';
// bootstrap loaded: ob_start, session, db, helpers, settings, lang
if (!defined('BASE_URL')) { define('BASE_URL', '/RestaurantDB/'); }
require_once __DIR__ . '/backend/includes/auth.php';

/* Already logged in → go to dashboard */
if (isset($_SESSION['user_id'])) {
    while(ob_get_level()){ob_end_clean();}
    header('Location: ' . BASE_URL . 'frontend/pages/dashboard.php');
    exit;
}

/* Handle language switch on login page */
if (isset($_GET['setlang'])) {
    $allowed = array('en','ps','fa');
    $_SESSION['lang'] = in_array($_GET['setlang'],$allowed) ? $_GET['setlang'] : 'en';
    while(ob_get_level()){ob_end_clean();}
    header('Location: '.BASE_URL.'index.php');
    exit;
}

$lang     = $_SESSION['lang'] ?? 'en';
$error    = '';
$restName = getSetting('restaurant_name', 'Zam Zam Restaurant');

/* Handle POST */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim(post('username'));
    $password = post('password');
    if ($username === '' || $password === '') {
        $error = t('login_error');
    } else {
        $user = loginUser($username, $password);
        if ($user) {
            $_SESSION['user_id']         = $user['user_id'];
            $_SESSION['username']        = $user['username'];
            $_SESSION['employee_id']     = $user['employee_id'];
            $_SESSION['role']            = $user['role'];
            $_SESSION['full_name']       = $user['full_name'];
            $_SESSION['theme']           = $user['theme'];
            $_SESSION['lang']            = $user['lang'];
            $_SESSION['currency_symbol'] = getSetting('currency_symbol', '؋');
            session_regenerate_id(true);
            while(ob_get_level()){ob_end_clean();}
            header('Location: ' . BASE_URL . 'frontend/pages/dashboard.php');
            exit;
        } else {
            $error = t('login_error');
        }
    }
}

/* Colors */
$primaryColor = getSetting('primary_color', '#c0392b');
$theme        = $_SESSION['theme'] ?? 'light';
$hex          = ltrim($primaryColor,'#');
$rgb          = hexdec(substr($hex,0,2)).','.hexdec(substr($hex,2,2)).','.hexdec(substr($hex,4,2));

/* RTL for login page too */
$rtl = isRtl();
$dir = $rtl ? 'rtl' : 'ltr';
?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>" dir="<?= $dir ?>" data-theme="<?= h($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h(t('login_title')) ?> &mdash; <?= h($restName) ?></title>
    <!-- Bootstrap 5 (offline) -->
    <link href="<?= BASE_URL ?>frontend/assets/vendors/bootstrap/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 (offline) -->
    <link href="<?= BASE_URL ?>frontend/assets/vendors/fontawesome/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>frontend/assets/css/style.css">
    <style>
        :root {
            --primary:     <?= h($primaryColor) ?>;
            --primary-rgb: <?= $rgb ?>;
        }
        <?php if ($rtl): ?>
        .login-card { direction: rtl; text-align: right; }
        .text-start  { text-align: right !important; }
        <?php endif; ?>
    </style>
</head>
<body class="login-body">

<div class="login-card">
    <?php
    $loginLogo = getSetting('logo_path', '');
    if ($loginLogo && file_exists($_SERVER['DOCUMENT_ROOT'].$loginLogo)):
    ?>
    <img src="<?= h($loginLogo) ?>?v=<?= time() ?>" alt="Logo"
         style="width:80px;height:80px;border-radius:20px;object-fit:cover;margin:0 auto 12px;display:block;
                border:3px solid rgba(var(--primary-rgb),.2);">
    <?php else: ?>
    <div class="login-brand-icon">
        <i class="fa-solid fa-utensils" style="color:#fff;"></i>
    </div>
    <?php endif; ?>
    <h1><?= h($restName) ?></h1>
    <p class="subtitle"><?= h(t('login_subtitle')) ?></p>

    <?php if ($error): ?>
    <div class="alert alert-danger text-start">
        <i class="fa-solid fa-circle-xmark me-1"></i> <?= h($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="">
        <!-- Username -->
        <div class="form-group text-start mb-3">
            <label for="username" class="form-label fw-semibold">
                <i class="fa-solid fa-user me-1" style="color:var(--primary);"></i>
                <?= h(t('login_username')) ?>
            </label>
            <div class="input-group">
                <span class="input-group-text bg-transparent border-end-0">
                    <i class="fa-solid fa-user text-muted" style="font-size:13px;"></i>
                </span>
                <input type="text"
                       id="username"
                       name="username"
                       class="form-control border-start-0 ps-0"
                       placeholder="<?= h(t('login_username')) ?>"
                       value="<?= h(post('username')) ?>"
                       required autofocus
                       autocomplete="username">
            </div>
        </div>

        <!-- Password -->
        <div class="form-group text-start mb-4">
            <label for="password" class="form-label fw-semibold">
                <i class="fa-solid fa-lock me-1" style="color:var(--primary);"></i>
                <?= h(t('login_password')) ?>
            </label>
            <div class="input-group">
                <span class="input-group-text bg-transparent border-end-0">
                    <i class="fa-solid fa-lock text-muted" style="font-size:13px;"></i>
                </span>
                <input type="password"
                       id="password"
                       name="password"
                       class="form-control border-start-0 ps-0"
                       placeholder="<?= h(t('login_password')) ?>"
                       required
                       autocomplete="current-password">
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-lg w-100" style="font-size:14px;">
            <i class="fa-solid fa-right-to-bracket me-1"></i>
            <?= h(t('login_btn')) ?>
        </button>
    </form>

    <!-- Language switcher on login page -->
    <div class="mt-3 d-flex justify-content-center gap-2">
        <?php
        $loginLangs = ['en'=>'English','ps'=>'پښتو','fa'=>'دری'];
        foreach ($loginLangs as $lk => $lv):
            $isActive = ($lang === $lk);
        ?>
        <a href="?setlang=<?= h($lk) ?>"
           style="font-size:12px;padding:3px 10px;border-radius:20px;text-decoration:none;
                  background:<?= $isActive?'var(--primary)':'rgba(0,0,0,.06)' ?>;
                  color:<?= $isActive?'#fff':'var(--text-muted)' ?>;">
            <?= h($lv) ?>
        </a>
        <?php endforeach; ?>
    </div>

    <p class="mt-3 mb-0" style="font-size:11px;color:var(--text-muted);">
        <i class="fa-solid fa-shield-halved me-1"></i>
        <?= h(t('login_subtitle')) ?>
    </p>
</div>

<script src="<?= BASE_URL ?>frontend/assets/vendors/bootstrap/bootstrap.bundle.min.js"></script>
</body>
</html>
