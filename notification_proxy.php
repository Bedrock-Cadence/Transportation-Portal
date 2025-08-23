<?php
// FILE: /public_html/portal/notification_proxy.php

// Bootstrap the application to access the session and Auth services.
require_once __DIR__ . '/../../app/init.php';

// This is the crucial step: We check for a valid login using the
// 'portal.bedrockcadence.com' session cookie.
if (!Auth::isLoggedIn()) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit;
}

// Since we are authenticated, we can now safely include and execute the
// actual API script. Its logic will run using the portal's authenticated session.
// This avoids the cross-domain issue entirely.
require __DIR__ . '/../api/notifications_api.php';