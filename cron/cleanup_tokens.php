<?php
// FILE: /public_html/portal/cron/cleanup_tokens.php

// This script should only be run from the command line (CLI)
if (php_sapi_name() !== 'cli') {
    die("Access Denied.");
}

require_once __DIR__ . '/../../../app/init.php';

$db = Database::getInstance();

try {
    // This query finds users who are inactive AND their token has expired.
    $sql = "DELETE FROM users WHERE is_active = 0 AND token_expires_at <= NOW()";
    
    $statement = $db->query($sql);
    $rowCount = $statement->rowCount();

    $logMessage = "Expired registration token cleanup complete. Removed {$rowCount} inactive users.";
    
    // Use your logging service to record this system action
    LoggingService::log(null, null, 'system_cleanup', $logMessage);

    echo $logMessage . "\n";

} catch (Exception $e) {
    // Log any errors that occur during the cron job
    LoggingService::log(null, null, 'system_cleanup_error', 'Error during token cleanup: ' . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}