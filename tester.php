<?php
// FILE: public_html/portal/login.php (TESTING VERSION)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Attempting to include init.php...<br>";

require_once __DIR__ . '/../../app/init.php';

echo "<br>init.php was included successfully!";