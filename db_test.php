<?php
// FILE: public_html/admin/billing_export.php (Temporary Test Contents)

// --- SECURE RAW DATABASE CONNECTION TEST ---
// This test uses your existing secrets file by including init.php first.
// It bypasses your Database class to test the fundamental PDO connection.

echo "<h1>Running Secure Database Connection Test...</h1>";

// Load the application config/secrets first
require_once __DIR__ . '/../../app/init.php';
echo "Loaded init.php...<br>";

try {
    // --- IMPORTANT: CONFIGURE THIS SECTION ---
    // You MUST replace these placeholder variables with the actual variables
    // that hold your credentials after your secrets file is loaded.
    // Here are common examples. Uncomment and/or edit the lines that match your system.

    $db_host = DB_HOST;             // Example for constants: define('DB_HOST', ...);
    $db_name = DB_NAME;
    $db_user = DB_USER;
    $db_pass = DB_PASS;

    /*
    // Example for .env files and the $_ENV superglobal:
    $db_host = $_ENV['DB_HOST'] ?? null;
    $db_name = $_ENV['DB_NAME'] ?? null;
    $db_user = $_ENV['DB_USER'] ?? null;
    $db_pass = $_ENV['DB_PASS'] ?? null;
    */

    // --- End of configuration section ---

    echo "Credentials loaded into variables...<br>";

    // This check tells us if the secrets were loaded at all.
    if (empty($db_host) || empty($db_name)) {
        die("<h2>FAILURE: Database credentials were not found.</h2><p>Check that your secrets file is correctly included and parsed by init.php, and that the variable names above are correct.</p>");
    }

    $dsn = "mysql:host=" . $db_host . ";dbname=" . $db_name . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    
    echo "Attempting to connect with PDO...<br>";
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);

    // If you see this, the connection is good.
    die("<h2>SUCCESS: Raw database connection was successful using your loaded secrets!</h2>");

} catch (PDOException $e) {
    // If you see this, the credentials are loaded but the connection failed.
    die("<h2>FAILURE: Raw database connection failed.</h2><p><b>Error:</b> " . $e->getMessage() . "</p>");
} catch (Exception $e) {
    // Catches other errors, like if the credential variables are not defined.
    die("<h2>FAILURE: A general error occurred.</h2><p><b>Error:</b> " . $e->getMessage() . "</p>");
}