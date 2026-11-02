<?php
if (!defined('BASE_URL')) { define('BASE_URL', '/RestaurantDB/'); }
require_once __DIR__ . '/../../backend/config/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: '.BASE_URL.'index.php'); exit;
}

$role = trim($_SESSION['role'] ?? 'Staff');
if (!in_array($role, array('Manager','Admin'))) {
    die('Access denied — Manager or Admin role required.');
}

$allowed = array('CUSTOMER','CATEGORY','MENU_ITEM','EMPLOYEE','APP_USER','ORDERS',
                 'ORDER_ITEM','KITCHEN_ORDER','PAYMENT','INVENTORY_ITEM',
                 'SUPPLIER','SUPPLIER_INVENTORY','SYS_SETTINGS');

$table = strtoupper(trim($_GET['table'] ?? ''));

/* ── Restore guide  */
if ($table === 'RESTORE_GUIDE') {
    header('Content-Type: text/html; charset=UTF-8');
    echo '';
    exit;
}

/* ── Full backup (ALL tables)  */
if ($table === 'ALL') {
    $tables = $allowed;
    header('Content-Type: text/plain; charset=UTF-8');
    header('Content-Disposition: attachment; filename="RestaurantDB_FullBackup_'.date('Ymd_His').'.sql"');

    echo "-- \n";
    echo "-- RestaurantDB FULL BACKUP\n";
    echo "-- Generated : ".date('Y-m-d H:i:s')."\n";
    echo "-- Run this file in SQL Developer as user: Ahmad\n";
    echo "-- \n\n";

    $conn = getDBConnection();
    foreach ($tables as $tbl) {
        echo "-- TABLE: $tbl\n";
        $stmt = oci_parse($conn, "SELECT * FROM $tbl ORDER BY 1");
        $ok   = @oci_execute($stmt);
        if (!$ok) { echo "-- (skipped — table may not exist)\n\n"; continue; }
        $ncols = oci_num_fields($stmt);
        $cols  = array();
        for ($i=1; $i<=$ncols; $i++) { $cols[] = oci_field_name($stmt,$i); }
        $count = 0;
        while ($row = oci_fetch_assoc($stmt)) {
            $vals = array();
            foreach ($cols as $col) {
                $v = $row[$col];
                if ($v === null) { $vals[] = 'NULL'; }
                elseif (is_numeric($v)) { $vals[] = $v; }
                else { $vals[] = "'".str_replace("'","''",$v)."'"; }
            }
            $colList = implode(',', array_map(function($c){ return $c==='PASSWORD'?'"PASSWORD"':$c; }, $cols));
            echo "INSERT INTO $tbl ($colList) VALUES (".implode(',',$vals).");\n";
            $count++;
        }
        oci_free_statement($stmt);
        echo "-- $count rows exported from $tbl\n";
        echo "COMMIT;\n\n";
    }
    oci_close($conn);
    exit;
}

/* ── Single table export  */
if (!in_array($table, $allowed)) { die('Invalid table name.'); }

$conn  = getDBConnection();
$stmt  = oci_parse($conn, "SELECT * FROM $table ORDER BY 1");
oci_execute($stmt);
$ncols = oci_num_fields($stmt);
$cols  = array();
for ($i=1; $i<=$ncols; $i++) { $cols[] = oci_field_name($stmt,$i); }

header('Content-Type: text/plain; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$table.'_'.date('Ymd_His').'.sql"');

echo "-- RestaurantDB Export: $table\n";
echo "-- Generated: ".date('Y-m-d H:i:s')."\n\n";

while ($row = oci_fetch_assoc($stmt)) {
    $vals = array();
    foreach ($cols as $col) {
        $v = $row[$col];
        if ($v === null) { $vals[] = 'NULL'; }
        elseif (is_numeric($v)) { $vals[] = $v; }
        else { $vals[] = "'".str_replace("'","''",$v)."'"; }
    }
    $colList = implode(',', array_map(function($c){ return $c==='PASSWORD'?'"PASSWORD"':$c; }, $cols));
    echo "INSERT INTO $table ($colList) VALUES (".implode(',',$vals).");\n";
}
echo "\nCOMMIT;\n";

oci_free_statement($stmt);
oci_close($conn);
