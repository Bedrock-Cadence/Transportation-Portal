<?php
// FILE: public_html/portal/add_user.php

require_once __DIR__ . '/../../app/init.php';

// --- Security & Permission Check ---
// Use our central Auth service for a clean, maintainable permission check.
if (!Auth::can('view_admin_tools')) { // A general permission for all admin tools
    Utils::redirect('index.php');
}

$page_title = 'Add New User';
$page_message = '';
$page_error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $userService = new UserService();
        $invitationData = $userService->createUserInvitation($_POST);
        
        // On success, send the notification email
        NotificationService::sendUserInvitationEmail($_POST['email'], $invitationData['token']);
        
        // Log the administrative action
        LoggingService::log(
            Auth::user('user_id'),
            $invitationData['user_id'],
            'user_created',
            'Admin created a new user invitation.',
            ['invited_email' => $_POST['email']]
        );

        $page_message = "New user invited successfully. An invitation has been sent to " . Utils::e($_POST['email']) . ".";

    } catch (Exception $e) {
        $page_error = $e->getMessage();
    }
}

// For the admin dropdown, fetch all active carriers and facilities
$allEntities = [];
if (Auth::hasRole('admin')) {
    $db = Database::getInstance();
    $carriers = $db->fetchAll("SELECT id, name FROM carriers WHERE is_active = 1 ORDER BY name ASC");
    $facilities = $db->fetchAll("SELECT id, name FROM facilities WHERE is_active = 1 ORDER BY name ASC");
    $allEntities = ['Carriers' => $carriers, 'Facilities' => $facilities];
}

require_once 'header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Add New User</h1>
        <a href="user_management.php" class="text-blue-600 hover:text-blue-800">&larr; Back to User Management</a>
    </div>

    <div class="bg-white shadow-md rounded-lg border border-gray-200">
        <div class="p-6">
            <?php if (!empty($page_message)): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                    <p class="font-bold">Success</p>
                    <p><?= Utils::e($page_message); ?></p>
                </div>
            <?php endif; ?>
            <?php if (!empty($page_error)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p class="font-bold">Error</p>
                    <p><?= Utils::e($page_error); ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" action="add_user.php" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="first_name" class="block text-sm font-medium text-gray-700">First Name</label>
                        <input type="text" id="first_name" name="first_name" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name</label>
                        <input type="text" id="last_name" name="last_name" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                        <input type="email" id="email" name="email" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="phone_number" class="block text-sm font-medium text-gray-700">Phone Number (Optional)</label>
                        <input type="tel" id="phone_number" name="phone_number" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                </div>

                <?php if (Auth::hasRole('admin')): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="entity_id" class="block text-sm font-medium text-gray-700">Assign to Entity</label>
                        <select id="entity_id" name="entity_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Select an Entity...</option>
                            <?php foreach ($allEntities as $groupName => $entities): ?>
                                <optgroup label="<?= Utils::e($groupName) ?>">
                                    <?php foreach ($entities as $entity): ?>
                                        <option value="<?= Utils::e($entity['id']) ?>"><?= Utils::e($entity['name']) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php endif; ?>

                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700">Access Role</label>
                    <select id="role" name="role" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <?php if (Auth::hasRole('superuser')): ?>
                            <option value="user">User</option>
                        <?php elseif (Auth::hasRole('admin')): ?>
                            <option value="user">User</option>
                            <option value="superuser">Superuser</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="flex justify-end pt-4">
                    <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Send Invitation
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>