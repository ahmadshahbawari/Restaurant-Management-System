<?php
 
require_once __DIR__ . '/../config/db.php';

 
function getAllSettings(): array {
    $defaults = [
        'restaurant_name'    => 'Zam Zam Restaurant',
        'restaurant_address' => '123 Main Street',
        'date_format'        => 'DD-Mon-YYYY',
        'currency_symbol'    => '$',
        'primary_color'      => '#c0392b',
        'sidebar_color'      => '#1a252f',
        'records_per_page'   => '10',
    ];
    try {
        $conn = getDBConnection();
        $stmt = oci_parse($conn, "SELECT SETTING_KEY, SETTING_VALUE FROM SYS_SETTINGS");
        oci_execute($stmt);
        $rows = [];
        while ($r = oci_fetch_assoc($stmt)) {
            $rows[$r['SETTING_KEY']] = $r['SETTING_VALUE'];
        }
        oci_free_statement($stmt);
        oci_close($conn);
        return array_merge($defaults, $rows);
    } catch (Throwable $e) {
        return $defaults;
    }
}

// Get a single setting value.

function getSetting(string $key, string $default = ''): string {
    static $cache = null;
    if ($cache === null) { $cache = getAllSettings(); }
    return $cache[$key] ?? $default;
}

// Update one or many settings.
// @param array $data  ['key' => 'value', ...]
 
function saveSettings(array $data, int $userId = 0): bool {
    $conn = getDBConnection();
    $ok   = true;
    foreach ($data as $key => $value) {
        $key   = trim($key);
        $value = trim($value);
        $sql   = "MERGE INTO SYS_SETTINGS s
                  USING DUAL ON (s.SETTING_KEY = :k)
                  WHEN MATCHED    THEN UPDATE SET SETTING_VALUE=:v, UPDATED_BY=:u, UPDATED_AT=SYSDATE
                  WHEN NOT MATCHED THEN INSERT (SETTING_KEY,SETTING_VALUE,UPDATED_BY,UPDATED_AT)
                                        VALUES (:k2,:v2,:u2,SYSDATE)";
        $stmt  = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':k',  $key);
        oci_bind_by_name($stmt, ':v',  $value);
        oci_bind_by_name($stmt, ':u',  $userId);
        oci_bind_by_name($stmt, ':k2', $key);
        oci_bind_by_name($stmt, ':v2', $value);
        oci_bind_by_name($stmt, ':u2', $userId);
        if (!oci_execute($stmt)) { $ok = false; }
        oci_free_statement($stmt);
    }
    oci_close($conn);
    return $ok;
}
