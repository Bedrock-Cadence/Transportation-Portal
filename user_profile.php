<?php
// FILE: public/user_profile.php

require_once 'init.php';

// --- Security & Permission Check ---
if (!isset($_SESSION["loggedin"])) {
    redirect('login.php');
}

$page_title = 'User Profile';
$db = Database::getInstance();
$page_message = '';
$page_error = '';

$view_user_uuid = $_GET['uuid'] ?? $_SESSION['user_uuid'];
$is_privileged = in_array($_SESSION['user_role'], ['superuser', 'admin']);

if (!$is_privileged && $view_user_uuid !== $_SESSION['user_uuid']) {
    redirect('user_profile.php?uuid=' . $_SESSION['user_uuid']);
}

// --- Form Submission Handling for Privileged Actions ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && $is_privileged) {
    $target_user_uuid = $_POST['uuid'] ?? null;
    $action = $_POST['action'] ?? null;

    if ($target_user_uuid === $_SESSION['user_uuid']) {
        $page_error = "You cannot perform this action on your own profile.";
    } else {
        try {
            $stmt = $db->query("SELECT id, is_active, email FROM users WHERE uuid = ? LIMIT 1", [$target_user_uuid]);
            $target_user = $stmt->fetch();

            if (!$target_user) {
                throw new Exception("User not found.");
            }

            $action_type = '';
            $action_message = '';

            switch ($action) {
                case 'deactivate':
                    if ($target_user['is_active']) {
                        $db->query("UPDATE users SET is_active = 0 WHERE id = ?", [$target_user['id']]);
                        $page_message = "User has been deactivated.";
                        $action_type = 'user_deactivated';
                    }
                    break;
                case 'activate':
                    if (!$target_user['is_active']) {
                        $db->query("UPDATE users SET is_active = 1 WHERE id = ?", [$target_user['id']]);
                        $page_message = "User has been activated.";
                        $action_type = 'user_activated';
                    }
                    break;
                case 'reset_password':
                    if ($target_user['is_active']) {
                        $new_password = bin2hex(random_bytes(8));
                        $password_hash = password_hash($new_password, PASSWORD_ARGON2ID);
                        $db->query("UPDATE users SET password_hash = ? WHERE id = ?", [$password_hash, $target_user['id']]);
                        $page_message = "User password has been reset. New temporary password: " . e($new_password);
                        $action_type = 'password_reset';
                    }
                    break;
            }

            if (!empty($action_type)) {
                log_user_action($action_type, "Action performed on user " . $target_user['email']);
            }
        } catch (Exception $e) {
            $page_error = "An error occurred: " . $e->getMessage();
        }
    }
}

// --- Data Fetching for Display ---
$user = null;
$timeline = [];
try {
    $stmt = $db->query("SELECT u.*, e.name as entity_name FROM users u LEFT JOIN entities e ON u.entity_id = e.id AND u.entity_type = e.type WHERE u.uuid = ? LIMIT 1", [$view_user_uuid]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception("User profile not found.");
    }
    
    // Admins can see the user activity timeline.
    if ($_SESSION['user_role'] === 'admin') {
        $timeline = $db->query("SELECT * FROM user_history WHERE target_user_id = ? ORDER BY created_at DESC LIMIT 50", [$user['id']])->fetchAll();
    }
} catch (Exception $e) {
    $page_error = $e->getMessage();
}

function getDisplayName($role) {
    return ucwords(str_replace('_', ' ', $role));
}

require_once 'header.php';
?>

<div id="user-profile-container" class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">User Profile</h1>
    </div>

    <?php if (!empty($page_message)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p class="font-bold">Success</p>
            <p><?= e($page_message); ?></p>
        </div>
    <?php endif; ?>
    <?php if (!empty($page_error)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p class="font-bold">Error</p>
            <p><?= e($page_error); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($user): ?>
    <div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200">
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
            <div>
                <h2 class="text-xl font-semibold text-gray-800"><?= e($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                <p class="text-sm text-gray-500 font-mono"><?= e($user['uuid']); ?></p>
            </div>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium leading-4 <?= $user['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                <?= $user['is_active'] ? 'Active' : 'Deactivated'; ?>
            </span>
        </div>

        <div class="p-6 space-y-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="space-y-6">
                    <h3 class="text-lg font-medium text-gray-900 border-b pb-2">Profile Information</h3>
                    <dl class="space-y-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Email Address</dt>
                            <dd class="mt-1 text-base text-gray-900"><?= e($user['email']); ?></dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Access Role</dt>
                            <dd class="mt-1 text-base text-gray-900"><?= e(getDisplayName($user['role'])); ?></dd>
                        </div>
                        <?php if (!empty($user['entity_name'])): ?>
                            <div>
                                <dt class="text-sm font-medium text-gray-500"><?= e(ucfirst($user['entity_type'])); ?> Name</dt>
                                <dd class="mt-1 text-base text-gray-900"><?= e($user['entity_name']); ?></dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                </div>

                <?php if ($is_privileged && $view_user_uuid !== $_SESSION['user_uuid']): ?>
                <div class="space-y-6">
                    <h3 class="text-lg font-medium text-gray-900 border-b pb-2">Admin Actions</h3>
                    <div class="space-y-4">
                        <?php if ($user['is_active']): ?>
                            <form method="POST" action="user_profile.php?uuid=<?= e($user['uuid']); ?>">
                                <input type="hidden" name="uuid" value="<?= e($user['uuid']); ?>">
                                <input type="hidden" name="action" value="deactivate">
                                <button type="submit" onclick="return confirm('Are you sure you want to deactivate this account?')" class="w-full bg-red-600 text-white font-semibold py-2 px-4 rounded-md hover:bg-red-700">Deactivate User</button>
                            </form>
                            <form method="POST" action="user_profile.php?uuid=<?= e($user['uuid']); ?>">
                                <input type="hidden" name="uuid" value="<?= e($user['uuid']); ?>">
                                <input type="hidden" name="action" value="reset_password">
                                <button type="submit" onclick="return confirm('Are you sure you want to reset this user\'s password?')" class="w-full bg-orange-500 text-white font-semibold py-2 px-4 rounded-md hover:bg-orange-600">Reset Password</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="user_profile.php?uuid=<?= e($user['uuid']); ?>">
                                <input type="hidden" name="uuid" value="<?= e($user['uuid']); ?>">
                                <input type="hidden" name="action" value="activate">
                                <button type="submit" onclick="return confirm('Are you sure you want to activate this account?')" class="w-full bg-green-600 text-white font-semibold py-2 px-4 rounded-md hover:bg-green-700">Activate User</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($_SESSION['user_role'] === 'admin' && !empty($timeline)): ?>
                <div class="mt-8">
                    <h3 class="text-lg font-medium text-gray-900 border-b pb-2">User Timeline</h3>
                    <ul class="space-y-4 mt-4">
                        <?php foreach ($timeline as $activity): ?>
                            <li class="p-4 bg-gray-50 rounded-md border border-gray-200">
                                <div class="text-xs text-gray-500"><?= e(date('F d, Y h:i:s A', strtotime($activity['created_at']))); ?></div>
                                <div class="font-semibold text-gray-800"><?= e(ucwords(str_replace('_', ' ', $activity['action']))); ?></div>
                                <div class="text-sm text-gray-600"><?= e($activity['message']); ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>