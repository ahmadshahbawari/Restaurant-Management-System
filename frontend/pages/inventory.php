<?php
require_once __DIR__ . '/../../backend/config/bootstrap.php';
if (!defined('BASE_URL')) { define('BASE_URL', '/RestaurantDB/'); }
$pageTitle = 'Inventory';
require_once __DIR__ . '/../../backend/includes/inventory.php';
require_once __DIR__ . '/../../backend/includes/rbac.php';
require_once __DIR__ . '/../../backend/includes/notifications.php';

requireAccess('inventory');
$canWrite = canWrite('inventory');

$action = get('action', 'list');
$id     = (int)get('id', '0');

/*  POST  */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canWrite) {
    $data = [
        'item_name'         => post('item_name'),
        'unit'              => post('unit'),
        'quantity_in_stock' => (float)post('quantity_in_stock'),
        'reorder_level'     => (float)post('reorder_level'),
        'unit_cost'         => (float)post('unit_cost'),
    ];
    $pa = post('action'); $pid = (int)post('id');
    if ($pa === 'create') {
        if (createInventoryItem($data)) {
            /* Check if newly added item is already low stock */
            if ($data['quantity_in_stock'] <= $data['reorder_level']) {
                notifyLowStock($data['item_name'], $data['quantity_in_stock'], $data['unit']);
            }
            setFlash('success','Item added.');
        } else { setFlash('danger','Failed to add.'); }
    }
    if ($pa === 'update') {
        if (updateInventoryItem($pid, $data)) {
            if ($data['quantity_in_stock'] <= $data['reorder_level']) {
                notifyLowStock($data['item_name'], $data['quantity_in_stock'], $data['unit']);
            }
            setFlash('success','Item updated.');
        } else { setFlash('danger','Update failed.'); }
    }
    if ($pa === 'delete') { deleteInventoryItem($pid) ? setFlash('success','Item deleted.') : setFlash('danger','Cannot delete — linked to a supplier.'); }
    redirect('frontend/pages/inventory.php');
}

require_once __DIR__ . '/../includes/header.php';

/*  Data  */
$search      = get('q');
$filterStock = get('stock_filter');
$allItems    = getAllInventory();
$lowStock    = getLowStockItems();

if ($search || $filterStock) {
    $allItems = array_filter($allItems, function($inv) use ($search, $filterStock) {
        if ($search && strpos(strtolower($inv['ITEM_NAME']), strtolower($search)) === false
            && strpos(strtolower($inv['UNIT'] ?? ''), strtolower($search)) === false) return false;
        if ($filterStock === 'low'  && !($inv['QUANTITY_IN_STOCK'] <= $inv['REORDER_LEVEL'])) return false;
        if ($filterStock === 'ok'   && ($inv['QUANTITY_IN_STOCK'] <= $inv['REORDER_LEVEL']))  return false;
        return true;
    });
    $allItems = array_values($allItems);
}

$totalCount = count($allItems);
$perPage    = (int)getSetting('records_per_page', '10');
$pag        = paginate($totalCount, $perPage);
$items      = array_slice($allItems, $pag['offset'], $pag['limit']);

$editRow    = ($action === 'edit'           && $id) ? getInventoryById($id) : null;
$confirmRow = ($action === 'confirm_delete' && $id) ? getInventoryById($id) : null;

/*  Stats  */
$conn    = getDBConnection();
$sTotal  = oci_parse($conn, "SELECT COUNT(*) C FROM INVENTORY_ITEM"); oci_execute($sTotal); $rTotal=oci_fetch_assoc($sTotal); oci_free_statement($sTotal);
$sLow    = oci_parse($conn, "SELECT COUNT(*) C FROM INVENTORY_ITEM WHERE QUANTITY_IN_STOCK <= REORDER_LEVEL"); oci_execute($sLow); $rLow=oci_fetch_assoc($sLow); oci_free_statement($sLow);
$sVal    = oci_parse($conn, "SELECT NVL(SUM(QUANTITY_IN_STOCK * UNIT_COST),0) V FROM INVENTORY_ITEM"); oci_execute($sVal); $rVal=oci_fetch_assoc($sVal); oci_free_statement($sVal);
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
            Delete inventory item <strong><?= h($confirmRow['ITEM_NAME']) ?></strong>? <?= t('confirm_msg') ?>
        </div>
        <div class="d-flex gap-2">
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$confirmRow['INVENTORY_ID'] ?>">
                <button type="submit" class="btn btn-danger"><i class="fa-solid fa-trash me-1"></i><?= t('btn_confirm_del') ?></button>
            </form>
            <a href="<?= BASE_URL ?>frontend/pages/inventory.php" class="btn btn-outline"><?= t('btn_cancel') ?></a>
        </div>
    </div>
</div>

<?php elseif ($action === 'new' || $action === 'edit'): ?>
<div class="card" style="max-width:640px;">
    <div class="card-header">
        <h3><i class="fa-solid <?= $action === 'new' ? 'fa-plus' : 'fa-pen' ?> me-2"></i>
            <?= $action === 'new' ? 'Add Inventory Item' : 'Edit Inventory Item' ?></h3>
        <a href="<?= BASE_URL ?>frontend/pages/inventory.php" class="btn btn-outline btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i><?= t('btn_back') ?>
        </a>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="<?= $action === 'new' ? 'create' : 'update' ?>">
            <?php if ($editRow): ?><input type="hidden" name="id" value="<?= (int)$editRow['INVENTORY_ID'] ?>"><?php endif; ?>
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fa-solid fa-box me-1"></i> Item Name *</label>
                    <input type="text" name="item_name" class="form-control" required
                           value="<?= h($editRow['ITEM_NAME'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-scale-balanced me-1"></i> Unit (kg, litre, pcs…)</label>
                    <input type="text" name="unit" class="form-control"
                           value="<?= h($editRow['UNIT'] ?? '') ?>">
                </div>
            </div>
            <div class="form-row-3">
                <div class="form-group">
                    <label><i class="fa-solid fa-cubes me-1"></i> Qty In Stock</label>
                    <input type="number" name="quantity_in_stock" class="form-control" step="0.01" min="0"
                           value="<?= h($editRow['QUANTITY_IN_STOCK'] ?? '0') ?>">
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-arrow-down me-1"></i> Reorder Level</label>
                    <input type="number" name="reorder_level" class="form-control" step="0.01" min="0"
                           value="<?= h($editRow['REORDER_LEVEL'] ?? '0') ?>">
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-dollar-sign me-1"></i> Unit Cost</label>
                    <input type="number" name="unit_cost" class="form-control" step="0.01" min="0"
                           value="<?= h($editRow['UNIT_COST'] ?? '0') ?>">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i><?= t('btn_save') ?></button>
                <a href="<?= BASE_URL ?>frontend/pages/inventory.php" class="btn btn-outline"><?= t('btn_cancel') ?></a>
            </div>
        </form>
    </div>
</div>

<?php else: ?>

<?php if (!empty($lowStock) && !$search && !$filterStock): ?>
<div class="alert alert-warning">
    <i class="fa-solid fa-triangle-exclamation me-1"></i>
    <strong><?= count($lowStock) ?></strong> item(s) are at or below reorder level:
    <?php foreach ($lowStock as $i => $ls): ?>
        <strong><?= h($ls['ITEM_NAME']) ?></strong> (<?= h($ls['QUANTITY_IN_STOCK']) ?> <?= h($ls['UNIT'] ?? '') ?>)<?= $i < count($lowStock)-1 ? ',' : '' ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Page Stats -->
<div class="page-stats">
    <div class="page-stat-item">
        <i class="fa-solid fa-boxes-stacked"></i>
        <div><div class="page-stat-num"><?= (int)($rTotal['C'] ?? 0) ?></div><div class="page-stat-lbl"><?= t('page_inventory') ?></div></div>
    </div>
    <div class="page-stat-item" style="<?= (int)($rLow['C'] ?? 0) > 0 ? 'border-color:rgba(231,76,60,.4);' : '' ?>">
        <i class="fa-solid fa-triangle-exclamation" style="<?= (int)($rLow['C'] ?? 0) > 0 ? 'color:#e74c3c;' : '' ?>"></i>
        <div>
            <div class="page-stat-num" style="<?= (int)($rLow['C'] ?? 0) > 0 ? 'color:#e74c3c;' : '' ?>"><?= (int)($rLow['C'] ?? 0) ?></div>
            <div class="page-stat-lbl"><?= t('inv_low_stock') ?></div>
        </div>
    </div>
    <div class="page-stat-item">
        <i class="fa-solid fa-dollar-sign" style="color:var(--primary);"></i>
        <div><div class="page-stat-num"><?= money((float)($rVal['V'] ?? 0)) ?></div><div class="page-stat-lbl"><?= t('inv_total_val') ?></div></div>
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
           placeholder="Search item name / unit…" value="<?= h($search) ?>">
    <select name="stock_filter" class="form-control" style="max-width:150px;">
        <option value=""><?= t('lbl_all_statuses') ?></option>
        <option value="low" <?= $filterStock === 'low' ? 'selected' : '' ?>><?= t('inv_low_stock') ?></option>
        <option value="ok"  <?= $filterStock === 'ok'  ? 'selected' : '' ?>><?= t('status_active') ?></option>
    </select>
    <button type="submit" class="btn btn-dark btn-sm">
        <i class="fa-solid fa-magnifying-glass me-1"></i><?= t('btn_search') ?>
    </button>
    <?php if ($search || $filterStock): ?>
    <a href="<?= BASE_URL ?>frontend/pages/inventory.php" class="btn btn-outline btn-sm">
        <i class="fa-solid fa-xmark me-1"></i><?= t('btn_clear') ?>
    </a>
    <?php endif; ?>
    <?php if ($canWrite): ?>
    <div class="ms-auto">
        <?= exportBtn('inventory.php') ?> <a href="?action=new" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-plus me-1"></i><?= t('btn_add') ?>
        </a>
    </div>
    <?php endif; ?>
</form>

<!-- Table -->
<div class="card">
    <div class="card-header">
        <h3><i class="fa-solid fa-boxes-stacked me-2" style="color:var(--primary);"></i>
            Inventory
            <span class="badge bg-secondary ms-1" style="font-size:11px;"><?= $totalCount ?></span>
        </h3>
        <span style="font-size:12px;color:var(--text-muted);"><?= t('lbl_showing') ?> <?= count($items) ?> <?= t('lbl_of') ?> <?= $totalCount ?></span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($items)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-box-open"></i>
            <p><?= t('empty_no_data') ?><?= ($search || $filterStock) ? ' Try different filters.' : '' ?></p>
        </div>
        <?php else: ?>
        <div class="table-wrapper">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>#</th><th>Item Name</th><th>Unit</th><th>In Stock</th>
                    <th>Reorder At</th><th>Unit Cost</th><th>Stock Status</th><th>Updated</th>
                    <?php if ($canWrite): ?><th><?= t('col_actions') ?></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $inv): ?>
            <?php $isLow = ((float)$inv['QUANTITY_IN_STOCK'] <= (float)$inv['REORDER_LEVEL']); ?>
            <tr style="<?= $isLow ? 'background:rgba(231,76,60,.04);' : '' ?>">
                <td><span class="text-muted"><?= h($inv['INVENTORY_ID']) ?></span></td>
                <td>
                    <strong><?= h($inv['ITEM_NAME']) ?></strong>
                    <?php if ($isLow): ?>
                    <i class="fa-solid fa-triangle-exclamation ms-1" style="color:#e74c3c;font-size:11px;" title="Low stock"></i>
                    <?php endif; ?>
                </td>
                <td><?= h($inv['UNIT'] ?? '—') ?></td>
                <td style="<?= $isLow ? 'color:#e74c3c;font-weight:700;' : '' ?>">
                    <?= h($inv['QUANTITY_IN_STOCK']) ?>
                </td>
                <td><?= h($inv['REORDER_LEVEL']) ?></td>
                <td><?= money((float)($inv['UNIT_COST'] ?? 0)) ?></td>
                <td>
                    <?php if ($isLow): ?>
                    <span class="badge" style="background:rgba(231,76,60,.12);color:#e74c3c;border:1px solid rgba(231,76,60,.3);">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i>Low Stock
                    </span>
                    <?php else: ?>
                    <span class="badge" style="background:rgba(39,174,96,.12);color:#27ae60;border:1px solid rgba(39,174,96,.3);">
                        <i class="fa-solid fa-check me-1"></i>OK
                    </span>
                    <?php endif; ?>
                </td>
                <td class="text-muted"><?= h($inv['LAST_UPDATED'] ?? '—') ?></td>
                <?php if ($canWrite): ?>
                <td>
                    <a href="?action=edit&id=<?= (int)$inv['INVENTORY_ID'] ?>" class="btn btn-warning btn-sm me-1">
                        <i class="fa-solid fa-pen"></i>
                    </a>
                    <a href="?action=confirm_delete&id=<?= (int)$inv['INVENTORY_ID'] ?>" class="btn btn-danger btn-sm">
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
