<?php


require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/session.php';

function loginUser(string $username, string $password) {

    if ($username === '' || $password === '') {
        return false;
    }

    $conn = getDBConnection();

    $sql = 'SELECT u.USER_ID, u.USERNAME, u."PASSWORD" 
            AS USER_PASSWORD, u.STATUS,
            u.EMPLOYEE_ID, e.ROLE,
            e.FIRST_NAME || \' \' || e.LAST_NAME AS FULL_NAME
            FROM   APP_USER u
            LEFT JOIN EMPLOYEE e ON e.EMPLOYEE_ID = u.EMPLOYEE_ID
            WHERE  UPPER(u.USERNAME) = UPPER(:uname)';

    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':uname', $username);
    oci_execute($stmt);
    $row = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);
    oci_close($conn);

    if (!$row) { return false; }

    if (trim((string)($row['STATUS'] ?? '')) !== 'Active') { return false; }

    $stored = trim((string)($row['USER_PASSWORD'] ?? ''));
    if ($stored === '') { return false; }

    $valid = false;
    if (!$valid && strlen($stored) >= 60 && $stored[0] === '$') {
        $valid = password_verify($password, $stored);
    }
    if (!$valid && $stored === $password)                     { $valid = true; }
    if (!$valid && strtolower($stored) === strtolower($password)) { $valid = true; }
    if (!$valid && strlen($stored) === 32 && $stored === md5($password))  { $valid = true; }
    if (!$valid && strlen($stored) === 40 && $stored === sha1($password)) { $valid = true; }

    if (!$valid) { return false; }

    $userId = $row['USER_ID'];

    /* Load per-user preferences (theme + language) from DB — req #5 */
    $theme = 'light';
    $lang  = 'en';
    try {
        $conn2 = getDBConnection();
        $pref  = oci_parse($conn2,
            "SELECT THEME, LANGUAGE FROM USER_PREFERENCES WHERE USER_ID = :uid");
        oci_bind_by_name($pref, ':uid', $userId);
        oci_execute($pref);
        $pr = oci_fetch_assoc($pref);
        oci_free_statement($pref);
        oci_close($conn2);
        if ($pr) {
            $theme = $pr['THEME']    ?? 'light';
            $lang  = $pr['LANGUAGE'] ?? 'en';
        }
    } catch (Throwable $e) {
        /* USER_PREFERENCES table may not exist yet — use defaults */
    }

    return [
        'user_id'     => $userId,
        'username'    => $row['USERNAME'],
        'employee_id' => $row['EMPLOYEE_ID'] ?? 0,
        'role'        => trim((string)($row['ROLE']      ?? 'Staff')),
        'full_name'   => trim((string)($row['FULL_NAME'] ?? $row['USERNAME'])),
        'theme'       => $theme,
        'lang'        => $lang,
    ];
}

function logoutUser(): void {
    session_unset();
    session_destroy();
    while (ob_get_level()) { ob_end_clean(); }
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}
