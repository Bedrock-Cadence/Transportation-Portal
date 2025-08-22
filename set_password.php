<?php
// FILE: public/set_password.php

require_once '../app/init.php';

$registrationService = new RegistrationService();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_POST['user_id'];
    $password = $_POST['password'];
    $passwordConfirm = $_POST['password_confirm'];
    $token = $_POST['token'];

    if ($password !== $passwordConfirm) {
        $errorMessage = "Passwords do not match.";
    } else {
        try {
            $registrationService->setPasswordAndAwaitApproval($userId, $password, $token);
            $successMessage = "Your password has been set. Your account is now pending approval by a super admin.";
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Set Password</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h2>Set Password</h2>
        <?php if (isset($successMessage)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
        <?php endif; ?>
        <?php if (isset($errorMessage)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>
    </div>
</body>
</html>