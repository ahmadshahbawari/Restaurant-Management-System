<?php
require_once __DIR__ . '/../../backend/config/bootstrap.php';
if (!defined('BASE_URL')) { define('BASE_URL', '/RestaurantDB/'); }
$pageTitle = 'My Profile';
require_once __DIR__ . '/../../backend/includes/rbac.php';
requireLogin();

/* ─── Step 1: get UID from session (Oracle may store as string) ─── */
$uid      = (int)($_SESSION['user_id'] ?? 0);
$username = trim((string)($_SESSION['username'] ?? ''));

/* ─── Step 2: load user — try by UID first, fallback by username ─── */
function loadUserFromDB(int $uid, string $username): ?array {
    $conn = getDBConnection();

    /* Try by USER_ID first */
    if ($uid > 0) {
        $sql  = "SELECT u.USER_ID, u.USERNAME, u.STATUS,
                        TO_CHAR(u.CREATED_DATE,'DD-Mon-YYYY') CREATED_DATE,
                        u.EMPLOYEE_ID,
                        NVL(e.FIRST_NAME,'') FIRST_NAME,
                        NVL(e.LAST_NAME,'')  LAST_NAME,
                        NVL(e.ROLE,'Staff')  ROLE,
                        NVL(e.PHONE,'—')     PHONE,
                        NVL(TO_CHAR(e.HIRE_DATE,'DD-Mon-YYYY'),'—') HIRE_DATE
                 FROM   APP_USER u
                 LEFT JOIN EMPLOYEE e ON e.EMPLOYEE_ID = u.EMPLOYEE_ID
                 WHERE  u.USER_ID = :uid";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':uid', $uid);
        oci_execute($stmt);
        $row = oci_fetch_assoc($stmt);
        oci_free_statement($stmt);
        if ($row) { oci_close($conn); return $row; }
    }

    /* Fallback: try by USERNAME */
    if ($username !== '') {
        $sql  = "SELECT u.USER_ID, u.USERNAME, u.STATUS,
                        TO_CHAR(u.CREATED_DATE,'DD-Mon-YYYY') CREATED_DATE,
                        u.EMPLOYEE_ID,
                        NVL(e.FIRST_NAME,'') FIRST_NAME,
                        NVL(e.LAST_NAME,'')  LAST_NAME,
                        NVL(e.ROLE,'Staff')  ROLE,
                        NVL(e.PHONE,'—')     PHONE,
                        NVL(TO_CHAR(e.HIRE_DATE,'DD-Mon-YYYY'),'—') HIRE_DATE
                 FROM   APP_USER u
                 LEFT JOIN EMPLOYEE e ON e.EMPLOYEE_ID = u.EMPLOYEE_ID
                 WHERE  UPPER(u.USERNAME) = UPPER(:un)";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':un', $username);
        oci_execute($stmt);
        $row = oci_fetch_assoc($stmt);
        oci_free_statement($stmt);
        if ($row) {
            /* Fix the session so next time UID works */
            $_SESSION['user_id'] = (int)$row['USER_ID'];
            oci_close($conn);
            return $row;
        }
    }

    oci_close($conn);
    return null;
}

/* ─── Step 3: Load user before POST (so we always have $user) ─── */
$user = loadUserFromDB($uid, $username);

/* If still null, redirect with a meaningful message */
if (!$user) {
    setFlash('danger', 'Profile not found. Please log out and log in again.');
    redirect('frontend/pages/dashboard.php');
}

/* Fix uid from DB row (handles case where session had wrong/string value) */
$uid = (int)$user['USER_ID'];
$_SESSION['user_id'] = $uid;

/* ─── Step 4: POST handling BEFORE header.php ─────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = post('action');

    /* Update username / password */
    if ($act === 'update_profile') {
        $newUser = trim(post('username'));
        $newPass = post('password');
        $confirm = post('confirm_password');

        if ($newUser === '') {
            setFlash('danger', 'Username cannot be empty.');
        } elseif ($newPass !== '' && $newPass !== $confirm) {
            setFlash('danger', 'Passwords do not match.');
        } elseif ($newPass !== '' && strlen($newPass) < 6) {
            setFlash('danger', 'Password must be at least 6 characters.');
        } else {
            $conn = getDBConnection();
            if ($newPass !== '') {
                $hashed = password_hash($newPass, PASSWORD_BCRYPT);
                $sql    = 'UPDATE APP_USER SET USERNAME=:un,"PASSWORD"=:pw WHERE USER_ID=:id';
                $stmt   = oci_parse($conn, $sql);
                oci_bind_by_name($stmt, ':pw', $hashed);
            } else {
                $sql  = 'UPDATE APP_USER SET USERNAME=:un WHERE USER_ID=:id';
                $stmt = oci_parse($conn, $sql);
            }
            oci_bind_by_name($stmt, ':un', $newUser);
            oci_bind_by_name($stmt, ':id', $uid);
            if (oci_execute($stmt)) {
                $_SESSION['username'] = $newUser;
                setFlash('success', 'Profile updated successfully.');
            } else {
                $e = oci_error($stmt);
                setFlash('danger', 'Update failed: ' . ($e['message'] ?? 'unknown'));
            }
            oci_free_statement($stmt);
            oci_close($conn);
        }
        redirect('frontend/pages/profile.php');

    /* Remove profile picture */
    } elseif ($act === 'remove_avatar') {
        /* Delete the actual file if it exists */
        $existingPath = getSetting('avatar_user_' . $uid, '');
        if ($existingPath) {
            $absPath = $_SERVER['DOCUMENT_ROOT'] . $existingPath;
            if (file_exists($absPath)) {
                @unlink($absPath);
            }
        }
        /* Clear from settings and session */
        saveSettings(array('avatar_user_' . $uid => ''), $uid);
        unset($_SESSION['avatar']);
        setFlash('success', 'Profile picture removed.');
        redirect('frontend/pages/profile.php');

    /* Upload profile picture */
    } elseif ($act === 'upload_avatar') {
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $ext     = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            $allowed = array('png','jpg','jpeg','gif','webp');
            if (in_array($ext, $allowed)) {
                $dir = __DIR__ . '/../../frontend/assets/img/avatars/';
                if (!is_dir($dir)) { mkdir($dir, 0755, true); }
                $file = 'user_' . $uid . '.' . $ext;
                $dest = $dir . $file;
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $dest)) {
                    $url = '/RestaurantDB/frontend/assets/img/avatars/' . $file;
                    $_SESSION['avatar'] = $url;
                    saveSettings(array('avatar_user_' . $uid => $url), $uid);
                    setFlash('success', 'Profile picture updated.');
                } else {
                    setFlash('danger', 'Upload failed. Check write permissions on frontend/assets/img/avatars/');
                }
            } else {
                setFlash('danger', 'Only PNG, JPG, GIF, WEBP allowed.');
            }
        } else {
            $codes = array(
                UPLOAD_ERR_INI_SIZE   => 'File too large (php.ini limit)',
                UPLOAD_ERR_FORM_SIZE  => 'File too large (form limit)',
                UPLOAD_ERR_PARTIAL    => 'File only partially uploaded',
                UPLOAD_ERR_NO_FILE    => 'No file selected',
                UPLOAD_ERR_NO_TMP_DIR => 'No temp folder',
                UPLOAD_ERR_CANT_WRITE => 'Cannot write to disk',
            );
            $errCode = $_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE;
            setFlash('danger', 'Upload error: ' . ($codes[$errCode] ?? 'Unknown error #'.$errCode));
        }
        redirect('frontend/pages/profile.php');
    }
}

/* ─── Step 5: Avatar ──────────────────────────────────── */
$avatarSetting = getSetting('avatar_user_' . $uid, '');
$avatarPath    = $avatarSetting ?: ($_SESSION['avatar'] ?? '');
$avatarAbsPath = $avatarPath ? ($_SERVER['DOCUMENT_ROOT'] . $avatarPath) : '';
$hasAvatar     = ($avatarPath !== '' && @file_exists($avatarAbsPath));
$initials      = strtoupper(substr($user['USERNAME'], 0, 1));

/* ─── Step 6: HTML ──────────────────────────────────── */
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row g-4">

    <!-- LEFT ─────────────────────────────────────────── -->
    <div class="col-lg-4 col-md-5">

        <!-- Profile info card -->
        <div class="card text-center mb-0">
            <div class="card-body" style="padding:32px 20px 24px;">

                <?php if ($hasAvatar): ?>
                <img src="<?= h($avatarPath) ?>?v=<?= time() ?>" alt="Avatar"
                     style="width:96px;height:96px;border-radius:50%;object-fit:cover;
                            border:3px solid var(--primary);margin-bottom:14px;">
                <?php else: ?>
                <div style="width:96px;height:96px;border-radius:50%;
                            background:linear-gradient(135deg,var(--primary),var(--primary-dk));
                            display:flex;align-items:center;justify-content:center;
                            font-size:38px;font-weight:700;color:#fff;
                            margin:0 auto 14px;">
                    <?= $initials ?>
                </div>
                <?php endif; ?>

                <h3 style="font-size:16px;font-weight:700;margin-bottom:3px;">
                    <?php
                    $fullName = trim($user['FIRST_NAME'] . ' ' . $user['LAST_NAME']);
                    echo $fullName ? h($fullName) : h($user['USERNAME']);
                    ?>
                </h3>
                <p style="color:var(--text-muted);font-size:13px;margin-bottom:6px;">
                    <i class="fa-solid fa-at" style="font-size:11px;"></i> <?= h($user['USERNAME']) ?>
                </p>
                <span class="badge" style="background:var(--primary);color:#fff;font-size:12px;padding:4px 12px;">
                    <?= h($user['ROLE']) ?>
                </span>

                <hr style="border-color:var(--border);margin:16px 0;">

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;text-align:left;">
                    <?php
                    $cards = array(
                        array('Phone',  $user['PHONE'],         'fa-phone'),
                        array('Status', $user['STATUS'] ?? '—', 'fa-circle-dot'),
                        array('Hired',  $user['HIRE_DATE'],     'fa-calendar'),
                        array('Since',  $user['CREATED_DATE'],  'fa-clock'),
                    );
                    foreach ($cards as $c):
                        $isActive   = ($c[0]==='Status' && $c[1]==='Active');
                        $isInactive = ($c[0]==='Status' && $c[1]==='Inactive');
                    ?>
                    <div style="background:var(--surface2);border-radius:8px;padding:9px 11px;border:1px solid var(--border);">
                        <div style="font-size:9.5px;color:var(--text-muted);text-transform:uppercase;margin-bottom:3px;">
                            <i class="fa-solid <?= $c[2] ?> me-1"></i><?= $c[0] ?>
                        </div>
                        <div style="font-size:12px;font-weight:600;
                            color:<?= $isActive?'#27ae60':($isInactive?'#e74c3c':'var(--text)') ?>;">
                            <?= h($c[1]) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Upload picture -->
        <div class="card">
            <div class="card-header">
                <h3 style="font-size:13px;">
                    <i class="fa-solid fa-camera me-2" style="color:var(--primary);"></i>
                    Profile Picture
                </h3>
            </div>
            <div class="card-body">
                <?php if ($hasAvatar): ?>
                <!-- Current picture preview + remove button -->
                <div style="text-align:center;margin-bottom:14px;">
                    <img src="<?= h($avatarPath) ?>?v=<?= time() ?>"
                         alt="Current"
                         style="width:70px;height:70px;border-radius:50%;object-fit:cover;
                                border:3px solid var(--primary);margin-bottom:8px;display:block;margin-left:auto;margin-right:auto;">
                    <small style="color:var(--text-muted);font-size:11px;display:block;margin-bottom:8px;">
                        Current profile picture
                    </small>
                    <!-- Remove button -->
                    <form method="POST">
                        <input type="hidden" name="action" value="remove_avatar">
                        <button type="submit" class="btn btn-sm w-100"
                                style="color:#e74c3c;border:1.5px solid #e74c3c;background:transparent;justify-content:center;">
                            <i class="fa-solid fa-trash me-1"></i>Remove Picture
                        </button>
                    </form>
                </div>
                <hr style="border-color:var(--border);margin:12px 0;">
                <?php endif; ?>

                <!-- Upload new picture -->
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_avatar">
                    <div class="form-group mb-3">
                        <label style="font-size:12px;font-weight:600;color:var(--text-muted);margin-bottom:6px;display:block;">
                            <?= $hasAvatar ? 'Change Picture' : 'Upload Picture' ?>
                        </label>
                        <input type="file" name="avatar" class="form-control"
                               accept=".png,.jpg,.jpeg,.gif,.webp" required>
                        <small style="color:var(--text-muted);font-size:11px;margin-top:3px;display:block;">
                            PNG, JPG, GIF or WEBP. Square image recommended.
                        </small>
                    </div>
                    <button type="submit" class="btn btn-primary w-100" style="justify-content:center;">
                        <i class="fa-solid fa-upload me-1"></i><?= $hasAvatar ? 'Change Picture' : 'Upload Picture' ?>
                    </button>
                </form>
            </div>
        </div>

        <!-- Preferences -->
        <div class="card">
            <div class="card-header">
                <h3 style="font-size:13px;">
                    <i class="fa-solid fa-sliders me-2" style="color:var(--primary);"></i>
                    My Preferences
                </h3>
            </div>
            <div class="card-body">
                <!-- Language -->
                <p style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:8px;">
                    <i class="fa-solid fa-globe me-1"></i> Language
                </p>
                <div class="d-flex gap-1 flex-wrap mb-3">
                    <?php foreach(array('en'=>'English','ps'=>'پښتو','fa'=>'دری') as $lk=>$lv): ?>
                    <a href="?setlang=<?= h($lk) ?>"
                       class="btn btn-sm <?= (($_SESSION['lang']??'en')===$lk)?'btn-primary':'btn-outline' ?>"
                       style="font-size:12px;padding:4px 10px;">
                        <?= h($lv) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <!-- Theme -->
                <p style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:8px;">
                    <i class="fa-solid fa-circle-half-stroke me-1"></i> Theme
                </p>
                <?php
                $curTheme = $_SESSION['theme'] ?? 'light';
                $qp  = $_GET; unset($qp['theme']);
                $bu  = $_SERVER['PHP_SELF'] . '?' . http_build_query($qp);
                ?>
                <div class="d-flex gap-1">
                    <a href="<?= h($bu.'&theme=light') ?>"
                       class="btn btn-sm <?= $curTheme==='light'?'btn-primary':'btn-outline' ?>">
                        <i class="fa-solid fa-sun me-1"></i>Light
                    </a>
                    <a href="<?= h($bu.'&theme=dark') ?>"
                       class="btn btn-sm <?= $curTheme==='dark'?'btn-primary':'btn-outline' ?>">
                        <i class="fa-solid fa-moon me-1"></i>Dark
                    </a>
                </div>
            </div>
        </div>
    </div><!-- col-lg-4 -->

    <!-- RIGHT ────────────────────────────────────────── -->
    <div class="col-lg-8 col-md-7">
        <div class="card">
            <div class="card-header">
                <h3>
                    <i class="fa-solid fa-user-pen me-2" style="color:var(--primary);"></i>
                    Edit Profile
                </h3>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="form-row" style="margin-bottom:16px;">
                        <div class="form-group">
                            <label><i class="fa-solid fa-user me-1"></i> Full Name</label>
                            <input type="text" class="form-control"
                                   value="<?= h(trim($user['FIRST_NAME'].' '.$user['LAST_NAME'])) ?>"
                                   readonly style="background:var(--surface2);cursor:not-allowed;">
                            <small style="color:var(--text-muted);font-size:11px;">
                                Managed in the Employees page.
                            </small>
                        </div>
                        <div class="form-group">
                            <label><i class="fa-solid fa-id-badge me-1"></i> Role</label>
                            <input type="text" class="form-control"
                                   value="<?= h($user['ROLE']) ?>"
                                   readonly style="background:var(--surface2);cursor:not-allowed;">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom:20px;">
                        <label><i class="fa-solid fa-at me-1"></i> Username *</label>
                        <input type="text" name="username" class="form-control" required
                               value="<?= h($user['USERNAME']) ?>"
                               autocomplete="username">
                        <small style="color:var(--text-muted);font-size:11px;">
                            This is what you use to log in.
                        </small>
                    </div>

                    <hr style="border-color:var(--border);margin:20px 0;">

                    <h4 style="font-size:13px;font-weight:700;margin-bottom:14px;">
                        <i class="fa-solid fa-lock me-1" style="color:var(--primary);"></i>
                        Change Password
                        <span style="font-weight:400;color:var(--text-muted);font-size:12px;">
                            — leave blank to keep current
                        </span>
                    </h4>

                    <div class="form-row">
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" name="password" class="form-control"
                                   placeholder="Min 6 characters"
                                   autocomplete="new-password">
                        </div>
                        <div class="form-group">
                            <label>Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control"
                                   placeholder="Repeat new password"
                                   autocomplete="new-password">
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-floppy-disk me-1"></i>Save Profile
                        </button>
                        <a href="<?= BASE_URL ?>frontend/pages/dashboard.php" class="btn btn-outline">
                            <i class="fa-solid fa-arrow-left me-1"></i>Dashboard
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div><!-- col-lg-8 -->

</div><!-- row -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
