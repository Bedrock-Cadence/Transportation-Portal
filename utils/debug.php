<?php
// FILE: public_html/portal/debug.php

// Force all errors to the screen, no matter what.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<pre>";
echo "Starting manual include test...\n\n";

// This is the complete list of files in your app directory.
$app_files = [
    'secrets.php',
    'utils.php',
    'database.php',
    'sessionManager.php',
    'loggingService.php',
    'errorHandler.php',
    'auth.php',
    'encryptionService.php',
    'googleMapsService.php',
    'notificationService.php',
    'authService.php',
    'userService.php',
    'tripService.php',
    'carrierService.php',
    'addressService.php',
    'dashboardService.php',
    'configService.php'
];

foreach ($app_files as $file) {
    echo "Attempting to include: <strong>{$file}</strong> ... ";
    
    // Use a try-catch to handle potential errors gracefully.
    try {
        require_once __DIR__ . '/../../app/' . $file;
        echo "<span style='color: green;'>OK</span>\n";
    } catch (Throwable $t) {
        echo "<span style='color: red; font-weight: bold;'>FATAL ERROR!</span>\n";
        echo "Error in file <strong>{$file}</strong>:\n";
        echo $t->getMessage();
        exit();
    }
}

echo "\nTest complete. All files included successfully.";
echo "</pre>";