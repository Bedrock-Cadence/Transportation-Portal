<?php
// FILE: public/init.php

// 1. Load Secrets
// The path goes up two directory levels to find the 'app' directory.
require_once __DIR__ . '/../../app/secrets.php';

// 2. Set Up Error Handling
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
require_once __DIR__ . '/../../app/error_handler.php';
set_error_handler('log_error_to_database');
register_shutdown_function('handle_fatal_errors');

// 3. Configure and Start a Secure Session
require_once __DIR__ . '/../../app/session_config.php';

// 4. Establish the Database Connection
require_once __DIR__ . '/../../app/db_connect.php'; // Creates the $mysqli object

// 5. Other global services
require_once __DIR__ . '/../../app/encryption_service.php';
require_once __DIR__ . '/../../app/logging_service.php';

// 6. Define Global Constants
define('SYSTEM_TIMEZONE', 'UTC');
// You should also define USER_TIMEZONE here or in another config file.
define('USER_TIMEZONE', 'America/Chicago');