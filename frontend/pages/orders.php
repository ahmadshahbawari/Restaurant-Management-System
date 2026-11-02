<?php
require_once __DIR__ . '/../../backend/config/bootstrap.php';
if (!defined('BASE_URL')) { define('BASE_URL', '/RestaurantDB/'); }
$pageTitle = 'Orders';
require_once __DIR__ . '/../../backend/includes/order.php';
require_once __DIR__ . '/../../backend/includes/customer.php';
require_once __DIR__ . '/../../backend/includes/menu_item.php';
require_once __DIR__ . '/../../backend/includes/kitchen.php';
require_once __DIR__ . '/../../backend/includes/rbac.php';
require_once __DIR__ . '/../../backend/includes/notifications.php';

requireAccess('orders');
$canWrite = canWrite('orders');

$action = get('action', 'list');
$id     = (int)get('id', '0');

/*  POST handlers — BEFORE header.php  */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = post('action');

    if ($postAction === 'create_order' && $canWrite) {
        $empId = (int)($_SESSION['employee_id'] ?? 0);
        $newId = createOrder([
            'customer_id' => (int)post('customer_id'),
            'employee_id' => $empId,
            'order_type'  => post('order_type'),
        ]);
        if ($newId > 0) {
            $itemIds = $_POST['item_id']              ?? [];
            $qtys    = $_POST['quantity']             ?? [];
            $prices  = $_POST['unit_price']           ?? [];
            $notes   = $_POST['special_instructions'] ?? [];
            for ($i = 0; $i < count($itemIds); $i++) {
                if (empty($itemIds[$i]) || empty($qtys[$i]) || (float)$prices[$i] <= 0) continue;
                addOrderItem($newId, [
                    'item_id'              => (int)$itemIds[$i],
                    'quantity'             => max(1, (int)$qtys[$i]),
                    'unit_price'           => (float)$prices[$i],
                    'special_instructions' => $notes[$i] ?? '',
                ]);
            }
            createKitchenOrder($newId);
            notifyNewOrder($newId, post('order_type'), (int)post('customer_id'));
            setFlash('success', "Order #$newId created and sent to kitchen.");
        } else {
            setFlash('danger', 'Failed to create order.');
        }
        redirect('frontend/pages/orders.php');

    } elseif ($postAction === 'update_status' && $canWrite) {
        $oid = (int)post('order_id');
        updateOrderStatus($oid, post('status'))
            ? setFlash('success', 'Order status updated.')
            : setFlash('danger',  'Update failed.');
        redirect('frontend/pages/orders.php?action=view&id='.$oid);

    } elseif ($postAction === 'delete_order' && $canWrite) {
        $oid = (int)post('order_id');
        deleteOrder($oid)
            ? setFlash('success', 'Order deleted.')
            : setFlash('danger',  'Failed to delete order.');
        redirect('frontend/pages/orders.php');
    }
}

/*  Data queries  */
$conn   = getDBConnection();
$s1 = oci_parse($conn,"SELECT COUNT(*) C FROM ORDERS"); oci_execute($s1); $rAll=oci_fetch_assoc($s1); oci_free_statement($s1);
$s2 = oci_parse($conn,"SELECT COUNT(*) C FROM ORDERS WHERE TRUNC(ORDER_DATE)=TRUNC(SYSDATE)"); oci_execute($s2); $rToday=oci_fetch_assoc($s2); oci_free_statement($s2);
$s3 = oci_parse($conn,"SELECT COUNT(*) C FROM ORDERS WHERE STATUS='Pending'"); oci_execute($s3); $rPend=oci_fetch_assoc($s3); oci_free_statement($s3);
$s4 = oci_parse($conn,"SELECT COUNT(*) C FROM ORDERS WHERE STATUS='Completed'"); oci_execute($s4); $rComp=oci_fetch_assoc($s4); oci_free_statement($s4);
oci_close($conn);

$orderStatuses = ['Pending','Processing','Completed','Cancelled'];
$viewOrder     = ($action==='view'           && $id) ? getOrderById($id)  : null;
$confirmOrder  = ($action==='confirm_delete' && $id) ? getOrderById($id)  : null;
$orderItems    = $viewOrder  ? getOrderItems($id)   : [];
$customers     = ($action==='new')                   ? getAllCustomers()   : [];
$menuItems     = ($action==='new')                   ? getAllMenuItems()   : [];

/*  HTML starts here ══ */
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($confirmOrder): ?>
<div class="card" style="max-width:520px;">
    <div class="card-header">
        <h3><i class="fa-solid fa-trash me-2"></i><?= t('confirm_delete') ?></h3>
    </div>
    <div class="card-body">
        <div class="alert alert-warning">
            <i class="fa-solid fa-triangle-exclamation me-1"></i>
            Delete <strong>Order #<?= h($confirmOrder['ORDER_ID']) ?></strong>
            for <strong><?= h($confirmOrder['CUSTOMER_NAME']) ?></strong>?<br>
            <?= t('confirm_msg') ?>
        </div>
        <div class="d-flex gap-2">
            <form method="POST">
                <input type="hidden" name="action" value="delete_order">
                <input type="hidden" name="order_id" value="<?= (int)$confirmOrder['ORDER_ID'] ?>">
                <button type="submit" class="btn btn-danger">
                    <i class="fa-solid fa-trash me-1"></i><?= t('btn_confirm_del') ?>
                </button>
            </form>
            <a href="<?= BASE_URL ?>frontend/pages/orders.php" class="btn btn-outline"><?= t('btn_cancel') ?></a>
        </div>
    </div>
</div>

<?php elseif ($action === 'new' && $canWrite): ?>
<div class="card">
    <div class="card-header">
        <h3><i class="fa-solid fa-plus me-2"></i><?= t('ord_new') ?></h3>
        <a href="<?= BASE_URL ?>frontend/pages/orders.php" class="btn btn-outline btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i><?= t('btn_back') ?>
        </a>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="create_order">
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fa-solid fa-user me-1"></i> Customer *</label>
                    <select name="customer_id" class="form-control" required>
                        <option value="">— Select Customer —</option>
                        <?php foreach ($customers as $c): ?>
                        <option value="<?= (int)$c['CUSTOMER_ID'] ?>">
                            <?= h($c['FIRST_NAME'].' '.$c['LAST_NAME']) ?>
                            <?= $c['PHONE'] ? ' ('.h($c['PHONE']).')' : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fa-solid fa-tag me-1"></i> Order Type *</label>
                    <select name="order_type" class="form-control" required>
                        <option value="Dine-In"><?= t('ord_type_dine') ?></option>
                        <option value="Takeaway"><?= t('ord_type_take') ?></option>
                        <option value="Delivery"><?= t('ord_type_del') ?></option>
                    </select>
                </div>
            </div>

            <hr style="margin:20px 0;border-color:var(--border);">
            <h4 style="font-size:14px;font-weight:700;margin-bottom:4px;">
                <i class="fa-solid fa-cart-shopping me-2" style="color:var(--primary);"></i>Order Items
            </h4>
            <p class="text-muted" style="font-size:12px;margin-bottom:12px;">
                Fill the rows you need. Rows with no item selected or zero price are skipped automatically.
            </p>

            <div class="table-wrapper mb-3">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th style="width:38%">Menu Item</th>
                        <th style="width:10%">Qty</th>
                        <th style="width:14%">Unit Price</th>
                        <th>Special Instructions</th>
                    </tr>
                </thead>
                <tbody>
                <?php for ($i = 0; $i < 10; $i++): ?>
                <tr>
                    <td>
                        <select name="item_id[]" class="form-control" style="font-size:12px;">
                            <option value="">— No item —</option>
                            <?php foreach ($menuItems as $m): ?>
                            <?php if ($m['AVAILABILITY'] !== 'Yes') continue; ?>
                            <option value="<?= (int)$m['ITEM_ID'] ?>">
                                <?= h($m['ITEM_NAME']) ?> — <?= money((float)$m['PRICE']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <input type="number" name="quantity[]" class="form-control"
                               min="1" value="1" style="font-size:12px;">
                    </td>
                    <td>
                        <input type="number" name="unit_price[]" class="form-control"
                               step="0.01" min="0" value="0.00" style="font-size:12px;">
                    </td>
                    <td>
                        <input type="text" name="special_instructions[]" class="form-control"
                               placeholder="e.g. No onions" style="font-size:12px;">
                    </td>
                </tr>
                <?php endfor; ?>
                </tbody>
            </table>
            </div>

            <div class="alert alert-info">
                <i class="fa-solid fa-circle-info me-1"></i>
                Select a menu item then enter its unit price manually. Rows with zero price are ignored.
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-paper-plane me-1"></i><?= t('ord_place') ?>
                </button>
                <a href="<?= BASE_URL ?>frontend/pages/orders.php" class="btn btn-outline"><?= t('btn_cancel') ?></a>
            </div>
        </form>
    </div>
</div>

<?php elseif ($action === 'view' && $viewOrder): ?>
<div class="row g-4">
    <!-- Receipt -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h3>
                    <i class="fa-solid fa-receipt me-2" style="color:var(--primary);"></i>
                    Order #<?= h($viewOrder['ORDER_ID']) ?>
                </h3>
                <a href="<?= BASE_URL ?>frontend/pages/orders.php" class="btn btn-outline btn-sm no-print">
                    <i class="fa-solid fa-arrow-left me-1"></i><?= t('btn_back') ?>
                </a>
            </div>
            <div class="card-body">
                <!-- Order meta grid -->
                <div class="row g-3 mb-4">
                    <?php
                    $meta = [
                        ['Customer',   $viewOrder['CUSTOMER_NAME'], 'fa-user'],
                        ['Served By',  $viewOrder['EMPLOYEE_NAME'], 'fa-id-badge'],
                        ['Order Type', $viewOrder['ORDER_TYPE'],    'fa-tag'],
                        ['Date',       $viewOrder['ORDER_DATE'],     'fa-calendar'],
                    ];
                    foreach ($meta as $m):
                    ?>
                    <div class="col-6 col-md-3">
                        <div style="background:var(--surface2);border-radius:8px;padding:12px;border:1px solid var(--border);">
                            <div style="font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);margin-bottom:4px;">
                                <i class="fa-solid <?= $m[2] ?> me-1"></i><?= $m[0] ?>
                            </div>
                            <div style="font-size:13px;font-weight:600;"><?= h($m[1]) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div class="col-6 col-md-3">
                        <div style="background:var(--surface2);border-radius:8px;padding:12px;border:1px solid var(--border);">
                            <div style="font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);margin-bottom:4px;">
                                <i class="fa-solid fa-circle-dot me-1"></i>Status
                            </div>
                            <?= statusBadge($viewOrder['STATUS']) ?>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div style="background:rgba(var(--primary-rgb),.06);border-radius:8px;padding:12px;border:1px solid rgba(var(--primary-rgb),.2);">
                            <div style="font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);margin-bottom:4px;">
                                <i class="fa-solid fa-sack-dollar me-1"></i>Total
                            </div>
                            <div style="font-size:18px;font-weight:700;color:var(--primary);">
                                <?= money((float)$viewOrder['TOTAL_AMOUNT']) ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Items table -->
                <h4 style="font-size:13px;font-weight:700;margin-bottom:10px;">
                    <i class="fa-solid fa-list me-1" style="color:var(--primary);"></i> <?= t('ord_items') ?>
                </h4>
                <?php if (empty($orderItems)): ?>
                <div class="empty-state" style="padding:24px;">
                    <i class="fa-solid fa-inbox"></i>
                    <p>No items recorded.</p>
                </div>
                <?php else: ?>
                <div class="table-wrapper">
                <table class="table mb-0">
                    <thead>
                        <tr><th><?= t('col_id') ?></th><th><?= t('col_name') ?></th><th><?= t('col_quantity') ?></th><th><?= t('col_unit_cost') ?></th><th><?= t('col_total') ?></th><th><?= t('col_address') ?></th></tr>
                    </thead>
                    <tbody>
                    <?php $n=1; foreach ($orderItems as $oi): ?>
                    <tr>
                        <td class="text-muted"><?= $n++ ?></td>
                        <td><strong><?= h($oi['ITEM_NAME']) ?></strong></td>
                        <td><?= h($oi['QUANTITY']) ?></td>
                        <td><?= money((float)$oi['UNIT_PRICE']) ?></td>
                        <td><?= money((float)$oi['SUBTOTAL']) ?></td>
                        <td class="text-muted" style="font-size:12px;"><?= h($oi['SPECIAL_INSTRUCTIONS']??'—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr style="background:var(--surface2);">
                        <td colspan="4" style="text-align:right;font-weight:700;">Total</td>
                        <td colspan="2">
                            <strong style="color:var(--primary);font-size:15px;">
                                <?= money((float)$viewOrder['TOTAL_AMOUNT']) ?>
                            </strong>
                        </td>
                    </tr>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Actions sidebar -->
    <div class="col-lg-4">
        <?php if ($canWrite): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h3><i class="fa-solid fa-sliders me-2"></i><?= t('ord_update_status') ?></h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="order_id" value="<?= (int)$viewOrder['ORDER_ID'] ?>">
                    <div class="form-group">
                        <label>Order Status</label>
                        <select name="status" class="form-control">
                            <?php foreach ($orderStatuses as $s): ?>
                            <option value="<?= h($s) ?>" <?= $viewOrder['STATUS']===$s?'selected':'' ?>>
                                <?= h($s) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100" style="justify-content:center;">
                        <i class="fa-solid fa-floppy-disk me-1"></i>Save Status
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3><i class="fa-solid fa-link me-2"></i>Quick Links</h3>
            </div>
            <div class="card-body d-flex flex-column gap-2">
                <a href="<?= BASE_URL ?>frontend/pages/payments.php?action=new&order_id=<?= (int)$viewOrder['ORDER_ID'] ?>"
                   class="btn btn-success w-100" style="justify-content:center;">
                    <i class="fa-solid fa-credit-card me-1"></i>Record Payment
                </a>
                <a href="<?= BASE_URL ?>frontend/pages/kitchen.php"
                   class="btn btn-warning w-100" style="justify-content:center;">
                    <i class="fa-solid fa-fire-burner me-1"></i>Kitchen Board
                </a>
                <?php if ($canWrite): ?>
                <a href="?action=confirm_delete&id=<?= (int)$viewOrder['ORDER_ID'] ?>"
                   class="btn btn-danger w-100" style="justify-content:center;">
                    <i class="fa-solid fa-trash me-1"></i>Delete Order
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!--  ORDER LIST ══ -->

<!-- Page Stats -->
<div class="page-stats mb-4">
    <div class="page-stat-item">
        <i class="fa-solid fa-clipboard-list"></i>
        <div><div class="page-stat-num"><?= (int)($rAll['C']??0) ?></div><div class="page-stat-lbl"><?= t('dash_total_orders') ?></div></div>
    </div>
    <div class="page-stat-item">
        <i class="fa-solid fa-calendar-day"></i>
        <div><div class="page-stat-num"><?= (int)($rToday['C']??0) ?></div><div class="page-stat-lbl"><?= t('col_date') ?></div></div>
    </div>
    <div class="page-stat-item">
        <i class="fa-solid fa-hourglass-half"></i>
        <div><div class="page-stat-num"><?= (int)($rPend['C']??0) ?></div><div class="page-stat-lbl"><?= t('status_pending') ?></div></div>
    </div>
    <div class="page-stat-item">
        <i class="fa-solid fa-circle-check"></i>
        <div><div class="page-stat-num"><?= (int)($rComp['C']??0) ?></div><div class="page-stat-lbl"><?= t('status_completed') ?></div></div>
    </div>
</div>

<?php
$search       = get('q');
$filterStatus = get('status_filter');
$filterType   = get('type_filter');
$allOrders    = getAllOrders();

if ($search || $filterStatus || $filterType) {
    $allOrders = array_filter($allOrders, function($o) use ($search,$filterStatus,$filterType) {
        if ($search && stripos($o['CUSTOMER_NAME'],$search)===false && strpos((string)$o['ORDER_ID'],$search)===false) return false;
        if ($filterStatus && $o['STATUS'] !== $filterStatus) return false;
        if ($filterType   && $o['ORDER_TYPE'] !== $filterType) return false;
        return true;
    });
    $allOrders = array_values($allOrders);
}
$totalCount = count($allOrders);
$perPage    = (int)getSetting('records_per_page','10');
$pag        = paginate($totalCount, $perPage);
$orders     = array_slice($allOrders, $pag['offset'], $pag['limit']);
?>

<!-- Filter Bar -->
<form method="GET" action="" class="filter-bar mb-3">
    <span class="filter-label"><i class="fa-solid fa-filter me-1"></i><?= t('lbl_filter') ?></span>
    <input type="text" name="q" class="form-control" style="max-width:200px;"
           placeholder="Search order# / customer…" value="<?= h($search) ?>">
    <select name="status_filter" class="form-control" style="max-width:140px;">
        <option value=""><?= t('lbl_all_statuses') ?></option>
        <?php foreach ($orderStatuses as $s): ?>
        <option value="<?= h($s) ?>" <?= $filterStatus===$s?'selected':'' ?>><?= h($s) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="type_filter" class="form-control" style="max-width:130px;">
        <option value=""><?= t('lbl_all_types') ?></option>
        <option value="Dine-In"  <?= $filterType==='Dine-In' ?'selected':'' ?>>Dine-In</option>
        <option value="Takeaway" <?= $filterType==='Takeaway'?'selected':'' ?>>Takeaway</option>
        <option value="Delivery" <?= $filterType==='Delivery'?'selected':'' ?>>Delivery</option>
    </select>
    <button type="submit" class="btn btn-dark btn-sm">
        <i class="fa-solid fa-magnifying-glass me-1"></i><?= t('btn_search') ?>
    </button>
    <?php if ($search||$filterStatus||$filterType): ?>
    <a href="<?= BASE_URL ?>frontend/pages/orders.php" class="btn btn-outline btn-sm">
        <i class="fa-solid fa-xmark me-1"></i><?= t('btn_clear') ?>
    </a>
    <?php endif; ?>
    <?php if ($canWrite): ?>
    <div class="ms-auto">
        <?= exportBtn('orders.php') ?> <a href="?action=new" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-plus me-1"></i><?= t('ord_new') ?>
        </a>
    </div>
    <?php endif; ?>
</form>

<div class="card">
    <div class="card-header">
        <h3>
            <i class="fa-solid fa-clipboard-list me-2" style="color:var(--primary);"></i>
            Orders
            <span class="badge bg-secondary ms-1" style="font-size:11px;"><?= $totalCount ?></span>
        </h3>
        <span style="font-size:12px;color:var(--text-muted);">
            <?= t('lbl_showing') ?> <?= count($orders) ?> <?= t('lbl_of') ?> <?= $totalCount ?>
        </span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($orders)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-clipboard"></i>
            <p><?= t('empty_no_data') ?><?= ($search||$filterStatus||$filterType)?' Try different filters.':'' ?></p>
        </div>
        <?php else: ?>
        <div class="table-wrapper">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Order #</th><th>Customer</th><th>Served By</th><th>Type</th>
                    <th>Date</th><th>Total</th><th>Status</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $typeIcon = ['Dine-In'=>'fa-utensils','Takeaway'=>'fa-bag-shopping','Delivery'=>'fa-motorcycle'];
            foreach ($orders as $o):
                $ti = $typeIcon[$o['ORDER_TYPE']] ?? 'fa-tag';
            ?>
            <tr>
                <td><strong style="color:var(--primary);">#<?= h($o['ORDER_ID']) ?></strong></td>
                <td><?= h($o['CUSTOMER_NAME']) ?></td>
                <td class="text-muted" style="font-size:12px;"><?= h($o['EMPLOYEE_NAME']) ?></td>
                <td>
                    <i class="fa-solid <?= $ti ?> me-1 text-muted"></i><?= h($o['ORDER_TYPE']) ?>
                </td>
                <td class="text-muted" style="font-size:12px;"><?= h($o['ORDER_DATE']) ?></td>
                <td><strong><?= money((float)$o['TOTAL_AMOUNT']) ?></strong></td>
                <td><?= statusBadge($o['STATUS']) ?></td>
                <td>
                    <a href="?action=view&id=<?= (int)$o['ORDER_ID'] ?>" class="btn btn-info btn-sm me-1">
                        <i class="fa-solid fa-eye"></i>
                    </a>
                    <?php if ($canWrite): ?>
                    <a href="?action=confirm_delete&id=<?= (int)$o['ORDER_ID'] ?>" class="btn btn-danger btn-sm">
                        <i class="fa-solid fa-trash"></i>
                    </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php if ($pag['total_pages'] > 1): ?>
        <div class="p-3 border-top"><?= $pag['html'] ?></div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
