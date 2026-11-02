<?php
require_once __DIR__ . '/../config/db.php';




function getAllAppUsers(): array {
    $conn = getDBConnection();
    $sql  = 'SELECT u.USER_ID, u.USERNAME, u.STATUS, u.EMPLOYEE_ID, u.CREATED_DATE,
             e.FIRST_NAME || \' \' || e.LAST_NAME AS FULL_NAME, e.ROLE
             FROM APP_USER u
             JOIN EMPLOYEE e ON e.EMPLOYEE_ID = u.EMPLOYEE_ID
             ORDER BY u.USER_ID';
    $stmt = oci_parse($conn, $sql);
    oci_execute($stmt);
    $rows = [];
    while ($row = oci_fetch_assoc($stmt)) {
        $rows[] = $row; // PASSWORD not selected, so nothing to unset
    }
    oci_free_statement($stmt);
    oci_close($conn);
    return $rows;
}

function getAppUserById(int $id): ?array {
    $conn = getDBConnection();
    $sql  = 'SELECT u.USER_ID, u.USERNAME, u.EMPLOYEE_ID, u.STATUS, u.CREATED_DATE,
                    e.FIRST_NAME, e.LAST_NAME, e.ROLE
             FROM APP_USER u
             JOIN EMPLOYEE e ON e.EMPLOYEE_ID = u.EMPLOYEE_ID
             WHERE u.USER_ID = :id';
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':id', $id);
    oci_execute($stmt);
    $row  = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);
    oci_close($conn);
    return $row ?: null;
}

function createAppUser(array $d): bool {
    $conn   = getDBConnection();
    $hashed = password_hash($d['password'], PASSWORD_BCRYPT);
    $sql    = 'INSERT INTO APP_USER (USER_ID, USERNAME, "PASSWORD", EMPLOYEE_ID, STATUS, CREATED_DATE)
               VALUES (APP_USER_SEQ.NEXTVAL, :un, :pw, :ei, :st, SYSDATE)';
    $stmt   = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':un', $d['username']);
    oci_bind_by_name($stmt, ':pw', $hashed);
    oci_bind_by_name($stmt, ':ei', $d['employee_id']);
    oci_bind_by_name($stmt, ':st', $d['status']);
    $ok = oci_execute($stmt);
    oci_free_statement($stmt);
    oci_close($conn);
    return (bool)$ok;
}

function updateAppUser(int $id, array $d): bool {
    $conn = getDBConnection();
    if (!empty($d['password'])) {
        $hashed = password_hash($d['password'], PASSWORD_BCRYPT);
        $sql    = 'UPDATE APP_USER SET USERNAME=:un, "PASSWORD"=:pw, EMPLOYEE_ID=:ei, STATUS=:st WHERE USER_ID=:id';
        $stmt   = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':pw', $hashed);
    } else {
        $sql  = 'UPDATE APP_USER SET USERNAME=:un, EMPLOYEE_ID=:ei, STATUS=:st WHERE USER_ID=:id';
        $stmt = oci_parse($conn, $sql);
    }
    oci_bind_by_name($stmt, ':un', $d['username']);
    oci_bind_by_name($stmt, ':ei', $d['employee_id']);
    oci_bind_by_name($stmt, ':st', $d['status']);
    oci_bind_by_name($stmt, ':id', $id);
    $ok = oci_execute($stmt);
    oci_free_statement($stmt);
    oci_close($conn);
    return (bool)$ok;
}

function deleteAppUser(int $id): bool {
    $conn = getDBConnection();
    $sql  = 'DELETE FROM APP_USER WHERE USER_ID = :id';
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':id', $id);
    $ok = oci_execute($stmt);
    oci_free_statement($stmt);
    oci_close($conn);
    return (bool)$ok;
}
