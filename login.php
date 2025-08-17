<?php
// FILE: public_html/portal/login.php

// This init file should handle autoloading our classes and starting the session manager
require_once __DIR__ . '/../../app/init.php'; 

// If the user is already logged in, send them to the main page.
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    Utils::redirect('index.php');
}

$page_title = 'Login';
$login_error = '';
$email = ''; // To repopulate form field on failure

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email'] ?? '');
    try {
        $authService = new AuthService();
        $loginData = $authService->login($_POST);

        // LOGIN SUCCESS - Establish the session
        // Our SessionManager handles regeneration and security locks automatically
        $_SESSION["loggedin"] = true;
        $_SESSION["user_id"] = $loginData['user']['id'];
        $_SESSION["user_uuid"] = $loginData['user']['uuid'];
        $_SESSION["first_name"] = $loginData['user']['first_name'];
        $_SESSION["last_name"] = $loginData['user']['last_name'];
        $_SESSION["email"] = $loginData['user']['email'];
        $_SESSION["user_role"] = $loginData['user']['role'];
        $_SESSION["entity_id"] = $loginData['user']['entity_id'];
        $_SESSION["entity_type"] = $loginData['user']['entity_type'];
        $_SESSION["entity_name"] = $loginData['entity_name'];
        
        // The SessionManager needs to be called to lock the session to the new user's IP/User Agent
        SessionManager::setSessionLocks();

        Utils::redirect('index.php');

    } catch (Exception $e) {
        $login_error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= Utils::e($page_title) ?> - Bedrock Cadence</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
</head>
<body class="h-full">
    <div class="flex min-h-full flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <!-- You can place a logo here -->
            <h2 class="mt-6 text-center text-3xl font-bold tracking-tight text-gray-900">Sign in to your account</h2>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
            <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
                <form class="space-y-6" id="loginForm" action="login.php" method="POST">
                    
                    <?php if (!empty($login_error)): ?>
                        <div class="rounded-md bg-red-50 p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-red-800"><?= Utils::e($login_error) ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div>
                        <label for="email" class="block text-sm font-medium leading-6 text-gray-900">Email address</label>
                        <div class="mt-2">
                            <input id="email" name="email" type="email" autocomplete="email" required value="<?= Utils::e($email) ?>" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                        </div>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium leading-6 text-gray-900">Password</label>
                        <div class="mt-2">
                            <input id="password" name="password" type="password" autocomplete="current-password" required class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6">
                        </div>
                    </div>

                    <div class="cf-turnstile" data-sitekey="0x4AAAAAABsE3bLaSnTnuUzR" data-callback="onTurnstileSuccess"></div>

                    <div>
                        <button type="submit" id="submitBtn" disabled class="flex w-full justify-center rounded-md bg-indigo-600 py-2 px-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:bg-indigo-300 disabled:cursor-not-allowed">Sign in</button>
                    </div>
                </form>

                <div class="mt-6">
                    <div class="relative">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-300"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="bg-white px-2 text-gray-500">System Access Warning</span>
                        </div>
                    </div>
                    <p class="mt-4 text-center text-xs text-gray-500">
                        Access is restricted to authorized users. Data within this system is protected under HIPAA. Unauthorized use is punishable by law. Your use of this system is subject to our BAA and other contracts. All activity is tracked, logged, and may be audited by Bedrock Cadence and government entities.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
      function onTurnstileSuccess(token) {
        const submitButton = document.getElementById('submitBtn');
        if (submitButton) {
          submitButton.disabled = false;
        }
      }
    </script>
</body>
</html>