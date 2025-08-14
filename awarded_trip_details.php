<?php
// We require all necessary files at the top.
require_once __DIR__ . '/../../app/cad_import_service.php';
session_start();
require_once __DIR__ . '/../../app/db_connect.php';

// --- Universal Check: Is user logged in and is a trip UUID provided? ---
if (!isset($_SESSION["loggedin"])) { header("location: login.php"); exit; }
if (!isset($_GET['uuid'])) { header("location: dashboard.php"); exit; }

// --- Re-using placeholder functions ---
function decrypt_data_placeholder($encrypted_data) {
    return base64_decode($encrypted_data);
}

$uuid = $_GET['uuid'];
$trip = null;
$is_authorized = false;
$page_message = '';

// --- Handle POST Requests ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --- CAD Import Logic ---
    if (isset($_POST['import_to_cad'])) {
        $trip_id_for_cad = $_POST['trip_id'];
        $sql_get_full_trip = "SELECT * FROM trips WHERE id = ?";
        $stmt_get_full_trip = $mysqli->prepare($sql_get_full_trip);
        $stmt_get_full_trip->bind_param("i", $trip_id_for_cad);
        $stmt_get_full_trip->execute();
        $full_trip_result = $stmt_get_full_trip->get_result();
        $full_trip_row = $full_trip_result->fetch_assoc();
        $stmt_get_full_trip->close();
        $full_trip_data = [
            "patient_first_name" => decrypt_data_placeholder($full_trip_row['patient_first_name_encrypted']),
            "patient_last_name" => decrypt_data_placeholder($full_trip_row['patient_last_name_encrypted']),
            "origin_street" => $full_trip_row['origin_street'],
            "destination_street" => $full_trip_row['destination_street'],
            "appointment_at" => $full_trip_row['appointment_at'],
            "medical_conditions" => decrypt_data_placeholder($full_trip_row['medical_conditions_encrypted'])
        ];
        $sql_get_config = "SELECT cad_import_config FROM carriers WHERE id = ?";
        if($stmt_get_config = $mysqli->prepare($sql_get_config)) {
            $stmt_get_config->bind_param("i", $_SESSION['entity_id']);
            $stmt_get_config->execute();
            $result_config = $stmt_get_config->get_result();
            $carrier_config = $result_config->fetch_assoc();
            $stmt_get_config->close();

            if (!empty($carrier_config['cad_import_config'])) {
                $import_result = send_trip_to_cad($full_trip_data, $carrier_config['cad_import_config']);
                if ($import_result['status'] == 'success') {
                    $page_message = '<div class="alert alert-success">' . htmlspecialchars($import_result['message']) . '</div>';
                } else {
                    $page_message = '<div class="alert alert-danger">' . htmlspecialchars($import_result['message']) . '</div>';
                }
            } else {
                $page_message = '<div class="alert alert-warning">CAD configuration not found. Please set it in your profile.</div>';
            }
        }
    }
    // --- ETA Update Logic ---
    elseif (isset($_POST['new_eta'])) {
        $new_eta = trim($_POST['new_eta']);
        $trip_id_from_form = $_POST['trip_id'];
        
        if (empty($new_eta)) {
            $page_message = '<div class="alert alert-danger">You must provide a new ETA.</div>';
        } else {
            $proposed_data = json_encode(['new_eta' => $new_eta]);
            $sql_request = "INSERT INTO trip_change_requests (trip_id, request_type, requested_by_user_id, proposed_data) VALUES (?, 'eta_change', ?, ?)";
            
            if ($stmt_request = $mysqli->prepare($sql_request)) {
                $stmt_request->bind_param("iis", $trip_id_from_form, $_SESSION['user_id'], $proposed_data);
                if ($stmt_request->execute()) {
                    $page_message = '<div class="alert alert-success">Your ETA change request has been sent to the facility for approval.</div>';
                    
                    // --- NEW: Create Notification for Facility ---
                    $facility_super_user_id = 1; // Placeholder for facility user ID
                    $request_id = $stmt_request->insert_id;
                    $message = "Carrier requested an ETA change for Trip ".substr($uuid, 0, 8).".";
                    $link = "review_change.php?id=" . $request_id;
                    $sql_notify = "INSERT INTO notifications (user_id, event_type, message, link) VALUES (?, 'eta_change_requested', ?, ?)";
                    $stmt_notify = $mysqli->prepare($sql_notify);
                    $stmt_notify->bind_param("iss", $facility_super_user_id, $message, $link);
                    $stmt_notify->execute();
                    $stmt_notify->close();
                    
                } else {
                    $page_message = '<div class="alert alert-danger">Could not submit your request. Please try again.</div>';
                }
                $stmt_request->close();
            }
        }
    }
}


// --- Step 1: Fetch trip data for display based on UUID alone ---
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

if (!$trip) {
    header("location: dashboard.php?error=notfound");
    exit;
}

// --- Step 2: Authorize the logged-in user ---
if ($_SESSION['user_role'] === 'bedrock_admin') {
    $is_authorized = true;
} elseif (in_array($_SESSION['user_role'], ['carrier_user', 'carrier_superuser']) && $_SESSION['entity_id'] == $trip['carrier_id']) {
    $is_authorized = true;
} elseif (in_array($_SESSION['user_role'], ['facility_user', 'facility_superuser']) && $_SESSION['entity_id'] == $trip['facility_id']) {
    $is_authorized = true;
}

if (!$is_authorized) {
    header("location: dashboard.php?error=unauthorized");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Awarded Trip Details</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php require_once 'header.php'; ?>
<main class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Awarded Trip Details</h2>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <?php echo $page_message; ?>

    <?php if ($_SESSION['user_role'] === 'bedrock_admin'): ?>
        <div class="alert alert-warning"><b>Admin Read-Only View</b></div>
    <?php elseif (isset($_SESSION['entity_id']) && $_SESSION['entity_id'] == $trip['carrier_id']): ?>
        <div class="alert alert-info"><b>Carrier View</b></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['entity_id']) && $_SESSION['entity_id'] == $trip['facility_id']): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-body text-center">
                <h5 class="card-title">Facility Actions</h5>
                <p class="card-text">Need to change patient or trip details? You can request an update here.</p>
                <a href="edit_trip_facility.php?uuid=<?php echo $trip['uuid']; ?>" class="btn btn-warning">Request Trip Update</a>
            </div>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-header"><h5 class="mb-0">Patient Information (PHI)</h5></div>
        <div class="card-body">
            <dl class="row">
                <dt class="col-sm-3">Name</dt>
                <dd class="col-sm-9"><?php echo htmlspecialchars(decrypt_data_placeholder($trip['patient_first_name_encrypted'])) . ' ' . htmlspecialchars(decrypt_data_placeholder($trip['patient_last_name_encrypted'])); ?></dd>
                <dt class="col-sm-3">Date of Birth</dt>
                <dd class="col-sm-9"><?php echo htmlspecialchars(decrypt_data_placeholder($trip['patient_dob_encrypted'])); ?></dd>
            </dl>
        </div>
    </div>

    <div class="card shadow-sm mt-4">
        <div class="card-header"><h5 class="mb-0">Trip & Appointment Details</h5></div>
        <div class="card-body">
            <dl class="row">
                <dt class="col-sm-3">Origin</dt>
                <dd class="col-sm-9"><?php echo htmlspecialchars($trip['origin_name']); ?></dd>
                <dt class="col-sm-3">Destination</dt>
                <dd class="col-sm-9"><?php echo htmlspecialchars($trip['destination_name']); ?></dd>
                <dt class="col-sm-3">Appointment Time</dt>
                <dd class="col-sm-9"><?php echo date('M j, Y g:i A', strtotime($trip['appointment_at'])); ?></dd>
                <dt class="col-sm-3">Awarded ETA</dt>
                <dd class="col-sm-9"><?php echo date('M j, Y g:i A', strtotime($trip['awarded_eta'])); ?></dd>
            </dl>
        </div>
    </div>

    <?php
    if (isset($_SESSION['entity_id']) && $_SESSION['entity_id'] == $trip['carrier_id']):
    ?>
        <div class="card shadow-sm mt-4">
            <div class="card-header"><h5 class="mb-0">Carrier Actions</h5></div>
            <div class="card-body">
                <div class="mb-3">
                    <form action="<?php echo htmlspecialchars($_SERVER["REQUEST_URI"]); ?>" method="post" class="d-inline">
                        <input type="hidden" name="trip_id" value="<?php echo $trip['id']; ?>">
                        <button type="submit" name="import_to_cad" class="btn btn-info">Import to CAD</button>
                    </form>
                    <?php if (strtotime($trip['awarded_eta']) < time() && is_null($trip['carrier_completed_at'])): ?>
                        <a href="complete_trip_carrier.php?uuid=<?php echo $trip['uuid']; ?>" class="btn btn-success">Complete Trip</a>
                    <?php endif; ?>
                </div>
                <hr>
                <form action="<?php echo htmlspecialchars($_SERVER["REQUEST_URI"]); ?>" method="post">
                    <h5 class="card-title">Request ETA Change</h5>
                    <input type="hidden" name="trip_id" value="<?php echo $trip['id']; ?>">
                    <div class="mb-3">
                        <label for="new_eta" class="form-label">New Estimated Time of Arrival (ETA)</label>
                        <input type="datetime-local" name="new_eta" id="new_eta" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-warning">Submit ETA Change Request</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</main>
<?php require_once 'footer.php'; ?>