<?php
// FILE: public/register.php

require_once 'init.php'; // Use the main init file for DB, session, etc.

$page_title = 'Complete Your Registration';
$db = Database::getInstance();
$page_error = '';
$user_to_register = null;
$token = $_GET['token'] ?? '';
$is_valid_token = false;

// --- Password Strength Helper ---
function isMediumStrengthPassword($password) {
    if (strlen($password) < 8) return false;
    $strength = 0;
    if (preg_match('/[A-Z]/', $password)) $strength++;
    if (preg_match('/[a-z]/', $password)) $strength++;
    if (preg_match('/[0-9]/', $password)) $strength++;
    if (preg_match('/[!@#$%^&*()\-_=+{};:,<.>]/', $password)) $strength++;
    return $strength >= 3;
}

// --- GET Request: Validate the token when the page loads ---
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    if (empty($token)) {
        $page_error = 'Invalid registration link. Please use the link provided in your invitation email.';
    } else {
        // We must check all inactive users because we don't know who the token belongs to until we verify it.
        $inactive_users = $db->query("SELECT * FROM users WHERE is_active = 0 AND token_expires_at > NOW()")->fetchAll();
        foreach ($inactive_users as $user) {
            // Use password_verify to securely check the provided token against the stored hash.
            if (password_verify($token, $user['registration_token_hash'])) {
                $user_to_register = $user;
                $is_valid_token = true;
                break;
            }
        }
        if (!$is_valid_token) {
            $page_error = 'This registration link is invalid or has expired. Please contact your admin for a new one.';
        }
    }
}

// --- POST Request: Handle the form submission ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST['token'] ?? '';
    $user_id = $_POST['user_id'] ?? null;
    $password = $_POST['password'] ?? '';
    $is_valid_token = false; // Re-validate on POST

    try {
        if (empty($token) || empty($password) || empty($user_id)) {
            throw new Exception("The registration form is invalid. Please refresh and try again.");
        }

        $user = $db->query("SELECT * FROM users WHERE id = ? AND is_active = 0 LIMIT 1", [$user_id])->fetch();

        if ($user && password_verify($token, $user['registration_token_hash']) && strtotime($user['token_expires_at']) > time()) {
            $is_valid_token = true;
            $user_to_register = $user; // Needed to show the form again on error
        } else {
             throw new Exception("This registration link has become invalid. Please contact your administrator.");
        }

        if (!isMediumStrengthPassword($password)) {
            throw new Exception('Password must be at least 8 characters and contain 3 of the following: uppercase, lowercase, numbers, or symbols.');
        }

        $db->pdo()->beginTransaction();
        $password_hash = password_hash($password, PASSWORD_ARGON2ID);
        $sql = "UPDATE users SET password_hash = ?, is_active = 1, registration_token_hash = NULL, token_expires_at = NULL, updated_at = NOW() WHERE id = ?";
        $db->query($sql, [$password_hash, $user['id']]);
        
        log_user_action('user_registered', 'User successfully completed registration and activated their account.');
        $db->pdo()->commit();

        // Automatically log the new user in
        session_regenerate_id(true);
        $_SESSION["loggedin"] = true;
        $_SESSION["user_id"] = $user['id'];
        $_SESSION["user_uuid"] = $user['uuid'];
        $_SESSION["first_name"] = $user['first_name'];
        $_SESSION["last_name"] = $user['last_name'];
        $_SESSION["email"] = $user['email'];
        $_SESSION["user_role"] = $user['role'];
        $_SESSION["entity_id"] = $user['entity_id'];
        $_SESSION["entity_type"] = $user['entity_type'];
        
        redirect("index.php?status=welcome");

    } catch (Exception $e) {
        if ($db->pdo()->inTransaction()) $db->pdo()->rollBack();
        $page_error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= e($page_title); ?> - Bedrock Cadence</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="w-full max-w-xl mx-auto p-6 bg-white shadow-lg rounded-lg border border-gray-200">
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
            <h2 class="text-2xl font-semibold text-gray-800 text-center">Complete Your Registration</h2>
        </div>
        <div class="p-6">
            <?php if (!empty($page_error)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                    <p class="font-bold">Error</p>
                    <p><?= e($page_error); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($is_valid_token): ?>
                <form method="POST" action="register.php" class="space-y-6">
                    <input type="hidden" name="token" value="<?= e($token); ?>">
                    <input type="hidden" name="user_id" value="<?= e($user_to_register['id']); ?>">
                    <p class="text-center text-gray-600 mb-6">Welcome! Please confirm your details and create a secure password to activate your account.</p>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email Address</label>
                        <input type="email" value="<?= e($user_to_register['email']); ?>" readonly class="mt-1 block w-full rounded-md bg-gray-100 border-gray-300 shadow-sm">
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">Create a Password</label>
                        <input type="password" id="password" name="password" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <p class="mt-2 text-xs text-gray-500">Must be at least 8 characters and contain at least three of the following: uppercase, lowercase, numbers, or symbols.</p>
                    </div>

                    <div class="flex justify-center">
                        <button type="submit" class="w-full sm:w-auto inline-flex justify-center py-2 px-6 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">Activate Account</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>