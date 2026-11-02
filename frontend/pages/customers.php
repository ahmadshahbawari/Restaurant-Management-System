<?php
require_once __DIR__ . '/../../backend/config/bootstrap.php';
if (!defined('BASE_URL')) { define('BASE_URL', '/RestaurantDB/'); }
$pageTitle = 'Customers';
require_once __DIR__ . '/../../backend/includes/customer.php';
require_once __DIR__ . '/../../backend/includes/rbac.php';
require_once __DIR__ . '/../../backend/includes/notifications.php';

requireAccess('customers');
$canWrite = canWrite('customers');

$action = get('action', 'list');
$id     = (int)get('id', '0');

/*  POST  */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canWrite) {
    $data = ['first_name'=>post('first_name'),'last_name'=>post('last_name'),
             'phone'=>post('phone'),'email'=>post('email'),'address'=>post('address')];
    $pa = post('action'); $pid = (int)post('id');
    if ($pa==='create') {
        if (createCustomer($data)) {
            notifyNewCustomer($data['first_name'].' '.$data['last_name']);
            setFlash('success','Customer added.');
        } else { setFlash('danger','Failed.'); }
    }
    if ($pa==='update')  { updateCustomer($pid,$data)  ? setFlash('success','Customer updated.') : setFlash('danger','Failed.'); }
    if ($pa==='delete')  { deleteCustomer($pid)        ? setFlash('success','Customer deleted.') : setFlash('danger','Cannot delete — has orders.'); }
    redirect('frontend/pages/customers.php');
}

require_once __DIR__ . '/../includes/header.php';

/*  Data  */
$search     = get('q');
$allCustomers = $search ? searchCustomers($search) : getAllCustomers();
$totalCount   = count($allCustomers);
$perPage      = (int)getSetting('records_per_page','10');
$pag          = paginate($totalCount, $perPage);
$customers    = array_slice($allCustomers, $pag['offset'], $pag['limit']);

$editRow    = ($action === 'edit'           && $id) ? getCustomerById($id) : null;
$confirmRow = ($action === 'confirm_delete' && $id) ? getCustomerById($id) : null;

/* Stats */
$conn = getDBConnection();
$sNew = oci_parse($conn,"SELECT COUNT(*) C FROM CUSTOMER WHERE REGISTRATION_DATE >= SYSDATE-30"); oci_execute($sNew); $rNew=oci_fetch_assoc($sNew); oci_free_statement($sNew);
oci_close($conn);
?>

<?php if ($confirmRow): ?>
<div class="card" style="max-width:500px;">
    <div class="card-header"><h3><i class="fa-solid fa-trash me-2"></i><?= t('confirm_delete') ?></h3></div>
    <div class="card-body">
        <div class="alert alert-warning">
            <i class="fa-solid fa-triangle-exclamation me-1"></i>
            Delete customer <strong><?= h($confirmRow['FIRST_NAME'].' '.$confirmRow['LAST_NAME']) ?></strong>? <?= t('confirm_msg') ?>
        </div>
        <div class="d-flex gap-2">
            <form method="POST"><input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$confirmRow['CUSTOMER_ID'] ?>">
                <button type="submit" class="btn btn-danger"><i class="fa-solid fa-trash me-1"></i><?= t('btn_confirm_del') ?></button>
            </form>
            <a href="<?= BASE_URL ?>frontend/pages/customers.php" class="btn btn-outline"><?= t('btn_cancel') ?></a>
        </div>
    </div>
</div>

<?php elseif ($action === 'new' || $action === 'edit'): ?>
<div class="card" style="max-width:680px;">
    <div class="card-header">
        <h3><i class="fa-solid <?= $action==='new'?'fa-user-plus':'fa-user-pen' ?> me-2"></i>
            <?= $action==='new' ? 'Add New Customer' : 'Edit Customer' ?></h3>
        <a href="<?= BASE_URL ?>frontend/pages/customers.php" class="btn btn-outline btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i><?= t('btn_back') ?>
        </a>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="<?= $action==='new'?'create':'update' ?>">
            <?php if ($editRow): ?><input type="hidden" name="id" value="<?= (int)$editRow['CUSTOMER_ID'] ?>"><?php endif; ?>
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fa-solid fa-user me-1"></i> First Name *</label>
                    <input type="text" name="first_name" class="form-control" required value="<?= h($editRow['FIRST_NAME']??'') ?>">
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-user me-1"></i> Last Name *</label>
                    <input type="text" name="last_name" class="form-control" required value="<?= h($editRow['LAST_NAME']??'') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fa-solid fa-phone me-1"></i> Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= h($editRow['PHONE']??'') ?>">
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-envelope me-1"></i> Email</label>
                    <input type="email" name="email" class="form-control" value="<?= h($editRow['EMAIL']??'') ?>">
                </div>
            </div>
            <div class="form-group">
                <label><i class="fa-solid fa-location-dot me-1"></i> Address</label>
                <textarea name="address" class="form-control"><?= h($editRow['ADDRESS']??'') ?></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i><?= t('btn_save') ?></button>
                <a href="<?= BASE_URL ?>frontend/pages/customers.php" class="btn btn-outline"><?= t('btn_cancel') ?></a>
            </div>
        </form>
    </div>
</div>

<?php else: ?>

<!-- Page Stats -->
<div class="page-stats">
    <div class="page-stat-item">
        <i class="fa-solid fa-users"></i>
        <div><div class="page-stat-num"><?= $totalCount ?></div><div class="page-stat-lbl"><?= t('dash_total_customers') ?></div></div>
    </div>
    <div class="page-stat-item">
        <i class="fa-solid fa-user-plus"></i>
        <div><div class="page-stat-num"><?= (int)($rNew['C']??0) ?></div><div class="page-stat-lbl">New This Month</div></div>
    </div>
    <div class="page-stat-item">
        <i class="fa-solid fa-file-lines"></i>
        <div><div class="page-stat-num"><?= $pag['total_pages'] ?></div><div class="page-stat-lbl"><?= t('lbl_pages') ?></div></div>
    </div>
</div>

<!-- Filter Bar -->
<form method="GET" action="" class="filter-bar">
    <span class="filter-label"><i class="fa-solid fa-filter me-1"></i><?= t('lbl_filter') ?></span>
    <input type="text" name="q" class="form-control" style="max-width:250px;"
           placeholder="Search name / email / phone…" value="<?= h($search) ?>">
    <button type="submit" class="btn btn-dark btn-sm">
        <i class="fa-solid fa-magnifying-glass me-1"></i><?= t('btn_search') ?>
    </button>
    <?php if ($search): ?>
    <a href="<?= BASE_URL ?>frontend/pages/customers.php" class="btn btn-outline btn-sm">
        <i class="fa-solid fa-xmark me-1"></i><?= t('btn_clear') ?>
    </a>
    <?php endif; ?>
    <?php if ($canWrite): ?>
    <div class="ms-auto">
        <?= exportBtn('customers.php') ?> <a href="?action=new" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-user-plus me-1"></i><?= t('cust_add') ?>
        </a>
    </div>
    <?php endif; ?>
</form>

<!-- Table -->
<div class="card">
    <div class="card-header">
        <h3><i class="fa-solid fa-users me-2" style="color:var(--primary);"></i>
            Customers
            <span class="badge bg-secondary ms-1" style="font-size:11px;"><?= $totalCount ?></span>
        </h3>
        <span style="font-size:12px;color:var(--text-muted);">
            Showing <?= count($customers) ?> of <?= $totalCount ?> records
        </span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($customers)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-users-slash"></i>
            <p><?= t('empty_no_data') ?><?= $search ? ' Try a different search.' : '' ?></p>
        </div>
        <?php else: ?>
        <div class="table-wrapper">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>#</th><th>Name</th><th>Phone</th><th>Email</th>
                    <th>Address</th><th>Registered</th>
                    <?php if ($canWrite): ?><th><?= t('col_actions') ?></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($customers as $c): ?>
            <tr>
                <td><span class="text-muted"><?= h($c['CUSTOMER_ID']) ?></span></td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--primary-dk));display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;font-weight:700;flex-shrink:0;">
                            <?= strtoupper(substr($c['FIRST_NAME'],0,1)) ?>
                        </div>
                        <strong><?= h($c['FIRST_NAME'].' '.$c['LAST_NAME']) ?></strong>
                    </div>
                </td>
                <td><?= h($c['PHONE']??'—') ?></td>
                <td><?= h($c['EMAIL']??'—') ?></td>
                <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= h($c['ADDRESS']??'—') ?></td>
                <td><?= h($c['REGISTRATION_DATE']??'—') ?></td>
                <?php if ($canWrite): ?>
                <td>
                    <a href="?action=edit&id=<?= (int)$c['CUSTOMER_ID'] ?>" class="btn btn-warning btn-sm me-1">
                        <i class="fa-solid fa-pen"></i>
                    </a>
                    <a href="?action=confirm_delete&id=<?= (int)$c['CUSTOMER_ID'] ?>" class="btn btn-danger btn-sm">
                        <i class="fa-solid fa-trash"></i>
                    </a>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <!-- Pagination -->
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
