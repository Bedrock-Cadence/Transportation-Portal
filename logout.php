<?php
// FILE: public/logout.php

require_once 'init.php'; // Ensures session is properly started before destroying

// Unset all session variables.
$_SESSION = [];

// Destroy the session cookie.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session.
session_destroy();

redirect('login.php');