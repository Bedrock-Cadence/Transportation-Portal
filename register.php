<?php
// FILE: public_html/portal/register.php

require_once __DIR__ . '/../../app/init.php';

$page_title = 'Complete Registration';
$userService = new UserService();
$token = $_GET['token'] ?? '';
$user = null;
$page_error = '';

if (empty($token)) {
    Utils::redirect('login.php?error=invalid_token');
}

// Find the user by the provided token. This also checks for token expiration.
$user = $userService->findUserByValidToken($token);

if (!$user) {
    Utils::redirect('login.php?error=invalid_or_expired_token');
}

// Handle the form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    if ($password !== $passwordConfirm) {
        $page_error = "The passwords you entered do not match.";
    } else {
        try {
            // The service handles password validation and account activation
            $activatedUser = $userService->activateAccount($user['id'], $password, $token);
            
            // Log the user in immediately
            SessionManager::establish($activatedUser, $user['entity_name'] ?? '');

            Utils::redirect('index.php?status=registration_complete');

        } catch (Exception $e) {
            $page_error = $e->getMessage();
        }
    }
}

require_once 'header.php';
?>

<div class="max-w-md mx-auto mt-10">
    <div class="bg-white shadow-md rounded-lg p-8 border border-gray-200">
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-6">Welcome, <?= Utils::e($user['first_name']) ?>!</h2>
        <p class="text-center text-gray-600 mb-6">Please set a password to activate your account.</p>

        <form action="register.php?token=<?= Utils::e($token) ?>" method="POST" class="space-y-6">
            <?php if ($page_error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-md" role="alert">
                    <?= Utils::e($page_error) ?>
                </div>
            <?php endif; ?>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">New Password</label>
                <input type="password" name="password" id="password" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                 <p class="mt-2 text-xs text-gray-500">Must be at least 8 characters and contain 3 of the following: uppercase, lowercase, numbers, or symbols.</p>
            </div>

            <div>
                <label for="password_confirm" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                <input type="password" name="password_confirm" id="password_confirm" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            </div>

            <div>
                <button type="submit" class="w-full flex justify-center py-2 px-4 border rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                    Set Password and Log In
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'footer.php'; ?>