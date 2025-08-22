<?php
// FILE: public_html/portal/add_user.php

require_once __DIR__ . '/../../app/init.php';

// Security: Only Admins and Superusers can access this page.
if (!Auth::can('view_admin_tools')) {
    Utils::redirect('index.php');
}

$page_title = 'Add New User';
$page_error = '';
$page_success = '';
$userService = new UserService();
$db = Database::getInstance();

// Pre-load entities for the admin dropdown
$all_entities = [];
if (Auth::hasRole('admin')) {
    $carriers = $db->fetchAll("SELECT id, name FROM carriers ORDER BY name ASC");
    $facilities = $db->fetchAll("SELECT id, name FROM facilities ORDER BY name ASC");
    $all_entities = array_merge($carriers, $facilities);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        // The UserService now handles all the logic for creating the invitation
        $invitationData = $userService->createUserInvitation($_POST);

        // Send the registration email
        NotificationService::sendRegistrationEmail(
            $_POST['email'], 
            $invitationData['token']
        );
        
        // Log this important action
        LoggingService::log(
            Auth::user('user_id'), 
            $invitationData['user_id'], 
            'user_created', 
            'User account created. Invitation sent to ' . $_POST['email'] . '.'
        );

        $page_success = "User invitation sent successfully to " . Utils::e($_POST['email']) . ".";

    } catch (Exception $e) {
        $page_error = $e->getMessage();
        LoggingService::log(
            Auth::user('user_id'), 
            null, 
            'user_creation_failed', 
            $e->getMessage()
        );
    }
}
?>

<?php require_once 'header.php'; ?>

<h1 class="text-3xl font-bold text-gray-800 mb-6">Add a New User</h1>

<div class="max-w-2xl mx-auto bg-white shadow-md rounded-lg border border-gray-200">
    <div class="p-6">
        <form action="add_user.php" method="post" class="space-y-6">

            <?php if ($page_success): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4" role="alert">
                    <p class="font-bold">Success</p>
                    <p><?= $page_success ?></p>
                </div>
            <?php endif; ?>
            <?php if ($page_error): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert">
                    <p class="font-bold">Error</p>
                    <p><?= $page_error ?></p>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="first_name" class="block text-sm font-medium text-gray-700">First Name</label>
                    <input type="text" name="first_name" id="first_name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                </div>
                <div>
                    <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name</label>
                    <input type="text" name="last_name" id="last_name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                </div>
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                <input type="email" name="email" id="email" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                 <p class="mt-2 text-sm text-gray-500">Note: Email addresses from public domains (e.g., Gmail, Yahoo) are not permitted.</p>
            </div>
            <div>
                <label for="phone_number" class="block text-sm font-medium text-gray-700">Phone Number (Optional)</label>
                <input type="tel" name="phone_number" id="phone_number" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            </div>

            <?php if (Auth::hasRole('admin')): ?>
                <div>
                    <label for="entity_id" class="block text-sm font-medium text-gray-700">Assign to Entity</label>
                    <select name="entity_id" id="entity_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                        <option value="">-- Select Company --</option>
                        <?php foreach ($all_entities as $entity): ?>
                            <option value="<?= Utils::e($entity['id']); ?>"><?= Utils::e($entity['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700">User Role</label>
                    <select name="role" id="role" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                        <option value="user">User</option>
                        <option value="superuser">Super User</option>
                    </select>
                </div>
            <?php else: // Superuser View ?>
                 <input type="hidden" name="role" value="user">
            <?php endif; ?>

             <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mt-4 text-sm" role="alert">
                <p>Upon submission, an email will be sent to the user with a secure link to set their password. This link is valid for 24 hours and can only be used once.</p>
            </div>

            <div class="flex justify-end pt-5">
                <button type="submit" class="w-full md:w-auto inline-flex justify-center py-2 px-4 border shadow-sm text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                    Send Invitation
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'footer.php'; ?>