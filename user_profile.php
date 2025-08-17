<?php
// FILE: public_html/portal/user_profile.php

require_once __DIR__ . '/../../app/init.php';

// --- 1. AUTHORIZATION & DATA FETCHING ---
// Ensure a user is logged in before proceeding.
if (!Auth::isLoggedIn()) {
    Utils::redirect('login.php');
}

$page_title = 'User Profile';
$page_message = $_GET['status'] ?? ''; // For success messages on redirect
$page_error = '';
$userService = new UserService();
$targetUser = null;
$currentUser = Auth::user();

// The UUID is required to identify the profile.
$targetUuid = $_GET['uuid'] ?? ($currentUser['user_uuid'] ?? null);

// --- NEW FIX: Validate UUID format to prevent fatal errors ---
$uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
if (empty($targetUuid) || !preg_match($uuidPattern, $targetUuid)) {
    // Log the invalid access attempt.
    LoggingService::log($currentUser['user_id'], null, 'view_profile_fail', 'Attempted to view with invalid UUID format: ' . $targetUuid);
    Utils::redirect('user_management.php?error=invalid_uuid');
}

try {
    $targetUser = $userService->getUserByUuid($targetUuid);
    if (!$targetUser) {
        LoggingService::log($currentUser['user_id'], null, 'view_profile_fail', 'Attempted to view non-existent UUID: ' . $targetUuid);
        Utils::redirect('user_management.php?error=notfound');
    }

    // --- Access Control Logic ---
    $isSelf = $targetUser['uuid'] === $currentUser['user_uuid'];
    $isTargetAdmin = $targetUser['role'] === 'admin';
    $canEdit = false;
    $viewOnly = true;

    if ($isSelf) {
        // A user can view their own profile, but cannot edit it.
        if (in_array($currentUser['user_role'], ['user', 'superuser', 'admin'])) {
            $viewOnly = true;
        }
    } elseif ($currentUser['user_role'] === 'admin') {
        // Admins can view and edit anyone (except themselves and other admins).
        $canEdit = !$isTargetAdmin;
        $viewOnly = false;
    } elseif ($currentUser['user_role'] === 'superuser') {
        // Superusers can view and edit users from their own entity.
        if ($targetUser['entity_type'] === $currentUser['entity_type'] && $targetUser['entity_id'] === $currentUser['entity_id']) {
            $canEdit = true;
            $viewOnly = false;
        } else {
            // Log unauthorized access attempt and redirect.
            LoggingService::log($currentUser['user_id'], $targetUser['id'], 'unauthorized_profile_access', "Superuser attempted to view a profile outside their entity.");
            Utils::redirect('user_management.php?error=unauthorized');
        }
    } else { // Standard user role
        // A standard user can only view their own profile (handled by $isSelf above).
        LoggingService::log($currentUser['user_id'], $targetUser['id'], 'unauthorized_profile_access', "Standard user attempted to view another user's profile.");
        Utils::redirect('index.php?error=unauthorized');
    }

    // Check if the form is submitted
    if ($_SERVER["REQUEST_METHOD"] === "POST" && $canEdit) {
        $action = $_POST['action'] ?? '';
        $targetUserId = $targetUser['id'];

        // Prevent admins from editing other admins.
        if ($currentUser['user_role'] === 'admin' && $targetUser['role'] === 'admin' && $targetUser['id'] !== $currentUser['user_id']) {
            throw new Exception("Admins cannot edit other admins' profiles.");
        }

        switch ($action) {
            case 'update_profile':
                $userData = [
                    'first_name' => trim($_POST['first_name']),
                    'last_name' => trim($_POST['last_name']),
                    'email' => trim($_POST['email']),
                    'role' => trim($_POST['role']),
                ];
                $userService->updateUser($targetUserId, $userData);
                LoggingService::log($currentUser['user_id'], $targetUserId, 'user_updated', 'User profile updated.', ['changes' => $userData]);
                $page_message = 'User profile updated successfully.';
                // Re-fetch the user data to show the latest changes.
                $targetUser = $userService->getUserByUuid($targetUuid);
                break;

            case 'send_password':
                $invitationData = $userService->createPasswordResetInvitation($targetUserId);
                NotificationService::sendPasswordResetEmail($targetUser['email'], $invitationData['token']);
                LoggingService::log($currentUser['user_id'], $targetUserId, 'password_reset_sent', 'Password reset link sent to user.', ['target_email' => $targetUser['email']]);
                $page_message = 'A password reset email has been sent to the user.';
                break;

            case 'deactivate_user':
                $userService->deactivateUser($targetUserId);
                LoggingService::log($currentUser['user_id'], $targetUserId, 'user_deactivated', 'User account deactivated.');
                Utils::redirect("user_management.php?status=deactivated");
                break;
        }
    }
} catch (Exception $e) {
    $page_error = $e->getMessage();
    LoggingService::log($currentUser['user_id'], $targetUser['id'] ?? null, 'profile_action_error', $e->getMessage());
}

require_once 'header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">User Profile: <?= e($targetUser['first_name'] . ' ' . $targetUser['last_name']); ?></h1>
        <a href="user_management.php" class="text-blue-600 hover:text-blue-800">&larr; Back to User Management</a>
    </div>

    <?php if ($page_message): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p class="font-bold">Success</p>
            <p><?= e($page_message); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($page_error): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p class="font-bold">Error</p>
            <p><?= e($page_error); ?></p>
        </div>
    <?php endif; ?>

    <div class="bg-white shadow-md rounded-lg border border-gray-200">
        <div class="p-6">
            <?php if ($viewOnly): ?>
                <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-6" role="alert">
                    <p class="font-bold">Read-Only View</p>
                    <p>
                        This profile is read-only. If you need to make changes, please contact your Super User.
                    </p>
                </div>
            <?php endif; ?>

            <form method="POST" action="user_profile.php?uuid=<?= e($targetUser['uuid']); ?>" class="space-y-6">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="first_name" class="block text-sm font-medium text-gray-700">First Name</label>
                        <input type="text" id="first_name" name="first_name" value="<?= e($targetUser['first_name']); ?>" required <?= $canEdit ? '' : 'readonly' ?> class="mt-1 block w-full rounded-md border-gray-300 shadow-sm read-only:bg-gray-100">
                    </div>
                    <div>
                        <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name</label>
                        <input type="text" id="last_name" name="last_name" value="<?= e($targetUser['last_name']); ?>" required <?= $canEdit ? '' : 'readonly' ?> class="mt-1 block w-full rounded-md border-gray-300 shadow-sm read-only:bg-gray-100">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                        <input type="email" id="email" name="email" value="<?= e($targetUser['email']); ?>" required <?= $canEdit ? '' : 'readonly' ?> class="mt-1 block w-full rounded-md border-gray-300 shadow-sm read-only:bg-gray-100">
                    </div>
                    <div>
                        <label for="phone_number" class="block text-sm font-medium text-gray-700">Phone Number (Optional)</label>
                        <input type="tel" id="phone_number" name="phone_number" value="<?= e($targetUser['phone_number']); ?>" <?= $canEdit ? '' : 'readonly' ?> class="mt-1 block w-full rounded-md border-gray-300 shadow-sm read-only:bg-gray-100">
                    </div>
                </div>

                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700">Access Role</label>
                    <select id="role" name="role" required <?= $canEdit ? '' : 'disabled' ?> class="mt-1 block w-full rounded-md border-gray-300 shadow-sm read-only:bg-gray-100">
                        <?php if (Auth::hasRole('superuser')): ?>
                            <option value="user" <?= ($targetUser['role'] === 'user') ? 'selected' : '' ?>>User</option>
                            <option value="superuser" <?= ($targetUser['role'] === 'superuser') ? 'selected' : '' ?>>Superuser</option>
                        <?php elseif (Auth::hasRole('admin')): ?>
                            <option value="user" <?= ($targetUser['role'] === 'user') ? 'selected' : '' ?>>User</option>
                            <option value="superuser" <?= ($targetUser['role'] === 'superuser') ? 'selected' : '' ?>>Superuser</option>
                            <?php if ($isTargetAdmin): ?><option value="admin" selected>Administrator</option><?php endif; ?>
                        <?php endif; ?>
                    </select>
                </div>
                
                <?php if ($canEdit): ?>
                    <div class="flex justify-end pt-4 space-x-4">
                        <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                            Save Changes
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <?php if ($canEdit): ?>
    <div class="mt-8 bg-white shadow-md rounded-lg border border-gray-200">
        <div class="p-6 space-y-4">
            <h2 class="text-xl font-semibold text-gray-800 border-b border-gray-200 pb-2">User Actions</h2>
            <div class="flex flex-col sm:flex-row space-y-4 sm:space-y-0 sm:space-x-4">
                <form method="POST" action="user_profile.php?uuid=<?= e($targetUser['uuid']); ?>">
                    <input type="hidden" name="action" value="send_password">
                    <button type="submit" class="w-full sm:w-auto inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                        Send Password Reset
                    </button>
                </form>
                <form method="POST" action="user_profile.php?uuid=<?= e($targetUser['uuid']); ?>" onsubmit="return confirm('Are you sure you want to deactivate this user? This cannot be undone easily.');">
                    <input type="hidden" name="action" value="deactivate_user">
                    <button type="submit" class="w-full sm:w-auto inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700">
                        Deactivate User
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php require_once 'footer.php'; ?>