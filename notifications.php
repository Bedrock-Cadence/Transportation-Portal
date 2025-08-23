<?php
// FILE: /public_html/portal/notifications.php

require_once __DIR__ . '/../../app/init.php';

if (Auth::isLoggedIn()) {
    $userId = Auth::user('user_id');
    $entityId = Auth::user('entity_id');
    $entityType = Auth::user('entity_type');
    
} else {
    Utils::redirect('login.php');
}

$page_title = 'My Notifications';
$notificationService = new NotificationService();

// Fetch all notifications, including dismissed ones
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
                    <?php
                        $is_acknowledged = !empty($notification['dismissed_at']);
                        $item_classes = 'notification-item border-b border-gray-200 pb-4';
                        if ($is_acknowledged) {
                            $item_classes .= ' opacity-50 italic';
                        }
                    ?>
                    <div class="<?= Utils::e($item_classes); ?>" id="notification-<?= Utils::e($notification['id']); ?>">
                        <p class="text-gray-800"><?= Utils::e($notification['message']); ?></p>
                        <div class="flex justify-between items-center mt-2">
                            <div>
                                <span class="text-xs text-gray-400">Created: <?= Utils::formatUtcToUserTime($notification['created_at']); ?></span>
                                <?php if ($is_acknowledged): ?>
                                    <span class="text-xs text-gray-400 ml-4">Acknowledged: <?= Utils::formatUtcToUserTime($notification['dismissed_at']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <?php if (!empty($notification['link'])): ?>
                                    <a href="<?= Utils::e($notification['link']); ?>" class="text-sm text-blue-600 hover:underline mr-4">View Details</a>
                                <?php endif; ?>
                                <?php if (!$is_acknowledged): ?>
                                    <button onclick="acknowledgeNotification(<?= Utils::e($notification['id']); ?>)" class="text-sm text-blue-600 hover:underline focus:outline-none">Acknowledge</button>
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
    function acknowledgeNotification(notificationId) {
        const formData = new URLSearchParams();
        formData.append('notification_id', notificationId);

        // --- FIX: Changed the URL to use the notification_proxy.php script.
        fetch('notification_proxy.php?action=acknowledge', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                const notificationElement = document.getElementById(`notification-${notificationId}`);
                if (notificationElement) {
                    notificationElement.classList.add('opacity-50', 'italic');
                    const button = notificationElement.querySelector('button');
                    if (button) button.remove();
                    
                    const acknowledgedAtSpan = document.createElement('span');
                    acknowledgedAtSpan.classList.add('text-xs', 'text-gray-400', 'ml-4');
                    acknowledgedAtSpan.textContent = 'Acknowledged: Just now';

                    const containerDiv = notificationElement.querySelector('.flex.justify-between.items-center.mt-2 > div');
                    if(containerDiv) {
                        containerDiv.appendChild(acknowledgedAtSpan);
                    }
                }
            } else {
                const errorMessage = data.error || 'Oops! Something went wrong. Please try again.';
                console.error('Failed to acknowledge notification:', errorMessage);
                alert(errorMessage);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Something went wrong with the network request. Please try again.');
        });
    }
</script>

<?php require_once 'footer.php'; ?>