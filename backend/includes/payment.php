<?php
require_once __DIR__ . '/../config/db.php';

function getAllPayments(): array {
    $conn = getDBConnection();
    $sql  = "SELECT p.*, o.TOTAL_AMOUNT, o.ORDER_TYPE,
                    c.FIRST_NAME || ' ' || c.LAST_NAME AS CUSTOMER_NAME
             FROM PAYMENT p
             JOIN ORDERS o ON o.ORDER_ID = p.ORDER_ID
             JOIN CUSTOMER c ON c.CUSTOMER_ID = o.CUSTOMER_ID
             ORDER BY p.PAYMENT_DATE DESC";
    $stmt = oci_parse($conn, $sql);
    oci_execute($stmt);
    $rows = [];
    while ($row = oci_fetch_assoc($stmt)) { $rows[] = $row; }
    oci_free_statement($stmt);
    oci_close($conn);
    return $rows;
}

function getPaymentByOrder(int $orderId): ?array {
    $conn = getDBConnection();
    $sql  = "SELECT * FROM PAYMENT WHERE ORDER_ID = :oid";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':oid', $orderId);
    oci_execute($stmt);
    $row  = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);
    oci_close($conn);
    return $row ?: null;
}

function createPayment(array $d): bool {
    $conn = getDBConnection();
    $sql  = "INSERT INTO PAYMENT (PAYMENT_ID, ORDER_ID, AMOUNT, PAYMENT_STATUS, PAYMENT_DATE)
             VALUES (PAYMENT_SEQ.NEXTVAL, :oid, :am, :ps, SYSDATE)";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':oid', $d['order_id']);
    oci_bind_by_name($stmt, ':am',  $d['amount']);
    oci_bind_by_name($stmt, ':ps',  $d['payment_status']);
    $ok = oci_execute($stmt);
    if ($ok) {
        // mark order as Completed
        $s2 = oci_parse($conn, "UPDATE ORDERS SET STATUS='Completed' WHERE ORDER_ID=:oid");
        oci_bind_by_name($s2, ':oid', $d['order_id']);
        oci_execute($s2);
        oci_free_statement($s2);
    }
    oci_free_statement($stmt);
    oci_close($conn);
    return (bool)$ok;
}

function updatePaymentStatus(int $id, string $status): bool {
    $conn = getDBConnection();
    $sql  = "UPDATE PAYMENT SET PAYMENT_STATUS=:st WHERE PAYMENT_ID=:id";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':st', $status);
    oci_bind_by_name($stmt, ':id', $id);
    $ok = oci_execute($stmt);
    oci_free_statement($stmt);
    oci_close($conn);
    return (bool)$ok;
}
