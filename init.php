<?php
// FILE: public_html/portal/init.php

/**
 * Bedrock Cadence Application Initializer
 *
 * This file is the single entry point for setting up the application environment.
 * It handles configuration, autoloading, error handling, and session management.
 */

// Start output buffering to prevent accidental output before headers are sent.
ob_start();

// --- 1. Define Core Paths ---
// This makes file includes reliable, regardless of where the script is called from.
define('APP_PATH', realpath(__DIR__ . '/../../app'));

// --- 2. Load Core Configuration ---
// The secrets file is the only file we need to manually include.
require_once APP_PATH . '/secrets.php';

// --- 3. Set Up PSR-4 Autoloader ---
// This function automatically loads any class from the `app` directory when it's first used.
// No more manual `require_once` statements are needed for our classes.
spl_autoload_register(function ($class_name) {
    // Sanitize the class name to prevent directory traversal attacks
    $class_name = str_replace('..', '', $class_name);
    $file = APP_PATH . '/' . str_replace('\\', '/', lcfirst($class_name)) . '.php';
    
    if (file_exists($file)) {
        require_once $file;
    }
});

// --- 4. Configure Environment Settings ---
// Use the APP_ENV from secrets.php to control error visibility.
if (defined('APP_ENV') && APP_ENV === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
}
error_reporting(E_ALL);

// --- 5. Register Global Error & Exception Handler ---
// This must be registered early to catch any errors during the rest of the startup process.
ErrorHandler::register();

// --- 6. Set Default Timezone ---
// Ensures all date/time functions in the application use a consistent timezone.
if (defined('USER_TIMEZONE')) {
    date_default_timezone_set(USER_TIMEZONE);
}

// --- 7. Start Secure Session Management ---
// Initializes our secure, class-based session manager for every page load.
SessionManager::start();