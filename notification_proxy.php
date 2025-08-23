<?php
// Bootstrap the application to access the session.
require_once __DIR__ . '/../app/init.php';

// Authenticate using the 'portal.bedrockcadence.com' session cookie. This works because it's a same-domain request.
if (!Auth::isLoggedIn()) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit;
}

// Now that we are authenticated, we can safely execute the main API script on the server side.
require __DIR__ . '/../api/notifications_api.php';