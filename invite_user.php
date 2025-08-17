<?php
// FILE: public/invite_user.php

require_once 'init.php';

// --- Permission Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION['user_role'] !== 'admin') { // Changed from 'bedrock_admin' for consistency
    redirect('login.php');
}

$page_title = 'Invite New User';
$db = Database::getInstance();
$invite_message = '';
$message_type = ''; // 'success' or 'danger'

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
    $role = $_POST['role'] ?? '';

    try {
        if (!$email) {
            throw new Exception("Please enter a valid email address.");
        }
        if (empty($role)) {
            throw new Exception("Please assign a role.");
        }

        $db->pdo()->beginTransaction();

        $stmt = $db->query("SELECT id FROM users WHERE email = ? LIMIT 1", [$email]);
        if ($stmt->fetch()) {
            throw new Exception("An account or pending invitation for this email already exists.");
        }

        $token = bin2hex(random_bytes(32));
        $token_hash = password_hash($token, PASSWORD_DEFAULT);
        $expires_at = date("Y-m-d H:i:s", strtotime('+24 hours'));
        
        $sql = "INSERT INTO users (email, role, registration_token_hash, token_expires_at) VALUES (?, ?, ?, ?)";
        $db->query($sql, [$email, $role, $token_hash, $expires_at]);
        
        // --- Email Sending Logic ---
        // In a real application, use a robust library like PHPMailer or Symfony Mailer
        $registration_link = "https://portal.aagva.org/register.php?token=" . $token; // Email is not needed in the URL
        $subject = "You're invited to the Bedrock Cadence Portal!";
        $body = "Howdy,\n\nYou have been invited to join the Bedrock Cadence transportation portal.\n\nPlease click the following link to complete your registration. This link will expire in 24 hours.\n" . $registration_link . "\n\nThanks,\nThe Bedrock Cadence Team";
        $headers = "From: no-reply@bedrockcadence.com";

        // The mail() function is notoriously unreliable. This is a placeholder.
        if (!mail($email, $subject, $body, $headers)) {
            // Even if mail fails, we don't roll back the transaction. The user exists and the link can be resent.
            error_log("Failed to send invitation email to {$email}.");
        }
        
        $db->pdo()->commit();
        $message_type = 'success';
        $invite_message = 'Invitation sent successfully to ' . e($email);

    } catch (Exception $e) {
        if ($db->pdo()->inTransaction()) $db->pdo()->rollBack();
        $message_type = 'danger';
        $invite_message = $e->getMessage();
    }
}

require_once 'header.php';
?>

<h2 class="mb-4">Invite a New User</h2>
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <p>Enter the email address and role for the new user. They will receive an email with a link to complete their registration.</p>
                <?php if (!empty($invite_message)): ?>
                    <div class="alert alert-<?= e($message_type); ?>"><?= e($invite_message); ?></div>
                <?php endif; ?>
                <form action="invite_user.php" method="post" class="mt-3">
                    <div class="mb-3">
                        <label for="email" class="form-label">User's Email Address</label>
                        <input type="email" name="email" id="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="role" class="form-label">Assign Role</label>
                        <select name="role" id="role" class="form-select" required>
                            <option value="user">Facility User</option>
                            <option value="superuser">Facility Super User</option>
                            <option value="user">Carrier User</option>
                            <option value="superuser">Carrier Super User</option>
                            <option value="admin">Bedrock Admin</option>
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

<?php require_once 'footer.php'; ?>