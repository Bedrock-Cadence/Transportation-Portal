<?php
// FILE: public/awarded_trip_details.php

require_once 'init.php';
require_once __DIR__ . '/../../app/cad_import_service.php'; // Assuming this has the send_trip_to_cad function

// --- Security & Permission Check ---
if (!isset($_SESSION["loggedin"])) { redirect('login.php'); }
if (empty($_GET['uuid'])) { redirect('dashboard.php'); }

$page_title = 'Awarded Trip Details';
$db = Database::getInstance();
$uuid = $_GET['uuid'];
$trip = null;
$trip_phi = null;
$page_message = '';
$page_error = '';

try {
    $trip = $db->query("SELECT * FROM trips WHERE uuid = ? LIMIT 1", [$uuid])->fetch();
    if ($trip) {
        $trip_phi = $db->query("SELECT * FROM trips_phi WHERE trip_id = ? LIMIT 1", [$trip['id']])->fetch();
    }
    if (!$trip || !$trip_phi) {
        redirect("dashboard.php?error=notfound");
    }

    $is_authorized = false;
    if ($_SESSION['user_role'] === 'admin' || ($_SESSION['entity_type'] === 'carrier' && $_SESSION['entity_id'] == $trip['carrier_id']) || ($_SESSION['entity_type'] === 'facility' && $_SESSION['entity_id'] == $trip['facility_id'])) {
        $is_authorized = true;
    }
    if (!$is_authorized) {
        redirect("dashboard.php?error=unauthorized");
    }

} catch (Exception $e) {
    error_log("Awarded Trip Details (Load) Error: " . $e->getMessage());
    die("A database error occurred.");
}

// --- Handle POST Requests ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        if (isset($_POST['import_to_cad'])) {
            // ... (Full CAD import logic using PDO) ...
        } elseif (isset($_POST['new_eta'])) {
            if (empty(trim($_POST['new_eta']))) {
                throw new Exception("You must provide a new ETA.");
            }
            $db->pdo()->beginTransaction();
            $proposed_data = json_encode(['new_eta' => trim($_POST['new_eta'])]);
            $db->query("INSERT INTO trip_change_requests (trip_id, request_type, requested_by_user_id, proposed_data) VALUES (?, 'eta_change', ?, ?)", [$trip['id'], $_SESSION['user_id'], $proposed_data]);
            // ... (notification logic) ...
            $db->pdo()->commit();
            $page_message = "Your ETA change request has been sent for approval.";
        }
    } catch (Exception $e) {
        if ($db->pdo()->inTransaction()) $db->pdo()->rollBack();
        $page_error = $e->getMessage();
    }
}

require_once 'header.php';
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