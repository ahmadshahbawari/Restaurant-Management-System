<?php
require_once __DIR__ . '/../config/db.php';

function getAllCustomers(): array {
    $conn = getDBConnection();
    $sql  = "SELECT * FROM CUSTOMER ORDER BY CUSTOMER_ID";
    $stmt = oci_parse($conn, $sql);
    oci_execute($stmt);
    $rows = [];
    while ($row = oci_fetch_assoc($stmt)) { $rows[] = $row; }
    oci_free_statement($stmt);
    oci_close($conn);
    return $rows;
}

function getCustomerById(int $id): ?array {
    $conn = getDBConnection();
    $sql  = "SELECT * FROM CUSTOMER WHERE CUSTOMER_ID = :id";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':id', $id);
    oci_execute($stmt);
    $row  = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);
    oci_close($conn);
    return $row ?: null;
}

function createCustomer(array $d): bool {
    $conn = getDBConnection();
    $sql  = "INSERT INTO CUSTOMER (CUSTOMER_ID, FIRST_NAME, LAST_NAME, PHONE, EMAIL, ADDRESS, REGISTRATION_DATE)
             VALUES (CUSTOMER_SEQ.NEXTVAL, :fn, :ln, :ph, :em, :ad, SYSDATE)";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':fn', $d['first_name']);
    oci_bind_by_name($stmt, ':ln', $d['last_name']);
    oci_bind_by_name($stmt, ':ph', $d['phone']);
    oci_bind_by_name($stmt, ':em', $d['email']);
    oci_bind_by_name($stmt, ':ad', $d['address']);
    $ok = oci_execute($stmt);
    oci_free_statement($stmt);
    oci_close($conn);
    return (bool)$ok;
}

function updateCustomer(int $id, array $d): bool {
    $conn = getDBConnection();
    $sql  = "UPDATE CUSTOMER SET FIRST_NAME=:fn, LAST_NAME=:ln, PHONE=:ph, EMAIL=:em, ADDRESS=:ad
             WHERE CUSTOMER_ID=:id";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':fn', $d['first_name']);
    oci_bind_by_name($stmt, ':ln', $d['last_name']);
    oci_bind_by_name($stmt, ':ph', $d['phone']);
    oci_bind_by_name($stmt, ':em', $d['email']);
    oci_bind_by_name($stmt, ':ad', $d['address']);
    oci_bind_by_name($stmt, ':id', $id);
    $ok = oci_execute($stmt);
    oci_free_statement($stmt);
    oci_close($conn);
    return (bool)$ok;
}

function deleteCustomer(int $id): bool {
    $conn = getDBConnection();
    $sql  = "DELETE FROM CUSTOMER WHERE CUSTOMER_ID = :id";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':id', $id);
    $ok = oci_execute($stmt);
    oci_free_statement($stmt);
    oci_close($conn);
    return (bool)$ok;
}

function searchCustomers(string $q): array {
    $conn = getDBConnection();
    $like = '%' . strtoupper($q) . '%';
    $sql  = "SELECT * FROM CUSTOMER
             WHERE UPPER(FIRST_NAME) LIKE :q
                OR UPPER(LAST_NAME)  LIKE :q
                OR UPPER(EMAIL)      LIKE :q
                OR PHONE             LIKE :q
             ORDER BY CUSTOMER_ID";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':q', $like);
    oci_execute($stmt);
    $rows = [];
    while ($row = oci_fetch_assoc($stmt)) { $rows[] = $row; }
    oci_free_statement($stmt);
    oci_close($conn);
    return $rows;
}
