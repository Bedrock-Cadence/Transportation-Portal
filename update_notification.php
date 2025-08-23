<?php
// FILE: /public_html/portal/update_notification.php

require_once __DIR__ . '/../../app/init.php';

header('Content-Type: application/json');

if (!Auth::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

$userId = Auth::user('user_id');

// Read the JSON data from the request body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate the input
if (!isset($data['notification_id']) || !is_numeric($data['notification_id']) || !isset($data['action'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
    exit;
}

$notificationId = (int)$data['notification_id'];
$action = $data['action'];

try {
    $notificationService = new NotificationService();
    
    if ($action === 'read') {
        // Mark as read for an individual user
        $notificationService->markNotificationAsRead($userId, $notificationId);
    } elseif ($action === 'dismiss') {
        // Dismiss for the entire entity
        $notificationService->dismissNotificationForEntity($userId, $notificationId);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        exit;
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log('Error processing notification action: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred.']);
}