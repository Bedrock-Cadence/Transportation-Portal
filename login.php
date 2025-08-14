<?php
// 1. Start the session. This MUST be the very first thing.
session_start();

// If the user is already logged in, redirect them to the dashboard
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("location: dashboard.php");
    exit;
}

require_once __DIR__ . '/../../app/db_connect.php';
$login_error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- Start of Cleaner Validation Flow ---

    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $user = null;

    if (empty($email) || empty($password)) {
        $login_error = "Email and password are required.";
    } else {
        // Step 1: Fetch the user from the database
        $sql = "SELECT id, uuid, email, password_hash, role, is_active, entity_id FROM users WHERE email = ?";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("s", $email);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($result->num_rows == 1) {
                    $user = $result->fetch_assoc();
                } else {
                    $login_error = "The email or password you entered is incorrect.";
                }
            }
            $stmt->close();
        }
    }

    // Step 2: If a user was found, run all checks in order
    if ($user) {
        // Check 2a: Is the account active?
        if (!$user['is_active']) {
            $login_error = "This account is inactive or pending activation.";
        }
        // Check 2b: Does the password match?
        elseif (!password_verify($password, $user['password_hash'])) {
            $login_error = "The email or password you entered is incorrect.";
        }
        // Check 2c: If it's a carrier, are they verified?
        elseif (strpos($user['role'], 'carrier') !== false) {
            $sql_check_carrier = "SELECT is_verified FROM carriers WHERE id = ?";
            if ($stmt_check_carrier = $mysqli->prepare($sql_check_carrier)) {
                $stmt_check_carrier->bind_param("i", $user['entity_id']);
                $stmt_check_carrier->execute();
                $stmt_check_carrier->bind_result($is_verified);
                $stmt_check_carrier->fetch();
                $stmt_check_carrier->close();

                if (!$is_verified) {
                    $login_error = "Your company's account is pending verification by our staff.";
                }
            }
        }
    }

    // Step 3: If after all checks there are NO errors and we have a user, log them in.
    if (empty($login_error) && $user) {
        session_regenerate_id(true); // Security: Prevent session fixation

        // Store data in session variables
        $_SESSION["loggedin"] = true;
        $_SESSION["user_id"] = $user['id'];
        $_SESSION["user_uuid"] = $user['uuid'];
        $_SESSION["user_role"] = $user['role'];
        $_SESSION["entity_id"] = $user['entity_id'];

        // Redirect user to dashboard page
        header("location: dashboard.php");
        exit;
    }
    
    $mysqli->close();
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
                                <input type="email" name="email" id="email" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" name="password" id="password" class="form-control" required>
                            </div>
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