<?php
require_once __DIR__ . '/../../backend/config/bootstrap.php';
if (!defined('BASE_URL')) { define('BASE_URL', '/RestaurantDB/'); }
$pageTitle = 'App Users';
require_once __DIR__ . '/../../backend/includes/app_user.php';
require_once __DIR__ . '/../../backend/includes/employee.php';
require_once __DIR__ . '/../../backend/includes/rbac.php';

requireAccess('app_users');
$canWrite = canWrite('app_users');

$action = get('action', 'list');
$id     = (int)get('id', '0');

/* ---- POST ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canWrite) {
    $data = [
        'username'    => post('username'),
        'password'    => post('password'),   // hashed in app_user.php
        'employee_id' => (int)post('employee_id'),
        'status'      => post('status', 'Active'),
    ];
    $pa = post('action'); $pid = (int)post('id');

    if ($pa === 'create') {
        if (empty($data['password'])) {
            setFlash('danger', 'Password is required for new users.');
        } elseif (createAppUser($data)) {
            setFlash('success', 'User created.');
        } else {
            setFlash('danger', 'Failed — username may already exist.');
        }
    } elseif ($pa === 'update') {
        updateAppUser($pid, $data) ? setFlash('success','User updated.') : setFlash('danger','Update failed.');
    } elseif ($pa === 'delete') {
        if ($pid == $_SESSION['user_id']) {
            setFlash('danger', 'You cannot delete your own account.');
        } elseif (deleteAppUser($pid)) {
            setFlash('success', 'User deleted.');
        } else {
            setFlash('danger', 'Failed to delete user.');
        }
    }
    redirect('frontend/pages/app_users.php');
}

require_once __DIR__ . '/../includes/header.php';

/* ---- Data ---- */
$search       = get('q');
$filterStatus = get('status_filter');
$allUsers     = getAllAppUsers();
$employees    = ($action === 'new' || $action === 'edit') ? getAllEmployees() : [];

if ($search || $filterStatus) {
    $allUsers = array_filter($allUsers, function($u) use ($search, $filterStatus) {
        if ($search && strpos(strtolower($u['USERNAME']), strtolower($search)) === false
            && strpos(strtolower($u['FULL_NAME'] ?? ''), strtolower($search)) === false
            && strpos(strtolower($u['ROLE'] ?? ''), strtolower($search)) === false) return false;
        if ($filterStatus && $u['STATUS'] !== $filterStatus) return false;
        return true;
    });
    $allUsers = array_values($allUsers);
}

$totalCount = count($allUsers);
$perPage    = (int)getSetting('records_per_page', '10');
$pag        = paginate($totalCount, $perPage);
$users      = array_slice($allUsers, $pag['offset'], $pag['limit']);

$editRow    = ($action === 'edit'           && $id) ? getAppUserById($id) : null;
$confirmRow = ($action === 'confirm_delete' && $id) ? getAppUserById($id) : null;

/* ---- Stats ---- */
$conn   = getDBConnection();
$sTotal = oci_parse($conn, "SELECT COUNT(*) C FROM APP_USER"); oci_execute($sTotal); $rTotal=oci_fetch_assoc($sTotal); oci_free_statement($sTotal);
$sAct   = oci_parse($conn, "SELECT COUNT(*) C FROM APP_USER WHERE STATUS='Active'"); oci_execute($sAct); $rAct=oci_fetch_assoc($sAct); oci_free_statement($sAct);
oci_close($conn);
?>

<?php if ($confirmRow): ?>
<div class="card" style="max-width:500px;">
    <div class="card-header">
        <h3><i class="fa-solid fa-trash me-2"></i><?= t('confirm_delete') ?></h3>
    </div>
    <div class="card-body">
        <div class="alert alert-warning">
            <i class="fa-solid fa-triangle-exclamation me-1"></i>
            Delete user account <strong><?= h($confirmRow['USERNAME']) ?></strong>? <?= t('confirm_msg') ?>
        </div>
        <div class="d-flex gap-2">
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$confirmRow['USER_ID'] ?>">
                <button type="submit" class="btn btn-danger"><i class="fa-solid fa-trash me-1"></i><?= t('btn_confirm_del') ?></button>
            </form>
            <a href="<?= BASE_URL ?>frontend/pages/app_users.php" class="btn btn-outline"><?= t('btn_cancel') ?></a>
        </div>
    </div>
</div>

<?php elseif ($action === 'new' || $action === 'edit'): ?>
<div class="card" style="max-width:600px;">
    <div class="card-header">
        <h3><i class="fa-solid <?= $action === 'new' ? 'fa-user-plus' : 'fa-user-pen' ?> me-2"></i>
            <?= $action === 'new' ? 'Create App User' : 'Edit App User' ?></h3>
        <a href="<?= BASE_URL ?>frontend/pages/app_users.php" class="btn btn-outline btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i><?= t('btn_back') ?>
        </a>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="<?= $action === 'new' ? 'create' : 'update' ?>">
            <?php if ($editRow): ?><input type="hidden" name="id" value="<?= (int)$editRow['USER_ID'] ?>"><?php endif; ?>

            <div class="form-group">
                <label><i class="fa-solid fa-user me-1"></i> Username *</label>
                <input type="text" name="username" class="form-control" required autocomplete="off"
                       value="<?= h($editRow['USERNAME'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>
                    <i class="fa-solid fa-lock me-1"></i>
                    <?php if ($action === 'edit'): ?>
                    New Password <small style="font-weight:400;color:var(--text-muted);">(leave blank to keep current)</small>
                    <?php else: ?>
                    Password *
                    <?php endif; ?>
                </label>
                <input type="password" name="password" class="form-control"
                       <?= $action === 'new' ? 'required' : '' ?> autocomplete="new-password">
            </div>
            <div class="form-group">
                <label><i class="fa-solid fa-id-badge me-1"></i> Linked Employee *</label>
                <select name="employee_id" class="form-control" required>
                    <option value="">— Select Employee —</option>
                    <?php foreach ($employees as $e): ?>
                    <option value="<?= (int)$e['EMPLOYEE_ID'] ?>"
                        <?= (isset($editRow['EMPLOYEE_ID']) && $editRow['EMPLOYEE_ID'] == $e['EMPLOYEE_ID']) ? 'selected' : '' ?>>
                        <?= h($e['FIRST_NAME'] . ' ' . $e['LAST_NAME'] . ' (' . $e['ROLE'] . ')') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label><i class="fa-solid fa-circle-dot me-1"></i> Status</label>
                <select name="status" class="form-control">
                    <option value="Active"   <?= (($editRow['STATUS'] ?? 'Active') === 'Active')   ? 'selected' : '' ?>><?= t('status_active') ?></option>
                    <option value="Inactive" <?= (($editRow['STATUS'] ?? '') === 'Inactive') ? 'selected' : '' ?>><?= t('status_inactive') ?></option>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i><?= t('btn_save') ?></button>
                <a href="<?= BASE_URL ?>frontend/pages/app_users.php" class="btn btn-outline"><?= t('btn_cancel') ?></a>
            </div>
        </form>
    </div>
</div>

<?php else: ?>

<!-- Page Stats -->
<div class="page-stats">
    <div class="page-stat-item">
        <i class="fa-solid fa-key"></i>
        <div><div class="page-stat-num"><?= (int)($rTotal['C'] ?? 0) ?></div><div class="page-stat-lbl"><?= t('page_app_users') ?></div></div>
    </div>
    <div class="page-stat-item">
        <i class="fa-solid fa-circle-check"></i>
        <div><div class="page-stat-num"><?= (int)($rAct['C'] ?? 0) ?></div><div class="page-stat-lbl"><?= t('status_active') ?></div></div>
    </div>
    <div class="page-stat-item">
        <i class="fa-solid fa-shield-halved"></i>
        <div><div class="page-stat-num"><?= (int)($rTotal['C'] ?? 0) - (int)($rAct['C'] ?? 0) ?></div><div class="page-stat-lbl"><?= t('status_inactive') ?></div></div>
    </div>
    <div class="page-stat-item">
        <i class="fa-solid fa-file-lines"></i>
        <div><div class="page-stat-num"><?= $pag['total_pages'] ?></div><div class="page-stat-lbl"><?= t('lbl_pages') ?></div></div>
    </div>
</div>

<!-- Filter Bar -->
<form method="GET" action="" class="filter-bar">
    <span class="filter-label"><i class="fa-solid fa-filter me-1"></i><?= t('lbl_filter') ?></span>
    <input type="text" name="q" class="form-control" style="max-width:240px;"
           placeholder="Search username / name / role…" value="<?= h($search) ?>">
    <select name="status_filter" class="form-control" style="max-width:140px;">
        <option value=""><?= t('lbl_all_statuses') ?></option>
        <option value="Active"   <?= $filterStatus === 'Active'   ? 'selected' : '' ?>><?= t('status_active') ?></option>
        <option value="Inactive" <?= $filterStatus === 'Inactive' ? 'selected' : '' ?>><?= t('status_inactive') ?></option>
    </select>
    <button type="submit" class="btn btn-dark btn-sm">
        <i class="fa-solid fa-magnifying-glass me-1"></i><?= t('btn_search') ?>
    </button>
    <?php if ($search || $filterStatus): ?>
    <a href="<?= BASE_URL ?>frontend/pages/app_users.php" class="btn btn-outline btn-sm">
        <i class="fa-solid fa-xmark me-1"></i><?= t('btn_clear') ?>
    </a>
    <?php endif; ?>
    <?php if ($canWrite): ?>
    <div class="ms-auto">
        <a href="?action=new" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-user-plus me-1"></i><?= t('btn_new') ?> <?= t('col_username') ?>
        </a>
    </div>
    <?php endif; ?>
</form>

<!-- Table -->
<div class="card">
    <div class="card-header">
        <h3><i class="fa-solid fa-key me-2" style="color:var(--primary);"></i>
            App Users
            <span class="badge bg-secondary ms-1" style="font-size:11px;"><?= $totalCount ?></span>
        </h3>
        <span style="font-size:12px;color:var(--text-muted);"><?= t('lbl_showing') ?> <?= count($users) ?> <?= t('lbl_of') ?> <?= $totalCount ?></span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($users)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-users-slash"></i>
            <p><?= t('empty_no_data') ?><?= ($search || $filterStatus) ? ' Try different filters.' : '' ?></p>
        </div>
        <?php else: ?>
        <div class="table-wrapper">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>#</th><th><?= t('col_username') ?></th><th><?= t('col_name') ?></th><th><?= t('col_role') ?></th>
                    <th><?= t('col_status') ?></th><th><?= t('col_date') ?></th>
                    <?php if ($canWrite): ?><th><?= t('col_actions') ?></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td><span class="text-muted"><?= h($u['USER_ID']) ?></span></td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--primary-dk));display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;font-weight:700;flex-shrink:0;">
                            <?= strtoupper(substr($u['USERNAME'], 0, 1)) ?>
                        </div>
                        <div>
                            <strong><?= h($u['USERNAME']) ?></strong>
                            <?php if ($u['USER_ID'] == $_SESSION['user_id']): ?>
                            <span class="badge ms-1" style="background:rgba(var(--primary-rgb),.12);color:var(--primary);font-size:10px;">You</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
                <td><?= h($u['FULL_NAME'] ?? '—') ?></td>
                <td>
                    <span class="badge bg-secondary" style="font-size:11px;"><?= h($u['ROLE'] ?? '—') ?></span>
                </td>
                <td><?= statusBadge($u['STATUS'] ?? 'Inactive') ?></td>
                <td class="text-muted"><?= h($u['CREATED_DATE'] ?? '—') ?></td>
                <?php if ($canWrite): ?>
                <td>
                    <a href="?action=edit&id=<?= (int)$u['USER_ID'] ?>" class="btn btn-warning btn-sm me-1">
                        <i class="fa-solid fa-pen"></i>
                    </a>
                    <?php if ($u['USER_ID'] != $_SESSION['user_id']): ?>
                    <a href="?action=confirm_delete&id=<?= (int)$u['USER_ID'] ?>" class="btn btn-danger btn-sm">
                        <i class="fa-solid fa-trash"></i>
                    </a>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php if ($pag['total_pages'] > 1): ?>
        <div class="p-3 border-top">
            <?= $pag['html'] ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
