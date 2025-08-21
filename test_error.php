<?php
// FILE: public_html/portal/test_error.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Checkpoint 1: Script started. Error reporting is ON.<br>";

// This next line is designed to cause a fatal error.
// We EXPECT to see an error message.
this_is_a_test_fatal_error();

echo "Checkpoint 2: You should NOT see this message.";

?>