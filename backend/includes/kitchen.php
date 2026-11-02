<?php
require_once __DIR__ . '/../config/db.php';

function getAllKitchenOrders(): array {
    $conn = getDBConnection();
    $sql  = "SELECT k.*, o.ORDER_TYPE, o.STATUS AS ORDER_STATUS,
                    c.FIRST_NAME || ' ' || c.LAST_NAME AS CUSTOMER_NAME
             FROM KITCHEN_ORDER k
             JOIN ORDERS o ON o.ORDER_ID = k.ORDER_ID
             JOIN CUSTOMER c ON c.CUSTOMER_ID = o.CUSTOMER_ID
             ORDER BY k.KITCHEN_ORDER_ID DESC";
    $stmt = oci_parse($conn, $sql);
    oci_execute($stmt);
    $rows = [];
    while ($row = oci_fetch_assoc($stmt)) { $rows[] = $row; }
    oci_free_statement($stmt);
    oci_close($conn);
    return $rows;
}

function getKitchenOrderById(int $id): ?array {
    $conn = getDBConnection();
    $sql  = "SELECT k.*, o.ORDER_TYPE
             FROM KITCHEN_ORDER k
             JOIN ORDERS o ON o.ORDER_ID = k.ORDER_ID
             WHERE k.KITCHEN_ORDER_ID = :id";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':id', $id);
    oci_execute($stmt);
    $row  = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);
    oci_close($conn);
    return $row ?: null;
}

function createKitchenOrder(int $orderId, string $notes = ''): bool {
    $conn = getDBConnection();
    $sql  = "INSERT INTO KITCHEN_ORDER (KITCHEN_ORDER_ID, ORDER_ID, KITCHEN_STATUS, STARTED_AT, NOTES)
             VALUES (KITCHEN_ORDER_SEQ.NEXTVAL, :oid, 'Pending', SYSDATE, :nt)";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':oid', $orderId);
    oci_bind_by_name($stmt, ':nt',  $notes);
    $ok = oci_execute($stmt);
    oci_free_statement($stmt);
    oci_close($conn);
    return (bool)$ok;
}

function updateKitchenStatus(int $id, string $status): bool {
    $conn = getDBConnection();
    if ($status === 'Ready') {
        $sql  = "UPDATE KITCHEN_ORDER SET KITCHEN_STATUS=:st, READY_AT=SYSDATE WHERE KITCHEN_ORDER_ID=:id";
    } else {
        $sql  = "UPDATE KITCHEN_ORDER SET KITCHEN_STATUS=:st WHERE KITCHEN_ORDER_ID=:id";
    }
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':st', $status);
    oci_bind_by_name($stmt, ':id', $id);
    $ok = oci_execute($stmt);
    oci_free_statement($stmt);
    oci_close($conn);
    return (bool)$ok;
}
