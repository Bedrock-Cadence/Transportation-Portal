<?php
// FILE: public/post_rejection_options.php

require_once 'init.php';

// --- Permission Check ---
if (!isset($_SESSION["loggedin"]) || !in_array($_SESSION['user_role'], ['user', 'superuser', 'admin'])) {
    redirect('login.php');
}
if (empty($_GET['uuid'])) {
    redirect('dashboard.php');
}

$page_title = 'Post-Rejection Options';
$db = Database::getInstance();
$uuid = $_GET['uuid'];
$facility_id = $_SESSION['entity_id'] ?? null;

try {
    // --- Verify this facility owns the trip OR user is admin ---
    $stmt_verify = $db->query("SELECT id, facility_id FROM trips WHERE uuid = ?", [$uuid]);
    $trip = $stmt_verify->fetch();

    if (!$trip || ($_SESSION['user_role'] !== 'admin' && $trip['facility_id'] != $facility_id)) {
        redirect("dashboard.php?error=unauthorized");
    }

} catch (Exception $e) {
    error_log("Post Rejection Page Error: " . $e->getMessage());
    die("A database error occurred.");
}

// --- Handle the form submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && $_SESSION['user_role'] !== 'admin') {
    $decision = $_POST['decision'];
    
    try {
        if ($decision == 'rebroadcast') {
            $new_bidding_closes_at = date('Y-m-d H:i:s', strtotime('+2 hours'));
            $db->query("UPDATE trips SET status = 'bidding', carrier_id = NULL, awarded_eta = NULL, bidding_closes_at = ? WHERE uuid = ?", [$new_bidding_closes_at, $uuid]);
            log_user_action('trip_rebroadcast', "Trip {$uuid} was re-broadcast after rejection.");
        } elseif ($decision == 'cancel') {
            $db->query("UPDATE trips SET status = 'cancelled' WHERE uuid = ?", [$uuid]);
            log_user_action('trip_cancelled', "Trip {$uuid} was cancelled after rejection.");
        }
        redirect("dashboard.php?status=trip_updated");

    } catch (Exception $e) {
        error_log("Post Rejection Action Error: " . $e->getMessage());
        // Display an error to the user
    }
}

require_once 'header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm text-center">
            <div class="card-header"><h2 class="h3">ETA Change Rejected</h2></div>
            <div class="card-body p-4">
                <p class="lead">You have rejected the carrier's new ETA for Trip <code><?= e(substr($uuid, 0, 8)); ?>...</code></p>
                <p>What would you like to do now?</p>

                <?php if ($_SESSION['user_role'] !== 'admin'): ?>
                    <form action="post_rejection_options.php?uuid=<?= e($uuid); ?>" method="post" class="mt-4">
                        <div class="d-grid gap-3 d-sm-flex justify-content-sm-center">
                            <button type="submit" name="decision" value="rebroadcast" class="btn btn-warning btn-lg px-4 gap-3">
                                Re-Broadcast Trip
                            </button>
                            <button type="submit" name="decision" value="cancel" class="btn btn-danger btn-lg px-4">
                                Cancel Trip
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert alert-warning mt-4"><b>Admin Read-Only View:</b> Actions are disabled.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>