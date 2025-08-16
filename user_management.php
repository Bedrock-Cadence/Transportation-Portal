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

require_once __DIR__ . '/../../app/db_connect.php';

?>

<div id="dashboard-container">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">User Management</h1>
            <p id="last-updated" class="text-sm text-gray-500"></p>

    </div>

<?php
// This includes the footer and necessary closing tags.
require_once 'footer.php';
?>