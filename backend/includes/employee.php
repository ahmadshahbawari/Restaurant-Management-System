<?php
require_once __DIR__ . '/../config/db.php';

function getAllEmployees(): array {
    $conn = getDBConnection();
    $sql  = "SELECT * FROM EMPLOYEE ORDER BY EMPLOYEE_ID";
    $stmt = oci_parse($conn, $sql);
    oci_execute($stmt);
    $rows = [];
    while ($row = oci_fetch_assoc($stmt)) { $rows[] = $row; }
    oci_free_statement($stmt);
    oci_close($conn);
    return $rows;
}

function getEmployeeById(int $id): ?array {
    $conn = getDBConnection();
    $sql  = "SELECT * FROM EMPLOYEE WHERE EMPLOYEE_ID = :id";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':id', $id);
    oci_execute($stmt);
    $row  = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);
    oci_close($conn);
    return $row ?: null;
}

function createEmployee(array $d): bool {
    $conn = getDBConnection();
    $sql  = "INSERT INTO EMPLOYEE (EMPLOYEE_ID, FIRST_NAME, LAST_NAME, PHONE, ROLE, SALARY, HIRE_DATE, STATUS)
             VALUES (EMPLOYEE_SEQ.NEXTVAL, :fn, :ln, :ph, :ro, :sa, TO_DATE(:hd,'YYYY-MM-DD'), :st)";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':fn', $d['first_name']);
    oci_bind_by_name($stmt, ':ln', $d['last_name']);
    oci_bind_by_name($stmt, ':ph', $d['phone']);
    oci_bind_by_name($stmt, ':ro', $d['role']);
    oci_bind_by_name($stmt, ':sa', $d['salary']);
    oci_bind_by_name($stmt, ':hd', $d['hire_date']);
    oci_bind_by_name($stmt, ':st', $d['status']);
    $ok = oci_execute($stmt);
    oci_free_statement($stmt);
    oci_close($conn);
    return (bool)$ok;
}

function updateEmployee(int $id, array $d): bool {
    $conn = getDBConnection();
    $sql  = "UPDATE EMPLOYEE SET FIRST_NAME=:fn, LAST_NAME=:ln, PHONE=:ph, ROLE=:ro,
                                  SALARY=:sa, HIRE_DATE=TO_DATE(:hd,'YYYY-MM-DD'), STATUS=:st
             WHERE EMPLOYEE_ID=:id";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':fn', $d['first_name']);
    oci_bind_by_name($stmt, ':ln', $d['last_name']);
    oci_bind_by_name($stmt, ':ph', $d['phone']);
    oci_bind_by_name($stmt, ':ro', $d['role']);
    oci_bind_by_name($stmt, ':sa', $d['salary']);
    oci_bind_by_name($stmt, ':hd', $d['hire_date']);
    oci_bind_by_name($stmt, ':st', $d['status']);
    oci_bind_by_name($stmt, ':id', $id);
    $ok = oci_execute($stmt);
    oci_free_statement($stmt);
    oci_close($conn);
    return (bool)$ok;
}

function deleteEmployee(int $id): bool {
    $conn = getDBConnection();
    $sql  = "DELETE FROM EMPLOYEE WHERE EMPLOYEE_ID = :id";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':id', $id);
    $ok = oci_execute($stmt);
    oci_free_statement($stmt);
    oci_close($conn);
    return (bool)$ok;
}
