<?php
// FILE: user_profile.php

// 1. Set the page title for the header.
$page_title = 'User Profile';

// 2. Include the header, which also handles session startup.
require_once 'header.php';

// 3. Security Check: If the user isn't logged in, send them to the login page.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// 4. Include the database connection file. The $mysqli object is now available for use.
require_once __DIR__ . '/../../app/db_connect.php';

// Initialize variables for messages and errors.
$page_message = '';
$page_error = '';

// Determine which user's profile to view.
$view_user_uuid = $_GET['uuid'] ?? $_SESSION['user_uuid'];

// Security Check: A non-admin can only view their own profile.
$allowed_roles = ['facility_superuser', 'carrier_superuser', 'bedrock_admin'];
$is_admin = isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], $allowed_roles);
?>

<?php require_once('footer.php'); ?>