<?php
// FILE: /public_html/portal/notifications.php

require_once __DIR__ . '/../../app/init.php';

// Assuming $notificationService is an instance of your NotificationService class
if (Auth::isLoggedIn()) {
    $userId = Auth::user('user_id');
    $entityId = Auth::user('entity_id');
    $entityType = Auth::user('entity_type');
    
} else {
    // This block handles the case where the user is not logged in.
    // Since you have a redirect, it's a good fail-safe.
    Utils::redirect('login.php');
    $notifications = [];
}

$page_title = 'My Notifications';
$notificationService = new NotificationService(); // Using the service
$notifications = []; // Initialize to prevent errors

$notifications = $notificationService->getAllNotificationsForUser($userId, $entityId, $entityType);

require_once 'header.php';
?>

<div class="max-w-4xl mx-auto">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Notifications</h1>

    <div class="bg-white shadow-md rounded-lg border border-gray-200">
        <div class="p-6 space-y-4">
            <?php if (empty($notifications)): ?>
                <p class="text-center text-gray-500 py-8">You have no new notifications.</p>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-item border-b border-gray-200 pb-4" id="notification-<?= Utils::e($notification['id']); ?>">
                        <p class="text-gray-800"><?= Utils::e($notification['message']); ?></p>
                        <div class="flex justify-between items-center mt-2">
                            <span class="text-xs text-gray-400"><?= Utils::formatUtcToUserTime($notification['created_at']); ?></span>
                            <div>
                                <?php if (!empty($notification['link'])): ?>
                                    <a href="<?= Utils::e($notification['link']); ?>" class="text-sm text-blue-600 hover:underline mr-4">View Details</a>
                                <?php endif; ?>
                                <?php if (!$notification['is_read']): ?>
                                    <?php if (!empty($notification['user_id'])): ?>
                                        <button onclick="updateNotificationStatus(<?= Utils::e($notification['id']); ?>, 'read')" class="text-sm text-blue-600 hover:underline focus:outline-none">Mark as Read</button>
                                    <?php elseif (!empty($notification['entity_id'])): ?>
                                        <button onclick="updateNotificationStatus(<?= Utils::e($notification['id']); ?>, 'dismiss')" class="text-sm text-blue-600 hover:underline focus:outline-none">Dismiss</button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function updateNotificationStatus(notificationId, action) {
        fetch('update_notification.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ notification_id: notificationId, action: action })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const notificationElement = document.getElementById(`notification-${notificationId}`);
                if (notificationElement) {
                    if (action === 'read') {
                        // Visually mark as read
                        notificationElement.classList.add('opacity-50', 'italic');
                        const button = notificationElement.querySelector('button');
                        if (button) button.remove();
                    } else if (action === 'dismiss') {
                        // Remove from view for all users in the entity
                        notificationElement.remove();
                    }
                }
            } else {
                console.error('Failed to update notification:', data.message);
                alert('Oops! Something went wrong. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Something went wrong with the network request. Please try again.');
        });
    }
</script>

<?php require_once 'footer.php'; ?>