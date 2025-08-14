<?php
$page_title = 'Request Trip Update';
require_once 'header.php';
require_once __DIR__ . '/../../app/db_connect.php';

// --- Permission Check & Data Fetch ---
if (!isset($_SESSION["loggedin"])) { header("location: login.php"); exit; }
if (!isset($_GET['uuid'])) { header("location: dashboard.php"); exit; }

function decrypt_data_placeholder($data) { return base64_decode($data); }

$uuid = $_GET['uuid'];
$trip = null;
$is_authorized = false;
$page_message = '';

$sql_fetch = "SELECT * FROM trips WHERE uuid = ? LIMIT 1";
if ($stmt_fetch = $mysqli->prepare($sql_fetch)) {
    $stmt_fetch->bind_param("s", $uuid);
    $stmt_fetch->execute();
    $result = $stmt_fetch->get_result();
    if ($result->num_rows == 1) {
        $trip = $result->fetch_assoc();
    }
    $stmt_fetch->close();
}

if (!$trip) { header("location: dashboard.php?error=notfound"); exit; }

if ($_SESSION['user_role'] === 'bedrock_admin' || (in_array($_SESSION['user_role'], ['facility_user', 'facility_superuser']) && $_SESSION['entity_id'] == $trip['facility_id'])) {
    $is_authorized = true;
}

if (!$is_authorized) { header("location: dashboard.php?error=unauthorized"); exit; }

// --- Handle Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && in_array($_SESSION['user_role'], ['facility_user', 'facility_superuser'])) {
    $original_trip_data = [];
    foreach ($trip as $key => $value) {
        if(str_contains($key, '_encrypted')) {
            $original_trip_data[str_replace('_encrypted', '', $key)] = decrypt_data_placeholder($value);
        } else {
            $original_trip_data[$key] = $value;
        }
    }
    
    $submitted_data = $_POST;
    $changes = [];

    foreach ($submitted_data as $key => $value) {
        if (array_key_exists($key, $original_trip_data) && $value != $original_trip_data[$key]) {
            $changes[$key] = ['old' => $original_trip_data[$key], 'new' => $value];
        }
    }

    if (!empty($changes)) {
        $proposed_data = json_encode($changes);
        $sql_request = "INSERT INTO trip_change_requests (trip_id, request_type, requested_by_user_id, proposed_data) VALUES (?, 'details_change', ?, ?)";
        if ($stmt_request = $mysqli->prepare($sql_request)) {
            $stmt_request->bind_param("iis", $trip['id'], $_SESSION['user_id'], $proposed_data);
            if ($stmt_request->execute()) {
                
                // --- NEW: Create Notification for Carrier ---
                $carrier_super_user_id = 2; // Placeholder for carrier user ID
                $request_id = $stmt_request->insert_id;
                $message = "Facility requested a detail change for Trip ".substr($uuid, 0, 8).".";
                $link = "review_details_change.php?id=" . $request_id;
                $sql_notify = "INSERT INTO notifications (user_id, event_type, message, link) VALUES (?, 'details_change_requested', ?, ?)";
                $stmt_notify = $mysqli->prepare($sql_notify);
                $stmt_notify->bind_param("iss", $carrier_super_user_id, $message, $link);
                $stmt_notify->execute();
                $stmt_notify->close();
                
                header("location: dashboard.php?status=update_requested");
                exit;
            } else {
                $page_message = '<div class="alert alert-danger">Could not submit update request.</div>';
            }
        }
    } else {
        $page_message = '<div class="alert alert-info">No changes were detected.</div>';
    }
}
?>

<h2 class="mb-4">Request Trip Update</h2>
<p>Modify the details below. The assigned carrier will be required to approve these changes.</p>
<?php echo $page_message; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="<?php echo htmlspecialchars($_SERVER["REQUEST_URI"]); ?>" method="post">
            <fieldset class="mb-4">
                <legend class="fs-5 border-bottom mb-3 pb-2">Patient Information (PHI)</legend>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="patient_first_name" class="form-label">First Name</label>
                        <input type="text" name="patient_first_name" class="form-control" value="<?php echo htmlspecialchars(decrypt_data_placeholder($trip['patient_first_name_encrypted'])); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="patient_last_name" class="form-label">Last Name</label>
                        <input type="text" name="patient_last_name" class="form-control" value="<?php echo htmlspecialchars(decrypt_data_placeholder($trip['patient_last_name_encrypted'])); ?>" required>
                    </div>
                </div>
                 <div class="mb-3">
                    <label for="patient_weight_kg" class="form-label">Patient Weight (kg)</label>
                    <input type="number" name="patient_weight_kg" class="form-control" value="<?php echo htmlspecialchars(decrypt_data_placeholder($trip['patient_weight_kg_encrypted'])); ?>" required>
                </div>
            </fieldset>

            <fieldset class="mb-4">
                <legend class="fs-5 border-bottom mb-3 pb-2">Trip Details</legend>
                 <div class="mb-3">
                    <label for="appointment_at" class="form-label">Appointment Time</label>
                    <input type="datetime-local" name="appointment_at" class="form-control" value="<?php echo date('Y-m-d\TH:i', strtotime($trip['appointment_at'])); ?>" required>
                </div>
            </fieldset>

            <?php if (in_array($_SESSION['user_role'], ['facility_user', 'facility_superuser'])): ?>
            <div class="d-grid">
                <button type="submit" class="btn btn-warning btn-lg">Submit Update Request for Carrier Approval</button>
            </div>
            <?php else: ?>
            <div class="alert alert-warning"><b>Admin Read-Only View:</b> Form submission is disabled.</div>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php
require_once 'footer.php';
?>