<?php
require_once __DIR__ . '/../config/db.php';

function getAllInventory(): array {
    $conn = getDBConnection();
    $sql  = "SELECT * FROM INVENTORY_ITEM ORDER BY INVENTORY_ID";
    $stmt = oci_parse($conn, $sql);
    oci_execute($stmt);
    $rows = [];
    while ($row = oci_fetch_assoc($stmt)) { $rows[] = $row; }
    oci_free_statement($stmt);
    oci_close($conn);
    return $rows;
}

function getInventoryById(int $id): ?array {
    $conn = getDBConnection();
    $sql  = "SELECT * FROM INVENTORY_ITEM WHERE INVENTORY_ID = :id";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':id', $id);
    oci_execute($stmt);
    $row  = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);
    oci_close($conn);
    return $row ?: null;
}

function getLowStockItems(): array {
    $conn = getDBConnection();
    $sql  = "SELECT * FROM INVENTORY_ITEM WHERE QUANTITY_IN_STOCK <= REORDER_LEVEL ORDER BY INVENTORY_ID";
    $stmt = oci_parse($conn, $sql);
    oci_execute($stmt);
    $rows = [];
    while ($row = oci_fetch_assoc($stmt)) { $rows[] = $row; }
    oci_free_statement($stmt);
    oci_close($conn);
    return $rows;
}

function createInventoryItem(array $d): bool {
    $conn = getDBConnection();
    $sql  = "INSERT INTO INVENTORY_ITEM (INVENTORY_ID, ITEM_NAME, UNIT, QUANTITY_IN_STOCK, REORDER_LEVEL, UNIT_COST, LAST_UPDATED)
             VALUES (INVENTORY_SEQ.NEXTVAL, :nm, :un, :qs, :rl, :uc, SYSDATE)";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':nm', $d['item_name']);
    oci_bind_by_name($stmt, ':un', $d['unit']);
    oci_bind_by_name($stmt, ':qs', $d['quantity_in_stock']);
    oci_bind_by_name($stmt, ':rl', $d['reorder_level']);
    oci_bind_by_name($stmt, ':uc', $d['unit_cost']);
    $ok = oci_execute($stmt);
    oci_free_statement($stmt);
    oci_close($conn);
    return (bool)$ok;
}

function updateInventoryItem(int $id, array $d): bool {
    $conn = getDBConnection();
    $sql  = "UPDATE INVENTORY_ITEM
             SET ITEM_NAME=:nm, UNIT=:un, QUANTITY_IN_STOCK=:qs, REORDER_LEVEL=:rl, UNIT_COST=:uc, LAST_UPDATED=SYSDATE
             WHERE INVENTORY_ID=:id";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':nm', $d['item_name']);
    oci_bind_by_name($stmt, ':un', $d['unit']);
    oci_bind_by_name($stmt, ':qs', $d['quantity_in_stock']);
    oci_bind_by_name($stmt, ':rl', $d['reorder_level']);
    oci_bind_by_name($stmt, ':uc', $d['unit_cost']);
    oci_bind_by_name($stmt, ':id', $id);
    $ok = oci_execute($stmt);
    oci_free_statement($stmt);
    oci_close($conn);
    return (bool)$ok;
}

function deleteInventoryItem(int $id): bool {
    $conn = getDBConnection();
    $sql  = "DELETE FROM INVENTORY_ITEM WHERE INVENTORY_ID = :id";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':id', $id);
    $ok = oci_execute($stmt);
    oci_free_statement($stmt);
    oci_close($conn);
    return (bool)$ok;
}
