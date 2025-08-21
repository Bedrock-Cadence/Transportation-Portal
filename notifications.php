<?php
// FILE: /public_html/portal/notifications.php

require_once __DIR__ . '/../../app/init.php';

if (!Auth::isLoggedIn()) {
    Utils::redirect('login.php');
}

$page_title = 'My Notifications';
$notificationService = new NotificationService(); // Using the service
$notifications = []; // Initialize to prevent errors

// Prepare the user data for JavaScript, fixing the syntax error
$userDataForJs = json_encode([
    'entityType' => Auth::user('entity_type'),
    'entityId' => Auth::user('entity_id'),
    'userId' => Auth::user('user_id'),
    'apiBaseUrl' => 'https://www.bedrockcadence.com/api' 
]);

// Call the service with the user ID

echo $userDataForJs;

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
                    <div class="notification-item border-b border-gray-200 pb-4">
                        <p class="text-gray-800"><?= Utils::e($notification['message']); ?></p>
                        <div class="flex justify-between items-center mt-2">
                            <span class="text-xs text-gray-400"><?= Utils::formatUtcToUserTime($notification['created_at']); ?></span>
                            <?php if (!empty($notification['link'])): ?>
                                <a href="<?= Utils::e($notification['link']); ?>" class="text-sm text-blue-600 hover:underline">View Details</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>