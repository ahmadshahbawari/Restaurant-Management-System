<?php
require_once __DIR__ . '/../../backend/config/bootstrap.php';
if (!defined('BASE_URL')) { define('BASE_URL', '/RestaurantDB/'); }
$pageTitle = 'Employees';
require_once __DIR__ . '/../../backend/includes/employee.php';
require_once __DIR__ . '/../../backend/includes/rbac.php';
require_once __DIR__ . '/../../backend/includes/notifications.php';

requireAccess('employees');
$canWrite = canWrite('employees');

$action = get('action', 'list');
$id     = (int)get('id', '0');

/*  POST  */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canWrite) {
    $data = [
        'first_name' => post('first_name'),
        'last_name'  => post('last_name'),
        'phone'      => post('phone'),
        'role'       => post('role'),
        'salary'     => (float)post('salary'),
        'hire_date'  => post('hire_date') ?: date('Y-m-d'),
        'status'     => post('status', 'Active'),
    ];
    $pa = post('action'); $pid = (int)post('id');
    if ($pa === 'create') {
        if (createEmployee($data)) {
            notifyNewEmployee($data['first_name'].' '.$data['last_name'], $data['role']);
            setFlash('success','Employee added.');
        } else { setFlash('danger','Failed to add.'); }
    }
    if ($pa === 'update') { updateEmployee($pid, $data)    ? setFlash('success','Employee updated.') : setFlash('danger','Update failed.'); }
    if ($pa === 'delete') { deleteEmployee($pid)           ? setFlash('success','Employee deleted.') : setFlash('danger','Cannot delete — linked to orders or user account.'); }
    redirect('frontend/pages/employees.php');
}

require_once __DIR__ . '/../includes/header.php';

/*  Data  */
$search       = get('q');
$filterRole   = get('role_filter');
$filterStatus = get('status_filter');

$allEmployees = getAllEmployees();

/* Client-side filtering */
if ($search || $filterRole || $filterStatus) {
    $allEmployees = array_filter($allEmployees, function($e) use ($search, $filterRole, $filterStatus) {
        $name = strtolower($e['FIRST_NAME'] . ' ' . $e['LAST_NAME']);
        if ($search && strpos($name, strtolower($search)) === false
            && strpos(strtolower($e['PHONE'] ?? ''), strtolower($search)) === false) return false;
        if ($filterRole   && $e['ROLE']   !== $filterRole)   return false;
        if ($filterStatus && $e['STATUS'] !== $filterStatus) return false;
        return true;
    });
    $allEmployees = array_values($allEmployees);
}

$totalCount = count($allEmployees);
$perPage    = (int)getSetting('records_per_page', '10');
$pag        = paginate($totalCount, $perPage);
$employees  = array_slice($allEmployees, $pag['offset'], $pag['limit']);

$editRow    = ($action === 'edit'           && $id) ? getEmployeeById($id) : null;
$confirmRow = ($action === 'confirm_delete' && $id) ? getEmployeeById($id) : null;
$roles      = ['Manager', 'Admin', 'Chef', 'Waiter', 'Cashier', 'Delivery', 'Staff'];

/*  Stats  */
$conn = getDBConnection();
$sAll   = oci_parse($conn, "SELECT COUNT(*) C FROM EMPLOYEE"); oci_execute($sAll); $rAll=oci_fetch_assoc($sAll); oci_free_statement($sAll);
$sAct   = oci_parse($conn, "SELECT COUNT(*) C FROM EMPLOYEE WHERE STATUS='Active'"); oci_execute($sAct); $rAct=oci_fetch_assoc($sAct); oci_free_statement($sAct);
$sNew   = oci_parse($conn, "SELECT COUNT(*) C FROM EMPLOYEE WHERE HIRE_DATE >= SYSDATE-30"); oci_execute($sNew); $rNew=oci_fetch_assoc($sNew); oci_free_statement($sNew);
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
            Delete employee <strong><?= h($confirmRow['FIRST_NAME'] . ' ' . $confirmRow['LAST_NAME']) ?></strong>?
            <?= t('confirm_msg') ?>
        </div>
        <div class="d-flex gap-2">
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$confirmRow['EMPLOYEE_ID'] ?>">
                <button type="submit" class="btn btn-danger"><i class="fa-solid fa-trash me-1"></i><?= t('btn_confirm_del') ?></button>
            </form>
            <a href="<?= BASE_URL ?>frontend/pages/employees.php" class="btn btn-outline">Cancel</a>
        </div>
    </div>
</div>

<?php elseif ($action === 'new' || $action === 'edit'): ?>
<div class="card" style="max-width:700px;">
    <div class="card-header">
        <h3><i class="fa-solid <?= $action === 'new' ? 'fa-user-plus' : 'fa-user-pen' ?> me-2"></i>
            <?= $action === 'new' ? 'Add New Employee' : 'Edit Employee' ?></h3>
        <a href="<?= BASE_URL ?>frontend/pages/employees.php" class="btn btn-outline btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i><?= t('btn_back') ?>
        </a>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="<?= $action === 'new' ? 'create' : 'update' ?>">
            <?php if ($editRow): ?><input type="hidden" name="id" value="<?= (int)$editRow['EMPLOYEE_ID'] ?>"><?php endif; ?>
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fa-solid fa-user me-1"></i> First Name *</label>
                    <input type="text" name="first_name" class="form-control" required
                           value="<?= h($editRow['FIRST_NAME'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-user me-1"></i> Last Name *</label>
                    <input type="text" name="last_name" class="form-control" required
                           value="<?= h($editRow['LAST_NAME'] ?? '') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fa-solid fa-phone me-1"></i> Phone</label>
                    <input type="text" name="phone" class="form-control"
                           value="<?= h($editRow['PHONE'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-id-badge me-1"></i> Role *</label>
                    <select name="role" class="form-control" required>
                        <option value="">— Select Role —</option>
                        <?php foreach ($roles as $r): ?>
                        <option value="<?= h($r) ?>" <?= (($editRow['ROLE'] ?? '') === $r) ? 'selected' : '' ?>>
                            <?= h($r) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fa-solid fa-dollar-sign me-1"></i> Salary</label>
                    <input type="number" name="salary" class="form-control" step="0.01" min="0"
                           value="<?= h($editRow['SALARY'] ?? '0') ?>">
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-calendar me-1"></i> Hire Date</label>
                    <input type="date" name="hire_date" class="form-control"
                           value="<?= h($editRow['HIRE_DATE'] ?? date('Y-m-d')) ?>">
                </div>
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
                <a href="<?= BASE_URL ?>frontend/pages/employees.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php else: ?>

<!-- Page Stats -->
<div class="page-stats">
    <div class="page-stat-item">
        <i class="fa-solid fa-users"></i>
        <div><div class="page-stat-num"><?= (int)($rAll['C'] ?? 0) ?></div><div class="page-stat-lbl"><?= t('page_employees') ?></div></div>
    </div>
    <div class="page-stat-item">
        <i class="fa-solid fa-circle-check"></i>
        <div><div class="page-stat-num"><?= (int)($rAct['C'] ?? 0) ?></div><div class="page-stat-lbl"><?= t('status_active') ?></div></div>
    </div>
    <div class="page-stat-item">
        <i class="fa-solid fa-user-plus"></i>
        <div><div class="page-stat-num"><?= (int)($rNew['C'] ?? 0) ?></div><div class="page-stat-lbl">Hired This Month</div></div>
    </div>
    <div class="page-stat-item">
        <i class="fa-solid fa-file-lines"></i>
        <div><div class="page-stat-num"><?= $pag['total_pages'] ?></div><div class="page-stat-lbl"><?= t('lbl_pages') ?></div></div>
    </div>
</div>

<!-- Filter Bar -->
<form method="GET" action="" class="filter-bar">
    <span class="filter-label"><i class="fa-solid fa-filter me-1"></i><?= t('lbl_filter') ?></span>
    <input type="text" name="q" class="form-control" style="max-width:220px;"
           placeholder="Search name / phone…" value="<?= h($search) ?>">
    <select name="role_filter" class="form-control" style="max-width:150px;">
        <option value=""><?= t('lbl_all_types') ?></option>
        <?php foreach ($roles as $r): ?>
        <option value="<?= h($r) ?>" <?= $filterRole === $r ? 'selected' : '' ?>><?= h($r) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="status_filter" class="form-control" style="max-width:130px;">
        <option value=""><?= t('lbl_all_statuses') ?></option>
        <option value="Active"   <?= $filterStatus === 'Active'   ? 'selected' : '' ?>><?= t('status_active') ?></option>
        <option value="Inactive" <?= $filterStatus === 'Inactive' ? 'selected' : '' ?>><?= t('status_inactive') ?></option>
    </select>
    <button type="submit" class="btn btn-dark btn-sm">
        <i class="fa-solid fa-magnifying-glass me-1"></i><?= t('btn_search') ?>
    </button>
    <?php if ($search || $filterRole || $filterStatus): ?>
    <a href="<?= BASE_URL ?>frontend/pages/employees.php" class="btn btn-outline btn-sm">
        <i class="fa-solid fa-xmark me-1"></i><?= t('btn_clear') ?>
    </a>
    <?php endif; ?>
    <?php if ($canWrite): ?>
    <div class="ms-auto">
        <?= exportBtn('employees.php') ?> <a href="?action=new" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-user-plus me-1"></i><?= t('btn_add') ?> <?= t('page_employees') ?>
        </a>
    </div>
    <?php endif; ?>
</form>

<!-- Table -->
<div class="card">
    <div class="card-header">
        <h3><i class="fa-solid fa-id-badge me-2" style="color:var(--primary);"></i>
            Employees
            <span class="badge bg-secondary ms-1" style="font-size:11px;"><?= $totalCount ?></span>
        </h3>
        <span style="font-size:12px;color:var(--text-muted);">
            <?= t('lbl_showing') ?> <?= count($employees) ?> <?= t('lbl_of') ?> <?= $totalCount ?> <?= t('lbl_records') ?>
        </span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($employees)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-users-slash"></i>
            <p><?= t('empty_no_data') ?><?= ($search || $filterRole || $filterStatus) ? ' Try different filters.' : '' ?></p>
        </div>
        <?php else: ?>
        <div class="table-wrapper">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th><?= t('col_id') ?></th><th><?= t('col_name') ?></th><th><?= t('col_phone') ?></th><th><?= t('col_role') ?></th>
                    <th><?= t('col_salary') ?></th><th><?= t('col_hire_date') ?></th><th><?= t('col_status') ?></th>
                    <?php if ($canWrite): ?><th><?= t('col_actions') ?></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($employees as $e): ?>
            <tr>
                <td><span class="text-muted"><?= h($e['EMPLOYEE_ID']) ?></span></td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--primary-dk));display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;font-weight:700;flex-shrink:0;">
                            <?= strtoupper(substr($e['FIRST_NAME'], 0, 1)) ?>
                        </div>
                        <strong><?= h($e['FIRST_NAME'] . ' ' . $e['LAST_NAME']) ?></strong>
                    </div>
                </td>
                <td><?= h($e['PHONE'] ?? '—') ?></td>
                <td>
                    <span class="badge bg-secondary" style="font-size:11px;"><?= h($e['ROLE'] ?? '—') ?></span>
                </td>
                <td><?= money((float)($e['SALARY'] ?? 0)) ?></td>
                <td><?= h($e['HIRE_DATE'] ?? '—') ?></td>
                <td><?= statusBadge($e['STATUS'] ?? 'Inactive') ?></td>
                <?php if ($canWrite): ?>
                <td>
                    <a href="?action=edit&id=<?= (int)$e['EMPLOYEE_ID'] ?>" class="btn btn-warning btn-sm me-1">
                        <i class="fa-solid fa-pen"></i>
                    </a>
                    <a href="?action=confirm_delete&id=<?= (int)$e['EMPLOYEE_ID'] ?>" class="btn btn-danger btn-sm">
                        <i class="fa-solid fa-trash"></i>
                    </a>
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
