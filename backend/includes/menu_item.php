<?php
require_once __DIR__ . '/../config/db.php';

function getAllMenuItems(): array {
    $conn = getDBConnection();
    $sql  = "SELECT m.*, c.CATEGORY_NAME
             FROM MENU_ITEM m
             JOIN CATEGORY c ON c.CATEGORY_ID = m.CATEGORY_ID
             ORDER BY m.ITEM_ID";
    $stmt = oci_parse($conn, $sql);
    oci_execute($stmt);
    $rows = [];
    while ($row = oci_fetch_assoc($stmt)) { $rows[] = $row; }
    oci_free_statement($stmt);
    oci_close($conn);
    return $rows;
}

function getMenuItemById(int $id): ?array {
    $conn = getDBConnection();
    $sql  = "SELECT m.*, c.CATEGORY_NAME
             FROM MENU_ITEM m
             JOIN CATEGORY c ON c.CATEGORY_ID = m.CATEGORY_ID
             WHERE m.ITEM_ID = :id";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':id', $id);
    oci_execute($stmt);
    $row  = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);
    oci_close($conn);
    return $row ?: null;
}

function getMenuItemsByCategory(int $catId): array {
    $conn = getDBConnection();
    $sql  = "SELECT * FROM MENU_ITEM WHERE CATEGORY_ID = :cid AND AVAILABILITY = 'Yes' ORDER BY ITEM_ID";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':cid', $catId);
    oci_execute($stmt);
    $rows = [];
    while ($row = oci_fetch_assoc($stmt)) { $rows[] = $row; }
    oci_free_statement($stmt);
    oci_close($conn);
    return $rows;
}

function createMenuItem(array $d): bool {
    $conn = getDBConnection();
    $sql  = "INSERT INTO MENU_ITEM (ITEM_ID, ITEM_NAME, DESCRIPTION, CATEGORY_ID, PRICE, AVAILABILITY)
             VALUES (MENU_ITEM_SEQ.NEXTVAL, :nm, :dc, :ci, :pr, :av)";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':nm', $d['item_name']);
    oci_bind_by_name($stmt, ':dc', $d['description']);
    oci_bind_by_name($stmt, ':ci', $d['category_id']);
    oci_bind_by_name($stmt, ':pr', $d['price']);
    oci_bind_by_name($stmt, ':av', $d['availability']);
    $ok = oci_execute($stmt);
    oci_free_statement($stmt);
    oci_close($conn);
    return (bool)$ok;
}

function updateMenuItem(int $id, array $d): bool {
    $conn = getDBConnection();
    $sql  = "UPDATE MENU_ITEM SET ITEM_NAME=:nm, DESCRIPTION=:dc, CATEGORY_ID=:ci, PRICE=:pr, AVAILABILITY=:av
             WHERE ITEM_ID=:id";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':nm', $d['item_name']);
    oci_bind_by_name($stmt, ':dc', $d['description']);
    oci_bind_by_name($stmt, ':ci', $d['category_id']);
    oci_bind_by_name($stmt, ':pr', $d['price']);
    oci_bind_by_name($stmt, ':av', $d['availability']);
    oci_bind_by_name($stmt, ':id', $id);
    $ok = oci_execute($stmt);
    oci_free_statement($stmt);
    oci_close($conn);
    return (bool)$ok;
}

function deleteMenuItem(int $id): bool {
    $conn = getDBConnection();
    $sql  = "DELETE FROM MENU_ITEM WHERE ITEM_ID = :id";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':id', $id);
    $ok = oci_execute($stmt);
    oci_free_statement($stmt);
    oci_close($conn);
    return (bool)$ok;
}
