<?php
require_once __DIR__ . '/../../backend/config/bootstrap.php';
if (!defined('BASE_URL')) { define('BASE_URL', '/RestaurantDB/'); }
$pageTitle = 'Menu Items';
require_once __DIR__ . '/../../backend/includes/menu_item.php';
require_once __DIR__ . '/../../backend/includes/category.php';
require_once __DIR__ . '/../../backend/includes/rbac.php';

requireAccess('menu_items');
$canWrite = canWrite('menu_items');

$action = get('action', 'list');
$id     = (int)get('id', '0');

/*  POST  */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canWrite) {
    $data = [
        'item_name'    => post('item_name'),
        'description'  => post('description'),
        'category_id'  => (int)post('category_id'),
        'price'        => (float)post('price'),
        'availability' => post('availability', 'Yes'),
    ];
    $pa = post('action'); $pid = (int)post('id');
    if ($pa === 'create') { createMenuItem($data)          ? setFlash('success','Menu item added.')  : setFlash('danger','Failed to add.'); }
    if ($pa === 'update') { updateMenuItem($pid, $data)    ? setFlash('success','Item updated.')     : setFlash('danger','Update failed.'); }
    if ($pa === 'delete') { deleteMenuItem($pid)           ? setFlash('success','Item deleted.')     : setFlash('danger','Cannot delete item.'); }
    redirect('frontend/pages/menu_items.php');
}

require_once __DIR__ . '/../includes/header.php';

/*  Data  */
$search    = get('q');
$filterCat = (int)get('cat_filter');
$filterAv  = get('av_filter');

$allItems   = getAllMenuItems();
$categories = getAllCategories();

if ($search || $filterCat || $filterAv) {
    $allItems = array_filter($allItems, function($m) use ($search, $filterCat, $filterAv) {
        if ($search && strpos(strtolower($m['ITEM_NAME']), strtolower($search)) === false
            && strpos(strtolower($m['CATEGORY_NAME'] ?? ''), strtolower($search)) === false) return false;
        if ($filterCat && (int)$m['CATEGORY_ID'] !== $filterCat) return false;
        if ($filterAv  && $m['AVAILABILITY'] !== $filterAv) return false;
        return true;
    });
    $allItems = array_values($allItems);
}

$totalCount = count($allItems);
$perPage    = (int)getSetting('records_per_page', '10');
$pag        = paginate($totalCount, $perPage);
$items      = array_slice($allItems, $pag['offset'], $pag['limit']);

$editRow    = ($action === 'edit'           && $id) ? getMenuItemById($id) : null;
$confirmRow = ($action === 'confirm_delete' && $id) ? getMenuItemById($id) : null;

/*  Stats  */
$conn = getDBConnection();
$sTotal = oci_parse($conn, "SELECT COUNT(*) C FROM MENU_ITEM"); oci_execute($sTotal); $rTotal=oci_fetch_assoc($sTotal); oci_free_statement($sTotal);
$sAvail = oci_parse($conn, "SELECT COUNT(*) C FROM MENU_ITEM WHERE AVAILABILITY='Yes'"); oci_execute($sAvail); $rAvail=oci_fetch_assoc($sAvail); oci_free_statement($sAvail);
$sCats  = oci_parse($conn, "SELECT COUNT(*) C FROM CATEGORY"); oci_execute($sCats); $rCats=oci_fetch_assoc($sCats); oci_free_statement($sCats);
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
            Delete menu item <strong><?= h($confirmRow['ITEM_NAME']) ?></strong>? <?= t('confirm_msg') ?>
        </div>
        <div class="d-flex gap-2">
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$confirmRow['ITEM_ID'] ?>">
                <button type="submit" class="btn btn-danger"><i class="fa-solid fa-trash me-1"></i><?= t('btn_confirm_del') ?></button>
            </form>
            <a href="<?= BASE_URL ?>frontend/pages/menu_items.php" class="btn btn-outline"><?= t('btn_cancel') ?></a>
        </div>
    </div>
</div>

<?php elseif ($action === 'new' || $action === 'edit'): ?>
<div class="card" style="max-width:680px;">
    <div class="card-header">
        <h3><i class="fa-solid <?= $action === 'new' ? 'fa-plus' : 'fa-pen' ?> me-2"></i>
            <?= $action === 'new' ? 'Add Menu Item' : 'Edit Menu Item' ?></h3>
        <a href="<?= BASE_URL ?>frontend/pages/menu_items.php" class="btn btn-outline btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i><?= t('btn_back') ?>
        </a>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="<?= $action === 'new' ? 'create' : 'update' ?>">
            <?php if ($editRow): ?><input type="hidden" name="id" value="<?= (int)$editRow['ITEM_ID'] ?>"><?php endif; ?>
            <div class="form-group">
                <label><i class="fa-solid fa-utensils me-1"></i> Item Name *</label>
                <input type="text" name="item_name" class="form-control" required
                       value="<?= h($editRow['ITEM_NAME'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label><i class="fa-solid fa-align-left me-1"></i> Description</label>
                <textarea name="description" class="form-control"><?= h($editRow['DESCRIPTION'] ?? '') ?></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fa-solid fa-layer-group me-1"></i> Category *</label>
                    <select name="category_id" class="form-control" required>
                        <option value="">— Select Category —</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int)$cat['CATEGORY_ID'] ?>"
                            <?= (isset($editRow['CATEGORY_ID']) && $editRow['CATEGORY_ID'] == $cat['CATEGORY_ID']) ? 'selected' : '' ?>>
                            <?= h($cat['CATEGORY_NAME']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-dollar-sign me-1"></i> Price *</label>
                    <input type="number" name="price" class="form-control" required step="0.01" min="0"
                           value="<?= h($editRow['PRICE'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label><i class="fa-solid fa-circle-check me-1"></i> Availability</label>
                <select name="availability" class="form-control">
                    <option value="Yes" <?= (($editRow['AVAILABILITY'] ?? 'Yes') === 'Yes') ? 'selected' : '' ?>>Yes — Available</option>
                    <option value="No"  <?= (($editRow['AVAILABILITY'] ?? '') === 'No')     ? 'selected' : '' ?>>No — Unavailable</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i><?= t('btn_save') ?></button>
                <a href="<?= BASE_URL ?>frontend/pages/menu_items.php" class="btn btn-outline"><?= t('btn_cancel') ?></a>
            </div>
        </form>
    </div>
</div>

<?php else: ?>

<!-- Page Stats -->
<div class="page-stats">
    <div class="page-stat-item">
        <i class="fa-solid fa-utensils"></i>
        <div><div class="page-stat-num"><?= (int)($rTotal['C'] ?? 0) ?></div><div class="page-stat-lbl"><?= t('page_menu_items') ?></div></div>
    </div>
    <div class="page-stat-item">
        <i class="fa-solid fa-circle-check"></i>
        <div><div class="page-stat-num"><?= (int)($rAvail['C'] ?? 0) ?></div><div class="page-stat-lbl"><?= t('dash_available_items') ?></div></div>
    </div>
    <div class="page-stat-item">
        <i class="fa-solid fa-layer-group"></i>
        <div><div class="page-stat-num"><?= (int)($rCats['C'] ?? 0) ?></div><div class="page-stat-lbl"><?= t('page_categories') ?></div></div>
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
           placeholder="Search name / category…" value="<?= h($search) ?>">
    <select name="cat_filter" class="form-control" style="max-width:160px;">
        <option value=""><?= t('page_categories') ?></option>
        <?php foreach ($categories as $cat): ?>
        <option value="<?= (int)$cat['CATEGORY_ID'] ?>" <?= $filterCat === (int)$cat['CATEGORY_ID'] ? 'selected' : '' ?>>
            <?= h($cat['CATEGORY_NAME']) ?>
        </option>
        <?php endforeach; ?>
    </select>
    <select name="av_filter" class="form-control" style="max-width:140px;">
        <option value=""><?= t('lbl_all_statuses') ?></option>
        <option value="Yes" <?= $filterAv === 'Yes' ? 'selected' : '' ?>>Available</option>
        <option value="No"  <?= $filterAv === 'No'  ? 'selected' : '' ?>>Unavailable</option>
    </select>
    <button type="submit" class="btn btn-dark btn-sm">
        <i class="fa-solid fa-magnifying-glass me-1"></i><?= t('btn_search') ?>
    </button>
    <?php if ($search || $filterCat || $filterAv): ?>
    <a href="<?= BASE_URL ?>frontend/pages/menu_items.php" class="btn btn-outline btn-sm">
        <i class="fa-solid fa-xmark me-1"></i><?= t('btn_clear') ?>
    </a>
    <?php endif; ?>
    <?php if ($canWrite): ?>
    <div class="ms-auto">
        <?= exportBtn('menu_items.php') ?> <a href="?action=new" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-plus me-1"></i><?= t('btn_add') ?> <?= t('page_menu_items') ?>
        </a>
    </div>
    <?php endif; ?>
</form>

<!-- Table -->
<div class="card">
    <div class="card-header">
        <h3><i class="fa-solid fa-utensils me-2" style="color:var(--primary);"></i>
            Menu Items
            <span class="badge bg-secondary ms-1" style="font-size:11px;"><?= $totalCount ?></span>
        </h3>
        <span style="font-size:12px;color:var(--text-muted);">
            <?= t('lbl_showing') ?> <?= count($items) ?> <?= t('lbl_of') ?> <?= $totalCount ?>
        </span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($items)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-bowl-food"></i>
            <p><?= t('empty_no_data') ?><?= ($search || $filterCat || $filterAv) ? ' Try different filters.' : '' ?></p>
        </div>
        <?php else: ?>
        <div class="table-wrapper">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>#</th><th><?= t('col_name') ?></th><th><?= t('col_category') ?></th><th><?= t('col_price') ?></th><th><?= t('col_availability') ?></th>
                    <?php if ($canWrite): ?><th><?= t('col_actions') ?></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $m): ?>
            <tr>
                <td><span class="text-muted"><?= h($m['ITEM_ID']) ?></span></td>
                <td>
                    <strong><?= h($m['ITEM_NAME']) ?></strong>
                    <?php if (!empty($m['DESCRIPTION'])): ?>
                    <br><small class="text-muted"><?= h(mb_strimwidth($m['DESCRIPTION'], 0, 60, '…')) ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge bg-secondary" style="font-size:11px;"><?= h($m['CATEGORY_NAME']) ?></span>
                </td>
                <td><strong><?= money((float)$m['PRICE']) ?></strong></td>
                <td><?= statusBadge($m['AVAILABILITY'] === 'Yes' ? 'Active' : 'Inactive') ?></td>
                <?php if ($canWrite): ?>
                <td>
                    <a href="?action=edit&id=<?= (int)$m['ITEM_ID'] ?>" class="btn btn-warning btn-sm me-1">
                        <i class="fa-solid fa-pen"></i>
                    </a>
                    <a href="?action=confirm_delete&id=<?= (int)$m['ITEM_ID'] ?>" class="btn btn-danger btn-sm">
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
