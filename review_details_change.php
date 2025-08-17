<?php
// FILE: public/review_details_change.php

require_once 'init.php';

// --- Permission Check & Data Fetch ---
if (!isset($_SESSION["loggedin"])) { redirect('login.php'); }
if (empty($_GET['id'])) { redirect('dashboard.php'); }

$page_title = 'Review Trip Update';
$db = Database::getInstance();
$change_request_id = (int)$_GET['id'];
$request_details = null;
$is_authorized = false;

try {
    $sql = "SELECT tcr.*, t.uuid AS trip_uuid, t.carrier_id
            FROM trip_change_requests tcr
            JOIN trips t ON tcr.trip_id = t.id
            WHERE tcr.id = ? AND tcr.status = 'pending' AND tcr.request_type = 'details_change'";
    $request_details = $db->query($sql, [$change_request_id])->fetch();

    if (!$request_details) {
        redirect("dashboard.php?error=notfound");
    }

    if ($_SESSION['user_role'] === 'admin' || (isset($_SESSION['entity_id']) && $_SESSION['entity_id'] == $request_details['carrier_id'])) {
        $is_authorized = true;
    }
    if (!$is_authorized) {
        redirect("dashboard.php?error=unauthorized");
    }

} catch (Exception $e) {
    error_log("Review Details Change Page Error: " . $e->getMessage());
    die("A database error occurred.");
}

// --- Handle Form Submission (Accept/Reject) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && $_SESSION['user_role'] !== 'admin') {
    $decision = $_POST['decision'];
    $user_id = $_SESSION['user_id'];
    $trip_id = $request_details['trip_id'];
    $requesting_user_id = $request_details['requested_by_user_id'];
    $trip_uuid = $request_details['trip_uuid'];
    
    try {
        $db->pdo()->beginTransaction();

        if ($decision == 'accept') {
            $changes = json_decode($request_details['proposed_data'], true);
            $update_parts = [];
            $update_params = [];

            // IMPORTANT: In a real-world scenario, you MUST have a whitelist of editable fields for security
            $allowed_fields = ['patient_first_name', 'patient_last_name', 'patient_weight_kg', 'appointment_at'];
            foreach ($changes as $field => $values) {
                if (in_array($field, $allowed_fields)) {
                    $update_parts[] = "`$field` = ?";
                    $update_params[] = $values['new'];
                }
            }

            if (!empty($update_parts)) {
                $update_params[] = $trip_id;
                $update_sql = "UPDATE trips SET " . implode(', ', $update_parts) . " WHERE id = ?";
                $db->query($update_sql, $update_params);
            }
            
            $db->query("UPDATE trip_change_requests SET status = 'accepted', resolved_at = NOW(), resolved_by_user_id = ? WHERE id = ?", [$user_id, $change_request_id]);
            
            $message = "Your requested changes for Trip ".substr($trip_uuid, 0, 8)." were APPROVED.";
            $link = "awarded_trip_details.php?uuid=" . $trip_uuid;
            $db->query("INSERT INTO notifications (user_id, event_type, message, link) VALUES (?, 'details_change_approved', ?, ?)", [$requesting_user_id, $message, $link]);
        
        } else { // Reject
            $db->query("UPDATE trip_change_requests SET status = 'rejected', resolved_at = NOW(), resolved_by_user_id = ? WHERE id = ?", [$user_id, $change_request_id]);
            
            $new_bidding_closes_at = date('Y-m-d H:i:s', strtotime('+2 hours'));
            $db->query("UPDATE trips SET status = 'bidding', carrier_id = NULL, awarded_eta = NULL, bidding_closes_at = ? WHERE id = ?", [$new_bidding_closes_at, $trip_id]);

            $message = "Your changes for Trip ".substr($trip_uuid, 0, 8)." were REJECTED. The trip has been re-broadcast.";
            $link = "dashboard.php";
            $db->query("INSERT INTO notifications (user_id, event_type, message, link) VALUES (?, 'details_change_rejected', ?, ?)", [$requesting_user_id, $message, $link]);
        }
        
        $db->pdo()->commit();
        redirect("dashboard.php?status=review_complete");

    } catch (Exception $e) {
        if ($db->pdo()->inTransaction()) $db->pdo()->rollBack();
        error_log("Review Details Change Submission Error: " . $e->getMessage());
        // Handle error display
    }
}

require_once 'header.php';
?>

<h2 class="mb-4">Review Trip Update Request</h2>
<?php if ($_SESSION['user_role'] === 'bedrock_admin'): ?>
    <div class="alert alert-warning"><b>Admin Read-Only View</b></div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header"><h5 class="mb-0">Proposed Changes for Trip <?php echo substr($request_details['trip_uuid'], 0, 8); ?>...</h5></div>
    <div class="card-body">
        <p>The facility has requested the following changes. Please review them carefully.</p>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Field</th>
                        <th>Old Value</th>
                        <th>New Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (json_decode($request_details['proposed_data'], true) as $field => $values): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $field))); ?></strong></td>
                        <td><span class="text-muted"><del><?php echo htmlspecialchars($values['old']); ?></del></span></td>
                        <td><span class="text-primary fw-bold"><?php echo htmlspecialchars($values['new']); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($_SESSION['user_role'] !== 'bedrock_admin' && isset($_SESSION['entity_id']) && $_SESSION['entity_id'] == $request_details['carrier_id']): ?>
            <hr>
            <form action="<?php echo htmlspecialchars($_SERVER["REQUEST_URI"]); ?>" method="post">
                <p class="fw-bold">Do you accept these changes? Rejecting will release the trip back for bidding.</p>
                <button type="submit" name="decision" value="accept" class="btn btn-success">Accept Changes & Keep Trip</button>
                <button type="submit" name="decision" value="reject" class="btn btn-danger">Reject Changes & Release Trip</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php
require_once 'footer.php';
?>