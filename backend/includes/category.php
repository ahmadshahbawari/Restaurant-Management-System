<?php
require_once __DIR__ . '/../config/db.php';

function getAllCategories(): array {
    $conn = getDBConnection();
    $sql  = "SELECT * FROM CATEGORY ORDER BY CATEGORY_ID";
    $stmt = oci_parse($conn, $sql);
    oci_execute($stmt);
    $rows = [];
    while ($row = oci_fetch_assoc($stmt)) { $rows[] = $row; }
    oci_free_statement($stmt);
    oci_close($conn);
    return $rows;
}

function getCategoryById(int $id): ?array {
    $conn = getDBConnection();
    $sql  = "SELECT * FROM CATEGORY WHERE CATEGORY_ID = :id";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':id', $id);
    oci_execute($stmt);
    $row  = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);
    oci_close($conn);
    return $row ?: null;
}

function createCategory(array $d): bool {
    $conn = getDBConnection();
    $sql  = "INSERT INTO CATEGORY (CATEGORY_ID, CATEGORY_NAME, DESCRIPTION)
             VALUES (CATEGORY_SEQ.NEXTVAL, :cn, :dc)";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':cn', $d['category_name']);
    oci_bind_by_name($stmt, ':dc', $d['description']);
    $ok = oci_execute($stmt);
    oci_free_statement($stmt);
    oci_close($conn);
    return (bool)$ok;
}

function updateCategory(int $id, array $d): bool {
    $conn = getDBConnection();
    $sql  = "UPDATE CATEGORY SET CATEGORY_NAME=:cn, DESCRIPTION=:dc WHERE CATEGORY_ID=:id";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':cn', $d['category_name']);
    oci_bind_by_name($stmt, ':dc', $d['description']);
    oci_bind_by_name($stmt, ':id', $id);
    $ok = oci_execute($stmt);
    oci_free_statement($stmt);
    oci_close($conn);
    return (bool)$ok;
}

function deleteCategory(int $id): bool {
    $conn = getDBConnection();
    $sql  = "DELETE FROM CATEGORY WHERE CATEGORY_ID = :id";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':id', $id);
    $ok = oci_execute($stmt);
    oci_free_statement($stmt);
    oci_close($conn);
    return (bool)$ok;
}
