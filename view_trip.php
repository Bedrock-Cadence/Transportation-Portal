<?php
session_start();
require_once __DIR__ . '/../../app/db_connect.php';
require_once __DIR__ . '/../../app/encryption_service.php'; // Real encryption service
require_once __DIR__ . '/../../app/logging_service.php';   // Real logging service

// --- AUTHORIZATION & INITIAL DATA FETCH ---

// 1. Universal Check: Is user logged in and is a trip UUID provided?
if (!isset($_SESSION["loggedin"]) || !isset($_SESSION["user_id"])) {
    header("location: login.php");
    exit;
}
if (!isset($_GET['uuid'])) {
    // Log this attempt to access a page without a required parameter
    log_activity($mysqli, $_SESSION['user_id'], 'access_denied', 'User attempted to access view_trip.php without a UUID.');
    header("location: trip_board.php?error=missing_uuid");
    exit;
}

$uuid = $_GET['uuid'];
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$entity_id = $_SESSION['entity_id'] ?? null; // The facility or carrier ID the user belongs to

$trip = null;
$patient_phi = null;
$view_mode = 'unauthorized'; // Default to no access
$page_message = '';
$page_error = '';

// 2. Fetch Core Trip and PHI Data in a single, efficient query
$sql = "
    SELECT 
        t.*, 
        p.patient_last_name_encrypted,
        p.patient_dob_encrypted,
        p.diagnosis_encrypted,
        p.special_equipment_encrypted,
        p.isolation_precautions_encrypted
    FROM trips t
    LEFT JOIN trips_phi p ON t.id = p.trip_id
    WHERE t.uuid = ?
    LIMIT 1
";

if ($stmt = $mysqli->prepare($sql)) {
    $stmt->bind_param("s", $uuid);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $trip = $result->fetch_assoc();
    }
    $stmt->close();
}

// If no trip was found, log it and redirect.
if (!$trip) {
    log_activity($mysqli, $user_id, 'trip_not_found', "User attempted to view a trip with non-existent UUID: {$uuid}");
    header("location: trip_board.php?error=notfound");
    exit;
}

// 3. Determine User's Authorization and View Mode
// Bedrock Admins can see everything.
if ($user_role === 'bedrock_admin') {
    $view_mode = 'admin';
} 
// Facility users can view if they created the trip.
elseif (in_array($user_role, ['facility_user', 'facility_superuser']) && $trip['facility_id'] == $entity_id) {
    $view_mode = 'facility';
} 
// Carrier users need to be checked against blacklists.
elseif (in_array($user_role, ['carrier_user', 'carrier_superuser'])) {
    // Check if this carrier is blacklisted by the facility that created the trip
    $sql_blacklist = "SELECT 1 FROM facility_carrier_preferences WHERE facility_id = ? AND carrier_id = ? AND preference_type = 'blacklisted' LIMIT 1";
    if ($stmt_blacklist = $mysqli->prepare($sql_blacklist)) {
        $stmt_blacklist->bind_param("ii", $trip['facility_id'], $entity_id);
        $stmt_blacklist->execute();
        $stmt_blacklist->store_result();
        if ($stmt_blacklist->num_rows === 0) {
            // Not blacklisted, now determine if they were awarded the trip
            if ($trip['carrier_id'] == $entity_id) {
                $view_mode = 'carrier_awarded';
                // HEAVY LOGGING: The awarded carrier is viewing the trip page.
                log_activity($mysqli, $user_id, 'view_awarded_trip', "Awarded carrier (ID: {$entity_id}) viewed trip details for Trip ID: {$trip['id']}.");
            } else {
                $view_mode = 'carrier_unawarded';
            }
        }
        $stmt_blacklist->close();
    }
}

// If after all checks the user is still unauthorized, log and boot them.
if ($view_mode === 'unauthorized') {
    log_activity($mysqli, $user_id, 'unauthorized_trip_view', "User was denied access to view Trip ID: {$trip['id']}.");
    header("location: trip_board.php?error=unauthorized");
    exit;
}


// --- POST REQUEST HANDLING (Actions) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    // Action: Facility cancels a trip
    if ($action === 'cancel_trip' && $view_mode === 'facility') {
        $sql_cancel = "UPDATE trips SET status = 'cancelled', updated_at = NOW() WHERE id = ?";
        if ($stmt_cancel = $mysqli->prepare($sql_cancel)) {
            $stmt_cancel->bind_param("i", $trip['id']);
            if ($stmt_cancel->execute()) {
                log_activity($mysqli, $user_id, 'trip_cancelled', "User cancelled Trip ID: {$trip['id']}.");
                header("Location: trip_board.php?status=trip_cancelled");
                exit;
            } else {
                $page_error = "Could not cancel the trip. Please try again.";
                log_activity($mysqli, $user_id, 'cancel_trip_failure', "Database error while cancelling Trip ID: {$trip['id']}. Error: " . $stmt_cancel->error);
            }
            $stmt_cancel->close();
        }
    }

    // Action: Carrier places a bid (submits an ETA)
    if ($action === 'place_bid' && $view_mode === 'carrier_unawarded') {
        // Double-check if bidding is still open to prevent race conditions
        if (new DateTime() < new DateTime($trip['bidding_closes_at'])) {
            $eta = trim($_POST['eta']);
            if (!empty($eta)) {
                $sql_bid = "INSERT INTO carrier_etas (trip_id, carrier_id, eta) VALUES (?, ?, ?)";
                if ($stmt_bid = $mysqli->prepare($sql_bid)) {
                    $stmt_bid->bind_param("iis", $trip['id'], $entity_id, $eta);
                    if ($stmt_bid->execute()) {
                        log_activity($mysqli, $user_id, 'bid_placed', "Carrier (ID: {$entity_id}) placed a bid on Trip ID: {$trip['id']} with ETA: {$eta}.");
                        header("Location: trip_board.php?status=bid_placed");
                        exit;
                    } else {
                        // Handle cases where a bid already exists (duplicate key error 1062)
                        if ($mysqli->errno == 1062) {
                            $page_error = 'You have already placed a bid on this trip.';
                        } else {
                            $page_error = 'An error occurred while placing your bid. Please try again.';
                            log_activity($mysqli, $user_id, 'bid_placement_failure', "Database error for Trip ID: {$trip['id']}. Error: " . $stmt_bid->error);
                        }
                    }
                    $stmt_bid->close();
                }
            } else {
                $page_error = 'You must provide an ETA to place a bid.';
            }
        } else {
            $page_error = 'The bidding window has closed. You can no longer place a bid.';
            log_activity($mysqli, $user_id, 'bid_attempt_after_close', "Carrier (ID: {$entity_id}) attempted to bid on closed Trip ID: {$trip['id']}.");
        }
    }

    // Action: Awarded carrier updates their ETA
    if ($action === 'update_eta' && $view_mode === 'carrier_awarded') {
        $new_eta = trim($_POST['awarded_eta']);
        if (!empty($new_eta)) {
            $sql_update_eta = "UPDATE trips SET awarded_eta = ?, updated_at = NOW() WHERE id = ? AND carrier_id = ?";
            if ($stmt_update = $mysqli->prepare($sql_update_eta)) {
                $stmt_update->bind_param("sii", $new_eta, $trip['id'], $entity_id);
                if ($stmt_update->execute()) {
                    log_activity($mysqli, $user_id, 'eta_updated', "Awarded carrier updated ETA for Trip ID: {$trip['id']} to {$new_eta}.");
                    // Refresh the page to show the new ETA
                    header("Location: " . htmlspecialchars($_SERVER["REQUEST_URI"]) . "&status=eta_updated");
                    exit;
                } else {
                     $page_error = 'Could not update the ETA. Please try again.';
                     log_activity($mysqli, $user_id, 'eta_update_failure', "Database error for Trip ID: {$trip['id']}. Error: " . $stmt_update->error);
                }
                $stmt_update->close();
            }
        } else {
            $page_error = 'Please provide a valid ETA.';
        }
    }
}

// --- DATA PREPARATION FOR DISPLAY ---

// Decrypt PHI data needed for the views
$patient_last_name = decrypt_data($trip['patient_last_name_encrypted'], ENCRYPTION_KEY);
$patient_dob = decrypt_data($trip['patient_dob_encrypted'], ENCRYPTION_KEY);
$patient_diagnosis = decrypt_data($trip['diagnosis_encrypted'], ENCRYPTION_KEY);
$special_equipment = decrypt_data($trip['special_equipment_encrypted'], ENCRYPTION_KEY);
$isolation_precautions = decrypt_data($trip['isolation_precautions_encrypted'], ENCRYPTION_KEY);

// Format data for "pretty" display
$patient_birth_year = $patient_dob ? (new DateTime($patient_dob))->format('Y') : '[N/A]';
$created_at_formatted = (new DateTime($trip['created_at']))->format('M j, Y, g:i A');
$updated_at_formatted = (new DateTime($trip['updated_at']))->format('M j, Y, g:i A');

function format_status($status) {
    switch ($status) {
        case 'bidding': return '<span class="status status-bidding">Open for Bids</span>';
        case 'awarded': return '<span class="status status-awarded">Awarded</span>';
        case 'completed': return '<span class="status status-completed">Completed</span>';
        case 'cancelled': return '<span class="status status-cancelled">Cancelled</span>';
        default: return '<span class="status">' . htmlspecialchars(ucfirst($status)) . '</span>';
    }
}

// Logic for the bidding window
$bidding_is_open = (new DateTime() < new DateTime($trip['bidding_closes_at']));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Trip Details</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background-color: #f4f7f6; color: #333; margin: 0; padding: 2em; }
        .container { max-width: 800px; margin: 0 auto; background-color: #fff; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { background-color: #0056b3; color: white; padding: 1.5em; border-top-left-radius: 8px; border-top-right-radius: 8px; }
        .header h2 { margin: 0; }
        .header .status { float: right; font-weight: bold; padding: 0.3em 0.8em; border-radius: 15px; font-size: 0.9em; }
        .status-bidding { background-color: #28a745; }
        .status-awarded { background-color: #ffc107; color: #333; }
        .status-completed { background-color: #6c757d; }
        .status-cancelled { background-color: #dc3545; }
        .content { padding: 2em; }
        .trip-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2em; }
        .trip-section { border-top: 3px solid #0056b3; padding-top: 1em; }
        .trip-section h3 { margin-top: 0; color: #0056b3; }
        dl { margin: 0; }
        dt { font-weight: bold; color: #555; margin-top: 1em; }
        dd { margin-left: 0; margin-bottom: 1em; font-size: 1.1em; }
        .message { padding: 1em; margin-bottom: 1em; border-radius: 5px; text-align: center; }
        .error { background-color: #f8d7da; color: #721c24; }
        .success { background-color: #d4edda; color: #155724; }
        .actions { margin-top: 2em; padding: 2em; background-color: #f8f9fa; border-top: 1px solid #ddd; }
        .actions h3 { margin-top: 0; }
        .button, button { display: inline-block; text-align: center; font-size: 1em; padding: 12px 20px; border-radius: 5px; text-decoration: none; color: white; border: none; cursor: pointer; margin-right: 10px; }
        .button-primary { background-color: #007bff; }
        .button-secondary { background-color: #6c757d; }
        .button-danger { background-color: #dc3545; }
        .button-info { background-color: #17a2b8; }
        .button-success { background-color: #28a745; }
        .form-group { margin-bottom: 1em; }
        .form-group label { display: block; margin-bottom: .5em; font-weight: bold; }
        .form-group input { width: 100%; padding: 10px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 5px; }
        .disabled-overlay { text-align: center; padding: 2em; background-color: #e9ecef; border-radius: 5px; }
        .admin-notice { text-align:center; background-color: #ffc107; color: #333; padding: 0.5em; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <?= format_status($trip['status']); ?>
            <h2>Trip #<?= htmlspecialchars($trip['uuid']); ?></h2>
        </div>

        <div class="content">
            <?php if (!empty($page_message)): ?><p class="message success"><?= htmlspecialchars($page_message); ?></p><?php endif; ?>
            <?php if (!empty($page_error)): ?><p class="message error"><?= htmlspecialchars($page_error); ?></p><?php endif; ?>
            
            <?php if ($view_mode === 'admin'): ?>
                <p class="admin-notice">Admin Read-Only View</p>
            <?php endif; ?>

            <div class="trip-grid">
                <div class="trip-section">
                    <h3>Route Details</h3>
                    <dl>
                        <dt>Pick-up Address</dt>
                        <dd>
                            <?= htmlspecialchars($trip['origin_name']); ?><br>
                            <?= htmlspecialchars($trip['origin_street']); ?><br>
                            <?= htmlspecialchars($trip['origin_city']); ?>, <?= htmlspecialchars($trip['origin_state']); ?> <?= htmlspecialchars($trip['origin_zip']); ?>
                        </dd>
                        
                        <dt>Drop-off Address</dt>
                        <dd>
                            <?= htmlspecialchars($trip['destination_name']); ?><br>
                            <?= htmlspecialchars($trip['destination_street']); ?><br>
                            <?= htmlspecialchars($trip['destination_city']); ?>, <?= htmlspecialchars($trip['destination_state']); ?> <?= htmlspecialchars($trip['destination_zip']); ?>
                        </dd>
                    </dl>
                </div>

                <div class="trip-section">
                    <h3>Patient & Trip Information</h3>
                    <dl>
                        <?php if ($view_mode === 'facility' || $view_mode === 'carrier_awarded' || $view_mode === 'admin'): ?>
                            <dt>Patient Last Name</dt>
                            <dd><?= htmlspecialchars($patient_last_name ?: '[Encrypted]'); ?></dd>
                            
                            <dt>Patient Year of Birth</dt>
                            <dd><?= htmlspecialchars($patient_birth_year); ?></dd>
                        <?php endif; ?>

                        <dt>Appointment Time</dt>
                        <dd><?= $trip['appointment_at'] ? (new DateTime($trip['appointment_at']))->format('M j, Y, g:i A') : 'ASAP'; ?></dd>
                        
                        <dt>Special Equipment</dt>
                        <dd><?= htmlspecialchars($special_equipment ?: 'None specified'); ?></dd>
                        
                        <dt>Isolation Precautions</dt>
                        <dd><?= htmlspecialchars($isolation_precautions ?: 'None specified'); ?></dd>

                        <?php if ($view_mode !== 'facility'): ?>
                            <dt>Diagnosis</dt>
                            <dd><?= htmlspecialchars($patient_diagnosis ?: 'Not provided'); ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>
            
            <div class="trip-section" style="grid-column: 1 / -1;">
                <h3>Timeline</h3>
                <dl>
                    <dt>Trip Created</dt>
                    <dd><?= $created_at_formatted; ?></dd>
                    <dt>Last Update</dt>
                    <dd><?= $updated_at_formatted; ?></dd>
                </dl>
            </div>
        </div>

        <div class="actions">
            <?php if ($view_mode === 'facility' && $trip['status'] !== 'cancelled' && $trip['status'] !== 'completed'): ?>
                <h3>Actions</h3>
                <a href="modify_trip.php?uuid=<?= htmlspecialchars($trip['uuid']); ?>" class="button button-primary">Modify Trip</a>
                <form action="<?= htmlspecialchars($_SERVER["REQUEST_URI"]); ?>" method="post" style="display: inline;" onsubmit="return confirm('Are you absolutely sure you want to cancel this trip? This action cannot be undone.');">
                    <input type="hidden" name="action" value="cancel_trip">
                    <button type="submit" class="button button-danger">Cancel Trip</button>
                </form>
            <?php endif; ?>

            <?php if ($view_mode === 'carrier_unawarded' && $trip['status'] === 'bidding'): ?>
                <?php if ($bidding_is_open): ?>
                    <h3>Actions</h3>
                    <form action="<?= htmlspecialchars($_SERVER["REQUEST_URI"]); ?>" method="post">
                        <div class="form-group">
                            <label for="eta">Submit Your ETA to Bid</label>
                            <input type="datetime-local" id="eta" name="eta" required>
                        </div>
                        <input type="hidden" name="action" value="place_bid">
                        <button type="submit" class="button button-success">Submit Bid</button>
                        <a href="#" class="button button-secondary">Calculate Mileage</a>
                        <a href="#" class="button button-info">Request Insurance Scan</a>
                    </form>
                <?php else: ?>
                    <div class="disabled-overlay">
                        <h3>Bidding Closed</h3>
                        <p>The bidding window for this trip has closed. No further actions can be taken.</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($view_mode === 'carrier_awarded' && $trip['status'] !== 'cancelled' && $trip['status'] !== 'completed'): ?>
                <h3>Manage Trip</h3>
                <a href="#" class="button button-primary">View Full PHI</a>
                <form action="<?= htmlspecialchars($_SERVER["REQUEST_URI"]); ?>" method="post">
                    <div class="form-group">
                        <label for="awarded_eta">Amend Your ETA</label>
                        <input type="datetime-local" id="awarded_eta" name="awarded_eta" value="<?= $trip['awarded_eta'] ? (new DateTime($trip['awarded_eta']))->format('Y-m-d\TH:i') : ''; ?>" required>
                    </div>
                    <input type="hidden" name="action" value="update_eta">
                    <button type="submit" class="button button-success">Update ETA</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>