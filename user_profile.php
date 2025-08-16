<?php
// FILE: user_profile.php

// 1. Set the page title for the header.
$page_title = 'User Profile';

// 2. Include the header, which also handles session startup.
require_once 'header.php';

// 3. Security Check: Redirect if the user isn't logged in.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// 4. Include the database connection file.
require_once __DIR__ . '/../../app/db_connect.php';

// Initialize variables for messages and errors.
$page_message = '';
$page_error = '';

// Determine which user's profile to view. Default to the logged-in user.
$view_user_uuid = $_GET['uuid'] ?? $_SESSION['user_uuid'];

// Determine the logged-in user's role.
$user_role = $_SESSION['user_role'] ?? null;
$is_privileged = in_array($user_role, ['superuser', 'admin']);

// Security Check: A non-privileged user can only view their own profile.
if (!$is_privileged && $view_user_uuid !== $_SESSION['user_uuid']) {
    header("location: user_profile.php?uuid=" . $_SESSION['user_uuid']);
    exit;
}

// --- Start of Form Submission Handling ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && $is_privileged) {
    $target_user_uuid = $_POST['uuid'] ?? null;
    $action = $_POST['action'] ?? null;

    if ($target_user_uuid === $_SESSION['user_uuid']) {
        $page_error = "You cannot perform this action on your own profile.";
    } else {
        $stmt = null;
        try {
            // Fetch the target user's data to ensure they exist and determine their status.
            $stmt = $mysqli->prepare("SELECT id, is_active, email FROM users WHERE uuid = ? LIMIT 1");
            $stmt->bind_param("s", $target_user_uuid);
            $stmt->execute();
            $result = $stmt->get_result();
            $target_user = $result->fetch_assoc();
            
            if (!$target_user) {
                $page_error = "User not found.";
            } else {
                $affected_user_id = $target_user['id'];
                $actor_user_id = $_SESSION['user_id'];
                $action_type = '';
                $action_message = '';

                switch ($action) {
                    case 'deactivate':
                        if ($target_user['is_active']) {
                            $stmt = $mysqli->prepare("UPDATE users SET is_active = 0 WHERE uuid = ?");
                            $stmt->bind_param("s", $target_user_uuid);
                            $stmt->execute();
                            $page_message = "User has been deactivated.";
                            $action_type = 'user_deactivated';
                            $action_message = 'User account was deactivated.';
                        } else {
                            $page_error = "User is already deactivated.";
                        }
                        break;
                    case 'activate':
                        if (!$target_user['is_active']) {
                            $stmt = $mysqli->prepare("UPDATE users SET is_active = 1 WHERE uuid = ?");
                            $stmt->bind_param("s", $target_user_uuid);
                            $stmt->execute();
                            $page_message = "User has been activated.";
                            $action_type = 'user_activated';
                            $action_message = 'User account was activated.';
                        } else {
                            $page_error = "User is already active.";
                        }
                        break;
                    case 'change_email':
                        if (!$target_user['is_active']) {
                            $page_error = "Cannot change email for a deactivated user.";
                        } else {
                            $old_email = $target_user['email'];
                            $new_email = trim($_POST['new_email'] ?? '');
                            if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                                $page_error = "Invalid email format.";
                            } else {
                                $stmt = $mysqli->prepare("UPDATE users SET email = ? WHERE uuid = ?");
                                $stmt->bind_param("ss", $new_email, $target_user_uuid);
                                $stmt->execute();
                                $page_message = "User email has been updated.";
                                $action_type = 'email_change';
                                $action_message = "Email changed from '{$old_email}' to '{$new_email}'.";
                            }
                        }
                        break;
                    case 'reset_password':
                        if (!$target_user['is_active']) {
                            $page_error = "Cannot reset password for a deactivated user.";
                        } else {
                            $new_password = bin2hex(random_bytes(8));
                            $password_hash = password_hash($new_password, PASSWORD_ARGON2ID);
                            
                            $stmt = $mysqli->prepare("UPDATE users SET password_hash = ? WHERE uuid = ?");
                            $stmt->bind_param("ss", $password_hash, $target_user_uuid);
                            $stmt->execute();
                            $page_message = "User password has been reset. The new password is: " . htmlspecialchars($new_password);
                            $action_type = 'password_reset';
                            $action_message = 'Password reset.';
                        }
                        break;
                }
                
                // Log the action if a message was created.
                if (!empty($action_type)) {
                    $log_stmt = $mysqli->prepare("INSERT INTO user_history (actor_user_id, target_user_id, action, message) VALUES (?, ?, ?, ?)");
                    $log_stmt->bind_param("iiss", $actor_user_id, $affected_user_id, $action_type, $action_message);
                    $log_stmt->execute();
                    $log_stmt->close();
                }
            }
        } catch (Exception $e) {
            $page_error = "An error occurred: " . $e->getMessage();
        } finally {
            if ($stmt) {
                $stmt->close();
            }
        }
    }
}
// --- End of Form Submission Handling ---

// Fetch the user's data from the database.
$user = null;
$timeline = [];
try {
    $stmt = $mysqli->prepare("SELECT * FROM users WHERE uuid = ? LIMIT 1");
    $stmt->bind_param("s", $view_user_uuid);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user && $user_role === 'admin') {
        // Only admins can see the user activity timeline.
        $stmt = $mysqli->prepare("SELECT * FROM user_history WHERE target_user_id = ? ORDER BY created_at DESC LIMIT 50");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $timeline[] = $row;
        }
        $stmt->close();
    }
} catch (Exception $e) {
    $page_error = "Could not load user data. " . $e->getMessage();
}

// Function to translate internal roles to display names.
function getDisplayName($role) {
    return ucfirst($role);
}
?>

<div id="user-profile-container" class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">User Profile</h1>
    </div>

    <?php if ($user): ?>
    <div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200">
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
            <div>
                <h2 class="text-xl font-semibold text-gray-800"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                <p class="text-sm text-gray-500 font-mono"><?= htmlspecialchars($user['uuid']); ?></p>
            </div>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium leading-4 <?= $user['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                <?= $user['is_active'] ? 'Active' : 'Deactivated'; ?>
            </span>
        </div>

        <div class="p-6 space-y-8">
            <?php if (!empty($page_message)): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4" role="alert">
                    <p class="font-bold">Success</p>
                    <p><?= htmlspecialchars($page_message); ?></p>
                </div>
            <?php endif; ?>
            <?php if (!empty($page_error)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert">
                    <p class="font-bold">Error</p>
                    <p><?= htmlspecialchars($page_error); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($is_privileged && $view_user_uuid !== $_SESSION['user_uuid']): ?>
                <div class="bg-yellow-100 text-yellow-800 p-3 rounded-md text-sm text-center font-semibold">
                    <?= htmlspecialchars(ucfirst($user_role)); ?> View
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="space-y-6">
                    <h3 class="text-lg font-medium text-gray-900 border-b pb-2">Profile Information</h3>
                    <dl class="space-y-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Email Address</dt>
                            <dd class="mt-1 text-base text-gray-900"><?= htmlspecialchars($user['email']); ?></dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Access Role</dt>
                            <dd class="mt-1 text-base text-gray-900"><?= htmlspecialchars(getDisplayName($user['role'])); ?></dd>
                        </div>
                        <?php if (isset($user['entity_type'])): ?>
                            <div>
                                <dt class="text-sm font-medium text-gray-500"><?= ucfirst($user['entity_type']); ?> Name</dt>
                                <dd class="mt-1 text-base text-gray-900"><?= htmlspecialchars($_SESSION['entity_name'] ?? 'N/A'); ?></dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                </div>

                <?php if ($is_privileged && $view_user_uuid !== $_SESSION['user_uuid']): ?>
                <div class="space-y-6">
                    <h3 class="text-lg font-medium text-gray-900 border-b pb-2">Admin Actions</h3>
                    <div class="space-y-4">
                        <?php if ($user['is_active']): ?>
                            <form method="POST" action="user_profile.php?uuid=<?= htmlspecialchars($user['uuid']); ?>">
                                <input type="hidden" name="uuid" value="<?= htmlspecialchars($user['uuid']); ?>">
                                <input type="hidden" name="action" value="deactivate">
                                <button type="submit" onclick="return confirm('Are you sure you want to deactivate this account? This action cannot be undone.')" class="w-full bg-red-600 text-white font-semibold py-2 px-4 rounded-md hover:bg-red-700 transition-colors duration-150 ease-in-out">
                                    Deactivate User
                                </button>
                            </form>
                            <form method="POST" action="user_profile.php?uuid=<?= htmlspecialchars($user['uuid']); ?>">
                                <input type="hidden" name="uuid" value="<?= htmlspecialchars($user['uuid']); ?>">
                                <input type="hidden" name="action" value="reset_password">
                                <button type="submit" onclick="return confirm('Are you sure you want to reset this user\'s password? A new temporary password will be generated.')" class="w-full bg-orange-500 text-white font-semibold py-2 px-4 rounded-md hover:bg-orange-600 transition-colors duration-150 ease-in-out">
                                    Reset Password
                                </button>
                            </form>
                            <form method="POST" action="user_profile.php?uuid=<?= htmlspecialchars($user['uuid']); ?>" class="space-y-2">
                                <input type="hidden" name="uuid" value="<?= htmlspecialchars($user['uuid']); ?>">
                                <input type="hidden" name="action" value="change_email">
                                <input type="email" name="new_email" placeholder="New Email Address" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <button type="submit" class="w-full bg-blue-600 text-white font-semibold py-2 px-4 rounded-md hover:bg-blue-700 transition-colors duration-150 ease-in-out">
                                    Change Email
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="user_profile.php?uuid=<?= htmlspecialchars($user['uuid']); ?>">
                                <input type="hidden" name="uuid" value="<?= htmlspecialchars($user['uuid']); ?>">
                                <input type="hidden" name="action" value="activate">
                                <button type="submit" onclick="return confirm('Are you sure you want to activate this account?')" class="w-full bg-green-600 text-white font-semibold py-2 px-4 rounded-md hover:bg-green-700 transition-colors duration-150 ease-in-out">
                                    Activate User
                                </button>
                            </form>
                            <div class="text-center text-sm text-gray-500">
                                Password reset and email change are disabled for deactivated users.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($user_role === 'admin'): ?>
                <div class="mt-8">
                    <h3 class="text-lg font-medium text-gray-900 border-b pb-2">User Timeline</h3>
                    <ul class="space-y-4 mt-4">
                        <?php if (empty($timeline)): ?>
                            <li class="text-sm text-gray-500">No recent activity found for this user.</li>
                        <?php else: ?>
                            <?php foreach ($timeline as $activity): ?>
                                <li class="p-4 bg-gray-50 rounded-md border border-gray-200">
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars(date('F d, Y h:i:s A', strtotime($activity['created_at']))); ?></div>
                                    <div class="font-semibold text-gray-800"><?= htmlspecialchars(str_replace('_', ' ', ucfirst($activity['action']))); ?></div>
                                    <div class="text-sm text-gray-600"><?= htmlspecialchars($activity['message']); ?></div>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert">
            <p class="font-bold">Error</p>
            <p>User profile not found. Please ensure the UUID is correct.</p>
        </div>
    <?php endif; ?>

</div>

<?php
// This includes the footer and necessary closing tags.
require_once 'footer.php';
?>