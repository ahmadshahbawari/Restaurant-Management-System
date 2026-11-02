<?php
/**
 * page_export.php
 * Generates a printable HTML report for any page.
 * The user prints it or uses browser "Save as PDF".
 * GET params:
 *   page  = filename of source page (e.g. customers.php)
 *   fmt   = html (default) | csv
 */
require_once __DIR__ . '/../../backend/config/bootstrap.php';
if (!defined('BASE_URL')) { define('BASE_URL', '/RestaurantDB/'); }
requireLogin();

$page = trim($_GET['page'] ?? 'dashboard.php');
$fmt  = trim($_GET['fmt']  ?? 'html');

/* ── Report settings ── */
$restName  = getSetting('restaurant_name',    'RestaurantDB');
$address   = getSetting('restaurant_address', '');
$logoPath  = getSetting('logo_path',          '');
$primary   = getSetting('primary_color',      '#c0392b');
$reportTitle = trim($_GET['title'] ?? '');

$conn = getDBConnection();

/* ── Query map ── */
$exports = array(
    'customers.php' => array(
        'title' => 'Customers Report',
        'sql'   => "SELECT CUSTOMER_ID \"#\", FIRST_NAME \"First Name\", LAST_NAME \"Last Name\",
                           PHONE, EMAIL,
                           TO_CHAR(REGISTRATION_DATE,'DD-Mon-YYYY') \"Registered\"
                    FROM CUSTOMER ORDER BY CUSTOMER_ID",
    ),
    'orders.php' => array(
        'title' => 'Orders Report',
        'sql'   => "SELECT o.ORDER_ID \"Order#\",
                           c.FIRST_NAME||' '||c.LAST_NAME \"Customer\",
                           o.ORDER_TYPE \"Type\",
                           TO_CHAR(o.ORDER_DATE,'DD-Mon-YYYY HH24:MI') \"Date\",
                           o.STATUS, o.TOTAL_AMOUNT \"Total\"
                    FROM ORDERS o
                    JOIN CUSTOMER c ON c.CUSTOMER_ID=o.CUSTOMER_ID
                    ORDER BY o.ORDER_DATE DESC",
    ),
    'payments.php' => array(
        'title' => 'Payments Report',
        'sql'   => "SELECT p.PAYMENT_ID \"Pay#\", p.ORDER_ID \"Order#\",
                           c.FIRST_NAME||' '||c.LAST_NAME \"Customer\",
                           o.ORDER_TYPE \"Type\",
                           p.AMOUNT \"Amount\", p.PAYMENT_STATUS \"Status\",
                           TO_CHAR(p.PAYMENT_DATE,'DD-Mon-YYYY HH24:MI') \"Date\"
                    FROM PAYMENT p
                    JOIN ORDERS o ON o.ORDER_ID=p.ORDER_ID
                    JOIN CUSTOMER c ON c.CUSTOMER_ID=o.CUSTOMER_ID
                    ORDER BY p.PAYMENT_DATE DESC",
    ),
    'employees.php' => array(
        'title' => 'Employees Report',
        'sql'   => "SELECT EMPLOYEE_ID \"#\", FIRST_NAME \"First Name\", LAST_NAME \"Last Name\",
                           PHONE, ROLE, SALARY,
                           TO_CHAR(HIRE_DATE,'DD-Mon-YYYY') \"Hire Date\", STATUS
                    FROM EMPLOYEE ORDER BY EMPLOYEE_ID",
    ),
    'menu_items.php' => array(
        'title' => 'Menu Items Report',
        'sql'   => "SELECT m.ITEM_ID \"#\", m.ITEM_NAME \"Item Name\",
                           c.CATEGORY_NAME \"Category\", m.PRICE, m.AVAILABILITY \"Available\"
                    FROM MENU_ITEM m JOIN CATEGORY c ON c.CATEGORY_ID=m.CATEGORY_ID
                    ORDER BY m.ITEM_ID",
    ),
    'categories.php' => array(
        'title' => 'Categories Report',
        'sql'   => "SELECT CATEGORY_ID \"#\", CATEGORY_NAME \"Name\", DESCRIPTION
                    FROM CATEGORY ORDER BY CATEGORY_ID",
    ),
    'inventory.php' => array(
        'title' => 'Inventory Report',
        'sql'   => "SELECT INVENTORY_ID \"#\", ITEM_NAME \"Item\", UNIT,
                           QUANTITY_IN_STOCK \"In Stock\", REORDER_LEVEL \"Reorder At\",
                           UNIT_COST \"Unit Cost\",
                           CASE WHEN QUANTITY_IN_STOCK<=REORDER_LEVEL THEN 'LOW STOCK' ELSE 'OK' END \"Stock Status\"
                    FROM INVENTORY_ITEM ORDER BY INVENTORY_ID",
    ),
    'suppliers.php' => array(
        'title' => 'Suppliers Report',
        'sql'   => "SELECT SUPPLIER_ID \"#\", SUPPLIER_NAME \"Supplier\",
                           PHONE, EMAIL, ADDRESS, STATUS
                    FROM SUPPLIER ORDER BY SUPPLIER_ID",
    ),
    'kitchen.php' => array(
        'title' => 'Kitchen Orders Report',
        'sql'   => "SELECT k.KITCHEN_ORDER_ID \"#\", k.ORDER_ID \"Order#\",
                           k.KITCHEN_STATUS \"Status\",
                           TO_CHAR(k.STARTED_AT,'DD-Mon-YYYY HH24:MI') \"Started\",
                           TO_CHAR(k.READY_AT,'DD-Mon-YYYY HH24:MI') \"Ready\",
                           c.FIRST_NAME||' '||c.LAST_NAME \"Customer\"
                    FROM KITCHEN_ORDER k
                    JOIN ORDERS o ON o.ORDER_ID=k.ORDER_ID
                    JOIN CUSTOMER c ON c.CUSTOMER_ID=o.CUSTOMER_ID
                    ORDER BY k.KITCHEN_ORDER_ID DESC",
    ),
    'app_users.php' => array(
        'title' => 'App Users Report',
        'sql'   => "SELECT u.USER_ID \"#\", u.USERNAME, e.ROLE, u.STATUS,
                           TO_CHAR(u.CREATED_DATE,'DD-Mon-YYYY') \"Created\"
                    FROM APP_USER u
                    LEFT JOIN EMPLOYEE e ON e.EMPLOYEE_ID=u.EMPLOYEE_ID
                    ORDER BY u.USER_ID",
    ),
    'dashboard.php' => array(
        'title' => 'Dashboard Summary',
        'sql'   => "SELECT 'Total Customers' \"Metric\", TO_CHAR(COUNT(*)) \"Value\" FROM CUSTOMER
                    UNION ALL SELECT 'Total Orders', TO_CHAR(COUNT(*)) FROM ORDERS
                    UNION ALL SELECT 'Total Revenue', TO_CHAR(NVL(SUM(AMOUNT),0),'FM999,999,990.00')
                        FROM PAYMENT WHERE PAYMENT_STATUS='Paid'
                    UNION ALL SELECT 'Orders Today', TO_CHAR(COUNT(*)) FROM ORDERS WHERE TRUNC(ORDER_DATE)=TRUNC(SYSDATE)
                    UNION ALL SELECT 'Low Stock Items', TO_CHAR(COUNT(*)) FROM INVENTORY_ITEM WHERE QUANTITY_IN_STOCK<=REORDER_LEVEL",
    ),
    'reports.php' => array(
        'title' => 'Revenue Report',
        'sql'   => "SELECT p.PAYMENT_ID \"Pay#\", p.ORDER_ID \"Order#\",
                           c.FIRST_NAME||' '||c.LAST_NAME \"Customer\",
                           o.ORDER_TYPE \"Type\",
                           p.AMOUNT \"Amount\", p.PAYMENT_STATUS \"Status\",
                           TO_CHAR(p.PAYMENT_DATE,'DD-Mon-YYYY') \"Date\"
                    FROM PAYMENT p
                    JOIN ORDERS o ON o.ORDER_ID=p.ORDER_ID
                    JOIN CUSTOMER c ON c.CUSTOMER_ID=o.CUSTOMER_ID
                    WHERE p.PAYMENT_STATUS='Paid'
                    ORDER BY p.PAYMENT_DATE DESC",
    ),
);

if (!isset($exports[$page])) { $page = 'dashboard.php'; }

$cfg   = $exports[$page];
$title = $reportTitle ?: $cfg['title'];

/* ── Fetch data ── */
$stmt = oci_parse($conn, $cfg['sql']);
oci_execute($stmt);
$ncols = oci_num_fields($stmt);
$cols  = array();
for ($i=1; $i<=$ncols; $i++) { $cols[] = oci_field_name($stmt,$i); }
$rows = array();
while ($r = oci_fetch_row($stmt)) { $rows[] = $r; }
oci_free_statement($stmt);
oci_close($conn);

/* ── CSV output ── */
if ($fmt === 'csv') {
    $filename = str_replace(' ','_',$restName).'_'.str_replace(' ','_',$title).'_'.date('Ymd_His').'.csv';
    while(ob_get_level()){ob_end_clean();}
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM
    $out = fopen('php://output','w');
    fputcsv($out, array($restName.' — '.$title));
    fputcsv($out, array('Generated: '.date('Y-m-d H:i:s')));
    fputcsv($out, array());
    fputcsv($out, $cols);
    foreach ($rows as $row) { fputcsv($out, array_map(function($v){return $v??'';}, $row)); }
    fclose($out);
    exit;
}

/* ── HTML/PDF output ── */
/* Build logo HTML — use actual restaurant logo if set */
$logoHtml = '';
if ($logoPath !== '') {
    /* Try absolute path on disk */
    $logoAbsPath = $_SERVER['DOCUMENT_ROOT'] . $logoPath;
    if (file_exists($logoAbsPath)) {
        /* Convert to base64 so it works in print/PDF without path issues */
        $logoMime = 'image/png';
        $ext = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
        $mimeMap = array('jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png',
                         'gif'=>'image/gif','svg'=>'image/svg+xml','webp'=>'image/webp');
        $logoMime = $mimeMap[$ext] ?? 'image/png';
        $logoB64  = base64_encode(file_get_contents($logoAbsPath));
        $logoHtml = '<img src="data:'.$logoMime.';base64,'.$logoB64.'" alt="Logo" '
                   .'style="height:55px;width:55px;border-radius:10px;object-fit:cover;">';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($title) ?> — <?= htmlspecialchars($restName) ?></title>
<style>
/* ── Reset ── */
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Segoe UI',Arial,sans-serif;font-size:12px;color:#222;background:#fff;padding:24px;}

/* ── Report header ── */
.rpt-header{display:flex;align-items:center;justify-content:space-between;
            border-bottom:3px solid <?= h($primary) ?>;padding-bottom:16px;margin-bottom:20px;}
.rpt-logo{display:flex;align-items:center;gap:12px;}
.rpt-logo-icon{width:55px;height:55px;border-radius:10px;
               background:<?= h($primary) ?>;display:flex;align-items:center;justify-content:center;
               color:#fff;font-size:24px;}
.rpt-name{font-size:18px;font-weight:700;color:#222;}
.rpt-address{font-size:11px;color:#666;margin-top:2px;}
.rpt-meta{text-align:right;font-size:11px;color:#666;}
.rpt-meta .rpt-title{font-size:16px;font-weight:700;color:<?= h($primary) ?>;display:block;margin-bottom:4px;}

/* ── Table ── */
table{width:100%;border-collapse:collapse;margin-bottom:20px;}
thead th{background:<?= h($primary) ?>;color:#fff;padding:8px 10px;
         text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;}
tbody tr:nth-child(even){background:#f7f7f7;}
tbody td{padding:7px 10px;border-bottom:1px solid #eee;font-size:12px;vertical-align:top;}
tbody tr:last-child td{border-bottom:none;}

/* ── Footer ── */
.rpt-footer{margin-top:24px;padding-top:12px;border-top:1px solid #ddd;
            display:flex;justify-content:space-between;font-size:10px;color:#999;}

/* ── Print controls (hidden when printing) ── */
.print-controls{background:#f4f4f4;border:1px solid #ddd;border-radius:8px;
                padding:14px 18px;margin-bottom:20px;display:flex;gap:10px;align-items:center;
                flex-wrap:wrap;}
.print-controls h3{font-size:13px;font-weight:700;margin:0;flex:1;}
.btn-print{background:<?= h($primary) ?>;color:#fff;border:none;padding:8px 18px;
           border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;}
.btn-csv{background:#27ae60;color:#fff;border:none;padding:8px 18px;
         border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block;}
.btn-back{background:#6c757d;color:#fff;border:none;padding:8px 18px;
          border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block;}
.row-count{font-size:12px;color:#666;margin-left:auto;}

@media print {
    .print-controls{display:none!important;}
    body{padding:12px;}
    /* Hide browser URL bar from printed output */
    @page {
        size: A4;
        margin: 1.5cm 1.5cm 1.5cm 1.5cm;
    }
    .rpt-header{page-break-inside:avoid;}
    thead{display:table-header-group;}
    tr{page-break-inside:avoid;}
    /* Force the footer to show our custom footer, not the browser URL */
    html {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
</style>
</head>
<body>

<!-- Print / download controls (not printed) -->
<div class="print-controls">
    <h3>📄 <?= htmlspecialchars($title) ?></h3>
    <span class="row-count"><?= count($rows) ?> records</span>
    <button class="btn-print" onclick="window.print()">🖨 Print / Save as PDF</button>
    <a class="btn-csv"
       href="?page=<?= urlencode($page) ?>&fmt=csv&title=<?= urlencode($title) ?>">
        ⬇ Download CSV
    </a>
    <a class="btn-back" href="javascript:history.back()">← Back</a>
    <small style="color:#999;font-size:11px;flex-basis:100%;margin-top:4px;">
        💡 To remove the URL from the printed page: in the print dialog → More Settings → uncheck "Headers and footers"
    </small>
</div>

<!-- Report header -->
<div class="rpt-header">
    <div class="rpt-logo">
        <?php if ($logoHtml): ?>
        <?= $logoHtml ?>
        <?php else: ?>
        <div class="rpt-logo-icon">🍽</div>
        <?php endif; ?>
        <div>
            <div class="rpt-name"><?= htmlspecialchars($restName) ?></div>
            <?php if ($address): ?>
            <div class="rpt-address"><?= htmlspecialchars($address) ?></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="rpt-meta">
        <span class="rpt-title"><?= htmlspecialchars($title) ?></span>
        <span>Date: <?= date('d M Y, H:i') ?></span><br>
        <span>Generated by: <?= htmlspecialchars($_SESSION['username'] ?? 'System') ?></span>
    </div>
</div>

<!-- Data table -->
<?php if (empty($rows)): ?>
<p style="color:#999;text-align:center;padding:32px;">No data found for this report.</p>
<?php else: ?>
<table>
    <thead>
        <tr>
            <?php foreach ($cols as $col): ?>
            <th><?= htmlspecialchars($col) ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
            <?php foreach ($row as $val): ?>
            <td><?= htmlspecialchars($val ?? '—') ?></td>
            <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<p style="font-size:11px;color:#999;text-align:right;margin-top:-12px;margin-bottom:16px;">
    Total: <?= count($rows) ?> record<?= count($rows)!==1?'s':'' ?>
</p>
<?php endif; ?>

<!-- Report footer -->
<div class="rpt-footer">
    <span><?= htmlspecialchars($restName) ?><?= $address ? ' | '.htmlspecialchars($address) : '' ?></span>
    <span>Printed: <?= date('d M Y H:i') ?></span>
</div>

</body>
</html>
