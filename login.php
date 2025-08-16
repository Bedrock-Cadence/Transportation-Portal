<?php
// FILE: login.php

// Start output buffering to prevent "headers already sent" errors.
ob_start();

// 1. Start the session using our centralized configuration.
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

// --- Utility function for logging login attempts ---
function log_login_attempt($conn, $email, $result, $ip) {
    $stmt = $conn->prepare("INSERT INTO login_history (email, ip_address, attempt_result) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $email, $ip, $result);
    $stmt->execute();
    $stmt->close();
}

// --- Start of Form Submission Handling ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST["email"] ?? ''); // Always capture email first
    $password = $_POST["password"] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'];

    $turnstile_response = $_POST['cf-turnstile-response'] ?? null;
    $secretKey = CLOUD_FLARE_SECRET; // Keep this safe!

    $postData = [
        'secret'   => $secretKey,
        'response' => $turnstile_response,
        'remoteip' => $ip,
    ];

    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // Corrected: Use http_build_query to properly encode POST data for the API.
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));

    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);

    if (isset($result['success']) && $result['success']) {
        // The user is likely human. Proceed with your login or form processing.
        
        // Basic validation: Ensure fields are not empty.
        if (empty($email) || empty($password)) {
            $login_error = "Email and password are required.";
            log_login_attempt($mysqli, $email, 'failure', $ip);
        } else {
            $user = null;
            $lockout_time = 15 * 60; // 15 minutes in seconds

            // Check for user account login attempts
            $stmt = $mysqli->prepare("SELECT COUNT(*) AS attempts FROM login_history WHERE email = ? AND attempt_result = 'failure' AND created_at > (NOW() - INTERVAL 15 MINUTE)");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result_attempts = $stmt->get_result();
            $user_attempts = $result_attempts->fetch_assoc();
            $stmt->close();

            if ($user_attempts['attempts'] >= 3) {
                $login_error = "Too many failed login attempts for this account. Please try again in 15 minutes.";
                // We'll log the failure later, after all checks.
            }

            // Check for IP address login attempts
            $stmt = $mysqli->prepare("SELECT COUNT(DISTINCT email) AS unique_users FROM login_history WHERE ip_address = ? AND created_at > (NOW() - INTERVAL 15 MINUTE)");
            $stmt->bind_param("s", $ip);
            $stmt->execute();
            $result_ip_attempts = $stmt->get_result();
            $ip_attempts = $result_ip_attempts->fetch_assoc();
            $stmt->close();

            if ($ip_attempts['unique_users'] >= 3) {
                $login_error = "Multiple login attempts from this IP address have been detected. Please contact Bedrock Cadence for assistance.";
            }

            // If no fatal login error was found yet, we proceed with credential validation.
            if (empty($login_error)) {
                // This single, powerful query gets everything we need in one shot.
                $sql = "SELECT 
                            u.id, u.uuid, u.email, u.password_hash, u.first_name, u.last_name, 
                            u.role, u.is_active, u.entity_id, u.entity_type,
                            c.verification_status,
                            COALESCE(c.name, f.name) AS entity_name
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

                // --- VALIDATION CHECKS ---
                // We log the failure here after the final login attempt validation.
                
                // Check 1: Was a user found and does the password match?
                if (!$user || !password_verify($password, $user['password_hash'])) {
                    $login_error = "The email or password you entered is incorrect.";
                
                // Check 2: Is the user's account active? (Only runs if password was correct)
                } elseif (!$user['is_active']) {
                    $login_error = "This account is inactive or pending activation.";
                    
                // Check 3: If it's a carrier, are they verified?
                } elseif ($user['entity_type'] === 'carrier' && $user['verification_status'] !== 'verified' && !in_array($user['role'], ['carrier_superuser', 'bedrock_admin'])) {
                    $login_error = "Your company's account is pending verification by our staff.";
                }

                // --- FINAL STEP: LOGIN ---
                if (empty($login_error) && $user) {
                    // Regenerate session ID for security
                    session_regenerate_id(true);

                    // Store user data in the session
                    $_SESSION["loggedin"] = true;
                    $_SESSION["user_id"] = $user['id'];
                    $_SESSION["user_uuid"] = $user['uuid'];
                    $_SESSION["first_name"] = $user['first_name'];
                    $_SESSION["last_name"] = $user['last_name'];
                    $_SESSION["email"] = $user['email'];
                    $_SESSION["user_role"] = $user['role'];
                    $_SESSION["entity_id"] = $user['entity_id'];
                    $_SESSION["entity_type"] = $user['entity_type'];
                    $_SESSION["entity_name"] = $user['entity_name'] ?: 'Bedrock Cadence';

                    // Log the successful login attempt
                    log_login_attempt($mysqli, $email, 'success', $ip);

                    // Redirect to the dashboard
                    header("location: index.php");
                    exit;
                }
            }

        }

    } else {
        // Verification failed. The user is likely a bot.
        $login_error = "Security check failed. Please try again.";
    }

    // Log a failure if there's any error at the end of the POST handler.
    if (!empty($login_error)) {
        log_login_attempt($mysqli, $email, 'failure', $ip);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Bedrock Cadence</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" xintegrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>
<body class="bg-light">

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-5 col-md-7 mt-5">
                <div class="card shadow-lg">
                    <div class="card-body p-4 p-md-5">
                        <h2 class="text-center mb-4">Portal Login</h2>

                        <?php 
                        if(!empty($login_error)){
                            echo '<div class="alert alert-danger">' . htmlspecialchars($login_error) . '</div>';
                        }
                        if (isset($_GET['status']) && $_GET['status'] == 'activation_success') {
                            echo '<div class="alert alert-success">Account activated! You can now log in.</div>';
                        }
                        ?>

                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" name="email" id="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" name="password" id="password" class="form-control" required>
                            </div>
                            <div class="cf-turnstile" data-sitekey="0x4AAAAAABsE3bLaSnTnuUzR"></div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">Log In</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" xintegrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>