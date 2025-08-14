<?php
$page_title = 'Invite New User';
require_once 'header.php';
require_once __DIR__ . '/../../app/db_connect.php';

// --- Permission Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION['user_role'] !== 'bedrock_admin') {
    header("location: login.php");
    exit;
}

$invite_message = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $role = $_POST['role'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $invite_message = '<div class="alert alert-danger">Please enter a valid email address.</div>';
    } else {
        $token = bin2hex(random_bytes(32));
        $token_hash = password_hash($token, PASSWORD_DEFAULT);
        $expires_at = date("Y-m-d H:i:s", strtotime('+24 hours'));
        
        $sql = "INSERT INTO users (email, role, registration_token_hash, token_expires_at) VALUES (?, ?, ?, ?)";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("ssss", $email, $role, $token_hash, $expires_at);
            if ($stmt->execute()) {
                $registration_link = "https://portal.aagva.org/register.php?token=" . $token . "&email=" . urlencode($email);
                $subject = "You're invited to the Bedrock Cadence Portal!";
                $body = "Howdy,\n\nYou have been invited to join the Bedrock Cadence transportation portal.\n\nPlease click the following link to complete your registration. This link will expire in 24 hours.\n" . $registration_link . "\n\nThanks,\nThe Bedrock Cadence Team";
                $headers = "From: no-reply@bedrockcadence.com";

                if (mail($email, $subject, $body, $headers)) {
                    $invite_message = '<div class="alert alert-success">Invitation sent successfully to ' . htmlspecialchars($email) . '</div>';
                } else {
                    $invite_message = '<div class="alert alert-danger">Failed to send invitation email. Please check server configuration.</div>';
                }
            } else {
                if ($mysqli->errno == 1062) {
                     $invite_message = '<div class="alert alert-danger">An account or pending invitation for this email already exists.</div>';
                } else {
                     $invite_message = '<div class="alert alert-danger">Database error. Could not create invitation.</div>';
                }
            }
            $stmt->close();
        }
    }
}
?>

<h2 class="mb-4">Invite a New User</h2>
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <p>Enter the email address and role for the new user. They will receive an email with a link to complete their registration.</p>
                <?php echo $invite_message; ?>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="mt-3">
                    <div class="mb-3">
                        <label for="email" class="form-label">User's Email Address</label>
                        <input type="email" name="email" id="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="role" class="form-label">Assign Role</label>
                        <select name="role" id="role" class="form-select" required>
                            <option value="facility_user">Facility User</option>
                            <option value="facility_superuser">Facility Super User</option>
                            <option value="carrier_user">Carrier User</option>
                            <option value="carrier_superuser">Carrier Super User</option>
                            <option value="bedrock_admin">Bedrock Admin</option>
                        </select>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Send Invitation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'footer.php';
?>