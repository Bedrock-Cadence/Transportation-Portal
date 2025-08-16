<?php
// FILE: index.php

// 1. Set the page title for the header.
$page_title = 'User Management';

// 2. Include the header, which also handles session startup.
require_once 'header.php';

// 3. Security Check: If the user isn't logged in, send them to the login page.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// Security Check: Only allow authorized roles to access this page.
$allowed_roles = ['facility_superuser', 'carrier_superuser', 'bedrock_admin'];
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], $allowed_roles)) {
    // Redirect to a dashboard or show an unauthorized message.
    header("location: dashboard.php");
    exit;
}

// 4. Include the database connection file. The $mysqli object is now available for use.
require_once __DIR__ . '/../../app/db_connect.php';
?>

<?php require_once('footer.php'); ?>