<?php
// FILE: public/init.php

// 1. Load Secrets
// This MUST be first so all other files have access to credentials and API keys.
require_once __DIR__ . '/../../app/secrets.php';

// 2. Set Up Error Handling
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
require_once __DIR__ . '/../app/error_handler.php'; // Your custom DB error logger
set_error_handler('log_error_to_database');
register_shutdown_function('handle_fatal_errors');

// 3. Configure and Start a Secure Session
require_once __DIR__ . '/../app/session_config.php';

// 4. Establish the Database Connection
require_once __DIR__ . '/../app/db_connect.php'; // This creates the $pdo object

// Other global services
require_once __DIR__ . '/../app/encryption_service.php';
require_once __DIR__ . '/../app/logging_service.php';

define('SYSTEM_TIMEZONE', 'UTC');