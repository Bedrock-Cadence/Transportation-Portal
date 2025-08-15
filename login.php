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

// 4. Handle the form submission.
if ($_SERVER["REQUEST_METHOD"] == "POST") {

$turnstile_response = $_POST['cf-turnstile-response'] ?? null;
    $CF_secretKey = CLOUD_FLARE_SECRET; // Keep this safe!

    $ip = $_SERVER['REMOTE_ADDR'];

    $postData = [
        'secret'   => $secretKey,
        'response' => $turnstile_response,
        'remoteip' => $ip,
    ];

    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);

    if (isset($result['success']) && $result['success']) {
        // The user is likely human. Proceed with your login or form processing.
        // For example: check_user_credentials($_POST['username'], $_POST['password']);

    } else {
        // Verification failed. The user is likely a bot.
        // Log the attempt and show an error message.
        $login_err = "Security check failed. Please try again.";
        // Optional: log the error details from $result['error-codes']
    }

    // Basic validation: Ensure fields are not empty.
    if (empty(trim($_POST["email"])) || empty($_POST["password"])) {
        $login_error = "Email and password are required.";
    } else {
        $email = trim($_POST["email"]);
        $password = $_POST["password"];
        $user = null;

        // This single, powerful query gets everything we need in one shot.
        // It joins the users table with carriers and facilities to get the
        // entity name and verification status without a second database call.
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
        // If any of these fail, we set an error and the script stops before the login section.

        // Check 1: Was a user found and does the password match?
        // We use a generic error for both cases to prevent email enumeration attacks.
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $login_error = "The email or password you entered is incorrect.";
        
        // Check 2: Is the user's account active? (Only runs if password was correct)
        } elseif (!$user['is_active']) {
            $login_error = "This account is inactive or pending activation.";
        
        // Check 3: If it's a carrier, are they verified?
        } elseif ($user['entity_type'] === 'carrier' && $user['verification_status'] !== 'verified') {
            $login_error = "Your company's account is pending verification by our staff.";
        }

        // --- FINAL STEP: LOGIN ---
        // If we got here with no errors, the user is valid and good to go.
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
            $_SESSION["entity_name"] = $user['entity_name'] ?: 'Bedrock Cadence'; // Use entity name or default

            // Redirect to the dashboard
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