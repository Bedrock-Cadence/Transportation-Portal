<?php
// FILE: login.php

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

/**
 * Logs a login attempt to the database.
 *
 * @param mysqli $mysqli The database connection object.
 * @param int|null $user_id The ID of the user attempting to log in.
 * @param string $ip_address The IP address of the user.
 * @param string $attempt_result The result of the login attempt ('success', 'fail').
 * @param string $failure_reason A brief description of why the login failed.
 */
function log_login_attempt($mysqli, $user_id, $ip_address, $attempt_result, $failure_reason = '') {
    $sql = "INSERT INTO login_history (user_id, ip_address, attempt_result, failure_reason) VALUES (?, ?, ?, ?)";
    if ($stmt = $mysqli->prepare($sql)) {
        // For failed attempts where user_id is unknown, we'll insert NULL.
        // The database column for user_id must allow NULL values.
        $stmt->bind_param("isss", $user_id, $ip_address, $attempt_result, $failure_reason);
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

    $postData = [
        'secret'   => $secretKey,
        'response' => $turnstile_response,
        'remoteip' => $ip_address,
    ];

    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    $response = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($response, true);

    if (!isset($result['success']) || !$result['success']) {
        $login_error = "Security check failed. Please try again.";
        // Note: We can't log with user_id yet, but we can log the attempt.
        log_login_attempt($mysqli, null, $ip_address, 'fail', 'Cloudflare Turnstile failed');
    } elseif (empty(trim($_POST["email"])) || empty($_POST["password"])) {
        $login_error = "Email and password are required.";
        // We can't log with user_id, but we can log the attempt with the provided email for context if desired.
        log_login_attempt($mysqli, null, $ip_address, 'fail', 'Empty email or password');
    } else {
        $email = trim($_POST["email"]);
        $password = $_POST["password"];
        $user = null;
        $user_id_for_logging = null;

        // --- STEP 1 & 2: Fetch User and Entity Details ---
        // This single query gets all necessary user and entity data in one go.
        // It now also fetches the entity's active status.
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
                $user_id_for_logging = $user['id']; // Get user ID for logging purposes
            }
            $stmt->close();
        }

        // --- VALIDATION SEQUENCE ---

        // Check 1: Does the user exist?
        if (!$user) {
            $login_error = "The email or password you entered is incorrect.";
            log_login_attempt($mysqli, null, $ip_address, 'fail', 'User not found');
        
        // Check 2: Is the user's own account active? (Applies to all roles)
        } elseif (!$user['user_is_active']) {
            $login_error = "This account is inactive or pending activation.";
            log_login_attempt($mysqli, $user_id_for_logging, $ip_address, 'fail', 'User account inactive');

        // Check 3: Is the user's associated entity active? (Bypassed for 'superuser')
        } elseif ($user['role'] === 'user' && !$user['entity_is_active']) {
            $login_error = "Your company's account is currently inactive. Please contact support.";
            log_login_attempt($mysqli, $user_id_for_logging, $ip_address, 'fail', 'Entity inactive');

        // Check 4: Does the password match? (This is checked last for security)
        } elseif (!password_verify($password, $user['password_hash'])) {
            $login_error = "The email or password you entered is incorrect.";
            log_login_attempt($mysqli, $user_id_for_logging, $ip_address, 'fail', 'Incorrect password');
        
        // --- FINAL STEP: LOGIN SUCCESS ---
        } else {
            // If we got here, all checks passed.
            
            // Step 5: Log the successful attempt.
            log_login_attempt($mysqli, $user_id_for_logging, $ip_address, 'success');

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Bedrock Cadence</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>