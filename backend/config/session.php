<?php

//   Session Configuration
//   Must be included at the top of every protected page.
 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


//   Redirects to login if user is not authenticated.
 
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        // Clear output buffer before redirect
        while (ob_get_level()) { ob_end_clean(); }
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }
}


//   Returns the currently logged-in username.
 
function currentUser() {
    return isset($_SESSION['username']) ? $_SESSION['username'] : '';
}


//   Returns the currently logged-in user role.
 
function currentRole() {
    return isset($_SESSION['role']) ? $_SESSION['role'] : '';
}
