<?php


// Suppress PHP errors from showing on screen (log them instead)
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

define('DB_HOST',     'localhost');
define('DB_PORT',     '1521');
define('DB_SERVICE',  'ORCLPDB');
define('DB_USER',     'Ahmad');
define('DB_PASSWORD', '1234');


//  Returns an OCI8 database connection resource.
//   Exits with a clean error page if the connection fails.
 
function getDBConnection() {
    $dsn  = DB_HOST . ':' . DB_PORT . '/' . DB_SERVICE;
    $conn = oci_connect(DB_USER, DB_PASSWORD, $dsn, 'AL32UTF8');
    if (!$conn) {
        $e = oci_error();
        error_log('Oracle Connection Error: ' . ($e['message'] ?? 'unknown'));
        
        die(' <h3>&#9888; Database Connection Failed</h3>
             <p>Could not connect to Oracle. Check your credentials in <code>backend/config/db.php</code>.</p>
             <p >Error logged to server error log.</p> ');
    }
    return $conn;
}
