<?php
require_once __DIR__ . '/../../backend/config/bootstrap.php';
// bootstrap.php loads: session, db, helpers, settings, lang
if (!defined('BASE_URL')) { define('BASE_URL', '/RestaurantDB/'); }
$pageTitle = 'System Settings';
require_once __DIR__ . '/../../backend/includes/rbac.php';
require_once __DIR__ . '/../../backend/includes/notifications.php';
requireLogin();  // defined in session.php — loaded by bootstrap

/*  POST — before any HTML  */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tab = trim($_POST['tab'] ?? 'general');
    $uid = (int)($_SESSION['user_id'] ?? 0);

    if ($tab === 'general') {
        $sym = trim($_POST['currency_symbol'] ?? '$');
        saveSettings([
            'restaurant_name'    => trim($_POST['restaurant_name']    ?? ''),
            'restaurant_address' => trim($_POST['restaurant_address'] ?? ''),
            'date_format'        => trim($_POST['date_format']        ?? 'DD-Mon-YYYY'),
            'currency_symbol'    => $sym,
            'records_per_page'   => trim($_POST['records_per_page']   ?? '10'),
            'font_family'        => trim($_POST['font_family']        ?? 'Segoe UI'),
            'sidebar_width'      => trim($_POST['sidebar_width']      ?? '210'),
            'font_size'          => trim($_POST['font_size']          ?? '14'),
        ], $uid);
        $_SESSION['currency_symbol'] = $sym;
        setFlash('success', 'General settings saved.');

    } elseif ($tab === 'appearance') {
        saveSettings([
            'primary_color' => trim($_POST['primary_color'] ?? '#c0392b'),
            'sidebar_color' => trim($_POST['sidebar_color'] ?? '#1a252f'),
        ], $uid);
        setFlash('success', 'Appearance saved — refresh to see changes.');

    } elseif ($tab === 'logo') {
        if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
            $ext     = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
            $allowed = array('png','jpg','jpeg','gif','svg','webp');
            if (in_array($ext, $allowed)) {
                $dir  = __DIR__ . '/../../frontend/assets/img/logos/';
                $dest = $dir . 'logo.' . $ext;
                if (!is_dir($dir)) { mkdir($dir, 0755, true); }
                if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $dest)) {
                    saveSettings(['logo_path' => '/RestaurantDB/frontend/assets/img/logos/logo.'.$ext], $uid);
                    setFlash('success', 'Logo updated.');
                } else {
                    setFlash('danger', 'Upload failed — check folder permissions.');
                }
            } else {
                setFlash('danger', 'Invalid file type. Use PNG, JPG, GIF, SVG or WEBP.');
            }
        } elseif (isset($_POST['remove_logo'])) {
            saveSettings(['logo_path' => ''], $uid);
            setFlash('success', 'Logo removed.');
        } else {
            setFlash('warning', 'No file selected.');
        }

    } elseif ($tab === 'language') {
        $lk      = trim($_POST['language'] ?? 'en');
        $allowed = array('en','ps','fa');
        if (!in_array($lk, $allowed)) { $lk = 'en'; }
        $_SESSION['lang'] = $lk;
        try { saveUserPreference($uid, $_SESSION['theme'] ?? 'light', $lk); }
        catch (Throwable $e) { /* USER_PREFERENCES table may not exist yet */ }
        setFlash('success', 'Language changed.');

    } elseif ($tab === 'restore') {
        /* ── SQL file restore ── */
        if (isset($_FILES['sql_file']) && $_FILES['sql_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['sql_file']['name'], PATHINFO_EXTENSION));
            if ($ext === 'sql') {
                $sqlContent = file_get_contents($_FILES['sql_file']['tmp_name']);
                /* Split into individual statements by semicolon */
                $statements = array_filter(
                    array_map('trim', preg_split('/;\s*\n/', $sqlContent)),
                    function($s){ return strlen($s) > 5; }
                );
                $conn    = getDBConnection();
                $success = 0; $errors = 0; $errmsg = '';
                foreach ($statements as $stmt) {
                    /* Skip pure comment lines */
                    if (preg_match('/^--/', ltrim($stmt))) { continue; }
                    $s = oci_parse($conn, $stmt);
                    if ($s && oci_execute($s, OCI_NO_AUTO_COMMIT)) {
                        $success++;
                        oci_free_statement($s);
                    } else {
                        $e = oci_error($conn);
                        /* Ignore "table already exists" / "sequence already exists" */
                        $ignoreErrors = array(955, 2291, 1); // ORA-00955, ORA-02291, ORA-00001
                        if (!in_array((int)($e['code'] ?? 0), $ignoreErrors)) {
                            $errors++;
                            $errmsg .= ' | ' . ($e['message'] ?? 'unknown');
                        }
                    }
                }
                oci_commit($conn);
                oci_close($conn);
                if ($errors === 0) {
                    setFlash('success', "Restore complete — $success statements executed successfully.");
                } else {
                    setFlash('warning', "Done with $errors error(s): ".substr($errmsg,3,200));
                }
            } else {
                setFlash('danger', 'Please upload a .sql file.');
            }
        } else {
            setFlash('danger', 'No file selected or upload error.');
        }
    }

    while(ob_get_level()){ ob_end_clean(); }
    header('Location: '.BASE_URL.'frontend/pages/settings.php?tab='.urlencode($tab));
    exit;
}

/*  Include header (outputs HTML)  */
require_once __DIR__ . '/../includes/header.php';

$s         = getAllSettings();
$activeTab = trim($_GET['tab'] ?? 'general');

$currencies = array(
    '؋'   => '؋  Afghani (AFN) — Afghanistan',
    '$'   => '$  Dollar (USD)',
    '€'   => '€  Euro (EUR)',
    '£'   => '£  Pound (GBP)',
    '﷼'   => '﷼  Riyal',
    'د.إ' => 'د.إ  Dirham (AED)',
    '₺'   => '₺  Lira (TRY)',
    '₹'   => '₹  Rupee (INR)',
    'PKR' => 'PKR  Pakistani Rupee',
);

$tabs = array(
    'general'    => array('fa-gear',        t('set_general')),
    'appearance' => array('fa-palette',     t('set_appearance')),
    'logo'       => array('fa-image',       t('set_logo')),
    'language'   => array('fa-globe',       t('set_language')),
    'backup'     => array('fa-database',    t('set_backup')),
    'restore'    => array('fa-upload',      t('set_restore')),
);
?>

<div class="row g-4">
<!-- ── Tabs sidebar ── -->
<div class="col-lg-3">
    <div class="card">
        <div class="card-body p-2">
        <?php foreach ($tabs as $tk => $td): $isAct = ($activeTab === $tk); ?>
        <a href="?tab=<?= h($tk) ?>"
           class="d-flex align-items-center gap-2 p-3 rounded mb-1 text-decoration-none"
           style="color:var(--text);background:<?= $isAct?'rgba(var(--primary-rgb),.1)':'transparent' ?>;
                  border-left:3px solid <?= $isAct?'var(--primary)':'transparent' ?>;transition:.2s;">
            <i class="fa-solid <?= h($td[0]) ?>" style="color:var(--primary);width:16px;"></i>
            <span style="font-size:13px;font-weight:<?= $isAct?700:400 ?>;"><?= h($td[1]) ?></span>
        </a>
        <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ── Tab content ── -->
<div class="col-lg-9">

<?php if ($activeTab === 'general'): ?>
<div class="card">
    <div class="card-header">
        <h3><i class="fa-solid fa-gear me-2" style="color:var(--primary);"></i>General Settings</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="tab" value="general">
            <div class="form-row" style="margin-bottom:16px;">
                <div class="form-group">
                    <label><i class="fa-solid fa-store me-1"></i> Restaurant Name</label>
                    <input type="text" name="restaurant_name" class="form-control"
                           value="<?= h($s['restaurant_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-location-dot me-1"></i> Address</label>
                    <input type="text" name="restaurant_address" class="form-control"
                           value="<?= h($s['restaurant_address'] ?? '') ?>">
                </div>
            </div>
            <div class="form-row" style="margin-bottom:16px;">
                <div class="form-group">
                    <label><i class="fa-solid fa-calendar me-1"></i> Date Format</label>
                    <select name="date_format" class="form-control">
                    <?php foreach (array('DD-Mon-YYYY','YYYY-MM-DD','MM/DD/YYYY','DD/MM/YYYY') as $fmt): ?>
                        <option value="<?= h($fmt) ?>" <?= ($s['date_format'] ?? '') === $fmt ? 'selected' : '' ?>>
                            <?= h($fmt) ?>
                        </option>
                    <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-coins me-1"></i> Currency Symbol</label>
                    <select name="currency_symbol" class="form-control">
                    <?php foreach ($currencies as $sym => $lbl): ?>
                        <option value="<?= h($sym) ?>" <?= ($s['currency_symbol'] ?? '$') === $sym ? 'selected' : '' ?>>
                            <?= h($lbl) ?>
                        </option>
                    <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group" style="max-width:260px;margin-bottom:20px;">
                <label><i class="fa-solid fa-list-ol me-1"></i> Records Per Page</label>
                <select name="records_per_page" class="form-control">
                <?php foreach (array(5,10,15,20,25,50) as $n): ?>
                    <option value="<?= $n ?>" <?= ($s['records_per_page'] ?? '10') == (string)$n ? 'selected' : '' ?>>
                        <?= $n ?> records per page
                    </option>
                <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row" style="margin-bottom:16px;">
                <div class="form-group">
                    <label><i class="fa-solid fa-font me-1"></i> System Font</label>
                    <select name="font_family" class="form-control">
                    <?php
                    $fonts = [
                        'Segoe UI'    => 'Segoe UI (Default)',
                        'Arial'       => 'Arial',
                        'Tahoma'      => 'Tahoma',
                        'Verdana'     => 'Verdana',
                        'Georgia'     => 'Georgia',
                        'Courier New' => 'Courier New (Monospace)',
                        'Noto Nastaliq Urdu' => 'Noto Nastaliq (Pashto/Dari)',
                    ];
                    $curFont = $s['font_family'] ?? 'Segoe UI';
                    foreach ($fonts as $fv => $fl):
                    ?>
                        <option value="<?= h($fv) ?>" <?= $curFont===$fv?'selected':'' ?>
                                style="font-family:<?= h($fv) ?>">
                            <?= h($fl) ?>
                        </option>
                    <?php endforeach; ?>
                    </select>
                    <small style="color:var(--text-muted);font-size:11px;margin-top:3px;display:block;">
                        Applies to all pages for all users.
                    </small>
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-text-height me-1"></i> Font Size</label>
                    <select name="font_size" class="form-control">
                    <?php foreach(['12'=>'12px (Small)','13'=>'13px','14'=>'14px (Default)','15'=>'15px','16'=>'16px','18'=>'18px (Large)'] as $fs=>$fl): ?>
                        <option value="<?= $fs ?>" <?= ($s['font_size']??'14')===$fs?'selected':'' ?>><?= $fl ?></option>
                    <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row" style="margin-bottom:16px;">
                <div class="form-group">
                    <label><i class="fa-solid fa-left-right me-1"></i> Sidebar Width</label>
                    <select name="sidebar_width" class="form-control">
                    <?php foreach(['160'=>'160px (Narrow)','190'=>'190px','210'=>'210px (Default)','240'=>'240px','280'=>'280px (Wide)'] as $sw=>$sl): ?>
                        <option value="<?= $sw ?>" <?= ($s['sidebar_width']??'210')===$sw?'selected':'' ?>><?= $sl ?></option>
                    <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <div style="background:var(--surface2);border-radius:8px;padding:10px 12px;border:1px solid var(--border);font-size:12px;color:var(--text-muted);margin-top:1px;">
                        <i class="fa-solid fa-circle-info me-1" style="color:var(--primary);"></i>
                        Changes apply system-wide after saving.
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk me-1"></i>Save Settings
                </button>
            </div>
        </form>
    </div>
</div>

<?php elseif ($activeTab === 'appearance'): ?>
<div class="card">
    <div class="card-header">
        <h3><i class="fa-solid fa-palette me-2" style="color:var(--primary);"></i>Appearance &amp; Colors</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="tab" value="appearance">
            <div class="form-row mb-4">
                <div class="form-group">
                    <label><i class="fa-solid fa-droplet me-1"></i> Primary Accent Color</label>
                    <div class="d-flex align-items-center gap-3 mt-1">
                        <input type="color" name="primary_color"
                               style="width:54px;height:38px;padding:2px;border-radius:8px;border:1.5px solid var(--border);cursor:pointer;"
                               value="<?= h($s['primary_color'] ?? '#c0392b') ?>">
                        <div>
                            <div style="font-size:13px;font-weight:600;">Buttons, charts, active items</div>
                            <div style="font-size:11px;color:var(--text-muted);">Current: <?= h($s['primary_color'] ?? '#c0392b') ?></div>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-sidebar me-1"></i> Sidebar Color</label>
                    <div class="d-flex align-items-center gap-3 mt-1">
                        <input type="color" name="sidebar_color"
                               style="width:54px;height:38px;padding:2px;border-radius:8px;border:1.5px solid var(--border);cursor:pointer;"
                               value="<?= h($s['sidebar_color'] ?? '#1a252f') ?>">
                        <div>
                            <div style="font-size:13px;font-weight:600;">Sidebar background</div>
                            <div style="font-size:11px;color:var(--text-muted);">Current: <?= h($s['sidebar_color'] ?? '#1a252f') ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk me-1"></i>Save Colors
                </button>
            </div>
        </form>
        <hr style="border-color:var(--border);margin:20px 0;">
        <p style="font-size:13px;font-weight:700;margin-bottom:10px;">
            <i class="fa-solid fa-swatchbook me-1" style="color:var(--primary);"></i>Quick Presets
        </p>
        <div class="d-flex gap-2 flex-wrap">
        <?php
        $presets = array(
            array('Red',             '#c0392b','#1a252f'),
            array('Navy Blue',       '#2980b9','#0d1b2a'),
            array('Forest Green',    '#27ae60','#0d1f0d'),
            array('Purple',          '#8e44ad','#1a0a2e'),
            array('Orange',          '#e67e22','#2a1500'),
            array('Teal',            '#1abc9c','#0a1f1e'),
            array('Charcoal',        '#2c3e50','#1a1a1a'),
            array('Afghanistan 🇦🇫', '#009a44','#002244'),
        );
        foreach ($presets as $p):
        ?>
        <form method="POST" action="" style="display:inline-block;">
            <input type="hidden" name="tab"           value="appearance">
            <input type="hidden" name="primary_color" value="<?= h($p[1]) ?>">
            <input type="hidden" name="sidebar_color" value="<?= h($p[2]) ?>">
            <button type="submit" class="btn btn-sm mb-1"
                    style="background:<?= h($p[1]) ?>;color:#fff;border:none;font-size:12px;border-radius:6px;">
                <?= h($p[0]) ?>
            </button>
        </form>
        <?php endforeach; ?>
        </div>
    </div>
</div>

<?php elseif ($activeTab === 'logo'): ?>
<div class="card">
    <div class="card-header">
        <h3><i class="fa-solid fa-image me-2" style="color:var(--primary);"></i>Restaurant Logo</h3>
    </div>
    <div class="card-body">
        <?php $logo = $s['logo_path'] ?? ''; ?>
        <?php if ($logo): ?>
        <div class="mb-4 text-center">
            <p style="font-size:13px;color:var(--text-muted);margin-bottom:8px;">Current Logo:</p>
            <img src="<?= h($logo) ?>?v=<?= time() ?>" alt="Logo"
                 style="max-height:80px;max-width:200px;border-radius:10px;border:2px solid var(--border);padding:6px;background:var(--surface2);">
        </div>
        <?php endif; ?>
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="tab" value="logo">
            <div class="form-group" style="max-width:480px;">
                <label><i class="fa-solid fa-upload me-1"></i> Upload New Logo</label>
                <input type="file" name="logo_file" class="form-control" accept="image/*">
                <small style="color:var(--text-muted);font-size:11px;margin-top:4px;display:block;">
                    PNG, JPG, GIF, SVG or WEBP. Recommended: square, 200×200px minimum.
                </small>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-upload me-1"></i>Upload Logo
                </button>
                <?php if ($logo): ?>
                <button type="submit" name="remove_logo" value="1" class="btn btn-outline"
                        style="color:#e74c3c;border-color:#e74c3c;">
                    <i class="fa-solid fa-trash me-1"></i>Remove Logo
                </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php elseif ($activeTab === 'language'): ?>
<div class="card">
    <div class="card-header">
        <h3><i class="fa-solid fa-globe me-2" style="color:var(--primary);"></i>Language &amp; Direction</h3>
    </div>
    <div class="card-body">
        <!-- <div class="alert alert-info mb-4">
            <i class="fa-solid fa-circle-info me-1"></i>
            Language is saved per user account. Pashto and Dari automatically use RTL (right-to-left) layout.
        </div> -->
        <form method="POST" action="">
            <input type="hidden" name="tab" value="language">
            <?php
            $langs   = array('en' => array(t('set_en'),'LTR'), 'ps' => array(t('set_ps'),'RTL'), 'fa' => array(t('set_fa'),'RTL'));
            $curLang = $_SESSION['lang'] ?? 'en';
            foreach ($langs as $lk => $lv):
                $sel = ($curLang === $lk);
            ?>
            <label class="d-flex align-items-center gap-3 p-3 rounded mb-2"
                   style="border:2px solid <?= $sel?'var(--primary)':'var(--border)' ?>;
                          background:<?= $sel?'rgba(var(--primary-rgb),.06)':'var(--surface2)' ?>;cursor:pointer;">
                <input type="radio" name="language" value="<?= h($lk) ?>"
                       <?= $sel?'checked':'' ?> style="width:16px;height:16px;accent-color:var(--primary);">
                <div>
                    <div style="font-size:14px;font-weight:600;"><?= h($lv[0]) ?></div>
                    <div style="font-size:11px;color:var(--text-muted);">Direction: <strong><?= $lv[1] ?></strong></div>
                </div>
                <?php if ($sel): ?>
                <i class="fa-solid fa-circle-check ms-auto" style="color:var(--primary);font-size:18px;"></i>
                <?php endif; ?>
            </label>
            <?php endforeach; ?>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk me-1"></i>Save Language
                </button>
            </div>
        </form>
    </div>
</div>

<?php elseif ($activeTab === 'backup'): ?>
<div class="card">
    <div class="card-header">
        <h3><i class="fa-solid fa-database me-2" style="color:var(--primary);"></i>Backup Data</h3>
    </div>
    <div class="card-body">
        <!-- One-click full backup -->
        <div class="p-4 rounded text-center mb-4"
             style="border:2px dashed var(--border);background:var(--surface2);">
            <i class="fa-solid fa-cloud-arrow-down" style="font-size:48px;color:var(--primary);display:block;margin-bottom:14px;"></i>
            <h5 style="font-size:15px;font-weight:700;margin-bottom:6px;">One-Click Full Backup</h5>
            <p style="font-size:13px;color:var(--text-muted);margin-bottom:20px;">
                Downloads all 13 tables as a single <code>.sql</code> file.<br>
                Use this file to restore the database later.
            </p>
            <a href="<?= BASE_URL ?>frontend/pages/backup_export.php?table=ALL"
               class="btn btn-primary btn-lg" style="font-size:14px;">
                <i class="fa-solid fa-download me-2"></i>Download Full Backup
            </a>
        </div>

        <!-- Per-table exports -->
        <p style="font-size:13px;font-weight:700;margin-bottom:10px;">
            <i class="fa-solid fa-table me-1" style="color:var(--primary);"></i>
            Export Individual Tables
        </p>
        <div class="row g-2">
        <?php
        $tables = array('CUSTOMER','CATEGORY','MENU_ITEM','EMPLOYEE','APP_USER',
                        'ORDERS','ORDER_ITEM','KITCHEN_ORDER','PAYMENT',
                        'INVENTORY_ITEM','SUPPLIER','SUPPLIER_INVENTORY','SYS_SETTINGS');
        foreach ($tables as $tbl):
        ?>
        <div class="col-md-4 col-sm-6">
            <a href="<?= BASE_URL ?>frontend/pages/backup_export.php?table=<?= urlencode($tbl) ?>"
               class="d-flex align-items-center gap-2 p-3 rounded border text-decoration-none"
               style="background:var(--surface2);color:var(--text);font-size:13px;
                      border-color:var(--border)!important;transition:border-color .2s;"
               onmouseover="this.style.borderColor='var(--primary)'"
               onmouseout="this.style.borderColor='var(--border)'">
                <i class="fa-solid fa-table" style="color:var(--primary);font-size:13px;"></i>
                <span><?= h($tbl) ?></span>
                <i class="fa-solid fa-download ms-auto" style="font-size:11px;color:var(--text-muted);"></i>
            </a>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>

<?php elseif ($activeTab === 'restore'): ?>
<div class="card">
    <div class="card-header">
        <h3><i class="fa-solid fa-upload me-2" style="color:var(--primary);"></i>Restore Database</h3>
    </div>
    <div class="card-body">
        <!-- <div class="alert alert-warning mb-4">
            <i class="fa-solid fa-triangle-exclamation me-1"></i>
            <strong>Warning:</strong> Restoring will INSERT data from the SQL file into existing tables.
            Duplicate rows will be skipped automatically. For a clean restore, truncate tables first in SQL Developer.
        </div> -->

        <!-- One-button restore -->
        <div class="p-4 rounded text-center mb-4"
             style="border:2px dashed var(--border);background:var(--surface2);">
            <i class="fa-solid fa-cloud-arrow-up" style="font-size:48px;color:#27ae60;display:block;margin-bottom:14px;"></i>
            <h5 style="font-size:15px;font-weight:700;margin-bottom:6px;">Restore from SQL Backup File</h5>
            <!-- <p style="font-size:13px;color:var(--text-muted);margin-bottom:20px;">
                Upload the <code>.sql</code> file downloaded from the Backup tab.<br>
                The system will execute all INSERT statements automatically.
            </p> -->
            <form method="POST" action="" enctype="multipart/form-data"
                  style="max-width:400px;margin:0 auto;">
                <input type="hidden" name="tab" value="restore">
                <div class="mb-3">
                    <input type="file" name="sql_file" class="form-control" accept=".sql" required
                           style="font-size:13px;">
                    <small style="color:var(--text-muted);font-size:11px;">Only .sql files accepted.</small>
                </div>
                <button type="submit" class="btn btn-success" style="width:100%;font-size:14px;justify-content:center;">
                    <i class="fa-solid fa-database me-2"></i>Restore Now
                </button>
            </form>
        </div>

        <!-- Manual restore info -->
        <!-- <div class="p-3 rounded" style="background:var(--surface2);border:1px solid var(--border);">
            <p style="font-size:13px;font-weight:700;margin-bottom:8px;">
                <i class="fa-solid fa-terminal me-1" style="color:var(--primary);"></i>
                Alternative: Oracle Data Pump (for full schema restore)
            </p>
            <pre style="background:#1e1e1e;color:#d4d4d4;padding:12px;border-radius:6px;font-size:12px;overflow-x:auto;margin:0;">impdp <?= h(defined('DB_USER') ? DB_USER : 'Ahmad') ?>/<password>@ORCLPDB ^
  SCHEMAS=<?= h(defined('DB_USER') ? DB_USER : 'Ahmad') ?> ^
  DUMPFILE=backup.dmp ^
  LOGFILE=import.log</pre>
        </div>
    </div>
</div> -->

<?php endif; ?>

</div><!-- col-lg-9 -->
</div><!-- row -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
