<?php
// FILE: register.php

// 1. Include the database connection file. The $mysqli object is now available for use.
require_once __DIR__ . '/../../app/db_connect.php';

// Initialize variables for messages and errors.
$page_message = '';
$page_error = '';
$user = null;
$show_form = false;

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
    // Note: The actor_user_id is the same as the target_user_id for self-registration.
    $log_stmt = $conn->prepare("INSERT INTO user_history (actor_user_id, target_user_id, action, message) VALUES (?, ?, ?, ?)");
    $log_stmt->bind_param("iiss", $actor_user_id, $target_user_id, $action, $message);
    $log_stmt->execute();
    $log_stmt->close();
}

/**
 * Checks if a password meets medium strength requirements.
 * At least 8 characters long and contains at least 3 of the following:
 * - Uppercase letters
 * - Lowercase letters
 * - Numbers
 * - Special characters
 * @param string $password The password to check.
 * @return bool True if the password is medium strength, false otherwise.
 */
function isMediumStrengthPassword($password) {
    if (strlen($password) < 8) {
        return false;
    }
    $strength = 0;
    if (preg_match('/[A-Z]/', $password)) $strength++;
    if (preg_match('/[a-z]/', $password)) $strength++;
    if (preg_match('/[0-9]/', $password)) $strength++;
    if (preg_match('/[!@#$%^&*()\-_=+{};:,<.>]/', $password)) $strength++;

    return $strength >= 3;
}

// --- End of Utility Functions ---


// --- Handle GET request to validate the UUID and token ---
if (isset($_GET['uuid'])) {
    $uuid = $_GET['uuid'];

    // Query the database to find the user by their UUID.
    $stmt = $mysqli->prepare("SELECT id, first_name, last_name, email, phone_number, entity_id, is_active, token_expires_at FROM users WHERE uuid = ? LIMIT 1");
    $stmt->bind_param("s", $uuid);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    // Check if the user exists and the invitation is still valid.
    if ($user && !$user['is_active'] && strtotime($user['token_expires_at']) > time()) {
        $show_form = true;
    } else {
        $page_error = 'This registration link is invalid or has expired. Please contact your admin for a new one.';
    }
} else {
    $page_error = 'Invalid registration link. Please use the link provided in your invitation email.';
}

// --- Handle POST request for form submission ---
if ($show_form && $_SERVER["REQUEST_METHOD"] === "POST") {
    $uuid = trim($_POST['uuid']);
    $entered_entity_id = trim($_POST['entity_id']);
    $password = $_POST['password'];
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $phone_number = trim($_POST['phone_number']);

    // Re-fetch the user data to ensure it's still valid.
    $stmt = $mysqli->prepare("SELECT id, first_name, last_name, phone_number, entity_id, is_active, token_expires_at FROM users WHERE uuid = ? LIMIT 1");
    $stmt->bind_param("s", $uuid);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user || $user['is_active'] || strtotime($user['token_expires_at']) < time()) {
        $page_error = 'This registration link has become invalid. Please refresh the page.';
    } else if ($entered_entity_id != $user['entity_id']) {
        $page_error = 'The entity ID you entered does not match the invitation. Please try again.';
    } else if (!isMediumStrengthPassword($password)) {
        $page_error = 'Password is too weak. It must be at least 8 characters and contain at least three of the following: uppercase letters, lowercase letters, numbers, and symbols.';
    } else {
        // All checks passed. Activate the account and update the user data.
        $password_hash = password_hash($password, PASSWORD_ARGON2ID);
        
        $stmt_update = $mysqli->prepare("UPDATE users SET password_hash = ?, first_name = ?, last_name = ?, phone_number = ?, is_active = 1, registration_token_hash = NULL, token_expires_at = NULL WHERE id = ?");
        $stmt_update->bind_param("ssssi", $password_hash, $first_name, $last_name, $phone_number, $user['id']);
        
        if ($stmt_update->execute()) {
            $stmt_update->close();
            
            // Log the successful registration. The actor and target are the same user.
            log_user_history($mysqli, $user['id'], $user['id'], 'user_registered', 'User successfully completed registration and activated their account.');

            // Redirect to the index page.
            header("location: index.php");
            exit;
        } else {
            $page_error = 'There was an error activating your account. Please try again.';
        }
    }
}
$mysqli->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Complete Your Registration - Bedrock Cadence</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="w-full max-w-xl mx-auto p-6 bg-white shadow-lg rounded-lg border border-gray-200">
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
            <h2 class="text-2xl font-semibold text-gray-800 text-center">Complete Your Registration</h2>
        </div>

        <div class="p-6">
            <?php if (!empty($page_message)): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                    <p class="font-bold">Success</p>
                    <p><?= htmlspecialchars($page_message); ?></p>
                </div>
            <?php endif; ?>
            <?php if (!empty($page_error)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                    <p class="font-bold">Error</p>
                    <p><?= htmlspecialchars($page_error); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($show_form): ?>
                <form method="POST" action="register.php?uuid=<?= htmlspecialchars($_GET['uuid']); ?>" class="space-y-6">
                    <input type="hidden" name="uuid" value="<?= htmlspecialchars($_GET['uuid']); ?>">

                    <div>
                        <label for="entity_id" class="block text-sm font-medium text-gray-700">Entity ID</label>
                        <input type="text" id="entity_id" name="entity_id" required placeholder="Enter the ID from your invitation email" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="first_name" class="block text-sm font-medium text-gray-700">First Name</label>
                            <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($user['first_name']); ?>" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name</label>
                            <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($user['last_name']); ?>" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']); ?>" readonly class="mt-1 block w-full rounded-md bg-gray-100 border-gray-300 shadow-sm">
                    </div>

                    <div>
                        <label for="phone_number" class="block text-sm font-medium text-gray-700">Phone Number</label>
                        <input type="tel" id="phone_number" name="phone_number" value="<?= htmlspecialchars($user['phone_number']); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">Create a Password</label>
                        <input type="password" id="password" name="password" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <p class="mt-2 text-xs text-gray-500">Must be at least 8 characters and contain at least three of the following: uppercase, lowercase, numbers, or symbols.</p>
                    </div>

                    <div class="flex justify-center">
                        <button type="submit" class="w-full sm:w-auto inline-flex justify-center py-2 px-6 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Activate Account
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>