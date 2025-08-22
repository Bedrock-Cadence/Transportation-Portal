<?php
// FILE: public_html/portal/user_profile.php

require_once __DIR__ . '/../../app/init.php';

// --- 1. AUTHORIZATION & DATA FETCHING ---
if (!Auth::isLoggedIn()) {
    Utils::redirect('login.php');
}

$page_title = 'User Profile';
$page_message = '';
$page_error = '';
$userService = new UserService();
$db = Database::getInstance(); // Database instance for activity log
$targetUser = null;
$userActivityLogs = []; // Initialize for admin view
$currentUser = Auth::all(); // CORRECTED: Use the new all() method

$targetUuid = $_GET['uuid'] ?? Auth::user('user_uuid');

// --- UUID Validation ---
if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $targetUuid)) {
    LoggingService::log(Auth::user('user_id'), null, 'view_profile_fail', 'Attempted to view with invalid UUID format: ' . $targetUuid);
    Utils::redirect('user_management.php?error=invalid_uuid');
}

try {
    $targetUser = $userService->getUserByUuid($targetUuid);
    if (!$targetUser) {
        LoggingService::log(Auth::user('user_id'), null, 'view_profile_fail', 'Attempted to view non-existent UUID: ' . $targetUuid);
        Utils::redirect('user_management.php?error=notfound');
    }

    // --- ACCESS CONTROL LOGIC ---
    $isSelf = $targetUser['uuid'] === Auth::user('user_uuid');
    $isTargetAdmin = $targetUser['role'] === 'admin';
    $canEdit = false;

    if (Auth::hasRole('admin') && !$isSelf && !$isTargetAdmin) {
        $canEdit = true;
    } elseif (Auth::hasRole('superuser') && !$isSelf && $targetUser['entity_id'] === Auth::user('entity_id')) {
        $canEdit = true;
    }

    // --- ADMIN: Fetch User Activity Logs ---
    if (Auth::hasRole('admin')) {
        $userActivityLogs = $db->fetchAll(
            "SELECT * FROM user_activity_logs WHERE actor_user_id = ? ORDER BY created_at DESC LIMIT 50",
            [$targetUser['id']]
        );
    }

    // --- POST REQUEST HANDLING ---
    if ($_SERVER["REQUEST_METHOD"] === "POST" && $canEdit) {
        $action = $_POST['action'] ?? '';
        $targetUserId = $targetUser['id'];

        switch ($action) {
            case 'update_profile':
                $newEmail = trim($_POST['email']);
                $userService->updateUserEmail($targetUserId, $newEmail);
                LoggingService::log(Auth::user('user_id'), $targetUserId, 'user_email_updated', 'User email updated.', ['new_email' => $newEmail]);
                $page_message = 'User email updated successfully.';
                break;

            case 'send_password_reset':
                $invitationData = $userService->createPasswordResetInvitation($targetUserId);
                NotificationService::sendPasswordResetEmail($targetUser['email'], $invitationData['token']);
                LoggingService::log(Auth::user('user_id'), $targetUserId, 'password_reset_sent', 'Password reset link sent to user.');
                $page_message = 'A password reset email has been sent.';
                break;

            case 'deactivate_user':
                $userService->deactivateUser($targetUserId);
                LoggingService::log(Auth::user('user_id'), $targetUserId, 'user_deactivated', 'User account deactivated.');
                Utils::redirect("user_profile.php?uuid={$targetUuid}&status=deactivated");
                break;
                
            case 'activate_user':
                $userService->activateUser($targetUserId);
                LoggingService::log(Auth::user('user_id'), $targetUserId, 'user_activated', 'User account reactivated.');
                Utils::redirect("user_profile.php?uuid={$targetUuid}&status=activated");
                break;
        }
        // Re-fetch user data to show the latest changes
        $targetUser = $userService->getUserByUuid($targetUuid);
    }
} catch (Exception $e) {
    $page_error = $e->getMessage();
    LoggingService::log(Auth::user('user_id'), $targetUser['id'] ?? null, 'profile_action_error', $e->getMessage());
}

require_once 'header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">User Profile</h1>
        <?php if ($canEdit): ?>
             <a href="user_management.php" class="text-blue-600 hover:text-blue-800">&larr; Back to User Management</a>
        <?php endif; ?>
    </div>

    <?php if ($page_message): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p><?= Utils::e($page_message); ?></p>
        </div>
    <?php endif; ?>
    <?php if ($page_error): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p><?= Utils::e($page_error); ?></p>
        </div>
    <?php endif; ?>

    <div class="bg-white shadow-md rounded-lg border border-gray-200">
        <div class="p-6">
            <form method="POST" action="user_profile.php?uuid=<?= Utils::e($targetUser['uuid']); ?>" class="space-y-6">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="first_name" class="block text-sm font-medium text-gray-700">First Name</label>
                        <input type="text" id="first_name" value="<?= Utils::e($targetUser['first_name']); ?>" readonly class="mt-1 block w-full rounded-md border-gray-300 shadow-sm bg-gray-100">
                    </div>
                    <div>
                        <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name</label>
                        <input type="text" id="last_name" value="<?= Utils::e($targetUser['last_name']); ?>" readonly class="mt-1 block w-full rounded-md border-gray-300 shadow-sm bg-gray-100">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                        <input type="email" id="email" name="email" value="<?= Utils::e($targetUser['email']); ?>" required <?= !$canEdit ? 'readonly' : '' ?> class="mt-1 block w-full rounded-md border-gray-300 shadow-sm read-only:bg-gray-100">
                    </div>
                    <div>
                        <label for="phone_number" class="block text-sm font-medium text-gray-700">Phone Number</label>
                        <input type="tel" id="phone_number" value="<?= Utils::e($targetUser['phone_number']); ?>" readonly class="mt-1 block w-full rounded-md border-gray-300 shadow-sm bg-gray-100">
                    </div>
                </div>
                
                <?php if ($canEdit): ?>
                    <div class="flex justify-end pt-4">
                        <button type="submit" class="inline-flex justify-center py-2 px-4 border shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                            Update Email
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <?php if ($canEdit): ?>
    <div class="mt-8 bg-white shadow-md rounded-lg border border-gray-200">
        <div class="p-6 space-y-4">
            <h2 class="text-xl font-semibold text-gray-800 border-b pb-2">User Actions</h2>
            <div class="flex flex-wrap gap-4">
                <form method="POST" action="user_profile.php?uuid=<?= Utils::e($targetUser['uuid']); ?>">
                    <input type="hidden" name="action" value="send_password_reset">
                    <button type="submit" class="btn-primary">Send Password Reset</button>
                </form>

                <?php if ($targetUser['is_active']): ?>
                    <form method="POST" action="user_profile.php?uuid=<?= Utils::e($targetUser['uuid']); ?>" onsubmit="return confirm('Are you sure you want to deactivate this user?');">
                        <input type="hidden" name="action" value="deactivate_user">
                        <button type="submit" class="btn-danger">Deactivate User</button>
                    </form>
                <?php else: ?>
                    <form method="POST" action="user_profile.php?uuid=<?= Utils::e($targetUser['uuid']); ?>">
                        <input type="hidden" name="action" value="activate_user">
                        <button type="submit" class="btn-success">Re-activate User</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (Auth::hasRole('admin') && !empty($userActivityLogs)): ?>
    <div class="mt-8 bg-white shadow-md rounded-lg border border-gray-200">
        <h2 class="text-xl font-semibold p-6 border-b">User Activity Log</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="table-th">Timestamp</th>
                        <th class="table-th">Action</th>
                        <th class="table-th">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($userActivityLogs as $log): ?>
                        <tr class="table-row-hover">
                            <td class="table-td"><?= Utils::formatUtcToUserTime($log['created_at']) ?></td>
                            <td class="table-td font-medium"><?= Utils::e(ucwords(str_replace('_', ' ', $log['action']))) ?></td>
                            <td class="table-td text-sm text-gray-600"><?= Utils::e($log['message']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>