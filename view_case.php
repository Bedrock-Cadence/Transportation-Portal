<?php
// FILE: public/view_case.php

require_once 'init.php';

// --- Permission Check & Input Validation ---
if (!isset($_SESSION["loggedin"]) || $_SESSION['user_role'] !== 'admin') { redirect('login.php'); }
if (empty($_GET['id'])) { redirect('case_management.php'); }

$page_title = 'Review Case';
$db = Database::getInstance();
$case_id = (int)$_GET['id'];
$case_details = null;
$page_error = '';

// --- Handle Case Resolution ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['resolve_case'])) {
    $resolution_notes = trim($_POST['resolution_notes']);
    
    try {
        $db->pdo()->beginTransaction();
        $sql_update = "UPDATE cases SET status = 'resolved', resolution_notes = ?, resolved_at = NOW(), resolved_by_user_id = ? WHERE id = ?";
        $params = [$resolution_notes, $_SESSION['user_id'], $case_id];
        $db->query($sql_update, $params);
        $db->pdo()->commit();

        log_user_action('case_resolved', "Admin resolved case ID: {$case_id}.");
        redirect("case_management.php?status=resolved");

    } catch (Exception $e) {
        if ($db->pdo()->inTransaction()) $db->pdo()->rollBack();
        $page_error = "Failed to resolve the case. Please try again.";
        error_log("Case Resolution Error: " . $e->getMessage());
    }
}

// --- Fetch Case Details ---
try {
    $sql = "SELECT c.*, t.uuid AS trip_uuid, u.email AS opener_email
            FROM cases c
            JOIN trips t ON c.trip_id = t.id
            JOIN users u ON c.opened_by_user_id = u.id
            WHERE c.id = ?";
    $case_details = $db->query($sql, [$case_id])->fetch();

    if (!$case_details) {
        redirect("case_management.php?error=notfound");
    }
} catch (Exception $e) {
    $page_error = "Could not retrieve case details.";
    error_log("View Case Error: " . $e->getMessage());
}

require_once 'header.php';
?>

<h2 class="mb-4">Reviewing Case #<?= e($case_details['id'] ?? ''); ?></h2>
        
<?php if (!empty($page_error)): ?>
    <div class="alert alert-danger" role="alert"><?= e($page_error); ?></div>
<?php endif; ?>

<?php if ($case_details): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header"><h5 class="mb-0">Case Details</h5></div>
        <div class="card-body">
            <dl class="row">
                <dt class="col-sm-3">Trip ID:</dt>
                <dd class="col-sm-9"><code><?= e($case_details['trip_uuid']); ?></code></dd>
                
                <dt class="col-sm-3">Case Type:</dt>
                <dd class="col-sm-9"><?= e($case_details['case_type']); ?></dd>
                
                <dt class="col-sm-3">Opened By:</dt>
                <dd class="col-sm-9"><?= e($case_details['opener_email']); ?></dd>
                
                <dt class="col-sm-3">Date Opened:</dt>
                <dd class="col-sm-9"><?= e(date('M j, Y g:i A', strtotime($case_details['created_at']))); ?></dd>
                
                <dt class="col-sm-3">Initial Notes:</dt>
                <dd class="col-sm-9"><?= nl2br(e($case_details['initial_notes'])); ?></dd>
            </dl>
        </div>
    </div>

    <?php if ($case_details['status'] == 'open'): ?>
        <div class="card shadow-sm">
            <div class="card-header"><h5 class="mb-0">Resolve Case</h5></div>
            <div class="card-body">
                <form action="view_case.php?id=<?= e($case_id); ?>" method="post">
                    <div class="mb-3">
                        <label for="resolution_notes" class="form-label">Resolution Notes</label>
                        <textarea name="resolution_notes" id="resolution_notes" class="form-control" rows="4" required></textarea>
                    </div>
                    <div class="d-grid">
                        <button type="submit" name="resolve_case" value="1" class="btn btn-primary btn-lg">Mark as Resolved</button>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="card shadow-sm">
             <div class="card-header"><h5 class="mb-0">Case Resolved</h5></div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-3">Status:</dt>
                    <dd class="col-sm-9"><span class="badge bg-success fs-6"><?= e(ucfirst($case_details['status'])); ?></span></dd>
                    <dt class="col-sm-3">Resolution Notes:</dt>
                    <dd class="col-sm-9"><?= nl2br(e($case_details['resolution_notes'])); ?></dd>
                </dl>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php require_once 'footer.php'; ?>