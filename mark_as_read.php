<?php
// FILE: /public_html/portal/mark_as_read.php

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
if (!isset($data['notification_id']) || !is_numeric($data['notification_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid notification ID.']);
    exit;
}

$notificationId = (int)$data['notification_id'];

try {
    $notificationService = new NotificationService();
    
    // This is the key line. It calls the markNotificationAsRead method
    // which handles the logic for marking the notification as read.
    $notificationService->markNotificationAsRead($userId, $notificationId);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    // Log the error for debugging
    error_log('Error marking notification as read: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred.']);
}