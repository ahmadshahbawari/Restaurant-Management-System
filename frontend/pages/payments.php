<?php
require_once __DIR__ . '/../../backend/config/bootstrap.php';
if (!defined('BASE_URL')) { define('BASE_URL', '/RestaurantDB/'); }
$pageTitle = 'Payments';
require_once __DIR__ . '/../../backend/includes/payment.php';
require_once __DIR__ . '/../../backend/includes/order.php';
require_once __DIR__ . '/../../backend/includes/rbac.php';
require_once __DIR__ . '/../../backend/includes/notifications.php';

requireAccess('payments');
$canWrite = canWrite('payments');

$action  = get('action', 'list');
$orderId = (int)get('order_id', '0');

/*  POST — before any HTML  */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canWrite) {
    $postAction = post('action');

    if ($postAction === 'create_payment') {
        $data = [
            'order_id'       => (int)post('order_id'),
            'amount'         => (float)post('amount'),
            'payment_status' => post('payment_status', 'Paid'),
        ];
        if (createPayment($data)) {
            notifyPayment($data['order_id'], money($data['amount']), $data['payment_status']);
            setFlash('success', 'Payment recorded successfully.');
        } else {
            setFlash('danger', 'Failed to record payment.');
        }
        redirect('frontend/pages/payments.php');

    } elseif ($postAction === 'update_payment_status') {
        $pid    = (int)post('payment_id');
        $status = post('payment_status');
        updatePaymentStatus($pid, $status)
            ? setFlash('success', 'Payment status updated.')
            : setFlash('danger',  'Update failed.');
        redirect('frontend/pages/payments.php');
    }
}

/*  Data  */
$conn = getDBConnection();
$q1 = oci_parse($conn,"SELECT COUNT(*) C FROM PAYMENT"); oci_execute($q1); $rTotal=oci_fetch_assoc($q1); oci_free_statement($q1);
$q2 = oci_parse($conn,"SELECT COUNT(*) C, NVL(SUM(AMOUNT),0) S FROM PAYMENT WHERE PAYMENT_STATUS='Paid'"); oci_execute($q2); $rPaid=oci_fetch_assoc($q2); oci_free_statement($q2);
$q3 = oci_parse($conn,"SELECT COUNT(*) C FROM PAYMENT WHERE PAYMENT_STATUS='Unpaid'"); oci_execute($q3); $rUnpd=oci_fetch_assoc($q3); oci_free_statement($q3);
$q4 = oci_parse($conn,"SELECT NVL(SUM(AMOUNT),0) S FROM PAYMENT WHERE TRUNC(PAYMENT_DATE)=TRUNC(SYSDATE) AND PAYMENT_STATUS='Paid'"); oci_execute($q4); $rToday=oci_fetch_assoc($q4); oci_free_statement($q4);
oci_close($conn);

$newOrder = ($action==='new' && $orderId) ? getOrderById($orderId) : null;

/*  HTML  */
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($action === 'new' && $newOrder && $canWrite): ?>
<!--  NEW PAYMENT FORM  -->
<div class="card" style="max-width:560px;">
    <div class="card-header">
        <h3>
            <i class="fa-solid fa-credit-card me-2" style="color:var(--primary);"></i>
            Record Payment — Order #<?= h($newOrder['ORDER_ID']) ?>
        </h3>
        <a href="<?= BASE_URL ?>frontend/pages/payments.php" class="btn btn-outline btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i>Back
        </a>
    </div>
    <div class="card-body">
        <!-- Order summary -->
        <div class="row g-3 mb-4">
            <div class="col-6">
                <div style="background:var(--surface2);border-radius:8px;padding:12px;border:1px solid var(--border);">
                    <div style="font-size:10px;text-transform:uppercase;color:var(--text-muted);margin-bottom:3px;">Customer</div>
                    <strong style="font-size:13px;"><?= h($newOrder['CUSTOMER_NAME']) ?></strong>
                </div>
            </div>
            <div class="col-6">
                <div style="background:var(--surface2);border-radius:8px;padding:12px;border:1px solid var(--border);">
                    <div style="font-size:10px;text-transform:uppercase;color:var(--text-muted);margin-bottom:3px;">Order Type</div>
                    <strong style="font-size:13px;"><?= h($newOrder['ORDER_TYPE']) ?></strong>
                </div>
            </div>
            <div class="col-6">
                <div style="background:var(--surface2);border-radius:8px;padding:12px;border:1px solid var(--border);">
                    <div style="font-size:10px;text-transform:uppercase;color:var(--text-muted);margin-bottom:3px;">Date</div>
                    <strong style="font-size:13px;"><?= h($newOrder['ORDER_DATE']) ?></strong>
                </div>
            </div>
            <div class="col-6">
                <div style="background:rgba(var(--primary-rgb),.08);border-radius:8px;padding:12px;border:1px solid rgba(var(--primary-rgb),.2);">
                    <div style="font-size:10px;text-transform:uppercase;color:var(--text-muted);margin-bottom:3px;">Order Total</div>
                    <strong style="font-size:20px;color:var(--primary);">
                        <?= money((float)$newOrder['TOTAL_AMOUNT']) ?>
                    </strong>
                </div>
            </div>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="create_payment">
            <input type="hidden" name="order_id" value="<?= (int)$newOrder['ORDER_ID'] ?>">
            <div class="form-group">
                <label><i class="fa-solid fa-coins me-1"></i> Amount Received *</label>
                <input type="number" name="amount" class="form-control" required step="0.01" min="0"
                       value="<?= h($newOrder['TOTAL_AMOUNT']) ?>" style="font-size:16px;font-weight:600;">
            </div>
            <div class="form-group">
                <label><i class="fa-solid fa-circle-dot me-1"></i> Payment Status</label>
                <select name="payment_status" class="form-control">
                    <option value="Paid">✅ Paid</option>
                    <option value="Unpaid">⏳ Unpaid</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-success">
                    <i class="fa-solid fa-credit-card me-1"></i><?= t('btn_confirm') ?>
                </button>
                <a href="<?= BASE_URL ?>frontend/pages/payments.php" class="btn btn-outline"><?= t('btn_cancel') ?></a>
            </div>
        </form>
    </div>
</div>

<?php else: ?>
<!--  PAYMENT LIST ═ -->

<!-- Page Stats -->
<div class="page-stats mb-4">
    <div class="page-stat-item">
        <i class="fa-solid fa-credit-card"></i>
        <div><div class="page-stat-num"><?= (int)($rTotal['C']??0) ?></div><div class="page-stat-lbl"><?= t('page_payments') ?></div></div>
    </div>
    <div class="page-stat-item" style="border-color:rgba(39,174,96,.3);">
        <i class="fa-solid fa-circle-check" style="color:#27ae60;background:rgba(39,174,96,.1);"></i>
        <div><div class="page-stat-num" style="color:#27ae60;"><?= (int)($rPaid['C']??0) ?></div><div class="page-stat-lbl"><?= t('status_paid') ?></div></div>
    </div>
    <div class="page-stat-item" style="border-color:rgba(231,76,60,.3);">
        <i class="fa-solid fa-circle-xmark" style="color:#e74c3c;background:rgba(231,76,60,.1);"></i>
        <div><div class="page-stat-num" style="color:#e74c3c;"><?= (int)($rUnpd['C']??0) ?></div><div class="page-stat-lbl"><?= t('status_unpaid') ?></div></div>
    </div>
    <div class="page-stat-item" style="border-color:rgba(var(--primary-rgb),.3);">
        <i class="fa-solid fa-calendar-day" style="color:var(--primary);background:rgba(var(--primary-rgb),.1);"></i>
        <div><div class="page-stat-num" style="color:var(--primary);"><?= money((float)($rToday['S']??0)) ?></div><div class="page-stat-lbl"><?= t('dash_revenue_today') ?></div></div>
    </div>
</div>

<?php
$search      = get('q');
$filterPS    = get('ps_filter');
$allPayments = getAllPayments();

if ($search || $filterPS) {
    $allPayments = array_filter($allPayments, function($p) use ($search,$filterPS) {
        if ($search && stripos($p['CUSTOMER_NAME'],$search)===false
            && strpos((string)$p['ORDER_ID'],$search)===false
            && strpos((string)$p['PAYMENT_ID'],$search)===false) return false;
        if ($filterPS && $p['PAYMENT_STATUS']!==$filterPS) return false;
        return true;
    });
    $allPayments = array_values($allPayments);
}
$totalCount = count($allPayments);
$perPage    = (int)getSetting('records_per_page','10');
$pag        = paginate($totalCount, $perPage);
$payments   = array_slice($allPayments, $pag['offset'], $pag['limit']);

$psMap = ['Paid'=>'Completed','Unpaid'=>'Pending','Refunded'=>'Cancelled'];
?>

<!-- Filter Bar -->
<form method="GET" action="" class="filter-bar mb-3">
    <span class="filter-label"><i class="fa-solid fa-filter me-1"></i><?= t('lbl_filter') ?></span>
    <input type="text" name="q" class="form-control" style="max-width:220px;"
           placeholder="Search payment# / order# / customer…" value="<?= h($search) ?>">
    <select name="ps_filter" class="form-control" style="max-width:140px;">
        <option value=""><?= t('lbl_all_statuses') ?></option>
        <option value="Paid"     <?= $filterPS==='Paid'    ?'selected':'' ?>><?= t('status_paid') ?></option>
        <option value="Unpaid"   <?= $filterPS==='Unpaid'  ?'selected':'' ?>><?= t('status_unpaid') ?></option>
        <option value="Refunded" <?= $filterPS==='Refunded'?'selected':'' ?>>Refunded</option>
    </select>
    <button type="submit" class="btn btn-dark btn-sm">
        <i class="fa-solid fa-magnifying-glass me-1"></i><?= t('btn_search') ?>
    </button>
    <?php if ($search||$filterPS): ?>
    <a href="<?= BASE_URL ?>frontend/pages/payments.php" class="btn btn-outline btn-sm">
        <i class="fa-solid fa-xmark me-1"></i><?= t('btn_clear') ?>
    </a>
    <?php endif; ?>
</form>

<div class="card">
    <div class="card-header">
        <h3>
            <i class="fa-solid fa-credit-card me-2" style="color:var(--primary);"></i>
            Payments
            <span class="badge bg-secondary ms-1" style="font-size:11px;"><?= $totalCount ?></span>
        </h3>
        <?= exportBtn('payments.php') ?> <span style="font-size:12px;color:var(--text-muted);">
            <?= t('lbl_showing') ?> <?= count($payments) ?> <?= t('lbl_of') ?> <?= $totalCount ?>
        </span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($payments)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-credit-card"></i>
            <p><?= t('empty_no_data') ?><?= ($search||$filterPS)?' Try different filters.':'' ?></p>
        </div>
        <?php else: ?>
        <div class="table-wrapper">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Pay #</th><th>Order #</th><th>Customer</th><th>Type</th>
                    <th>Amount</th><th>Status</th><th>Date</th>
                    <?php if ($canWrite): ?><th><?= t('btn_edit') ?></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($payments as $p): ?>
            <tr>
                <td class="text-muted">#<?= h($p['PAYMENT_ID']) ?></td>
                <td>
                    <a href="<?= BASE_URL ?>frontend/pages/orders.php?action=view&id=<?= (int)$p['ORDER_ID'] ?>" style="font-weight:600;">
                        #<?= h($p['ORDER_ID']) ?>
                    </a>
                </td>
                <td><?= h($p['CUSTOMER_NAME']) ?></td>
                <td>
                    <span class="badge bg-secondary" style="font-size:11px;"><?= h($p['ORDER_TYPE']) ?></span>
                </td>
                <td><strong style="color:var(--primary);"><?= money((float)$p['AMOUNT']) ?></strong></td>
                <td><?= statusBadge($psMap[$p['PAYMENT_STATUS']] ?? 'Pending') ?></td>
                <td class="text-muted" style="font-size:12px;"><?= h($p['PAYMENT_DATE']??'—') ?></td>
                <?php if ($canWrite): ?>
                <td>
                    <form method="POST" class="d-flex gap-1">
                        <input type="hidden" name="action" value="update_payment_status">
                        <input type="hidden" name="payment_id" value="<?= (int)$p['PAYMENT_ID'] ?>">
                        <select name="payment_status" class="form-control"
                                style="width:auto;font-size:12px;padding:5px 8px;">
                            <option value="Paid"     <?= $p['PAYMENT_STATUS']==='Paid'    ?'selected':'' ?>><?= t('status_paid') ?></option>
                            <option value="Unpaid"   <?= $p['PAYMENT_STATUS']==='Unpaid'  ?'selected':'' ?>><?= t('status_unpaid') ?></option>
                            <option value="Refunded" <?= $p['PAYMENT_STATUS']==='Refunded'?'selected':'' ?>>Refunded</option>
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
        <?php if ($pag['total_pages']>1): ?>
        <div class="p-3 border-top"><?= $pag['html'] ?></div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
