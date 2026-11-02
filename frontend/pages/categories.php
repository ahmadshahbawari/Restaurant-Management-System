<?php
require_once __DIR__ . '/../../backend/config/bootstrap.php';
if (!defined('BASE_URL')) { define('BASE_URL', '/RestaurantDB/'); }
$pageTitle = 'Categories';
require_once __DIR__ . '/../../backend/includes/category.php';
require_once __DIR__ . '/../../backend/includes/rbac.php';

requireAccess('categories');
$canWrite = canWrite('categories');

$action = get('action', 'list');
$id     = (int)get('id', '0');

/*  POST  */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canWrite) {
    $data = ['category_name' => post('category_name'), 'description' => post('description')];
    $pa   = post('action'); $pid = (int)post('id');
    if ($pa === 'create') { createCategory($data)          ? setFlash('success','Category added.')   : setFlash('danger','Failed to add.'); }
    if ($pa === 'update') { updateCategory($pid, $data)    ? setFlash('success','Category updated.') : setFlash('danger','Update failed.'); }
    if ($pa === 'delete') { deleteCategory($pid)           ? setFlash('success','Category deleted.') : setFlash('danger','Cannot delete — menu items may depend on it.'); }
    redirect('frontend/pages/categories.php');
}

require_once __DIR__ . '/../includes/header.php';

/*  Data  */
$search       = get('q');
$allCats      = getAllCategories();

if ($search) {
    $allCats = array_filter($allCats, function($c) use ($search) {
        return strpos(strtolower($c['CATEGORY_NAME']), strtolower($search)) !== false
            || strpos(strtolower($c['DESCRIPTION'] ?? ''), strtolower($search)) !== false;
    });
    $allCats = array_values($allCats);
}

$totalCount = count($allCats);
$perPage    = (int)getSetting('records_per_page', '10');
$pag        = paginate($totalCount, $perPage);
$categories = array_slice($allCats, $pag['offset'], $pag['limit']);

$editRow    = ($action === 'edit'           && $id) ? getCategoryById($id) : null;
$confirmRow = ($action === 'confirm_delete' && $id) ? getCategoryById($id) : null;

/*  Stats  */
$conn   = getDBConnection();
$sTotal = oci_parse($conn, "SELECT COUNT(*) C FROM CATEGORY"); oci_execute($sTotal); $rTotal=oci_fetch_assoc($sTotal); oci_free_statement($sTotal);
$sItems = oci_parse($conn, "SELECT COUNT(*) C FROM MENU_ITEM"); oci_execute($sItems); $rItems=oci_fetch_assoc($sItems); oci_free_statement($sItems);
$sAvail = oci_parse($conn, "SELECT COUNT(*) C FROM MENU_ITEM WHERE AVAILABILITY='Yes'"); oci_execute($sAvail); $rAvail=oci_fetch_assoc($sAvail); oci_free_statement($sAvail);
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
            Delete category <strong><?= h($confirmRow['CATEGORY_NAME']) ?></strong>? <?= t('confirm_msg') ?>
        </div>
        <div class="d-flex gap-2">
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$confirmRow['CATEGORY_ID'] ?>">
                <button type="submit" class="btn btn-danger"><i class="fa-solid fa-trash me-1"></i><?= t('btn_confirm_del') ?></button>
            </form>
            <a href="<?= BASE_URL ?>frontend/pages/categories.php" class="btn btn-outline"><?= t('btn_cancel') ?></a>
        </div>
    </div>
</div>

<?php elseif ($action === 'new' || $action === 'edit'): ?>
<div class="card" style="max-width:580px;">
    <div class="card-header">
        <h3><i class="fa-solid <?= $action === 'new' ? 'fa-plus' : 'fa-pen' ?> me-2"></i>
            <?= $action === 'new' ? 'Add Category' : 'Edit Category' ?></h3>
        <a href="<?= BASE_URL ?>frontend/pages/categories.php" class="btn btn-outline btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i><?= t('btn_back') ?>
        </a>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="<?= $action === 'new' ? 'create' : 'update' ?>">
            <?php if ($editRow): ?><input type="hidden" name="id" value="<?= (int)$editRow['CATEGORY_ID'] ?>"><?php endif; ?>
            <div class="form-group">
                <label><i class="fa-solid fa-layer-group me-1"></i> Category Name *</label>
                <input type="text" name="category_name" class="form-control" required
                       value="<?= h($editRow['CATEGORY_NAME'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label><i class="fa-solid fa-align-left me-1"></i> Description</label>
                <textarea name="description" class="form-control"><?= h($editRow['DESCRIPTION'] ?? '') ?></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i><?= t('btn_save') ?></button>
                <a href="<?= BASE_URL ?>frontend/pages/categories.php" class="btn btn-outline"><?= t('btn_cancel') ?></a>
            </div>
        </form>
    </div>
</div>

<?php else: ?>

<!-- Page Stats -->
<div class="page-stats">
    <div class="page-stat-item">
        <i class="fa-solid fa-layer-group"></i>
        <div><div class="page-stat-num"><?= (int)($rTotal['C'] ?? 0) ?></div><div class="page-stat-lbl"><?= t('page_categories') ?></div></div>
    </div>
    <div class="page-stat-item">
        <i class="fa-solid fa-utensils"></i>
        <div><div class="page-stat-num"><?= (int)($rItems['C'] ?? 0) ?></div><div class="page-stat-lbl"><?= t('page_menu_items') ?></div></div>
    </div>
    <div class="page-stat-item">
        <i class="fa-solid fa-circle-check"></i>
        <div><div class="page-stat-num"><?= (int)($rAvail['C'] ?? 0) ?></div><div class="page-stat-lbl"><?= t('dash_available_items') ?></div></div>
    </div>
    <div class="page-stat-item">
        <i class="fa-solid fa-file-lines"></i>
        <div><div class="page-stat-num"><?= $pag['total_pages'] ?></div><div class="page-stat-lbl"><?= t('lbl_pages') ?></div></div>
    </div>
</div>

<!-- Filter Bar -->
<form method="GET" action="" class="filter-bar">
    <span class="filter-label"><i class="fa-solid fa-filter me-1"></i><?= t('lbl_filter') ?></span>
    <input type="text" name="q" class="form-control" style="max-width:280px;"
           placeholder="Search category name…" value="<?= h($search) ?>">
    <button type="submit" class="btn btn-dark btn-sm">
        <i class="fa-solid fa-magnifying-glass me-1"></i><?= t('btn_search') ?>
    </button>
    <?php if ($search): ?>
    <a href="<?= BASE_URL ?>frontend/pages/categories.php" class="btn btn-outline btn-sm">
        <i class="fa-solid fa-xmark me-1"></i><?= t('btn_clear') ?>
    </a>
    <?php endif; ?>
    <?php if ($canWrite): ?>
    <div class="ms-auto">
        <?= exportBtn('categories.php') ?> <a href="?action=new" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-plus me-1"></i><?= t('btn_add') ?> <?= t('page_categories') ?>
        </a>
    </div>
    <?php endif; ?>
</form>

<!-- Table -->
<div class="card">
    <div class="card-header">
        <h3><i class="fa-solid fa-layer-group me-2" style="color:var(--primary);"></i>
            Categories
            <span class="badge bg-secondary ms-1" style="font-size:11px;"><?= $totalCount ?></span>
        </h3>
        <span style="font-size:12px;color:var(--text-muted);">
            <?= t('lbl_showing') ?> <?= count($categories) ?> <?= t('lbl_of') ?> <?= $totalCount ?>
        </span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($categories)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-folder-open"></i>
            <p><?= t('empty_no_data') ?><?= $search ? ' Try a different search.' : '' ?></p>
        </div>
        <?php else: ?>
        <div class="table-wrapper">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>#</th><th><?= t('col_name') ?></th><th>Description</th>
                    <?php if ($canWrite): ?><th><?= t('col_actions') ?></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($categories as $c): ?>
            <tr>
                <td><span class="text-muted"><?= h($c['CATEGORY_ID']) ?></span></td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,var(--primary),var(--primary-dk));display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;flex-shrink:0;">
                            <i class="fa-solid fa-layer-group"></i>
                        </div>
                        <strong><?= h($c['CATEGORY_NAME']) ?></strong>
                    </div>
                </td>
                <td class="text-muted"><?= h($c['DESCRIPTION'] ?? '—') ?></td>
                <?php if ($canWrite): ?>
                <td>
                    <a href="?action=edit&id=<?= (int)$c['CATEGORY_ID'] ?>" class="btn btn-warning btn-sm me-1">
                        <i class="fa-solid fa-pen"></i>
                    </a>
                    <a href="?action=confirm_delete&id=<?= (int)$c['CATEGORY_ID'] ?>" class="btn btn-danger btn-sm">
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
