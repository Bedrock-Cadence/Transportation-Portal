<?php
// FILE: public/admin/approve_user.php

require_once __DIR__ . '/../../app/init.php';

Auth::requireRole('admin');

$registrationService = new RegistrationService();
$userId = $_GET['user_id'] ?? null;

if ($userId) {
    try {
        $registrationService->approveUser($userId);
        $successMessage = "User has been approved.";
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

// You would typically have a list of users to approve here
// For simplicity, this example just processes the approval
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Approve User</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="container">
        <h2>Approve User</h2>
        <?php if (isset($successMessage)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
        <?php endif; ?>
        <?php if (isset($errorMessage)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>
    </div>
</body>
</html>