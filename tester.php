<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Attempting to include the test file...<br>";

require_once __DIR__ . '/../../app/test_include.php';

echo "<br>This message will only appear if the include worked.";