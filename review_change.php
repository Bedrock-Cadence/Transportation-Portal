<?php
// FILE: public/review_change.php

require_once 'init.php';

// --- Permission Check & Data Fetch ---
if (!isset($_SESSION["loggedin"])) { redirect('login.php'); }
if (empty($_GET['id'])) { redirect('dashboard.php'); }

$page_title = 'Review ETA Change Request';
$db = Database::getInstance();
$change_request_id = (int)$_GET['id'];
$request_details = null;
$is_authorized = false;

try {
    $sql = "SELECT tcr.*, t.awarded_eta, t.uuid AS trip_uuid, t.facility_id
            FROM trip_change_requests tcr
            JOIN trips t ON tcr.trip_id = t.id
            WHERE tcr.id = ? AND tcr.status = 'pending'";
    $request_details = $db->query($sql, [$change_request_id])->fetch();

    if (!$request_details) {
        redirect("dashboard.php?error=notfound");
    }

    // Authorization: User must be an admin or belong to the facility that owns the trip
    if ($_SESSION['user_role'] === 'admin' || (in_array($_SESSION['user_role'], ['user', 'superuser']) && $_SESSION['entity_id'] == $request_details['facility_id'])) {
        $is_authorized = true;
    }
    if (!$is_authorized) {
        redirect("dashboard.php?error=unauthorized");
    }

} catch (Exception $e) {
    error_log("Review Change Page Error: " . $e->getMessage());
    // In a real app, you would show a user-friendly error page
    die("A database error occurred.");
}


// --- Handle Form Submission (Accept/Reject) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && in_array($_SESSION['user_role'], ['user', 'superuser'])) {
    $decision = $_POST['decision'];
    $user_id = $_SESSION['user_id'];
    $trip_id = $request_details['trip_id'];
    $requesting_user_id = $request_details['requested_by_user_id'];
    $trip_uuid = $request_details['trip_uuid'];

    try {
        $db->pdo()->beginTransaction();

        if ($decision == 'accept') {
            $proposed_data = json_decode($request_details['proposed_data'], true);
            $new_eta = $proposed_data['new_eta'];

            // 1. Update the trip itself
            $db->query("UPDATE trips SET awarded_eta = ? WHERE id = ?", [$new_eta, $trip_id]);

            // 2. Update the change request status
            $db->query("UPDATE trip_change_requests SET status = 'accepted', resolved_at = NOW(), resolved_by_user_id = ? WHERE id = ?", [$user_id, $change_request_id]);
            
            // 3. Notify the original requestor
            $message = "Your ETA change for Trip ".substr($trip_uuid, 0, 8)." was APPROVED.";
            $link = "awarded_trip_details.php?uuid=" . $trip_uuid;
            $db->query("INSERT INTO notifications (user_id, event_type, message, link) VALUES (?, 'eta_change_approved', ?, ?)", [$requesting_user_id, $message, $link]);

            $db->pdo()->commit();
            redirect("dashboard.php?status=review_complete");

        } else { // Reject
            $db->query("UPDATE trip_change_requests SET status = 'rejected', resolved_at = NOW(), resolved_by_user_id = ? WHERE id = ?", [$user_id, $change_request_id]);
            
            $message = "Your ETA change for Trip ".substr($trip_uuid, 0, 8)." was REJECTED.";
            $link = "awarded_trip_details.php?uuid=" . $trip_uuid;
            $db->query("INSERT INTO notifications (user_id, event_type, message, link) VALUES (?, 'eta_change_rejected', ?, ?)", [$requesting_user_id, $message, $link]);
            
            $db->pdo()->commit();
            redirect("post_rejection_options.php?uuid=" . $trip_uuid);
        }

    } catch (Exception $e) {
        if ($db->pdo()->inTransaction()) $db->pdo()->rollBack();
        error_log("Review Change Submission Error: " . $e->getMessage());
        // Handle error display
    }
}

require_once 'header.php';
?>

<h2 class="mb-4">Review ETA Change Request</h2>
<?php if ($_SESSION['user_role'] === 'admin'): ?>
    <div class="alert alert-warning"><b>Admin Read-Only View</b></div>
<?php endif; ?>

<p>A carrier has requested to change their ETA for Trip <b><?= e(substr($request_details['trip_uuid'], 0, 8)); ?>...</b></p>
<div class="card">
    <div class="card-body">
        <dl class="row">
            <dt class="col-sm-4">Current Awarded ETA</dt>
            <dd class="col-sm-8"><?= e(date('M j, Y g:i A', strtotime($request_details['awarded_eta']))); ?></dd>

            <dt class="col-sm-4 text-primary">Proposed New ETA</dt>
            <dd class="col-sm-8 text-primary fw-bold">
                <?php 
                $proposed = json_decode($request_details['proposed_data'], true);
                echo e(date('M j, Y g:i A', strtotime($proposed['new_eta']))); 
                ?>
            </dd>
        </dl>
    </div>
</div>

<?php if (in_array($_SESSION['user_role'], ['user', 'superuser'])): ?>
    <form action="review_change.php?id=<?= e($change_request_id); ?>" method="post" class="mt-4">
        <p class="fw-bold">Do you accept this change?</p>
        <button type="submit" name="decision" value="accept" class="btn btn-success">Accept New ETA</button>
        <button type="submit" name="decision" value="reject" class="btn btn-danger">Reject Change</button>
    </form>
<?php endif; ?>

<?php require_once 'footer.php'; ?>