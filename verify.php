<?php
// FILE: public/verify.php

require_once __DIR__ . '/../../app/init.php';

$userService = new UserService();
$token = $_GET['token'] ?? '';

try {
    $user = $userService->findUserByValidToken($token);
    if (!$user) {
        throw new Exception("Invalid or expired verification link.");
    }
} catch (Exception $e) {
    $errorMessage = $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Your Account</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h2>Set Your Password</h2>
        <?php if (isset($errorMessage)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div>
        <?php else: ?>
            <form action="set_password.php" method="post">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <input type="hidden" name="user_id" value="<?= htmlspecialchars($user['id']) ?>">
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password" required>
                </div>
                <div class="form-group">
                    <label for="password_confirm">Confirm Password</label>
                    <input type="password" name="password_confirm" id="password_confirm" required>
                </div>
                <button type="submit">Set Password</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>