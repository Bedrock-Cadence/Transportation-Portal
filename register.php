<?php
// FILE: public_html/portal/register.php

require_once __DIR__ . '/../../app/init.php';

$page_title = 'Complete Your Registration';
$page_error = '';
$user_to_register = null;
$token = $_GET['token'] ?? '';
$userService = new UserService();

// --- Handle POST Request First ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST['token'] ?? '';
    $userId = $_POST['user_id'] ?? null;
    $password = $_POST['password'] ?? '';
    
    try {
        if (empty($token) || empty($userId) || empty($password)) {
            throw new Exception("The registration form is invalid. Please refresh and try again.");
        }

        $activatedUser = $userService->activateAccount((int)$userId, $password, $token);
        
        // Use our central SessionManager to log the new user in
        SessionManager::establish($activatedUser);

        Utils::redirect("index.php?status=welcome");

    } catch (Exception $e) {
        $page_error = $e->getMessage();
        // Re-fetch user data to display the form again on error
        $user_to_register = $userService->findUserByValidToken($token);
    }
} else {
    // --- Handle GET Request: Validate token on page load ---
    if (empty($token)) {
        $page_error = 'Invalid registration link. Please use the link provided in your invitation email.';
    } else {
        $user_to_register = $userService->findUserByValidToken($token);
        if (!$user_to_register) {
            $page_error = 'This registration link is invalid or has expired. Please contact your admin for a new one.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= Utils::e($page_title); ?> - Bedrock Cadence</title>
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
                    <p><?= Utils::e($page_error); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($user_to_register): ?>
                <form method="POST" action="register.php" class="space-y-6">
                    <input type="hidden" name="token" value="<?= Utils::e($token); ?>">
                    <input type="hidden" name="user_id" value="<?= Utils::e($user_to_register['id']); ?>">
                    <p class="text-center text-gray-600 mb-6">Welcome, <?= Utils::e($user_to_register['first_name']) ?>! Please create a secure password to activate your account.</p>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email Address</label>
                        <input type="email" value="<?= Utils::e($user_to_register['email']); ?>" readonly class="mt-1 block w-full rounded-md bg-gray-100 border-gray-300 shadow-sm">
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">Create a Password</label>
                        <input type="password" id="password" name="password" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <p class="mt-2 text-xs text-gray-500">Must be at least 8 characters and contain at least three of the following: uppercase, lowercase, numbers, or symbols.</p>
                    </div>

                    <div class="flex justify-center pt-4">
                        <button type="submit" class="w-full sm:w-auto inline-flex justify-center py-2 px-6 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">Activate Account</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>