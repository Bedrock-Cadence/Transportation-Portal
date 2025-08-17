<?php
// FILE: public/user_management.php

require_once __DIR__ . '/../../app/init.php';

// --- Security Check: Only allow 'admin' and 'superuser' roles ---
if (!isset($_SESSION["loggedin"]) || !in_array($_SESSION['user_role'], ['admin', 'superuser'])) {
    redirect('index.php');
}

// --- NEW CODE: Immediate redirection for superusers missing session data ---
if ($_SESSION['user_role'] === 'superuser' && !isset($_SESSION['entity_id'])) {
    // Log the error for debugging purposes.
    error_log("Superuser login failed: missing entity_id for user UUID " . $_SESSION['user_uuid']);

    // Redirect to a logout or general error page to prevent the broken page from loading.
    redirect('index.php?error=no_entity_id');
}

$page_title = 'User Management';
$db = Database::getInstance();
$active_users = [];
$inactive_users = [];
$all_users = []; // Initialize to an empty array to prevent fatal errors

try {
    $sql = "SELECT uuid, first_name, last_name, email, role, is_active FROM users";
    $params = [];

    // Superusers can only see users associated with their own entity.
    if ($_SESSION['user_role'] === 'superuser') {
        $sql .= " WHERE entity_id = ?";
        $params[] = $_SESSION['entity_id'];
    }
    
    $sql .= " ORDER BY last_name ASC, first_name ASC";

    $stmt = $db->query($sql, $params);
    $all_users = $stmt->fetchAll();

foreach ($all_users as $user) {
    if ($user['is_active'] === '1' || $user['is_active'] === 1) {
        $active_users[] = $user;
    } else {
        $inactive_users[] = $user;
    }
}

} catch (Exception $e) {
    error_log("User Management Page Error: " . $e->getMessage());
    // This message is safe to show the user.
    $page_error = "A problem occurred while retrieving user data. Please contact support.";
}

// Function to translate internal role names into user-friendly display names.
function getDisplayName($role) {
    switch ($role) {
        case 'admin': return 'Administrator';
        case 'superuser': return 'Super User';
        case 'user': return 'User';
        default: return ucfirst(str_replace('_', ' ', $role));
    }
}

require_once 'header.php';
?>

<div id="dashboard-container" class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">User Management</h1>
    </div>

    <?php if (isset($page_error)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p class="font-bold">Error</p>
            <p><?= e($page_error); ?></p>
        </div>
    <?php endif; ?>

    <div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200 mb-8">
        <div class="px-6 py-4 border-b bg-gray-50">
            <h2 class="text-xl font-semibold text-gray-800">Active Users</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Access</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
    <tr><td colspan="4" class="text-center py-2"><a href="/add_user.php" class="text-blue-600 hover:underline font-semibold">Add User</a></td></tr>
    
    <?php if (empty($active_users)): ?>
        <tr><td colspan="4" class="px-6 py-4 text-center text-gray-500">No active users found.</td></tr>
    <?php else: ?>
        <?php foreach ($active_users as $user): ?>
            <tr class="hover:bg-gray-50 transition-colors duration-150 ease-in-out">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?= e($user['first_name'] . ' ' . $user['last_name']); ?></td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?= e($user['email']); ?></td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?= e(getDisplayName($user['role'])); ?></td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">
                    <a href="user_profile.php?uuid=<?= e($user['uuid']); ?>" class="text-blue-600 hover:text-blue-800 font-semibold">View</a>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</tbody>
            </table>
        </div>
    </div>

    <div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200">
        <div class="px-6 py-4 border-b bg-gray-50">
            <h2 class="text-xl font-semibold text-gray-800">Inactive Users</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Access</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($inactive_users)): ?>
                        <tr><td colspan="4" class="px-6 py-4 text-center text-gray-500">No inactive users found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($inactive_users as $user): ?>
                            <tr class="hover:bg-gray-50 transition-colors duration-150 ease-in-out">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?= Utils::e($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?= Utils::e($user['email']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?= Utils::e(getDisplayName($user['role'])); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">
                                    <a href="user_profile.php?uuid=<?= Utils::e($user['uuid']); ?>" class="text-blue-600 hover:text-blue-800 font-semibold">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>