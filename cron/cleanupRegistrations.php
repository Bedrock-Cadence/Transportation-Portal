<?php
// FILE: cron/cleanupRegistrations.php

require_once __DIR__ . '/../../../app/init.php';

$db = Database::getInstance();
$sql = "DELETE FROM users WHERE status = 'pending_verification' AND token_expires_at < NOW()";
$db->execute($sql);

LoggingService::log(null, null, 'cron_cleanup', 'Cleaned up expired user registrations.');