<?php
require_once __DIR__ . '/../../backend/config/bootstrap.php';
if (!defined('BASE_URL')) { define('BASE_URL', '/RestaurantDB/'); }
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../../backend/includes/rbac.php';
require_once __DIR__ . '/../includes/header.php';
// NOTE: adjustBrightness() and hexToRgb() are already defined in header.php

requireAccess('dashboard');
$conn = getDBConnection();

/*  Inline query helper  */
function qdash($conn, string $sql): array {
    $s = oci_parse($conn, $sql);
    oci_execute($s);
    $r = oci_fetch_assoc($s);
    oci_free_statement($s);
    return $r ?: [];
}

/*  Summary stats  */
$totalCustomers = (int)(qdash($conn, "SELECT COUNT(*) C FROM CUSTOMER")['C'] ?? 0);
$ordersToday    = (int)(qdash($conn, "SELECT COUNT(*) C FROM ORDERS WHERE TRUNC(ORDER_DATE)=TRUNC(SYSDATE)")['C'] ?? 0);
$revenueToday   = (float)(qdash($conn, "SELECT NVL(SUM(AMOUNT),0) C FROM PAYMENT WHERE TRUNC(PAYMENT_DATE)=TRUNC(SYSDATE) AND PAYMENT_STATUS='Paid'")['C'] ?? 0);
$lowStock       = (int)(qdash($conn, "SELECT COUNT(*) C FROM INVENTORY_ITEM WHERE QUANTITY_IN_STOCK<=REORDER_LEVEL")['C'] ?? 0);
$kitchenActive  = (int)(qdash($conn, "SELECT COUNT(*) C FROM KITCHEN_ORDER WHERE KITCHEN_STATUS NOT IN ('Ready','Cancelled')")['C'] ?? 0);
$totalOrders    = (int)(qdash($conn, "SELECT COUNT(*) C FROM ORDERS")['C'] ?? 0);
$revenueMonth   = (float)(qdash($conn, "SELECT NVL(SUM(AMOUNT),0) C FROM PAYMENT WHERE TRUNC(PAYMENT_DATE,'MM')=TRUNC(SYSDATE,'MM') AND PAYMENT_STATUS='Paid'")['C'] ?? 0);
$totalMenuItems = (int)(qdash($conn, "SELECT COUNT(*) C FROM MENU_ITEM WHERE AVAILABILITY='Yes'")['C'] ?? 0);

/*  Revenue last 7 days: use TO_CHAR so PHP gets a real string, not Oracle LOB  */
$r7 = oci_parse($conn,
    "SELECT TO_CHAR(TRUNC(PAYMENT_DATE),'YYYY-MM-DD') DT,
            NVL(SUM(AMOUNT),0) AMT
     FROM   PAYMENT
     WHERE  PAYMENT_DATE >= SYSDATE-6
       AND  PAYMENT_STATUS='Paid'
     GROUP BY TRUNC(PAYMENT_DATE)
     ORDER BY TRUNC(PAYMENT_DATE)");
oci_execute($r7);
$rev7Labels = []; $rev7Data = [];
while ($r = oci_fetch_assoc($r7)) {
    // DT is now 'YYYY-MM-DD' string — safe for date()
    $ts = strtotime($r['DT']);
    $rev7Labels[] = $ts !== false ? date('D d', $ts) : $r['DT'];
    $rev7Data[]   = round((float)$r['AMT'], 2);
}
oci_free_statement($r7);

/*  Orders by type  */
$rType = oci_parse($conn, "SELECT ORDER_TYPE, COUNT(*) CNT FROM ORDERS GROUP BY ORDER_TYPE");
oci_execute($rType);
$typeLabels = []; $typeData = [];
while ($r = oci_fetch_assoc($rType)) { $typeLabels[] = $r['ORDER_TYPE']; $typeData[] = (int)$r['CNT']; }
oci_free_statement($rType);

/*  Orders by status (last 30 days)  */
$rStat = oci_parse($conn, "SELECT STATUS, COUNT(*) CNT FROM ORDERS WHERE ORDER_DATE >= SYSDATE-29 GROUP BY STATUS ORDER BY COUNT(*) DESC");
oci_execute($rStat);
$stLabels = []; $stData = [];
while ($r = oci_fetch_assoc($rStat)) { $stLabels[] = $r['STATUS']; $stData[] = (int)$r['CNT']; }
oci_free_statement($rStat);

/*  Top 5 menu items by quantity sold  */
$rTop = oci_parse($conn,
    "SELECT m.ITEM_NAME, NVL(SUM(oi.QUANTITY),0) QTY
     FROM MENU_ITEM m
     LEFT JOIN ORDER_ITEM oi ON oi.ITEM_ID = m.ITEM_ID
     GROUP BY m.ITEM_NAME
     ORDER BY QTY DESC
     FETCH FIRST 5 ROWS ONLY");
oci_execute($rTop);
$topLabels = []; $topData = [];
while ($r = oci_fetch_assoc($rTop)) { $topLabels[] = $r['ITEM_NAME']; $topData[] = (int)$r['QTY']; }
oci_free_statement($rTop);

/*  Monthly revenue (last 6 months)  */
$rMonth = oci_parse($conn,
    "SELECT TO_CHAR(PAYMENT_DATE,'Mon YYYY') MON,
            TO_CHAR(TRUNC(PAYMENT_DATE,'MM'),'YYYY-MM-DD') SORT_DT,
            NVL(SUM(AMOUNT),0) AMT
     FROM   PAYMENT
     WHERE  PAYMENT_STATUS='Paid'
       AND  PAYMENT_DATE >= ADD_MONTHS(SYSDATE,-5)
     GROUP BY TO_CHAR(PAYMENT_DATE,'Mon YYYY'), TRUNC(PAYMENT_DATE,'MM')
     ORDER BY TRUNC(PAYMENT_DATE,'MM')");
oci_execute($rMonth);
$monthLabels = []; $monthData = [];
while ($r = oci_fetch_assoc($rMonth)) { $monthLabels[] = $r['MON']; $monthData[] = round((float)$r['AMT'], 2); }
oci_free_statement($rMonth);

/*  Kitchen status distribution  */
$rKitch = oci_parse($conn, "SELECT KITCHEN_STATUS, COUNT(*) CNT FROM KITCHEN_ORDER GROUP BY KITCHEN_STATUS ORDER BY COUNT(*) DESC");
oci_execute($rKitch);
$kitLabels = []; $kitData = [];
while ($r = oci_fetch_assoc($rKitch)) { $kitLabels[] = $r['KITCHEN_STATUS']; $kitData[] = (int)$r['CNT']; }
oci_free_statement($rKitch);

/*  Recent orders  */
$rRecent = oci_parse($conn,
    "SELECT o.ORDER_ID, o.ORDER_TYPE, o.STATUS, o.TOTAL_AMOUNT,
            TO_CHAR(o.ORDER_DATE,'DD-Mon-YYYY HH24:MI') OD,
            c.FIRST_NAME||' '||c.LAST_NAME CNAME
     FROM   ORDERS o
     JOIN   CUSTOMER c ON c.CUSTOMER_ID = o.CUSTOMER_ID
     ORDER BY o.ORDER_DATE DESC
     FETCH FIRST 8 ROWS ONLY");
oci_execute($rRecent);
$recentOrders = [];
while ($r = oci_fetch_assoc($rRecent)) { $recentOrders[] = $r; }
oci_free_statement($rRecent);

oci_close($conn);

/*  Chart color  */
$primaryColor = getSetting('primary_color', '#c0392b');
$rgb          = hexToRgb($primaryColor);   // already defined in header.php

/*  Build chart scripts (output into $chartScripts, printed by footer.php)  */
ob_start(); ?>
(function(){
    const pc  = '<?= h($primaryColor) ?>';
    const rgb = '<?= $rgb ?>';

    /* helper — creates a consistent chart config */
    function lineChart(id, labels, data, label) {
        const ctx = document.getElementById(id);
        if (!ctx) return;
        new Chart(ctx.getContext('2d'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: label,
                    data: data,
                    borderColor: pc,
                    backgroundColor: 'rgba('+rgb+',.10)',
                    borderWidth: 2.5, fill: true, tension: .4,
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

    /* 1 — Revenue 7 days */
    lineChart('chartRevenue7',
        <?= json_encode($rev7Labels   ?: ['No data']) ?>,
        <?= json_encode($rev7Data     ?: [0]) ?>,
        'Revenue');

    /* 2 — Orders by type (pie) — with percentages */
    var ctx2 = document.getElementById('chartOrderType');
    if (ctx2) new Chart(ctx2.getContext('2d'), {
        type: 'pie',
        data: {
            labels: <?= json_encode($typeLabels ?: ['No data']) ?>,
            datasets: [{ data: <?= json_encode($typeData ?: [1]) ?>,
                backgroundColor: [pc,'#2980b9','#27ae60','#f39c12','#8e44ad'],
                borderWidth: 2, borderColor: 'rgba(255,255,255,.3)' }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { padding: 10, font: { size: 11 } } },
                tooltip: {
                    callbacks: {
                        label: function(c) {
                            var total = c.dataset.data.reduce(function(a,b){return a+b;},0);
                            var pct   = total > 0 ? Math.round(c.parsed / total * 100) : 0;
                            return ' ' + c.label + ': ' + c.parsed + ' (' + pct + '%)';
                        }
                    }
                }
            }
        }
    });

    /* 3 — Orders by status (bar) — with percentage in tooltip */
    var ctx3 = document.getElementById('chartOrderStatus');
    if (ctx3) new Chart(ctx3.getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($stLabels ?: ['No data']) ?>,
            datasets: [{ label: 'Orders',
                data: <?= json_encode($stData ?: [0]) ?>,
                backgroundColor: ['#27ae60', pc, '#f39c12', '#e74c3c', '#2980b9'],
                borderRadius: 6, borderWidth: 0 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(c) {
                            var total = c.dataset.data.reduce(function(a,b){return a+b;},0);
                            var pct   = total > 0 ? Math.round(c.parsed.y / total * 100) : 0;
                            return ' ' + c.dataset.label + ': ' + c.parsed.y + ' (' + pct + '%)';
                        }
                    }
                }
            },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });

    /* 4 — Top items (horizontal bar) */
    var ctx4 = document.getElementById('chartTopItems');
    if (ctx4) new Chart(ctx4.getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($topLabels ?: ['No data']) ?>,
            datasets: [{ label: 'Qty Sold',
                data: <?= json_encode($topData ?: [0]) ?>,
                backgroundColor: 'rgba('+rgb+',.75)',
                borderRadius: 5, borderWidth: 0 }]
        },
        options: {
            indexAxis: 'y', responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true } }
        }
    });

    /* 5 — Monthly revenue (bar) */
    var ctx5 = document.getElementById('chartMonthly');
    if (ctx5) new Chart(ctx5.getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($monthLabels ?: ['No data']) ?>,
            datasets: [{
                label: 'Revenue',
                data: <?= json_encode($monthData ?: [0]) ?>,
                backgroundColor: 'rgba('+rgb+',.80)',
                borderRadius: 6, borderWidth: 0
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });

    /* 6 — Kitchen doughnut — with percentages */
    var ctx6 = document.getElementById('chartKitchen');
    if (ctx6) new Chart(ctx6.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($kitLabels ?: ['No data']) ?>,
            datasets: [{ data: <?= json_encode($kitData ?: [1]) ?>,
                backgroundColor: ['#f39c12', pc, '#27ae60', '#6c757d'],
                borderWidth: 0 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false, cutout: '65%',
            plugins: {
                legend: { position: 'bottom', labels: { padding: 10, font: { size: 11 } } },
                tooltip: {
                    callbacks: {
                        label: function(c) {
                            var total = c.dataset.data.reduce(function(a,b){return a+b;},0);
                            var pct   = total > 0 ? Math.round(c.parsed / total * 100) : 0;
                            return ' ' + c.label + ': ' + c.parsed + ' (' + pct + '%)';
                        }
                    }
                }
            }
        }
    });
})();
<?php
$chartScripts = ob_get_clean();
?>

<!-- ════════════════════════════════
     STAT CARDS (8 cards)
════════════════════════════════ -->
<div class="stats-grid mb-4">
    <div class="stat-card">
        <div class="stat-icon red"><i class="fa-solid fa-users"></i></div>
        <div><div class="stat-value"><?= number_format($totalCustomers) ?></div><div class="stat-label"><?= t('dash_total_customers') ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fa-solid fa-clipboard-list"></i></div>
        <div><div class="stat-value"><?= $ordersToday ?></div><div class="stat-label"><?= t('dash_orders_today') ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fa-solid fa-sack-dollar"></i></div>
        <div><div class="stat-value"><?= money($revenueToday) ?></div><div class="stat-label"><?= t('dash_revenue_today') ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fa-solid fa-chart-line"></i></div>
        <div><div class="stat-value"><?= money($revenueMonth) ?></div><div class="stat-label"><?= t('dash_revenue_month') ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fa-solid fa-fire-burner"></i></div>
        <div><div class="stat-value"><?= $kitchenActive ?></div><div class="stat-label"><?= t('dash_kitchen_active') ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div><div class="stat-value"><?= $lowStock ?></div><div class="stat-label"><?= t('dash_low_stock') ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple"><i class="fa-solid fa-utensils"></i></div>
        <div><div class="stat-value"><?= $totalMenuItems ?></div><div class="stat-label"><?= t('dash_available_items') ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fa-solid fa-receipt"></i></div>
        <div><div class="stat-value"><?= number_format($totalOrders) ?></div><div class="stat-label"><?= t('dash_total_orders') ?></div></div>
    </div>
</div>

<!-- ════════════════════════════════
     QUICK ACTIONS
════════════════════════════════ -->
<div class="d-flex gap-2 flex-wrap mb-4">
    <a href="<?= BASE_URL ?>frontend/pages/orders.php?action=new" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-plus me-1"></i><?= t('ord_new') ?>
    </a>
    <a href="<?= BASE_URL ?>frontend/pages/kitchen.php" class="btn btn-warning btn-sm">
        <i class="fa-solid fa-fire-burner me-1"></i><?= t('nav_kitchen') ?>
    </a>
    <a href="<?= BASE_URL ?>frontend/pages/payments.php" class="btn btn-success btn-sm">
        <i class="fa-solid fa-credit-card me-1"></i><?= t('nav_payments') ?>
    </a>
    <a href="<?= BASE_URL ?>frontend/pages/reports.php" class="btn btn-info btn-sm">
        <i class="fa-solid fa-chart-line me-1"></i><?= t('nav_revenue') ?>
    </a>
    <a href="<?= BASE_URL ?>frontend/pages/inventory.php" class="btn btn-secondary btn-sm">
        <i class="fa-solid fa-boxes-stacked me-1"></i><?= t('nav_inventory') ?>
    </a>
</div>

<!-- ════════════════════════════════
     ROW 1: Line chart + Pie chart
════════════════════════════════ -->
<div class="row g-3 mb-3">
    <div class="col-xl-8 col-lg-7">
        <div class="chart-card h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h4 class="mb-0">
                    <i class="fa-solid fa-chart-line me-2" style="color:var(--primary);"></i>
                    <?= t('dash_revenue_7days') ?>
                </h4>
                <span class="badge bg-secondary" style="font-size:11px;"><?= money($revenueToday) ?> today</span>
            </div>
            <div class="chart-wrapper" style="height:200px;">
                <canvas id="chartRevenue7"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-lg-5">
        <div class="chart-card h-100">
            <h4 class="mb-3">
                <i class="fa-solid fa-chart-pie me-2" style="color:var(--primary);"></i>
                <?= t('dash_orders_by_type') ?>
            </h4>
            <div class="chart-wrapper" style="height:200px;">
                <canvas id="chartOrderType"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════
     ROW 2: Status bar + Top items
════════════════════════════════ -->
<div class="row g-3 mb-3">
    <div class="col-lg-5">
        <div class="chart-card h-100">
            <h4 class="mb-3">
                <i class="fa-solid fa-chart-bar me-2" style="color:var(--primary);"></i>
                Order Status (Last 30 Days)
            </h4>
            <div class="chart-wrapper" style="height:200px;">
                <canvas id="chartOrderStatus"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="chart-card h-100">
            <h4 class="mb-3">
                <i class="fa-solid fa-star me-2" style="color:var(--primary);"></i>
                Top 5 Menu Items by Sales
            </h4>
            <div class="chart-wrapper" style="height:200px;">
                <canvas id="chartTopItems"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════
     ROW 3: Monthly revenue + Kitchen
════════════════════════════════ -->
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="chart-card h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h4 class="mb-0">
                    <i class="fa-solid fa-calendar-days me-2" style="color:var(--primary);"></i>
                    Monthly Revenue — Last 6 Months
                </h4>
                <span class="badge bg-secondary" style="font-size:11px;"><?= money($revenueMonth) ?> this month</span>
            </div>
            <div class="chart-wrapper" style="height:200px;">
                <canvas id="chartMonthly"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="chart-card h-100">
            <h4 class="mb-3">
                <i class="fa-solid fa-fire-burner me-2" style="color:var(--primary);"></i>
                Kitchen Distribution
            </h4>
            <div class="chart-wrapper" style="height:200px;">
                <canvas id="chartKitchen"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════
     RECENT ORDERS TABLE
════════════════════════════════ -->
<div class="card">
    <div class="card-header">
        <h3 class="mb-0">
            <i class="fa-solid fa-clock-rotate-left me-2" style="color:var(--primary);"></i>
            <?= t('dash_recent_orders') ?>
        </h3>
        <a href="<?= BASE_URL ?>frontend/pages/orders.php" class="btn btn-outline btn-sm">
            <i class="fa-solid fa-eye me-1"></i><?= t('btn_view_all') ?>
        </a>
    </div>
    <div class="card-body p-0">
        <?php if (empty($recentOrders)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-inbox"></i>
            <p>No orders yet. <a href="<?= BASE_URL ?>frontend/pages/orders.php?action=new">Create the first order.</a></p>
        </div>
        <?php else: ?>
        <div class="table-wrapper">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th><?= t('col_order') ?></th><th><?= t('col_customer') ?></th><th><?= t('col_type') ?></th>
                    <th><?= t('col_date') ?></th><th><?= t('col_total') ?></th><th><?= t('col_status') ?></th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recentOrders as $o): ?>
            <tr>
                <td><strong style="color:var(--primary);">#<?= h($o['ORDER_ID']) ?></strong></td>
                <td><?= h($o['CNAME']) ?></td>
                <td><span class="badge bg-secondary" style="font-size:11px;"><?= h($o['ORDER_TYPE']) ?></span></td>
                <td style="color:var(--text-muted);font-size:12px;"><?= h($o['OD']) ?></td>
                <td><strong><?= money((float)$o['TOTAL_AMOUNT']) ?></strong></td>
                <td><?= statusBadge($o['STATUS']) ?></td>
                <td>
                    <a href="<?= BASE_URL ?>frontend/pages/orders.php?action=view&id=<?= (int)$o['ORDER_ID'] ?>"
                       class="btn btn-outline btn-sm">
                        <i class="fa-solid fa-eye"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
