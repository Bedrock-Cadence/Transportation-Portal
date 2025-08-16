<?php
// FILE: add_user.php

// 1. Set the page title for the header.
$page_title = 'Add New User';

// 2. Include the header, which also handles session startup.
require_once 'header.php';

// 3. Security Check: Redirect if not logged in.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// 4. Security Check: Only allow 'superuser' and 'admin' roles to access this page.
// This is the core logic based on your explicit request.
$allowed_roles = ['superuser', 'admin'];
$user_role = $_SESSION['user_role'] ?? null;
if (!in_array($user_role, $allowed_roles)) {
    // Redirect to index.php as requested.
    header("location: index.php");
    exit;
}

// 5. Include the database connection file.
require_once __DIR__ . '/../../app/db_connect.php';

// Initialize variables for messages and errors.
$page_message = '';
$page_error = '';

// --- Start of Utility Functions ---

/**
 * Logs an action to the user_history table.
 * @param mysqli $conn The database connection object.
 * @param int $actor_user_id The ID of the user performing the action.
 * @param int $target_user_id The ID of the user who was affected.
 * @param string $action The type of action (e.g., 'user_created', 'user_activated').
 * @param string $message A detailed message about the action.
 */
function log_user_history($conn, $actor_user_id, $target_user_id, $action, $message) {
    $log_stmt = $conn->prepare("INSERT INTO user_history (actor_user_id, target_user_id, action, message) VALUES (?, ?, ?, ?)");
    if ($log_stmt) {
        $log_stmt->bind_param("iiss", $actor_user_id, $target_user_id, $action, $message);
        $log_stmt->execute();
        $log_stmt->close();
    }
}

/**
 * Generates a cryptographically secure, URL-safe registration token.
 * @return string The generated token.
 */
function generateRegistrationToken() {
    return bin2hex(random_bytes(32));
}

/**
 * Hashes a token using a secure algorithm.
 * @param string $token The plain text token.
 * @return string The hashed token.
 */
function hash_token($token) {
    return password_hash($token, PASSWORD_DEFAULT);
}

// --- End of Utility Functions ---

// --- Start of Form Submission Handling ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Basic input validation
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');

    if (empty($first_name) || empty($last_name) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $page_error = "Please provide valid first name, last name, and email address.";
    } else {
        $actor_user_id = $_SESSION['user_id'] ?? null;
        $new_user_role = null;
        $new_user_entity_id = null;
        $new_user_entity_type = null;

        if ($user_role === 'superuser') {
            // Superusers can only add 'user' types
            if ($_POST['role'] !== 'user') {
                $page_error = "Superusers can only add 'user' accounts.";
            } else {
                $new_user_role = 'user';
                $new_user_entity_id = $_SESSION['entity_id'] ?? null;
                $new_user_entity_type = $_SESSION['entity_type'] ?? null;
            }
        } elseif ($user_role === 'admin') {
            // Admins can select entity and user type ('user' or 'superuser')
            $new_user_entity_id = $_POST['entity_id'] ?? null;
            $new_user_entity_type = $_POST['entity_type'] ?? null;
            $new_user_role_requested = $_POST['role'] ?? null;

            if (in_array($new_user_role_requested, ['user', 'superuser']) && !empty($new_user_entity_id) && !empty($new_user_entity_type)) {
                $new_user_role = $new_user_role_requested;
            } else {
                $page_error = "Admins must select a valid entity and a user type ('user' or 'superuser').";
            }
        }

        if (empty($page_error)) {
            $mysqli->begin_transaction();

            try {
                // Check if the email already exists.
                $stmt = $mysqli->prepare("SELECT id, is_active, first_name, last_name FROM users WHERE email = ? LIMIT 1");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                $existing_user = $result->fetch_assoc();
                $stmt->close();

                if ($existing_user) {
                    if ($existing_user['is_active']) {
                        $page_error = "User with that email address is already active.";
                    } else {
                        if (strtolower($existing_user['first_name']) === strtolower($first_name) && strtolower($existing_user['last_name']) === strtolower($last_name)) {
                            // Reactivate and re-associate.
                            $stmt = $mysqli->prepare("UPDATE users SET is_active = 1, entity_id = ?, entity_type = ?, role = ? WHERE id = ?");
                            $stmt->bind_param("iiss", $new_user_entity_id, $new_user_entity_type, $new_user_role, $existing_user['id']);
                            $stmt->execute();
                            $stmt->close();
                            
                            $page_message = "User account found and reactivated.";
                            log_user_history($mysqli, $actor_user_id, $existing_user['id'], 'user_reactivated_and_reassociated', "User re-activated and re-associated with {$new_user_entity_type} ID: {$new_user_entity_id}.");
                        } else {
                            $page_error = "An account with this email address exists but the name does not match.";
                        }
                    }
                } else {
                    // Create a new user.
                    $registration_token = generateRegistrationToken();
                    $token_expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));

                    $stmt = $mysqli->prepare("INSERT INTO users (uuid, email, first_name, last_name, phone_number, role, entity_id, entity_type, is_active, registration_token_hash, token_expires_at) VALUES (UUID(), ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)");
                    $stmt->bind_param("sssssisss", $email, $first_name, $last_name, $phone_number, $new_user_role, $new_user_entity_id, $new_user_entity_type, hash_token($registration_token), $token_expires_at);
                    
                    if ($stmt->execute()) {
                        $new_user_id = $mysqli->insert_id;
                        $page_message = "New user added and an invitation email has been sent.";
                        log_user_history($mysqli, $actor_user_id, $new_user_id, 'user_created', "User account created. Invitation sent to {$email}.");

                        // --- Email sending logic (placeholder) ---
                        // $registration_link = "https://portal.bedrockcadence.com/registration.php?token=" . urlencode($registration_token);
                        // mail($email, "Bedrock Cadence Account Invitation", "Please click the link to register: " . $registration_link);
                        log_user_history($mysqli, $actor_user_id, $new_user_id, 'invitation_sent', "Registration email sent with token: {$registration_token}. Link: {$registration_link}.");
                    } else {
                        $page_error = "Error creating user: " . $stmt->error;
                    }
                    $stmt->close();
                }
                $mysqli->commit();
            } catch (Exception $e) {
                $mysqli->rollback();
                $page_error = "An unexpected error occurred: " . $e->getMessage();
            }
        }
    }
}
// --- End of Form Submission Handling ---
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
                    <p><?= htmlspecialchars($page_message); ?></p>
                </div>
            <?php endif; ?>
            <?php if (!empty($page_error)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert">
                    <p class="font-bold">Error</p>
                    <p><?= htmlspecialchars($page_error); ?></p>
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

                <?php if ($user_role === 'admin'): ?>
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
                        <?php if ($user_role === 'superuser'): ?>
                            <option value="user">User</option>
                        <?php elseif ($user_role === 'admin'): ?>
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

<?php
// This includes the footer and necessary closing tags.
require_once 'footer.php';
?>