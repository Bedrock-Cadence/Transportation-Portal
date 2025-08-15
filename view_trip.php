<?php
session_start();
require_once __DIR__ . '/../../app/db_connect.php';
require_once __DIR__ . '/../../app/encryption_service.php'; // Real encryption service
require_once __DIR__ . '/../../app/logging_service.php';   // Real logging service
require_once('header.php');
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
if ($user_role === 'bedrock_admin') {
    $view_mode = 'admin';
} 
elseif (in_array($user_role, ['facility_user', 'facility_superuser']) && $trip['facility_id'] == $entity_id) {
    $view_mode = 'facility';
} 
elseif (in_array($user_role, ['carrier_user', 'carrier_superuser'])) {
    $sql_blacklist = "SELECT 1 FROM facility_carrier_preferences WHERE facility_id = ? AND carrier_id = ? AND preference_type = 'blacklisted' LIMIT 1";
    if ($stmt_blacklist = $mysqli->prepare($sql_blacklist)) {
        $stmt_blacklist->bind_param("ii", $trip['facility_id'], $entity_id);
        $stmt_blacklist->execute();
        $stmt_blacklist->store_result();
        if ($stmt_blacklist->num_rows === 0) {
            if ($trip['carrier_id'] == $entity_id) {
                $view_mode = 'carrier_awarded';
                log_activity($mysqli, $user_id, 'view_awarded_trip', "Awarded carrier (ID: {$entity_id}) viewed trip details for Trip ID: {$trip['id']}.");
            } else {
                $view_mode = 'carrier_unawarded';
            }
        }
        $stmt_blacklist->close();
    }
}

if ($view_mode === 'unauthorized') {
    log_activity($mysqli, $user_id, 'unauthorized_trip_view', "User was denied access to view Trip ID: {$trip['id']}.");
    header("location: trip_board.php?error=unauthorized");
    exit;
}


// --- POST REQUEST HANDLING (Actions) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

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

    // MODIFIED: Carrier places a bid (submits an ETA)
    if ($action === 'place_bid' && $view_mode === 'carrier_unawarded') {
        // MODIFIED: Time comparison is now timezone-aware
        $now_utc = new DateTime('now', new DateTimeZone('UTC'));
        $bidding_closes_utc = new DateTime($trip['bidding_closes_at'], new DateTimeZone('UTC'));
        
        if ($now_utc < $bidding_closes_utc) {
            $eta_local = trim($_POST['eta']);
            if (!empty($eta_local)) {
                // NEW: Convert the local time from the form to UTC for database storage
                $local_time_obj = new DateTime($eta_local, new DateTimeZone(USER_TIMEZONE));
                $local_time_obj->setTimezone(new DateTimeZone('UTC'));
                $eta_for_db = $local_time_obj->format('Y-m-d H:i:s');

                $sql_bid = "INSERT INTO carrier_etas (trip_id, carrier_id, eta) VALUES (?, ?, ?)";
                if ($stmt_bid = $mysqli->prepare($sql_bid)) {
                    // MODIFIED: Use the UTC-converted timestamp
                    $stmt_bid->bind_param("iis", $trip['id'], $entity_id, $eta_for_db);
                    if ($stmt_bid->execute()) {
                        log_activity($mysqli, $user_id, 'bid_placed', "Carrier (ID: {$entity_id}) placed a bid on Trip ID: {$trip['id']} with ETA: {$eta_for_db} UTC.");
                        header("Location: trip_board.php?status=bid_placed");
                        exit;
                    } else {
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

    // MODIFIED: Awarded carrier updates their ETA
    if ($action === 'update_eta' && $view_mode === 'carrier_awarded') {
        $new_eta_local = trim($_POST['awarded_eta']);
        if (!empty($new_eta_local)) {
            // NEW: Convert the local time from the form to UTC for database storage
            $local_time_obj = new DateTime($new_eta_local, new DateTimeZone(USER_TIMEZONE));
            $local_time_obj->setTimezone(new DateTimeZone('UTC'));
            $eta_for_db = $local_time_obj->format('Y-m-d H:i:s');

            $sql_update_eta = "UPDATE trips SET awarded_eta = ?, updated_at = NOW() WHERE id = ? AND carrier_id = ?";
            if ($stmt_update = $mysqli->prepare($sql_update_eta)) {
                // MODIFIED: Use the UTC-converted timestamp
                $stmt_update->bind_param("sii", $eta_for_db, $trip['id'], $entity_id);
                if ($stmt_update->execute()) {
                    log_activity($mysqli, $user_id, 'eta_updated', "Awarded carrier updated ETA for Trip ID: {$trip['id']} to {$eta_for_db} UTC.");
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

// Decrypt PHI data
$patient_last_name = decrypt_data($trip['patient_last_name_encrypted'], ENCRYPTION_KEY);
$patient_dob = decrypt_data($trip['patient_dob_encrypted'], ENCRYPTION_KEY);
$patient_diagnosis = decrypt_data($trip['diagnosis_encrypted'], ENCRYPTION_KEY);
$special_equipment = decrypt_data($trip['special_equipment_encrypted'], ENCRYPTION_KEY);
$isolation_precautions = decrypt_data($trip['isolation_precautions_encrypted'], ENCRYPTION_KEY);

// NEW: Helper function to convert UTC to user's local time for display
function format_utc_to_user_time($utc_string, $format = 'M j, Y, g:i A') {
    if (empty($utc_string)) {
        return null;
    }
    try {
        $utc_dt = new DateTime($utc_string, new DateTimeZone('UTC'));
        $utc_dt->setTimezone(new DateTimeZone(USER_TIMEZONE));
        return $utc_dt->format($format);
    } catch (Exception $e) {
        // Log error and return a safe value
        error_log("Time formatting error: " . $e->getMessage());
        return '[Invalid Time]';
    }
}

// MODIFIED: Use the new helper function for all displayed times
$patient_birth_year = $patient_dob ? (new DateTime($patient_dob))->format('Y') : '[N/A]';
$created_at_formatted = format_utc_to_user_time($trip['created_at']);
$updated_at_formatted = format_utc_to_user_time($trip['updated_at']);
$appointment_at_formatted = $trip['appointment_at'] ? format_utc_to_user_time($trip['appointment_at']) : 'ASAP';
$awarded_eta_for_form = $trip['awarded_eta'] ? format_utc_to_user_time($trip['awarded_eta'], 'Y-m-d\TH:i') : '';

// UPDATED: This function now returns Tailwind CSS classes for status badges.
function format_status($status) {
    $status_text = htmlspecialchars(ucfirst($status));
    $base_classes = 'text-xs font-medium mr-2 px-2.5 py-0.5 rounded-full';
    switch ($status) {
        case 'bidding': return "<span class='bg-green-100 text-green-800 {$base_classes}'>Open for Bids</span>";
        case 'awarded': return "<span class='bg-yellow-100 text-yellow-800 {$base_classes}'>Awarded</span>";
        case 'completed': return "<span class='bg-gray-100 text-gray-800 {$base_classes}'>Completed</span>";
        case 'cancelled': return "<span class='bg-red-100 text-red-800 {$base_classes}'>Cancelled</span>";
        default: return "<span class='bg-blue-100 text-blue-800 {$base_classes}'>{$status_text}</span>";
    }
}

// MODIFIED: Time comparison is now timezone-aware and more robust
$bidding_is_open = false;
if ($trip['bidding_closes_at']) {
    $now_utc = new DateTime('now', new DateTimeZone('UTC'));
    $bidding_closes_utc = new DateTime($trip['bidding_closes_at'], new DateTimeZone('UTC'));
    $bidding_is_open = $now_utc < $bidding_closes_utc;
}
?>

<div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200">
    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
        <div>
            <h2 class="text-xl font-semibold text-gray-800">Trip Details</h2>
            <p class="text-sm text-gray-500 font-mono"><?= htmlspecialchars($trip['uuid']); ?></p>
        </div>
        <?= format_status($trip['status']); ?>
    </div>

    <div class="p-6 space-y-8">
        <?php if (!empty($page_message)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4" role="alert">
                <p class="font-bold">Success</p>
                <p><?= htmlspecialchars($page_message); ?></p>
            </div>
        <?php endif; ?>
        <?php if (!empty($page_error)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert">
                <p class="font-bold">Error</p>
                <p><?= htmlspecialchars($page_error); ?></p>
            </div>
        <?php endif; ?>
        
        <?php if ($view_mode === 'admin'): ?>
            <div class="bg-yellow-100 text-yellow-800 p-3 rounded-md text-sm text-center font-semibold">
                Admin Read-Only View
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="space-y-6">
                <h3 class="text-lg font-medium text-gray-900 border-b pb-2">Route Details</h3>
                <dl class="space-y-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Pick-up Address</dt>
                        <dd class="mt-1 text-base text-gray-900">
                            <?= htmlspecialchars($trip['origin_name']); ?><br>
                            <?= htmlspecialchars($trip['origin_street']); ?><br>
                            <?= htmlspecialchars($trip['origin_city']); ?>, <?= htmlspecialchars($trip['origin_state']); ?> <?= htmlspecialchars($trip['origin_zip']); ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Drop-off Address</dt>
                        <dd class="mt-1 text-base text-gray-900">
                            <?= htmlspecialchars($trip['destination_name']); ?><br>
                            <?= htmlspecialchars($trip['destination_street']); ?><br>
                            <?= htmlspecialchars($trip['destination_city']); ?>, <?= htmlspecialchars($trip['destination_state']); ?> <?= htmlspecialchars($trip['destination_zip']); ?>
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="space-y-6">
                <h3 class="text-lg font-medium text-gray-900 border-b pb-2">Patient & Trip Information</h3>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-6">
                    <?php if ($view_mode === 'facility' || $view_mode === 'carrier_awarded' || $view_mode === 'admin'): ?>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Patient Last Name</dt>
                            <dd class="mt-1 text-base text-gray-900"><?= htmlspecialchars($patient_last_name ?: '[Encrypted]'); ?></dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Patient Year of Birth</dt>
                            <dd class="mt-1 text-base text-gray-900"><?= htmlspecialchars($patient_birth_year); ?></dd>
                        </div>
                    <?php endif; ?>
                     <div>
                        <dt class="text-sm font-medium text-gray-500">Appointment Time</dt>
                        <dd class="mt-1 text-base text-gray-900"><?= htmlspecialchars($appointment_at_formatted); ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Isolation Precautions</dt>
                        <dd class="mt-1 text-base text-gray-900"><?= htmlspecialchars($isolation_precautions ?: 'None specified'); ?></dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-sm font-medium text-gray-500">Special Equipment</dt>
                        <dd class="mt-1 text-base text-gray-900"><?= htmlspecialchars($special_equipment ?: 'None specified'); ?></dd>
                    </div>
                    <?php if ($view_mode !== 'facility'): ?>
                        <div class="sm:col-span-2">
                            <dt class="text-sm font-medium text-gray-500">Diagnosis</dt>
                            <dd class="mt-1 text-base text-gray-900"><?= htmlspecialchars($patient_diagnosis ?: 'Not provided'); ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <div>
            <h3 class="text-lg font-medium text-gray-900 border-b pb-2">Timeline</h3>
            <dl class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-6">
                <div>
                    <dt class="text-sm font-medium text-gray-500">Trip Created</dt>
                    <dd class="mt-1 text-base text-gray-900"><?= $created_at_formatted; ?></dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">Last Update</dt>
                    <dd class="mt-1 text-base text-gray-900"><?= $updated_at_formatted; ?></dd>
                </div>
            </dl>
        </div>
    </div>

    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
        <?php if ($view_mode === 'facility' && $trip['status'] !== 'cancelled' && $trip['status'] !== 'completed'): ?>
            <div class="flex justify-end items-center space-x-3">
                <h3 class="text-lg font-medium text-gray-900 flex-grow">Actions</h3>
                <a href="modify_trip.php?uuid=<?= htmlspecialchars($trip['uuid']); ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Modify Trip</a>
                <form action="<?= htmlspecialchars($_SERVER["REQUEST_URI"]); ?>" method="post" class="inline" onsubmit="return confirm('Are you absolutely sure you want to cancel this trip? This action cannot be undone.');">
                    <input type="hidden" name="action" value="cancel_trip">
                    <button type="submit" class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">Cancel Trip</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($view_mode === 'carrier_unawarded' && $trip['status'] === 'bidding'): ?>
            <?php if ($bidding_is_open): ?>
                <div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Place Your Bid</h3>
                    <form action="<?= htmlspecialchars($_SERVER["REQUEST_URI"]); ?>" method="post" class="sm:flex sm:items-end sm:space-x-3">
                        <div class="w-full sm:w-auto flex-grow">
                            <label for="eta" class="block text-sm font-medium text-gray-700">Submit Your ETA</label>
                            <input type="datetime-local" id="eta" name="eta" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>
                        <input type="hidden" name="action" value="place_bid">
                        <button type="submit" class="mt-2 sm:mt-0 w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">Submit Bid</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="text-center bg-gray-100 p-4 rounded-md">
                    <h3 class="text-lg font-medium text-gray-900">Bidding Closed</h3>
                    <p class="mt-1 text-sm text-gray-600">The bidding window for this trip has closed.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($view_mode === 'carrier_awarded' && $trip['status'] !== 'cancelled' && $trip['status'] !== 'completed'): ?>
             <div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Manage Trip</h3>
                 <form action="<?= htmlspecialchars($_SERVER["REQUEST_URI"]); ?>" method="post" class="sm:flex sm:items-end sm:space-x-3">
                    <div class="w-full sm:w-auto flex-grow">
                        <label for="awarded_eta" class="block text-sm font-medium text-gray-700">Amend Your ETA</label>
                        <input type="datetime-local" id="awarded_eta" name="awarded_eta" value="<?= $awarded_eta_for_form; ?>" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                    <input type="hidden" name="action" value="update_eta">
                    <div class="mt-2 sm:mt-0 flex space-x-3">
                        <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">Update ETA</button>
                        <a href="#" class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">View Full PHI</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>