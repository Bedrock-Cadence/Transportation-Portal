<?php
// FILE: public_html/portal/login.php

require_once __DIR__ . '/../../app/init.php';

// --- Helper function to detect if the request is from our app ---
function isApiRequest() {
    // Checks if the request body is JSON, which our app sends.
    if (!empty($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
        return true;
    }
    return false;
}

// --- Main Logic ---
if (Auth::isLoggedIn() && !isApiRequest()) {
    Utils::redirect('index.php');
}

$login_error = '';
$email = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    // For API requests, data comes in as JSON, not a form post.
    $postData = $_POST;
    if (isApiRequest()) {
        $json = file_get_contents('php://input');
        $postData = json_decode($json, true) ?? [];
    }

    $email = trim($postData['email'] ?? '');
    try {
        $authService = new AuthService();
        // We pass the potentially JSON-decoded data to the login service
        $loginData = $authService->login($postData); 
        
        SessionManager::establish($loginData['user'], $loginData['entity_name']);

        // *** THIS IS THE NEW PART FOR THE APP ***
        if (isApiRequest()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true, 
                'data' => [
                    'first_name' => $loginData['user']['first_name'],
                    'entity_name' => $loginData['entity_name']
                ]
            ]);
            exit; // Stop the script here for API calls
        }
        // *** END OF NEW PART ***

        // This part remains for the web browser login
        Utils::redirect('index.php');

    } catch (Exception $e) {
        $login_error = $e->getMessage();

        // *** THIS IS THE NEW PART FOR THE APP ***
        if (isApiRequest()) {
            header('Content-Type: application/json');
            http_response_code(401); // Use 'Unauthorized' status for login failures
            echo json_encode(['success' => false, 'error' => $login_error]);
            exit; // Stop the script here for API calls
        }
        // *** END OF NEW PART ***
    }
}

// The rest of this file is the HTML for the web page. 
// The 'exit' commands above ensure this part is never sent to the app.
$cspNonce = bin2hex(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$cspNonce}' https://challenges.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com;");
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50"><head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Bedrock Cadence</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script nonce="<?= $cspNonce ?>" src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
</head>
<body class="h-full">
    <div class="flex min-h-full flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <h2 class="mt-6 text-center text-3xl font-bold tracking-tight text-gray-900">Sign in to your account</h2>
        </div>
        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
            <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
                <form class="space-y-6" id="loginForm" action="login.php" method="POST">
                    <?php if (!empty($login_error)): ?>
                        <div class="rounded-md bg-red-50 p-4"><div class="flex"><div class="flex-shrink-0"><svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"></path></svg></div><div class="ml-3"><p class="text-sm font-medium text-red-800"><?= htmlspecialchars($login_error) ?></p></div></div></div>
                    <?php endif; ?>
                    <div><label for="email" class="block text-sm font-medium leading-6 text-gray-900">Email address</label><div class="mt-2"><input id="email" name="email" type="email" autocomplete="email" required value="<?= htmlspecialchars($email) ?>" class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"></div></div>
                    <div><label for="password" class="block text-sm font-medium leading-6 text-gray-900">Password</label><div class="mt-2"><input id="password" name="password" type="password" autocomplete="current-password" required class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"></div></div>
                    <div class="cf-turnstile" data-sitekey="0x4AAAAAABsE3bLaSnTnuUzR" data-callback="onTurnstileSuccess"></div>
                    <div><button type="submit" id="submitBtn" disabled class="flex w-full justify-center rounded-md bg-indigo-600 py-2 px-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:bg-indigo-300 disabled:cursor-not-allowed">Sign in</button></div>
                </form>
            </div>
        </div>
    </div>
    <script nonce="<?= $cspNonce ?>">
      function onTurnstileSuccess(token) {
        const submitButton = document.getElementById('submitBtn');
        if (submitButton) {
          submitButton.disabled = false;
        }
      }
    </script>
</body>
</html>