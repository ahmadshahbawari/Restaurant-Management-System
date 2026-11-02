<?php
require_once __DIR__ . '/../config/db.php';

function getAllOrders(): array {
    $conn = getDBConnection();
    $sql  = "SELECT o.*,
                    c.FIRST_NAME || ' ' || c.LAST_NAME AS CUSTOMER_NAME,
                    e.FIRST_NAME || ' ' || e.LAST_NAME AS EMPLOYEE_NAME
             FROM ORDERS o
             JOIN CUSTOMER c ON c.CUSTOMER_ID = o.CUSTOMER_ID
             JOIN EMPLOYEE e ON e.EMPLOYEE_ID = o.EMPLOYEE_ID
             ORDER BY o.ORDER_DATE DESC";
    $stmt = oci_parse($conn, $sql);
    oci_execute($stmt);
    $rows = [];
    while ($row = oci_fetch_assoc($stmt)) { $rows[] = $row; }
    oci_free_statement($stmt);
    oci_close($conn);
    return $rows;
}

function getOrderById(int $id): ?array {
    $conn = getDBConnection();
    $sql  = "SELECT o.*,
                    c.FIRST_NAME || ' ' || c.LAST_NAME AS CUSTOMER_NAME,
                    e.FIRST_NAME || ' ' || e.LAST_NAME AS EMPLOYEE_NAME
             FROM ORDERS o
             JOIN CUSTOMER c ON c.CUSTOMER_ID = o.CUSTOMER_ID
             JOIN EMPLOYEE e ON e.EMPLOYEE_ID = o.EMPLOYEE_ID
             WHERE o.ORDER_ID = :id";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':id', $id);
    oci_execute($stmt);
    $row  = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);
    oci_close($conn);
    return $row ?: null;
}

function getOrderItems(int $orderId): array {
    $conn = getDBConnection();
    $sql  = "SELECT oi.*, m.ITEM_NAME
             FROM ORDER_ITEM oi
             JOIN MENU_ITEM m ON m.ITEM_ID = oi.ITEM_ID
             WHERE oi.ORDER_ID = :oid";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':oid', $orderId);
    oci_execute($stmt);
    $rows = [];
    while ($row = oci_fetch_assoc($stmt)) { $rows[] = $row; }
    oci_free_statement($stmt);
    oci_close($conn);
    return $rows;
}

function createOrder(array $d): int {
    $conn = getDBConnection();
    $sql  = "INSERT INTO ORDERS (ORDER_ID, CUSTOMER_ID, EMPLOYEE_ID, ORDER_TYPE, ORDER_DATE, STATUS, TOTAL_AMOUNT)
             VALUES (ORDER_SEQ.NEXTVAL, :ci, :ei, :ot, SYSDATE, 'Pending', 0)
             RETURNING ORDER_ID INTO :new_id";
    $stmt   = oci_parse($conn, $sql);
    $new_id = -1;
    oci_bind_by_name($stmt, ':ci', $d['customer_id']);
    oci_bind_by_name($stmt, ':ei', $d['employee_id']);
    oci_bind_by_name($stmt, ':ot', $d['order_type']);
    oci_bind_by_name($stmt, ':new_id', $new_id, 10, SQLT_INT);
    oci_execute($stmt);
    oci_free_statement($stmt);
    oci_close($conn);
    return (int)$new_id;
}

function addOrderItem(int $orderId, array $item): bool {
    $conn     = getDBConnection();
    $subtotal = $item['quantity'] * $item['unit_price'];
    $sql      = "INSERT INTO ORDER_ITEM (ORDER_ITEM_ID, ORDER_ID, ITEM_ID, QUANTITY, UNIT_PRICE, SUBTOTAL, SPECIAL_INSTRUCTIONS)
                 VALUES (ORDER_ITEM_SEQ.NEXTVAL, :oid, :iid, :qty, :up, :sub, :si)";
    $stmt     = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':oid', $orderId);
    oci_bind_by_name($stmt, ':iid', $item['item_id']);
    oci_bind_by_name($stmt, ':qty', $item['quantity']);
    oci_bind_by_name($stmt, ':up',  $item['unit_price']);
    oci_bind_by_name($stmt, ':sub', $subtotal);
    oci_bind_by_name($stmt, ':si',  $item['special_instructions']);
    $ok = oci_execute($stmt);
    oci_free_statement($stmt);
    // update total
    $sql2  = "UPDATE ORDERS SET TOTAL_AMOUNT = (SELECT NVL(SUM(SUBTOTAL),0) FROM ORDER_ITEM WHERE ORDER_ID=:oid) WHERE ORDER_ID=:oid2";
    $stmt2 = oci_parse($conn, $sql2);
    oci_bind_by_name($stmt2, ':oid',  $orderId);
    oci_bind_by_name($stmt2, ':oid2', $orderId);
    oci_execute($stmt2);
    oci_free_statement($stmt2);
    oci_close($conn);
    return (bool)$ok;
}

function updateOrderStatus(int $id, string $status): bool {
    $conn = getDBConnection();
    $sql  = "UPDATE ORDERS SET STATUS=:st WHERE ORDER_ID=:id";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':st', $status);
    oci_bind_by_name($stmt, ':id', $id);
    $ok = oci_execute($stmt);
    oci_free_statement($stmt);
    oci_close($conn);
    return (bool)$ok;
}

function deleteOrder(int $id): bool {
    $conn = getDBConnection();
    // Delete child records first
    $s1 = oci_parse($conn, "DELETE FROM ORDER_ITEM WHERE ORDER_ID=:id");
    oci_bind_by_name($s1, ':id', $id);
    oci_execute($s1);
    oci_free_statement($s1);

    $s2 = oci_parse($conn, "DELETE FROM KITCHEN_ORDER WHERE ORDER_ID=:id");
    oci_bind_by_name($s2, ':id', $id);
    oci_execute($s2);
    oci_free_statement($s2);

    $s3 = oci_parse($conn, "DELETE FROM PAYMENT WHERE ORDER_ID=:id");
    oci_bind_by_name($s3, ':id', $id);
    oci_execute($s3);
    oci_free_statement($s3);

    $s4 = oci_parse($conn, "DELETE FROM ORDERS WHERE ORDER_ID=:id");
    oci_bind_by_name($s4, ':id', $id);
    $ok = oci_execute($s4);
    oci_free_statement($s4);
    oci_close($conn);
    return (bool)$ok;
}
