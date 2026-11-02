<?php
require_once __DIR__ . '/../../backend/config/bootstrap.php';
if (!defined('BASE_URL')) { define('BASE_URL', '/RestaurantDB/'); }
$pageTitle = 'Kitchen Board';
require_once __DIR__ . '/../../backend/includes/kitchen.php';
require_once __DIR__ . '/../../backend/includes/rbac.php';
require_once __DIR__ . '/../../backend/includes/notifications.php';

requireAccess('kitchen');
$canWrite = canWrite('kitchen');

/*  POST  */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canWrite) {
    $kitchenId = (int)post('kitchen_order_id');
    $orderId   = (int)post('order_id_for_notif', 0);
    $status    = post('kitchen_status');
    if (updateKitchenStatus($kitchenId, $status)) {
        notifyKitchenStatusChange($orderId ?: $kitchenId, $status);
        setFlash('success', 'Kitchen status updated to: '.$status);
    } else {
        setFlash('danger', 'Update failed.');
    }
    redirect('frontend/pages/kitchen.php');
}

require_once __DIR__ . '/../includes/header.php';

$allKitchenOrders = getAllKitchenOrders();

/* Group by status for kanban */
$grouped = ['Pending' => [], 'Preparing' => [], 'Ready' => []];
foreach ($allKitchenOrders as $ko) {
    $s = $ko['KITCHEN_STATUS'];
    if ($s === 'Cancelled') continue;
    if (isset($grouped[$s])) { $grouped[$s][] = $ko; } else { $grouped['Pending'][] = $ko; }
}

/* Pagination for full table */
$search = get('q');
$filterKS = get('ks_filter');
$tableOrders = $allKitchenOrders;

if ($search || $filterKS) {
    $tableOrders = array_filter($tableOrders, function($ko) use ($search, $filterKS) {
        if ($search && strpos(strtolower($ko['CUSTOMER_NAME']), strtolower($search)) === false
            && strpos((string)$ko['ORDER_ID'], $search) === false) return false;
        if ($filterKS && $ko['KITCHEN_STATUS'] !== $filterKS) return false;
        return true;
    });
    $tableOrders = array_values($tableOrders);
}

$totalCount      = count($tableOrders);
$perPage         = (int)getSetting('records_per_page', '10');
$pag             = paginate($totalCount, $perPage);
$pagedOrders     = array_slice($tableOrders, $pag['offset'], $pag['limit']);
$kitchenStatuses = ['Pending', 'Preparing', 'Ready', 'Cancelled'];

/* Stats */
$pending   = count($grouped['Pending']);
$preparing = count($grouped['Preparing']);
$ready     = count($grouped['Ready']);
$total     = count($allKitchenOrders);
?>

<!-- Page Stats -->
<div class="page-stats">
    <div class="page-stat-item">
        <i class="fa-solid fa-fire-burner"></i>
        <div><div class="page-stat-num"><?= $total ?></div><div class="page-stat-lbl"><?= t('page_kitchen') ?></div></div>
    </div>
    <div class="page-stat-item" style="border-color:rgba(243,156,18,.3);">
        <i class="fa-solid fa-hourglass-start" style="color:#f39c12;"></i>
        <div><div class="page-stat-num" style="color:#f39c12;"><?= $pending ?></div><div class="page-stat-lbl"><?= t('kit_pending') ?></div></div>
    </div>
    <div class="page-stat-item" style="border-color:rgba(41,128,185,.3);">
        <i class="fa-solid fa-fire" style="color:#2980b9;"></i>
        <div><div class="page-stat-num" style="color:#2980b9;"><?= $preparing ?></div><div class="page-stat-lbl"><?= t('kit_preparing') ?></div></div>
    </div>
    <div class="page-stat-item" style="border-color:rgba(39,174,96,.3);">
        <i class="fa-solid fa-check-circle" style="color:#27ae60;"></i>
        <div><div class="page-stat-num" style="color:#27ae60;"><?= $ready ?></div><div class="page-stat-lbl"><?= t('kit_ready') ?></div></div>
    </div>
</div>

<!-- ======== KANBAN BOARD ======== -->
<?php
require_once __DIR__ . '/../../backend/config/bootstrap.php';
$kanbanCols = [
    'Pending'   => ['icon'=>'fa-hourglass-start', 'color'=>'#f39c12', 'bg'=>'rgba(243,156,18,.08)'],
    'Preparing' => ['icon'=>'fa-fire',            'color'=>'#2980b9', 'bg'=>'rgba(41,128,185,.08)'],
    'Ready'     => ['icon'=>'fa-check-double',    'color'=>'#27ae60', 'bg'=>'rgba(39,174,96,.08)'],
];
?>
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-bottom:28px;">
    <?php $colLabels=array('Pending'=>t('kit_pending'),'Preparing'=>t('kit_preparing'),'Ready'=>t('kit_ready')); foreach ($kanbanCols as $colStatus => $meta): ?>
    <div class="card">
        <div class="card-header" style="background:<?= $meta['bg'] ?>;">
            <h3>
                <i class="fa-solid <?= $meta['icon'] ?> me-2" style="color:<?= $meta['color'] ?>;"></i>
                <?= h($colLabels[$colStatus] ?? $colStatus) ?>
            </h3>
            <span class="badge" style="background:<?= $meta['color'] ?>;color:#fff;font-size:12px;"><?= count($grouped[$colStatus]) ?></span>
        </div>
        <div class="card-body" style="padding:12px;">
            <?php if (empty($grouped[$colStatus])): ?>
            <div style="text-align:center;color:var(--text-muted);padding:24px 0;font-size:13px;">
                <i class="fa-solid fa-inbox" style="font-size:24px;opacity:.3;display:block;margin-bottom:8px;"></i>
                No orders
            </div>
            <?php endif; ?>
            <?php foreach ($grouped[$colStatus] as $ko): ?>
            <div class="kanban-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong style="font-size:13px;">Order #<?= h($ko['ORDER_ID']) ?></strong>
                    <span class="badge bg-secondary" style="font-size:10px;"><?= h($ko['ORDER_TYPE']) ?></span>
                </div>
                <div style="font-size:12px;color:var(--text-muted);margin-bottom:6px;">
                    <i class="fa-solid fa-user me-1"></i><?= h($ko['CUSTOMER_NAME']) ?>
                </div>
                <?php if (!empty($ko['NOTES'])): ?>
                <div style="font-size:12px;background:var(--surface);border:1px solid var(--border);padding:6px 8px;border-radius:4px;margin-bottom:8px;">
                    <i class="fa-solid fa-note-sticky me-1 text-muted"></i><?= h($ko['NOTES']) ?>
                </div>
                <?php endif; ?>
                <div style="font-size:11px;color:var(--text-muted);margin-bottom:10px;">
                    <i class="fa-regular fa-clock me-1"></i>Started: <?= h($ko['STARTED_AT'] ?? '—') ?>
                    <?php if ($ko['READY_AT']): ?>
                    <br><i class="fa-solid fa-flag-checkered me-1"></i>Ready: <?= h($ko['READY_AT']) ?>
                    <?php endif; ?>
                </div>
                <?php if ($canWrite): ?>
                <form method="POST">
                    <input type="hidden" name="kitchen_order_id" value="<?= (int)$ko['KITCHEN_ORDER_ID'] ?>">
                    <div class="d-flex gap-2">
                        <select name="kitchen_status" class="form-control" style="flex:1;font-size:12px;padding:5px 8px;">
                            <?php foreach ($kitchenStatuses as $ks): ?>
                            <option value="<?= h($ks) ?>" <?= $ko['KITCHEN_STATUS'] === $ks ? 'selected' : '' ?>><?= h($ks) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fa-solid fa-check"></i>
                        </button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ======== FULL TABLE ======== -->

<!-- Filter Bar -->
<form method="GET" action="" class="filter-bar">
    <span class="filter-label"><i class="fa-solid fa-filter me-1"></i><?= t('lbl_filter') ?></span>
    <input type="text" name="q" class="form-control" style="max-width:220px;"
           placeholder="Search order# / customer…" value="<?= h($search) ?>">
    <select name="ks_filter" class="form-control" style="max-width:150px;">
        <option value=""><?= t('lbl_all_statuses') ?></option>
        <?php foreach ($kitchenStatuses as $ks): ?>
        <option value="<?= h($ks) ?>" <?= $filterKS === $ks ? 'selected' : '' ?>><?= h($ks) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-dark btn-sm">
        <i class="fa-solid fa-magnifying-glass me-1"></i><?= t('btn_search') ?>
    </button>
    <?php if ($search || $filterKS): ?>
    <a href="<?= BASE_URL ?>frontend/pages/kitchen.php" class="btn btn-outline btn-sm">
        <i class="fa-solid fa-xmark me-1"></i><?= t('btn_clear') ?>
    </a>
    <?php endif; ?>
</form>

<div class="card">
    <div class="card-header">
        <h3><i class="fa-solid fa-list-check me-2" style="color:var(--primary);"></i>
            All Kitchen Orders
            <span class="badge bg-secondary ms-1" style="font-size:11px;"><?= $totalCount ?></span>
        </h3>
        <span style="font-size:12px;color:var(--text-muted);"><?= t('lbl_showing') ?> <?= count($pagedOrders) ?> <?= t('lbl_of') ?> <?= $totalCount ?></span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($pagedOrders)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-fire-burner"></i>
            <p><?= t('empty_no_data') ?></p>
        </div>
        <?php else: ?>
        <div class="table-wrapper">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Kitchen ID</th><th>Order #</th><th>Customer</th><th>Type</th>
                    <th>Kitchen Status</th><th>Started</th><th>Ready At</th>
                    <?php if ($canWrite): ?><th><?= t('btn_edit') ?></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pagedOrders as $ko): ?>
            <tr>
                <td><span class="text-muted">#<?= h($ko['KITCHEN_ORDER_ID']) ?></span></td>
                <td>
                    <a href="<?= BASE_URL ?>frontend/pages/orders.php?action=view&id=<?= (int)$ko['ORDER_ID'] ?>">
                        #<?= h($ko['ORDER_ID']) ?>
                    </a>
                </td>
                <td><?= h($ko['CUSTOMER_NAME']) ?></td>
                <td><?= h($ko['ORDER_TYPE']) ?></td>
                <td>
                    <?php
require_once __DIR__ . '/../../backend/config/bootstrap.php';
                    $ksMap = ['Pending'=>'Pending','Preparing'=>'Processing','Ready'=>'Active','Cancelled'=>'Cancelled'];
                    echo statusBadge($ksMap[$ko['KITCHEN_STATUS']] ?? 'Pending');
                    ?>
                    <span style="font-size:11px;margin-left:4px;"><?= h($ko['KITCHEN_STATUS']) ?></span>
                </td>
                <td class="text-muted"><?= h($ko['STARTED_AT'] ?? '—') ?></td>
                <td class="text-muted"><?= h($ko['READY_AT'] ?? '—') ?></td>
                <?php if ($canWrite): ?>
                <td>
                    <form method="POST" class="d-flex gap-1">
                        <input type="hidden" name="kitchen_order_id" value="<?= (int)$ko['KITCHEN_ORDER_ID'] ?>">
                        <select name="kitchen_status" class="form-control" style="width:auto;font-size:12px;padding:5px 8px;">
                            <?php foreach ($kitchenStatuses as $ks): ?>
                            <option value="<?= h($ks) ?>" <?= $ko['KITCHEN_STATUS'] === $ks ? 'selected' : '' ?>><?= h($ks) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fa-solid fa-check"></i>
                        </button>
                    </form>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
