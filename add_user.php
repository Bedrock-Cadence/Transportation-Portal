<?php
// FILE: public/add_user.php

require_once 'init.php';

// --- Security & Permission Check ---
if (!isset($_SESSION["loggedin"]) || !in_array($_SESSION['user_role'], ['superuser', 'admin'])) {
    redirect('index.php');
}

$page_title = 'Add New User';
$page_message = '';
$page_error = '';
$db = Database::getInstance();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $new_user_role = $_POST['role'] ?? '';

    try {
        if (empty($first_name) || empty($last_name) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please provide a valid first name, last name, and email address.");
        }

        $user_role = $_SESSION['user_role'];
        if ($user_role === 'superuser') {
            $entity_id = $_SESSION['entity_id'];
            $entity_type = $_SESSION['entity_type'];
            if ($new_user_role !== 'user') {
                 throw new Exception("Superusers can only add 'user' accounts.");
            }
        } else { // admin
            $entity_id = $_POST['entity_id'] ?? null;
            $entity_type = $_POST['entity_type'] ?? null;
            if (!in_array($new_user_role, ['user', 'superuser'])) {
                throw new Exception("Admins must select a valid user role.");
            }
        }

        if (empty($entity_id) || empty($entity_type)) {
            throw new Exception("An entity must be assigned to the new user.");
        }

        $db->pdo()->beginTransaction();

        $stmt = $db->query("SELECT id FROM users WHERE email = ? LIMIT 1", [$email]);
        if ($stmt->fetch()) {
            throw new Exception("A user with this email address already exists.");
        }

        $registration_token = bin2hex(random_bytes(32));
        $token_hash = password_hash($registration_token, PASSWORD_DEFAULT);
        $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $sql = "INSERT INTO users (uuid, email, first_name, last_name, phone_number, role, entity_id, entity_type, is_active, registration_token_hash, token_expires_at)
                VALUES (UUID(), ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)";
        $params = [$email, $first_name, $last_name, $phone_number, $new_user_role, $entity_id, $entity_type, $token_hash, $expires_at];

        $db->query($sql, $params);
        $new_user_id = $db->pdo()->lastInsertId();

        // --- Email Sending Logic Placeholder ---
        // In production, you would use a robust mailer library (e.g., PHPMailer or Symfony Mailer)
        // $registration_link = "https://portal.bedrockcadence.com/register.php?token=" . urlencode($registration_token);
        // mail($email, "Bedrock Cadence Account Invitation", "Please click the link to register: " . $registration_link);

        log_user_action('user_created', "Created new user invitation for {$email} (User ID: {$new_user_id}).");

        $db->pdo()->commit();
        $page_message = "New user invited successfully. An invitation has been sent to " . e($email) . ".";

    } catch (Exception $e) {
        if ($db->pdo()->inTransaction()) {
            $db->pdo()->rollBack();
        }
        $page_error = $e->getMessage();
    }
}

require_once 'header.php';
?>

<div id="add-user-container" class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Add New User</h1>
    </div>

    <div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200">
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-800">New User Information</h2>
        </div>

        <div class="p-6 space-y-8">
            <?php if (!empty($page_message)): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4" role="alert">
                    <p class="font-bold">Success</p>
                    <p><?= e($page_message); ?></p>
                </div>
            <?php endif; ?>
            <?php if (!empty($page_error)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert">
                    <p class="font-bold">Error</p>
                    <p><?= e($page_error); ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" action="add_user.php" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="first_name" class="block text-sm font-medium text-gray-700">First Name</label>
                        <input type="text" id="first_name" name="first_name" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name</label>
                        <input type="text" id="last_name" name="last_name" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                        <input type="email" id="email" name="email" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="phone_number" class="block text-sm font-medium text-gray-700">Phone Number</label>
                        <input type="tel" id="phone_number" name="phone_number" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>

                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="entity_type" class="block text-sm font-medium text-gray-700">Entity Type</label>
                        <select id="entity_type" name="entity_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                             <option value="carrier">Carrier</option>
                             <option value="facility">Facility</option>
                        </select>
                    </div>
                    <div>
                        <label for="entity_id" class="block text-sm font-medium text-gray-700">Entity ID</label>
                        <input type="number" id="entity_id" name="entity_id" required placeholder="Enter entity ID" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>
                <?php endif; ?>

                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700">Access Role</label>
                    <select id="role" name="role" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <?php if ($_SESSION['user_role'] === 'superuser'): ?>
                            <option value="user">User</option>
                        <?php elseif ($_SESSION['user_role'] === 'admin'): ?>
                            <option value="superuser">Superuser</option>
                            <option value="user">User</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Add User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>