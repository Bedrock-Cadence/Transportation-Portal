<?php
$page_title = 'Review ETA Change Request';
require_once 'header.php';
require_once __DIR__ . '/../../app/db_connect.php';

// --- Permission Check & Data Fetch ---
if (!isset($_SESSION["loggedin"])) { header("location: login.php"); exit; }
if (!isset($_GET['id'])) { header("location: dashboard.php"); exit; }

$change_request_id = $_GET['id'];
$request_details = null;
$is_authorized = false;

$sql = "SELECT tcr.*, t.awarded_eta, t.uuid AS trip_uuid, t.facility_id 
        FROM trip_change_requests tcr
        JOIN trips t ON tcr.trip_id = t.id
        WHERE tcr.id = ? AND tcr.status = 'pending'";
if ($stmt = $mysqli->prepare($sql)) {
    $stmt->bind_param("i", $change_request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 1) {
        $request_details = $result->fetch_assoc();
    } else {
        header("location: dashboard.php?error=notfound"); exit;
    }
    $stmt->close();
}

if ($_SESSION['user_role'] === 'bedrock_admin' || (in_array($_SESSION['user_role'], ['facility_user', 'facility_superuser']) && $_SESSION['entity_id'] == $request_details['facility_id'])) {
    $is_authorized = true;
}

if (!$is_authorized) { header("location: dashboard.php?error=unauthorized"); exit; }

// --- Handle Form Submission (Accept/Reject) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && in_array($_SESSION['user_role'], ['facility_user', 'facility_superuser'])) {
    $decision = $_POST['decision'];
    $user_id = $_SESSION['user_id'];
    $trip_id = $request_details['trip_id'];
    $requesting_user_id = $request_details['requested_by_user_id'];
    $trip_uuid = $request_details['trip_uuid'];

    $mysqli->begin_transaction();
    try {
        if ($decision == 'accept') {
            $proposed_data = json_decode($request_details['proposed_data'], true);
            $new_eta = $proposed_data['new_eta'];

            $stmt1 = $mysqli->prepare("UPDATE trips SET awarded_eta = ? WHERE id = ?");
            $stmt1->bind_param("si", $new_eta, $trip_id);
            $stmt1->execute();
            $stmt1->close();

            $stmt2 = $mysqli->prepare("UPDATE trip_change_requests SET status = 'accepted', resolved_at = NOW(), resolved_by_user_id = ? WHERE id = ?");
            $stmt2->bind_param("ii", $user_id, $change_request_id);
            $stmt2->execute();
            $stmt2->close();

            // --- NEW: Notify carrier of approval ---
            $message = "Your ETA change for Trip ".substr($trip_uuid, 0, 8)." was APPROVED.";
            $link = "awarded_trip_details.php?uuid=" . $trip_uuid;
            $sql_notify = "INSERT INTO notifications (user_id, event_type, message, link) VALUES (?, 'eta_change_approved', ?, ?)";
            $stmt_notify = $mysqli->prepare($sql_notify);
            $stmt_notify->bind_param("iss", $requesting_user_id, $message, $link);
            $stmt_notify->execute();
            $stmt_notify->close();

            $mysqli->commit();
            header("location: dashboard.php?status=review_complete");
            exit;

        } else { // Reject
            $stmt_reject = $mysqli->prepare("UPDATE trip_change_requests SET status = 'rejected', resolved_at = NOW(), resolved_by_user_id = ? WHERE id = ?");
            $stmt_reject->bind_param("ii", $user_id, $change_request_id);
            $stmt_reject->execute();
            $stmt_reject->close();
            
            // --- NEW: Notify carrier of rejection ---
            $message = "Your ETA change for Trip ".substr($trip_uuid, 0, 8)." was REJECTED.";
            $link = "awarded_trip_details.php?uuid=" . $trip_uuid;
            $sql_notify = "INSERT INTO notifications (user_id, event_type, message, link) VALUES (?, 'eta_change_rejected', ?, ?)";
            $stmt_notify = $mysqli->prepare($sql_notify);
            $stmt_notify->bind_param("iss", $requesting_user_id, $message, $link);
            $stmt_notify->execute();
            $stmt_notify->close();
            
            $mysqli->commit();
            header("location: post_rejection_options.php?uuid=" . $trip_uuid);
            exit;
        }

    } catch (mysqli_sql_exception $exception) {
        $mysqli->rollback();
        die("An error occurred. Please try again.");
    }
}
?>

<h2 class="mb-4">Review ETA Change Request</h2>
<?php if ($_SESSION['user_role'] === 'bedrock_admin'): ?>
    <div class="alert alert-warning"><b>Admin Read-Only View</b></div>
<?php endif; ?>

<p>A carrier has requested to change their ETA for Trip <b><?php echo substr($request_details['trip_uuid'], 0, 8); ?>...</b></p>
<div class="card">
    <div class="card-body">
        <dl class="row">
            <dt class="col-sm-4">Current Awarded ETA</dt>
            <dd class="col-sm-8"><?php echo date('M j, Y g:i A', strtotime($request_details['awarded_eta'])); ?></dd>

            <dt class="col-sm-4 text-primary">Proposed New ETA</dt>
            <dd class="col-sm-8 text-primary fw-bold"><?php 
                $proposed = json_decode($request_details['proposed_data'], true);
                echo date('M j, Y g:i A', strtotime($proposed['new_eta'])); 
            ?></dd>
        </dl>
    </div>
</div>

<?php if (in_array($_SESSION['user_role'], ['facility_user', 'facility_superuser'])): ?>
    <form action="<?php echo htmlspecialchars($_SERVER["REQUEST_URI"]); ?>" method="post" class="mt-4">
        <p class="fw-bold">Do you accept this change?</p>
        <button type="submit" name="decision" value="accept" class="btn btn-success">Accept New ETA</button>
        <button type="submit" name="decision" value="reject" class="btn btn-danger">Reject Change</button>
    </form>
<?php endif; ?>

<?php
require_once 'footer.php';
?>