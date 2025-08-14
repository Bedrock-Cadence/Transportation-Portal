<?php
$page_title = 'Review Trip Update';
require_once 'header.php';
require_once __DIR__ . '/../../app/db_connect.php';

// --- Permission Check & Data Fetch ---
if (!isset($_SESSION["loggedin"])) { header("location: login.php"); exit; }
if (!isset($_GET['id'])) { header("location: dashboard.php"); exit; }

$change_request_id = $_GET['id'];
$request_details = null;
$is_authorized = false;

$sql = "SELECT tcr.*, t.uuid AS trip_uuid, t.carrier_id 
        FROM trip_change_requests tcr
        JOIN trips t ON tcr.trip_id = t.id
        WHERE tcr.id = ? AND tcr.status = 'pending' AND tcr.request_type = 'details_change'";

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

if ($_SESSION['user_role'] === 'bedrock_admin' || (isset($_SESSION['entity_id']) && $_SESSION['entity_id'] == $request_details['carrier_id'])) {
    $is_authorized = true;
}

if (!$is_authorized) { header("location: dashboard.php?error=unauthorized"); exit; }

// --- Handle Form Submission (Accept/Reject) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && $_SESSION['user_role'] !== 'bedrock_admin') {
    $decision = $_POST['decision'];
    $user_id = $_SESSION['user_id'];
    $trip_id = $request_details['trip_id'];
    $requesting_user_id = $request_details['requested_by_user_id'];
    $trip_uuid = $request_details['trip_uuid'];
    $changes = json_decode($request_details['proposed_data'], true);

    $mysqli->begin_transaction();
    try {
        if ($decision == 'accept') {
            $update_sql = "UPDATE trips SET ";
            $update_params = [];
            $param_types = '';
            foreach ($changes as $field => $values) {
                // IMPORTANT: Whitelist of editable fields is crucial for security in production
                $update_sql .= "$field = ?, ";
                $update_params[] = $values['new'];
                $param_types .= 's';
            }
            $update_sql = rtrim($update_sql, ', ') . " WHERE id = ?";
            $update_params[] = $trip_id;
            $param_types .= 'i';

            $stmt1 = $mysqli->prepare($update_sql);
            $stmt1->bind_param($param_types, ...$update_params);
            $stmt1->execute();
            $stmt1->close();

            $stmt2 = $mysqli->prepare("UPDATE trip_change_requests SET status = 'accepted', resolved_at = NOW(), resolved_by_user_id = ? WHERE id = ?");
            $stmt2->bind_param("ii", $user_id, $change_request_id);
            $stmt2->execute();
            $stmt2->close();
            
            // --- NEW: Notify facility of approval ---
            $message = "Your requested changes for Trip ".substr($trip_uuid, 0, 8)." were APPROVED.";
            $link = "awarded_trip_details.php?uuid=" . $trip_uuid;
            $sql_notify = "INSERT INTO notifications (user_id, event_type, message, link) VALUES (?, 'details_change_approved', ?, ?)";
            $stmt_notify = $mysqli->prepare($sql_notify);
            $stmt_notify->bind_param("iss", $requesting_user_id, $message, $link);
            $stmt_notify->execute();
            $stmt_notify->close();

        } else { // Reject
            $stmt1 = $mysqli->prepare("UPDATE trip_change_requests SET status = 'rejected', resolved_at = NOW(), resolved_by_user_id = ? WHERE id = ?");
            $stmt1->bind_param("ii", $user_id, $change_request_id);
            $stmt1->execute();
            $stmt1->close();
            
            $new_bidding_closes_at = date('Y-m-d H:i:s', strtotime('+2 hours'));
            $stmt2 = $mysqli->prepare("UPDATE trips SET status = 'bidding', carrier_id = NULL, awarded_eta = NULL, bidding_closes_at = ? WHERE id = ?");
            $stmt2->bind_param("si", $new_bidding_closes_at, $trip_id);
            $stmt2->execute();
            $stmt2->close();

            // --- NEW: Notify facility of rejection and re-broadcast ---
            $message = "Your changes for Trip ".substr($trip_uuid, 0, 8)." were REJECTED. The trip has been re-broadcast.";
            $link = "dashboard.php";
            $sql_notify = "INSERT INTO notifications (user_id, event_type, message, link) VALUES (?, 'details_change_rejected', ?, ?)";
            $stmt_notify = $mysqli->prepare($sql_notify);
            $stmt_notify->bind_param("iss", $requesting_user_id, $message, $link);
            $stmt_notify->execute();
            $stmt_notify->close();
        }
        
        $mysqli->commit();
        header("location: dashboard.php?status=review_complete");
        exit;

    } catch (mysqli_sql_exception $exception) {
        $mysqli->rollback();
        die("An error occurred: " . $exception->getMessage());
    }
}
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