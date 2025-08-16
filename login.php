<?php
// FILE: login.php

// 1. Start the session using our centralized configuration.
require_once ('init.php');
require_once __DIR__ . '/../../app/session_config.php';

// 2. If the user is already logged in, send them straight to the dashboard.
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("location: index.php");
    exit;
}

// 3. Include the database connection and initialize variables.
require_once __DIR__ . '/../../app/db_connect.php';
$login_error = '';
$email = ''; // Keep email in the form field on a failed attempt

/**
 * Logs a login attempt to the database.
 *
 * @param mysqli $mysqli The database connection object.
 * @param string $email The email address used in the login attempt.
 * @param string $ip_address The IP address of the user.
 * @param string $attempt_result The result of the login attempt ('success', 'fail').
 * @param string $failure_reason A brief description of why the login failed.
 */
function log_login_attempt($mysqli, $email, $ip_address, $attempt_result, $failure_reason = '') {
    $sql = "INSERT INTO login_history (email, ip_address, attempt_result, failure_reason) VALUES (?, ?, ?, ?)";
    if ($stmt = $mysqli->prepare($sql)) {
        // Bind parameters as strings.
        $stmt->bind_param("ssss", $email, $ip_address, $attempt_result, $failure_reason);
        $stmt->execute();
        $stmt->close();
    }
}


// 4. Handle the form submission.
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- PRE-CHECKS: Turnstile and Input Validation ---
    $turnstile_response = $_POST['cf-turnstile-response'] ?? null;
    $secretKey = CLOUD_FLARE_SECRET; // Keep this safe!
    $ip_address = $_SERVER['REMOTE_ADDR'];
    // Grab email early for logging, provide a default if not present.
    $email_for_logging = trim($_POST["email"] ?? 'N/A');


    $postData = [
        'secret'   => $secretKey,
        'response' => $turnstile_response,
        'remoteip' => $ip_address,
    ];

    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // IMPORTANT: Cloudflare expects the data as a URL-encoded string.
    // http_build_query() correctly formats the data.
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    
    $response = curl_exec($ch);

    // Add cURL error handling for better debugging.
    if (curl_errno($ch)) {
        error_log('cURL error when verifying Turnstile: ' . curl_error($ch));
        $login_error = "A security service error occurred. Please try again later.";
        log_login_attempt($mysqli, $email_for_logging, $ip_address, 'fail', 'cURL error to Cloudflare');
        curl_close($ch);
    } else {
        curl_close($ch);
        $result = json_decode($response, true);

        // --- ENHANCED DEBUGGING: Log the specific error codes from Cloudflare ---
        if (!isset($result['success']) || !$result['success']) {
            $login_error = "Security check failed. Please try again.";
            // Capture and log the specific error codes from Cloudflare for easier debugging.
            $error_codes = isset($result['error-codes']) ? implode(', ', $result['error-codes']) : 'No error codes provided';
            $failure_reason = 'Cloudflare Turnstile failed: ' . $error_codes;
            log_login_attempt($mysqli, $email_for_logging, $ip_address, 'fail', $failure_reason);

        } elseif (empty(trim($_POST["email"])) || empty($_POST["password"])) {
            $login_error = "Email and password are required.";
            log_login_attempt($mysqli, $email_for_logging, $ip_address, 'fail', 'Empty email or password');
        } else {
            $email = trim($_POST["email"]);
            $password = $_POST["password"];
            $user = null;

            // --- STEP 1 & 2: Fetch User and Entity Details ---
            // This single query gets all necessary user and entity data in one go.
            $sql = "SELECT 
                        u.id, u.uuid, u.email, u.password_hash, u.first_name, u.last_name, 
                        u.role, u.is_active AS user_is_active, u.entity_id, u.entity_type,
                        COALESCE(c.name, f.name) AS entity_name,
                        COALESCE(c.is_active, f.is_active, 1) AS entity_is_active -- Default to 1 (active) for admins/superusers with no entity
                    FROM users u
                    LEFT JOIN carriers c ON u.entity_id = c.id AND u.entity_type = 'carrier'
                    LEFT JOIN facilities f ON u.entity_id = f.id AND u.entity_type = 'facility'
                    WHERE u.email = ?
                    LIMIT 1";

            if ($stmt = $mysqli->prepare($sql)) {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                }
                $stmt->close();
            }

            // --- VALIDATION SEQUENCE ---

            // Check 1: Does the user exist?
            if (!$user) {
                $login_error = "The email or password you entered is incorrect.";
                log_login_attempt($mysqli, $email, $ip_address, 'fail', 'User not found');
            
            // Check 2: Is the user's own account active? (Applies to all roles)
            } elseif (!$user['user_is_active']) {
                $login_error = "This account is inactive or pending activation.";
                log_login_attempt($mysqli, $email, $ip_address, 'fail', 'User account inactive');

            // Check 3: Is the user's associated entity active? (Bypassed for 'superuser')
            } elseif ($user['role'] === 'user' && !$user['entity_is_active']) {
                $login_error = "Your company's account is currently inactive. Please contact support.";
                log_login_attempt($mysqli, $email, $ip_address, 'fail', 'Entity inactive');

            // Check 4: Does the password match? (This is checked last for security)
            } elseif (!password_verify($password, $user['password_hash'])) {
                $login_error = "The email or password you entered is incorrect.";
                log_login_attempt($mysqli, $email, $ip_address, 'fail', 'Incorrect password');
            
            // --- FINAL STEP: LOGIN SUCCESS ---
            } else {
                // If we got here, all checks passed.
                
                // Step 5: Log the successful attempt.
                log_login_attempt($mysqli, $email, $ip_address, 'success');

                // Step 6: Set session variables and redirect.
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
                $_SESSION["entity_name"] = $user['entity_name'];

                header("location: index.php");
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <!-- Include the Turnstile script from Cloudflare -->
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <style>
        /* Basic styles for the page */
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f4f7f6;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        /* Container for the login form */
        .login-container {
            background-color: #ffffff;
            padding: 2rem 2.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            box-sizing: border-box;
        }

        /* Form title */
        h2 {
            text-align: center;
            color: #333;
            margin-top: 0;
            margin-bottom: 1.5rem;
        }

        /* Styling for form groups (label + input) */
        .form-group {
            margin-bottom: 1.25rem;
        }

        /* Styling for labels */
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #555;
            font-weight: 600;
        }

        /* Styling for text and password inputs */
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }

        input[type="email"]:focus,
        input[type="password"]:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
            outline: none;
        }
        
        /* Center the Turnstile widget */
        .cf-turnstile {
            margin: 1.5rem auto;
            display: table;
        }

        /* Styling for the submit button */
        button[type="submit"] {
            width: 100%;
            padding: 0.85rem;
            border: none;
            border-radius: 4px;
            background-color: #007bff;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s ease-in-out;
        }

        button[type="submit"]:hover:not(:disabled) {
            background-color: #0056b3;
        }
        
        /* Style for the button when it is disabled */
        button[type="submit"]:disabled {
            background-color: #a0c7ff;
            cursor: not-allowed;
        }

        /* Styling for the HIPAA warning */
        .hipaa-warning {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
            font-size: 0.8rem;
            color: #6c757d;
            text-align: center;
            line-height: 1.4;
        }
    </style>
</head>
<body>

    <div class="login-container">
        <h2>Portal Login</h2>
        <form id="loginForm" method="POST" action="login.php">
            
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>

            <!-- 
              This is the Turnstile widget. 
              The 'data-callback' tells Turnstile which JavaScript 
              function to run when it's successful.
            -->
            <div class="cf-turnstile" 
                 data-sitekey="0x4AAAAAABsE3bLaSnTnuUzR" 
                 data-callback="onTurnstileSuccess">
            </div>

            <!-- 
              The login button starts off as 'disabled'. 
              Our JavaScript function will enable it.
            -->
            <button type="submit" id="submitBtn" disabled>Log In</button>

        </form>

        <div class="hipaa-warning">
            <strong>System Access Warning:</strong> Access is restricted to authorized users. Data within this system is protected under HIPAA. Unauthorized use is punishable by law. Your use of this system is subject to our BAA and other contracts. All activity is tracked, logged, and may be audited by Bedrock Cadence and government entities.
        </div>
    </div>

    <!-- This script should be placed at the bottom of your page -->
    <script>
      // This is the function that the Turnstile widget will call on success.
      function onTurnstileSuccess(token) {
        // Find the submit button on the page.
        const submitButton = document.getElementById('submitBtn');
        
        // If the button exists, remove the 'disabled' attribute.
        if (submitButton) {
          submitButton.disabled = false;
        }
      }
    </script>

</body>
</html>