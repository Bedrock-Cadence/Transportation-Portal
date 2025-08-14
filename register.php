<?php
// --- Begin PHP Processing Block ---
require_once __DIR__ . '/../../app/db_connect.php';

$error_message = '';
$token_is_valid = false;
$user_email = '';

// 1. Check for token and email in the URL
if (isset($_GET['token']) && isset($_GET['email'])) {
    $token = $_GET['token'];
    $email = $_GET['email'];
    $user_email = htmlspecialchars($email); // For displaying in the form

    // 2. Find the pending user by email
    $sql = "SELECT id, registration_token_hash, token_expires_at FROM users WHERE email = ? AND is_active = 0 LIMIT 1";
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 1) {
            $stmt->bind_result($user_id, $token_hash, $expires_at);
            $stmt->fetch();

            $current_time = date("Y-m-d H:i:s");

            // 3. Verify the token is correct and not expired
            if (password_verify($token, $token_hash) && $current_time < $expires_at) {
                $token_is_valid = true;
            } else {
                $error_message = 'This registration link is invalid or has expired. Please request a new one.';
            }
        } else {
            $error_message = 'No pending invitation found for this email address.';
        }
        $stmt->close();
    }
} else {
    $error_message = 'Invalid registration link. Please use the link provided in your invitation email.';
}

// 4. Handle form submission if the token was valid
if ($token_is_valid && $_SERVER["REQUEST_METHOD"] == "POST") {
    $first_name = htmlspecialchars(trim($_POST['first_name']));
    $last_name = htmlspecialchars(trim($_POST['last_name']));
    $password = $_POST['password'];

    if (empty($first_name) || empty($last_name) || empty($password)) {
        $error_message = 'All fields are required, partner.';
    } else {
        // 5. Hash the new password
        $password_hash = password_hash($password, PASSWORD_ARGON2ID);

        // 6. UPDATE the user: set password, activate account, and nullify the token
        $sql_update = "UPDATE users SET password_hash = ?, first_name = ?, last_name = ?, is_active = 1, registration_token_hash = NULL, token_expires_at = NULL WHERE id = ?";
        
        if ($stmt_update = $mysqli->prepare($sql_update)) {
            $stmt_update->bind_param("sssi", $password_hash, $first_name, $last_name, $user_id);

            if ($stmt_update->execute()) {
                // 7. Success! Redirect to login page.
                header("location: login.php?status=activation_success");
                exit;
            } else {
                $error_message = 'There was an error activating your account. Please try again.';
            }
            $stmt_update->close();
        }
    }
}
$mysqli->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Complete Registration</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: sans-serif; padding: 2em; }
        form { max-width: 400px; margin: 0 auto; }
        input { width: 100%; padding: 8px; margin-bottom: 10px; box-sizing: border-box; }
        input[readonly] { background-color: #eee; }
        button { padding: 10px 15px; background-color: #007bff; color: white; border: none; cursor: pointer; }
        .error { color: red; text-align: center; border: 1px solid red; padding: 1em; }
    </style>
</head>
<body>

    <?php if ($token_is_valid): ?>
        <form action="<?php echo htmlspecialchars($_SERVER["REQUEST_URI"]); ?>" method="post">
            <h2>Complete Your Registration</h2>
            <p>Welcome! Set your name and password to activate your account.</p>

            <?php if(!empty($error_message)){ echo '<p class="error">' . $error_message . '</p>'; } ?>

            <div>
                <label for="email">Email Address (Invited)</label>
                <input type="email" name="email" id="email" value="<?php echo $user_email; ?>" readonly>
            </div>
            <div>
                <label for="first_name">First Name</label>
                <input type="text" name="first_name" id="first_name" required>
            </div>
            <div>
                <label for="last_name">Last Name</label>
                <input type="text" name="last_name" id="last_name" required>
            </div>
            <div>
                <label for="password">Choose a Password</label>
                <input type="password" name="password" id="password" required>
            </div>
            <div>
                <button type="submit">Activate Account</button>
            </div>
        </form>
    <?php else: ?>
        <div class="error">
            <h2>Link Invalid</h2>
            <p><?php echo $error_message; ?></p>
        </div>
    <?php endif; ?>

</body>
</html>