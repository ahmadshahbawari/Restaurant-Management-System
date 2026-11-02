<?php
require_once __DIR__ . '/../../backend/config/bootstrap.php';
if (!defined('BASE_URL')) { define('BASE_URL', '/RestaurantDB/'); }
$pageTitle = 'Suppliers';
require_once __DIR__ . '/../../backend/includes/supplier.php';
require_once __DIR__ . '/../../backend/includes/inventory.php';
require_once __DIR__ . '/../../backend/includes/rbac.php';
require_once __DIR__ . '/../../backend/includes/notifications.php';

requireAccess('suppliers');
$canWrite = canWrite('suppliers');

$action = get('action', 'list');
$id     = (int)get('id', '0');

/* ═══ POST ══════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canWrite) {
    $postAction = post('action');
    $postId     = (int)post('id');

    if ($postAction === 'create' || $postAction === 'update') {
        $data = [
            'supplier_name' => post('supplier_name'),
            'phone'         => post('phone'),
            'email'         => post('email'),
            'address'       => post('address'),
            'status'        => post('status','Active'),
        ];
        if ($postAction === 'create') {
            if (createSupplier($data)) {
                notifyNewSupplier($data['supplier_name']);
                setFlash('success','Supplier added.');
            } else { setFlash('danger','Failed to add supplier.'); }
        } else {
            updateSupplier($postId,$data)
                ? setFlash('success','Supplier updated.')
                : setFlash('danger','Update failed.');
        }
        redirect('frontend/pages/suppliers.php');

    } elseif ($postAction === 'delete') {
        deleteSupplier($postId)
            ? setFlash('success','Supplier deleted.')
            : setFlash('danger','Cannot delete — linked to inventory items.');
        redirect('frontend/pages/suppliers.php');

    } elseif ($postAction === 'link_inventory') {
        $suppId = (int)post('supplier_id');
        $invId  = (int)post('inventory_id');
        $price  = (float)post('supply_price');
        $date   = post('last_supply_date') ?: date('Y-m-d');
        upsertSupplierInventory($suppId,$invId,$price,$date)
            ? setFlash('success','Inventory link saved.')
            : setFlash('danger','Failed to save link.');
        while(ob_get_level()){ ob_end_clean(); }
        header('Location: '.BASE_URL.'frontend/pages/suppliers.php?action=view&id='.$suppId);
        exit;

    } elseif ($postAction === 'delete_link') {
        /* Remove supplier-inventory link (issue #7) */
        $suppId = (int)post('supplier_id');
        $invId  = (int)post('inventory_id');
        try {
            $conn = getDBConnection();
            $sql  = "DELETE FROM SUPPLIER_INVENTORY WHERE SUPPLIER_ID=:sid AND INVENTORY_ID=:iid";
            $stmt = oci_parse($conn,$sql);
            oci_bind_by_name($stmt,':sid',$suppId);
            oci_bind_by_name($stmt,':iid',$invId);
            oci_execute($stmt);
            oci_free_statement($stmt);
            oci_close($conn);
            setFlash('success','Inventory link removed.');
        } catch(Throwable $e) {
            setFlash('danger','Failed to remove link.');
        }
        while(ob_get_level()){ ob_end_clean(); }
        header('Location: '.BASE_URL.'frontend/pages/suppliers.php?action=view&id='.$suppId);
        exit;
    }
}

/* ═══ Data ════════════════════════════════════════════════ */
$search       = get('q');
$filterSt     = get('st_filter');
$allSuppliers = getAllSuppliers();

if ($search || $filterSt) {
    $allSuppliers = array_filter($allSuppliers, function($s) use ($search,$filterSt) {
        if ($search && stripos($s['SUPPLIER_NAME'],$search)===false
            && stripos($s['EMAIL']??'',$search)===false) return false;
        if ($filterSt && $s['STATUS']!==$filterSt) return false;
        return true;
    });
    $allSuppliers = array_values($allSuppliers);
}

$totalCount = count($allSuppliers);
$perPage    = (int)getSetting('records_per_page','10');
$pag        = paginate($totalCount, $perPage);
$suppliers  = array_slice($allSuppliers, $pag['offset'], $pag['limit']);

$editRow    = ($action==='edit'           && $id) ? getSupplierById($id)   : null;
$viewRow    = ($action==='view'           && $id) ? getSupplierById($id)   : null;
$confirmRow = ($action==='confirm_delete' && $id) ? getSupplierById($id)   : null;
$supInv     = ($action==='view'           && $id) ? getSupplierInventory($id) : [];
$allInv     = ($action==='view')                  ? getAllInventory()       : [];

$conn = getDBConnection();
$qa = oci_parse($conn,"SELECT COUNT(*) C FROM SUPPLIER");           oci_execute($qa); $rTotal=oci_fetch_assoc($qa); oci_free_statement($qa);
$qb = oci_parse($conn,"SELECT COUNT(*) C FROM SUPPLIER WHERE STATUS='Active'"); oci_execute($qb); $rAct=oci_fetch_assoc($qb); oci_free_statement($qb);
$qc = oci_parse($conn,"SELECT COUNT(*) C FROM SUPPLIER_INVENTORY"); oci_execute($qc); $rLinks=oci_fetch_assoc($qc); oci_free_statement($qc);
oci_close($conn);

/* ═══ HTML ════════════════════════════════════════════════ */
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($confirmRow): ?>
<div class="card" style="max-width:500px;">
    <div class="card-header">
        <h3><i class="fa-solid fa-trash me-2"></i><?= t('confirm_delete') ?></h3>
    </div>
    <div class="card-body">
        <div class="alert alert-warning">
            <i class="fa-solid fa-triangle-exclamation me-1"></i>
            Delete supplier <strong><?= h($confirmRow['SUPPLIER_NAME']) ?></strong>? <?= t('confirm_msg') ?>
        </div>
        <div class="d-flex gap-2">
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$confirmRow['SUPPLIER_ID'] ?>">
                <button type="submit" class="btn btn-danger">
                    <i class="fa-solid fa-trash me-1"></i>Yes, Delete
                </button>
            </form>
            <a href="<?= BASE_URL ?>frontend/pages/suppliers.php" class="btn btn-outline"><?= t('btn_cancel') ?></a>
        </div>
    </div>
</div>

<?php elseif ($action==='new' || $action==='edit'): ?>
<div class="card" style="max-width:640px;">
    <div class="card-header">
        <h3>
            <i class="fa-solid <?= $action==='new'?'fa-plus':'fa-pen' ?> me-2"></i>
            <?= $action==='new' ? 'Add Supplier' : 'Edit Supplier' ?>
        </h3>
        <a href="<?= BASE_URL ?>frontend/pages/suppliers.php" class="btn btn-outline btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i><?= t('btn_back') ?>
        </a>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="<?= $action==='new'?'create':'update' ?>">
            <?php if ($editRow): ?>
            <input type="hidden" name="id" value="<?= (int)$editRow['SUPPLIER_ID'] ?>">
            <?php endif; ?>
            <div class="form-group">
                <label><i class="fa-solid fa-truck me-1"></i> Supplier Name *</label>
                <input type="text" name="supplier_name" class="form-control" required
                       value="<?= h($editRow['SUPPLIER_NAME']??'') ?>">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fa-solid fa-phone me-1"></i> Phone</label>
                    <input type="text" name="phone" class="form-control"
                           value="<?= h($editRow['PHONE']??'') ?>">
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-envelope me-1"></i> Email</label>
                    <input type="email" name="email" class="form-control"
                           value="<?= h($editRow['EMAIL']??'') ?>">
                </div>
            </div>
            <div class="form-group">
                <label><i class="fa-solid fa-location-dot me-1"></i> Address</label>
                <textarea name="address" class="form-control"><?= h($editRow['ADDRESS']??'') ?></textarea>
            </div>
            <div class="form-group">
                <label><i class="fa-solid fa-circle-dot me-1"></i> Status</label>
                <select name="status" class="form-control">
                    <option value="Active"   <?= (($editRow['STATUS']??'Active')==='Active'  )?'selected':'' ?>><?= t('status_active') ?></option>
                    <option value="Inactive" <?= (($editRow['STATUS']??'')==='Inactive')?'selected':'' ?>><?= t('status_inactive') ?></option>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk me-1"></i><?= t('btn_save') ?>
                </button>
                <a href="<?= BASE_URL ?>frontend/pages/suppliers.php" class="btn btn-outline"><?= t('btn_cancel') ?></a>
            </div>
        </form>
    </div>
</div>

<?php elseif ($action==='view' && $viewRow): ?>
<!-- ═══ SUPPLIER DETAIL VIEW ═════════════════════════════ -->
<div class="row g-4">
    <!-- Left: details + inventory table -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h3>
                    <i class="fa-solid fa-truck me-2" style="color:var(--primary);"></i>
                    <?= h($viewRow['SUPPLIER_NAME']) ?>
                </h3>
                <div class="d-flex gap-2">
                    <?php if ($canWrite): ?>
                    <a href="?action=edit&id=<?= (int)$viewRow['SUPPLIER_ID'] ?>" class="btn btn-warning btn-sm">
                        <i class="fa-solid fa-pen me-1"></i>Edit
                    </a>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>frontend/pages/suppliers.php" class="btn btn-outline btn-sm">
                        <i class="fa-solid fa-arrow-left me-1"></i><?= t('btn_back') ?>
                    </a>
                </div>
            </div>
            <div class="card-body">
                <!-- Info grid -->
                <div class="row g-3 mb-4">
                    <?php
                    $info = [
                        ['Phone',   $viewRow['PHONE']??'—',   'fa-phone'],
                        ['Email',   $viewRow['EMAIL']??'—',   'fa-envelope'],
                        ['Address', $viewRow['ADDRESS']??'—', 'fa-location-dot'],
                        ['Status',  null,                      'fa-circle-dot'],
                    ];
                    foreach ($info as $inf):
                    ?>
                    <div class="col-6">
                        <div style="background:var(--surface2);border-radius:8px;padding:12px;border:1px solid var(--border);">
                            <div style="font-size:10px;text-transform:uppercase;color:var(--text-muted);margin-bottom:4px;">
                                <i class="fa-solid <?= $inf[2] ?> me-1"></i><?= $inf[0] ?>
                            </div>
                            <?php if ($inf[0]==='Status'): ?>
                                <?= statusBadge($viewRow['STATUS']??'Inactive') ?>
                            <?php else: ?>
                                <strong style="font-size:13px;"><?= h($inf[1]) ?></strong>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Inventory table -->
                <h4 style="font-size:13px;font-weight:700;margin-bottom:12px;">
                    <i class="fa-solid fa-boxes-stacked me-2" style="color:var(--primary);"></i>
                    Supplied Inventory Items
                </h4>
                <?php if (empty($supInv)): ?>
                <div class="empty-state" style="padding:24px;">
                    <i class="fa-solid fa-box-open"></i>
                    <p>No inventory items linked. Use the panel on the right to link items.</p>
                </div>
                <?php else: ?>
                <?php
                $totalSupplyVal = 0;
                foreach ($supInv as $si) { $totalSupplyVal += (float)$si['SUPPLY_PRICE']; }
                ?>
                <div class="table-wrapper">
                <table class="table mb-0">
                    <thead>
                        <tr><th>Item</th><th>Unit</th><th>Supply Price</th><th>Last Supply Date</th><?php if($canWrite):?><th>Action</th><?php endif;?></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($supInv as $si): ?>
                    <tr>
                        <td><strong><?= h($si['ITEM_NAME']) ?></strong></td>
                        <td><?= h($si['UNIT']??'—') ?></td>
                        <td><strong style="color:var(--primary);"><?= money((float)$si['SUPPLY_PRICE']) ?></strong></td>
                        <td class="text-muted"><?= h($si['LAST_SUPPLY_DATE']??'—') ?></td>
                        <?php if($canWrite): ?>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action"      value="delete_link">
                                <input type="hidden" name="supplier_id" value="<?= (int)$viewRow['SUPPLIER_ID'] ?>">
                                <input type="hidden" name="inventory_id"value="<?= (int)$si['INVENTORY_ID'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm"
                                        title="Remove this inventory link">
                                    <i class="fa-solid fa-unlink"></i>
                                </button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <div class="p-3 border-top d-flex justify-content-end">
                    <div style="font-size:13px;">
                        Total Supply Value:
                        <strong style="font-size:16px;color:var(--primary);margin-left:8px;">
                            <?= money($totalSupplyVal) ?>
                        </strong>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right: link inventory panel -->
    <?php if ($canWrite): ?>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3><i class="fa-solid fa-link me-2"></i>Link Inventory Item</h3>
            </div>
            <div class="card-body">
                <p class="text-muted" style="font-size:13px;margin-bottom:16px;">
                    Link an inventory item to this supplier. Existing links will be updated.
                </p>
                <form method="POST">
                    <input type="hidden" name="action" value="link_inventory">
                    <input type="hidden" name="supplier_id" value="<?= (int)$viewRow['SUPPLIER_ID'] ?>">
                    <div class="form-group">
                        <label><i class="fa-solid fa-box me-1"></i> Inventory Item *</label>
                        <select name="inventory_id" class="form-control" required>
                            <option value="">— Select Item —</option>
                            <?php foreach ($allInv as $inv): ?>
                            <option value="<?= (int)$inv['INVENTORY_ID'] ?>">
                                <?= h($inv['ITEM_NAME'].' ('.(($inv['UNIT'])??'—').')') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fa-solid fa-coins me-1"></i> Supply Price *</label>
                        <input type="number" name="supply_price" class="form-control"
                               step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fa-solid fa-calendar me-1"></i> Last Supply Date</label>
                        <input type="date" name="last_supply_date" class="form-control"
                               value="<?= date('Y-m-d') ?>">
                    </div>
                    <button type="submit" class="btn btn-primary w-100" style="justify-content:center;">
                        <i class="fa-solid fa-link me-1"></i><?= t('btn_save') ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php else: ?>
<!-- ═══ SUPPLIER LIST ═════════════════════════════════════ -->

<!-- Page Stats -->
<div class="page-stats mb-4">
    <div class="page-stat-item">
        <i class="fa-solid fa-truck"></i>
        <div><div class="page-stat-num"><?= (int)($rTotal['C']??0) ?></div><div class="page-stat-lbl"><?= t('page_suppliers') ?></div></div>
    </div>
    <div class="page-stat-item">
        <i class="fa-solid fa-circle-check"></i>
        <div><div class="page-stat-num"><?= (int)($rAct['C']??0) ?></div><div class="page-stat-lbl"><?= t('status_active') ?></div></div>
    </div>
    <div class="page-stat-item">
        <i class="fa-solid fa-link"></i>
        <div><div class="page-stat-num"><?= (int)($rLinks['C']??0) ?></div><div class="page-stat-lbl">Inventory Links</div></div>
    </div>
    <div class="page-stat-item">
        <i class="fa-solid fa-file-lines"></i>
        <div><div class="page-stat-num"><?= $pag['total_pages'] ?></div><div class="page-stat-lbl"><?= t('lbl_pages') ?></div></div>
    </div>
</div>

<!-- Filter Bar -->
<form method="GET" action="" class="filter-bar mb-3">
    <span class="filter-label"><i class="fa-solid fa-filter me-1"></i><?= t('lbl_filter') ?></span>
    <input type="text" name="q" class="form-control" style="max-width:240px;"
           placeholder="Search name / email…" value="<?= h($search) ?>">
    <select name="st_filter" class="form-control" style="max-width:140px;">
        <option value=""><?= t('lbl_all_statuses') ?></option>
        <option value="Active"   <?= $filterSt==='Active'  ?'selected':'' ?>><?= t('status_active') ?></option>
        <option value="Inactive" <?= $filterSt==='Inactive'?'selected':'' ?>><?= t('status_inactive') ?></option>
    </select>
    <button type="submit" class="btn btn-dark btn-sm">
        <i class="fa-solid fa-magnifying-glass me-1"></i><?= t('btn_search') ?>
    </button>
    <?php if ($search||$filterSt): ?>
    <a href="<?= BASE_URL ?>frontend/pages/suppliers.php" class="btn btn-outline btn-sm">
        <i class="fa-solid fa-xmark me-1"></i><?= t('btn_clear') ?>
    </a>
    <?php endif; ?>
    <?php if ($canWrite): ?>
    <div class="ms-auto">
        <?= exportBtn('suppliers.php') ?> <a href="?action=new" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-plus me-1"></i><?= t('btn_add') ?> <?= t('page_suppliers') ?>
        </a>
    </div>
    <?php endif; ?>
</form>

<div class="card">
    <div class="card-header">
        <h3>
            <i class="fa-solid fa-truck me-2" style="color:var(--primary);"></i>
            Suppliers
            <span class="badge bg-secondary ms-1" style="font-size:11px;"><?= $totalCount ?></span>
        </h3>
        <span style="font-size:12px;color:var(--text-muted);"><?= t('lbl_showing') ?> <?= count($suppliers) ?> <?= t('lbl_of') ?> <?= $totalCount ?></span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($suppliers)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-truck"></i>
            <p><?= t('empty_no_data') ?><?= ($search||$filterSt)?' Try different filters.':'' ?></p>
        </div>
        <?php else: ?>
        <div class="table-wrapper">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>#</th><th><?= t('col_name') ?></th><th><?= t('col_phone') ?></th><th><?= t('col_email') ?></th>
                    <th><?= t('col_address') ?></th><th><?= t('col_status') ?></th><th><?= t('col_actions') ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($suppliers as $s): ?>
            <tr>
                <td class="text-muted"><?= h($s['SUPPLIER_ID']) ?></td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:32px;height:32px;border-radius:8px;
                                    background:linear-gradient(135deg,var(--primary),var(--primary-dk));
                                    display:flex;align-items:center;justify-content:center;
                                    color:#fff;flex-shrink:0;">
                            <i class="fa-solid fa-truck" style="font-size:12px;"></i>
                        </div>
                        <strong><?= h($s['SUPPLIER_NAME']) ?></strong>
                    </div>
                </td>
                <td><?= h($s['PHONE']??'—') ?></td>
                <td><?= h($s['EMAIL']??'—') ?></td>
                <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    <?= h($s['ADDRESS']??'—') ?>
                </td>
                <td><?= statusBadge($s['STATUS']??'Inactive') ?></td>
                <td>
                    <a href="?action=view&id=<?= (int)$s['SUPPLIER_ID'] ?>" class="btn btn-info btn-sm me-1">
                        <i class="fa-solid fa-eye"></i>
                    </a>
                    <?php if ($canWrite): ?>
                    <a href="?action=edit&id=<?= (int)$s['SUPPLIER_ID'] ?>" class="btn btn-warning btn-sm me-1">
                        <i class="fa-solid fa-pen"></i>
                    </a>
                    <a href="?action=confirm_delete&id=<?= (int)$s['SUPPLIER_ID'] ?>" class="btn btn-danger btn-sm">
                        <i class="fa-solid fa-trash"></i>
                    </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php if ($pag['total_pages']>1): ?>
        <div class="p-3 border-top"><?= $pag['html'] ?></div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
