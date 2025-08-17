<?php
// FILE: public/case_management.php

require_once 'init.php';

// --- Permission Check for Bedrock Admin ---
if (!isset($_SESSION["loggedin"]) || $_SESSION['user_role'] !== 'admin') {
    redirect('login.php');
}

$page_title = 'Case Management';
$db = Database::getInstance();
$open_cases = [];

try {
    $sql = "SELECT c.id, c.case_type, c.created_at, t.uuid AS trip_uuid
            FROM cases c
            JOIN trips t ON c.trip_id = t.id
            WHERE c.status = 'open'
            ORDER BY c.created_at ASC";
    $open_cases = $db->query($sql)->fetchAll();
} catch (Exception $e) {
    error_log("Case Management Page Error: " . $e->getMessage());
    $page_error = "A database error occurred while fetching open cases.";
}

require_once 'header.php';
?>

<h2 class="mb-4">Open Case Queue</h2>

<?php if (isset($page_error)): ?>
    <div class="alert alert-danger" role="alert"><?= e($page_error); ?></div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Case ID</th>
                        <th>Trip ID</th>
                        <th>Case Type</th>
                        <th>Date Opened</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($open_cases)): ?>
                        <?php foreach ($open_cases as $case): ?>
                            <tr>
                                <td><?= e($case['id']); ?></td>
                                <td><code><?= e(substr($case['trip_uuid'], 0, 8)); ?>...</code></td>
                                <td><?= e($case['case_type']); ?></td>
                                <td><?= e(date('M j, Y g:i A', strtotime($case['created_at']))); ?></td>
                                <td>
                                    <a href="view_case.php?id=<?= e($case['id']); ?>" class="btn btn-primary btn-sm">Review Case</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center p-4">No open cases at this time. Great job! ✅</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>