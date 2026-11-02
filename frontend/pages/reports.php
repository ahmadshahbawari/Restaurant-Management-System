<?php
if (!defined('BASE_URL')) { define('BASE_URL', '/RestaurantDB/'); }
$pageTitle = 'Revenue Reports';
require_once __DIR__ . '/../../backend/includes/rbac.php';
require_once __DIR__ . '/../includes/header.php';
// hexToRgb() and adjustBrightness() are already defined in header.php

requireAccess('reports');

$period = get('period', 'weekly'); // today | weekly | monthly | yearly

/*  Build WHERE clauses using if/else (PHP 7 compatible, no match)  */
if ($period === 'today') {
    $payWhere   = "TRUNC(p.PAYMENT_DATE) = TRUNC(SYSDATE)";
    $ordWhere   = "TRUNC(o.ORDER_DATE)   = TRUNC(SYSDATE)";
    $groupExpr  = "TO_CHAR(p.PAYMENT_DATE,'HH24')||':00'";
    $periodLabel = 'Today';
} elseif ($period === 'monthly') {
    $payWhere   = "TRUNC(p.PAYMENT_DATE,'MM') = TRUNC(SYSDATE,'MM')";
    $ordWhere   = "TRUNC(o.ORDER_DATE,'MM')   = TRUNC(SYSDATE,'MM')";
    $groupExpr  = "TO_CHAR(p.PAYMENT_DATE,'DD Mon')";
    $periodLabel = 'This Month';
} elseif ($period === 'yearly') {
    $payWhere   = "TRUNC(p.PAYMENT_DATE,'YYYY') = TRUNC(SYSDATE,'YYYY')";
    $ordWhere   = "TRUNC(o.ORDER_DATE,'YYYY')   = TRUNC(SYSDATE,'YYYY')";
    $groupExpr  = "TO_CHAR(p.PAYMENT_DATE,'Mon YYYY')";
    $periodLabel = 'This Year';
} else {
    // default: weekly (last 7 days)
    $period     = 'weekly';
    $payWhere   = "p.PAYMENT_DATE >= SYSDATE - 6";
    $ordWhere   = "o.ORDER_DATE   >= SYSDATE - 6";
    $groupExpr  = "TO_CHAR(p.PAYMENT_DATE,'DY DD-Mon')";
    $periodLabel = 'Last 7 Days';
}

$conn = getDBConnection();

/*  Helper: run a single-row query  */
function qrep($conn, $sql) {
    $s = oci_parse($conn, $sql);
    oci_execute($s);
    $r = oci_fetch_assoc($s);
    oci_free_statement($s);
    return $r ? $r : array();
}

/*  Summary stats  */
$rRev = qrep($conn,
    "SELECT NVL(SUM(p.AMOUNT),0) C
     FROM PAYMENT p
     WHERE p.PAYMENT_STATUS = 'Paid'
       AND $payWhere");
$totalRevenue = (float)($rRev['C'] ?? 0);

$rOrd = qrep($conn,
    "SELECT COUNT(*) C
     FROM ORDERS o
     WHERE $ordWhere");
$totalOrders = (int)($rOrd['C'] ?? 0);

$avgOrder = ($totalOrders > 0) ? ($totalRevenue / $totalOrders) : 0;

$rPaid = qrep($conn,
    "SELECT COUNT(*) C
     FROM PAYMENT p
     WHERE p.PAYMENT_STATUS = 'Paid'
       AND $payWhere");
$paidCount = (int)($rPaid['C'] ?? 0);

$rUnpd = qrep($conn,
    "SELECT COUNT(*) C
     FROM PAYMENT p
     WHERE p.PAYMENT_STATUS = 'Unpaid'
       AND $payWhere");
$unpaidCount = (int)($rUnpd['C'] ?? 0);

/*  Revenue over time  */
$sRevTime = oci_parse($conn,
    "SELECT $groupExpr LBL,
            NVL(SUM(p.AMOUNT), 0) AMT
     FROM   PAYMENT p
     WHERE  p.PAYMENT_STATUS = 'Paid'
       AND  $payWhere
     GROUP BY $groupExpr
     ORDER BY MIN(p.PAYMENT_DATE)");
oci_execute($sRevTime);
$revLabels = array();
$revData   = array();
while ($r = oci_fetch_assoc($sRevTime)) {
    $revLabels[] = $r['LBL'];
    $revData[]   = round((float)$r['AMT'], 2);
}
oci_free_statement($sRevTime);

/*  Revenue by order type  */
$sType = oci_parse($conn,
    "SELECT o.ORDER_TYPE,
            NVL(SUM(p.AMOUNT), 0) AMT
     FROM   PAYMENT p
     JOIN   ORDERS o ON o.ORDER_ID = p.ORDER_ID
     WHERE  p.PAYMENT_STATUS = 'Paid'
       AND  $payWhere
     GROUP BY o.ORDER_TYPE
     ORDER BY AMT DESC");
oci_execute($sType);
$typeLabels = array();
$typeData   = array();
while ($r = oci_fetch_assoc($sType)) {
    $typeLabels[] = $r['ORDER_TYPE'];
    $typeData[]   = round((float)$r['AMT'], 2);
}
oci_free_statement($sType);

/*  Top 5 customers  */
$sCust = oci_parse($conn,
    "SELECT c.FIRST_NAME || ' ' || c.LAST_NAME AS CNAME,
            NVL(SUM(p.AMOUNT), 0) AMT,
            COUNT(p.PAYMENT_ID) CNT
     FROM   PAYMENT p
     JOIN   ORDERS   o ON o.ORDER_ID   = p.ORDER_ID
     JOIN   CUSTOMER c ON c.CUSTOMER_ID = o.CUSTOMER_ID
     WHERE  p.PAYMENT_STATUS = 'Paid'
       AND  $payWhere
     GROUP BY c.FIRST_NAME || ' ' || c.LAST_NAME
     ORDER BY AMT DESC
     FETCH FIRST 5 ROWS ONLY");
oci_execute($sCust);
$topCust = array();
while ($r = oci_fetch_assoc($sCust)) { $topCust[] = $r; }
oci_free_statement($sCust);

/*  Payment detail list (last 50)  */
$sPay = oci_parse($conn,
    "SELECT p.PAYMENT_ID,
            p.ORDER_ID,
            p.AMOUNT,
            p.PAYMENT_STATUS,
            TO_CHAR(p.PAYMENT_DATE,'DD-Mon-YYYY HH24:MI') PD,
            c.FIRST_NAME || ' ' || c.LAST_NAME AS CNAME,
            o.ORDER_TYPE
     FROM   PAYMENT p
     JOIN   ORDERS   o ON o.ORDER_ID   = p.ORDER_ID
     JOIN   CUSTOMER c ON c.CUSTOMER_ID = o.CUSTOMER_ID
     WHERE  $payWhere
     ORDER BY p.PAYMENT_DATE DESC
     FETCH FIRST 50 ROWS ONLY");
oci_execute($sPay);
$payments = array();
while ($r = oci_fetch_assoc($sPay)) { $payments[] = $r; }
oci_free_statement($sPay);

oci_close($conn);

/*  Colors  */
$primaryColor = getSetting('primary_color', '#c0392b');
// hexToRgb() is already defined in header.php
$rgb = hexToRgb($primaryColor);

/*  Chart JS (collected into $chartScripts for footer.php)  */
ob_start(); ?>
(function () {
    var pc  = '<?= h($primaryColor) ?>';
    var rgb = '<?= $rgb ?>';

    /* Revenue over time — Line */
    var c1 = document.getElementById('chartRevTime');
    if (c1) {
        new Chart(c1.getContext('2d'), {
            type: 'line',
            data: {
                labels: <?= json_encode($revLabels ? $revLabels : array('No data')) ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?= json_encode($revData ? $revData : array(0)) ?>,
                    borderColor: pc,
                    backgroundColor: 'rgba(' + rgb + ',.12)',
                    fill: true, tension: .4, borderWidth: 2.5,
                    pointBackgroundColor: pc, pointRadius: 4, pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }

    /* Revenue by type — Doughnut */
    var c2 = document.getElementById('chartRevType');
    if (c2) {
        new Chart(c2.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($typeLabels ? $typeLabels : array('No data')) ?>,
                datasets: [{
                    data: <?= json_encode($typeData ? $typeData : array(1)) ?>,
                    backgroundColor: [pc, '#2980b9', '#27ae60', '#f39c12'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '60%',
                plugins: { legend: { position: 'bottom', labels: { padding: 10, font: { size: 11 } } } }
            }
        });
    }
})();
<?php
$chartScripts = ob_get_clean();
?>

<!-- ═══ Period filter buttons ═══ -->
<div class="filter-bar mb-4">
    <span class="filter-label">
        <i class="fa-solid fa-clock-rotate-left me-1"></i> Period:
    </span>
    <?php
    $periods = array(
        'today'   => 'Today',
        'weekly'  => 'Last 7 Days',
        'monthly' => 'This Month',
        'yearly'  => 'This Year',
    );
    foreach ($periods as $k => $v):
    ?>
    <a href="?period=<?= h($k) ?>"
       class="btn btn-sm <?= ($period === $k) ? 'btn-primary' : 'btn-outline' ?>">
        <?= h($v) ?>
    </a>
    <?php endforeach; ?>
    <span style="font-size:12px;color:var(--text-muted);margin-left:auto;">
        Showing: <strong><?= h($periodLabel) ?></strong>
    </span>
</div>

<!-- ═══ Summary stat bar ═══ -->
<div class="page-stats mb-4">
    <div class="page-stat-item">
        <i class="fa-solid fa-sack-dollar" style="color:#27ae60;background:rgba(39,174,96,.1);"></i>
        <div><div class="page-stat-num"><?= money($totalRevenue) ?></div><div class="page-stat-lbl">Total Revenue</div></div>
    </div>
    <div class="page-stat-item">
        <i class="fa-solid fa-receipt"></i>
        <div><div class="page-stat-num"><?= $totalOrders ?></div><div class="page-stat-lbl"><?= t('dash_total_orders') ?></div></div>
    </div>
    <div class="page-stat-item">
        <i class="fa-solid fa-chart-line"></i>
        <div><div class="page-stat-num"><?= money($avgOrder) ?></div><div class="page-stat-lbl">Avg Order Value</div></div>
    </div>
    <div class="page-stat-item">
        <i class="fa-solid fa-circle-check" style="color:#27ae60;background:rgba(39,174,96,.1);"></i>
        <div><div class="page-stat-num"><?= $paidCount ?></div><div class="page-stat-lbl"><?= t('status_paid') ?></div></div>
    </div>
    <div class="page-stat-item">
        <i class="fa-solid fa-circle-xmark" style="color:#e74c3c;background:rgba(231,76,60,.1);"></i>
        <div><div class="page-stat-num"><?= $unpaidCount ?></div><div class="page-stat-lbl"><?= t('status_unpaid') ?></div></div>
    </div>
</div>

<!-- ═══ Charts ═══ -->
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="chart-card h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h4 class="mb-0">
                    <i class="fa-solid fa-chart-area me-2" style="color:var(--primary);"></i>
                    Revenue Over Time
                </h4>
                <span class="badge bg-secondary" style="font-size:11px;"><?= h($periodLabel) ?></span>
            </div>
            <div class="chart-wrapper" style="height:230px;">
                <canvas id="chartRevTime"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="chart-card h-100">
            <h4 class="mb-3">
                <i class="fa-solid fa-chart-pie me-2" style="color:var(--primary);"></i>
                Revenue by Order Type
            </h4>
            <div class="chart-wrapper" style="height:230px;">
                <canvas id="chartRevType"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ═══ Top customers + payment table ═══ -->
<div class="row g-3">
    <!-- Top 5 customers -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="mb-0">
                    <i class="fa-solid fa-trophy me-2" style="color:var(--primary);"></i>
                    Top Customers
                </h3>
            </div>
            <div class="card-body p-0">
                <?php if (empty($topCust)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-users"></i>
                    <p><?= t('empty_no_data') ?></p>
                </div>
                <?php else: ?>
                <table class="table mb-0">
                    <thead>
                        <tr><th>#</th><th><?= t('col_customer') ?></th><th><?= t('dash_revenue_today') ?></th><th><?= t('dash_total_orders') ?></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($topCust as $i => $tc): ?>
                    <tr>
                        <td>
                            <span class="badge" style="background:var(--primary);color:#fff;font-size:11px;">
                                <?= $i + 1 ?>
                            </span>
                        </td>
                        <td><?= h($tc['CNAME']) ?></td>
                        <td><strong><?= money((float)$tc['AMT']) ?></strong></td>
                        <td><?= (int)$tc['CNT'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Payment details -->
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="mb-0">
                    <i class="fa-solid fa-credit-card me-2" style="color:var(--primary);"></i>
                    Payment Details
                </h3>
                <span style="font-size:12px;color:var(--text-muted);">
                    <?= count($payments) ?> records
                </span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($payments)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-credit-card"></i>
                    <p><?= t('empty_no_data') ?></p>
                </div>
                <?php else: ?>
                <div class="table-wrapper">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Order #</th><th><?= t('col_customer') ?></th><th><?= t('col_type') ?></th>
                            <th><?= t('col_amount') ?></th><th><?= t('col_status') ?></th><th><?= t('col_date') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($payments as $p): ?>
                    <tr>
                        <td>
                            <a href="<?= BASE_URL ?>frontend/pages/orders.php?action=view&id=<?= (int)$p['ORDER_ID'] ?>">
                                <strong>#<?= h($p['ORDER_ID']) ?></strong>
                            </a>
                        </td>
                        <td><?= h($p['CNAME']) ?></td>
                        <td>
                            <span class="badge bg-secondary" style="font-size:11px;">
                                <?= h($p['ORDER_TYPE']) ?>
                            </span>
                        </td>
                        <td><strong><?= money((float)$p['AMOUNT']) ?></strong></td>
                        <td><?= statusBadge($p['PAYMENT_STATUS']) ?></td>
                        <td style="font-size:12px;color:var(--text-muted);"><?= h($p['PD']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
