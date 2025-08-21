<?php
// FILE: public_html/portal/view_trip.php

// --- Aggressive Debugging ---
// This will force any and all errors to be displayed on the screen.
error_reporting(E_ALL);
ini_set('display_errors', 1);

// We load the core application files first. A white screen after this point means the error is below.
require_once __DIR__ . '/../../app/init.php';

// --- Admin-Specific Debug Path ---
// We will check if the logged-in user is an admin. If so, we run a special diagnostic
// that bypasses the normal page logic to find the exact point of failure.
if (Auth::user('entity_type') === 'bedrock' || Auth::user('user_role') === 'admin') {
    
    echo "<strong>Bedrock Cadence - Admin Debug Mode</strong><br>";
    echo "----------------------------------------------------<br>";
    
    if (!isset($_GET['uuid'])) {
        die("DEBUG ERROR: No trip UUID was provided in the URL.");
    }
    
    $uuid = $_GET['uuid'];
    echo "DEBUG: Attempting to fetch trip with UUID: " . htmlspecialchars($uuid) . "<br>";

    try {
        // We will connect to the database directly, bypassing the TripService for now.
        $db = Database::getInstance();
        echo "DEBUG: Database instance created successfully.<br>";

        // We will attempt to fetch the trip data directly.
        $sql = "SELECT * FROM trips WHERE uuid = :uuid LIMIT 1";
        $trip = $db->fetch($sql, [':uuid' => $uuid]);

        if ($trip) {
            echo "<font color='green'>DEBUG SUCCESS: Trip data fetched directly from the database.</font><br>";
            echo "If you can see this, the database connection and the trips table are working correctly.<br>";
            echo "<pre>";
            print_r($trip);
            echo "</pre>";
        } else {
            echo "<font color='orange'>DEBUG NOTICE: No trip was found with that UUID in the database.</font><br>";
        }

    } catch (Throwable $t) {
        // If there's an error during the database operation, we will catch it here.
        echo "<font color='red'>DEBUG EXCEPTION: An error occurred during the direct database fetch.</font><br>";
        echo "<pre>" . htmlspecialchars($t->getMessage()) . "</pre>";
    }

    // The script will stop here for the admin.
    die("<br>--- DEBUGGING COMPLETE ---");
}

// --- Regular Page Logic (for non-admins) ---
// If the user is not an admin, the script will continue and load the page as normal.

$page_title = 'Trip Details';
$page_message = $_GET['status'] ?? '';
$page_error = '';
$tripService = new TripService();
$encryption = new EncryptionService(ENCRYPTION_KEY);

$trip = $tripService->getTripByUuid($_GET['uuid']);

if (!$trip) {
    LoggingService::log(Auth::user('user_id'), null, 'trip_not_found', 'User attempted to view non-existent UUID: ' . $_GET['uuid']);
    Utils::redirect('index.php?error=notfound');
}

$viewMode = $tripService->determineViewMode($trip);

LoggingService::log(Auth::user('user_id'), null, 'trip_viewed', "User viewed Trip ID: {$trip['id']}.", ['trip_id' => $trip['id']]);

if ($viewMode === 'unauthorized') {
    LoggingService::log(Auth::user('user_id'), null, 'unauthorized_trip_view', "User denied access to Trip ID: {$trip['id']}.");
    Utils::redirect('index.php?error=unauthorized');
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $action = $_POST['action'] ?? '';
        switch ($action) {
            case 'cancel_trip':
                if ($viewMode === 'facility') $tripService->cancelTrip($trip['id']);
                Utils::redirect("index.php?status=trip_cancelled");
                break;
            case 'place_or_update_bid':
                if ($viewMode === 'carrier_unawarded') $tripService->placeOrUpdateBid($trip['id'], $trip['bidding_closes_at'], $_POST['eta']);
                Utils::redirect("view_trip.php?uuid={$trip['uuid']}&status=bid_placed");
                break;
            case 'retract_bid':
                 if ($viewMode === 'carrier_unawarded') $tripService->retractBid($trip['id']);
                 Utils::redirect("view_trip.php?uuid={$trip['uuid']}&status=bid_retracted");
                break;
            case 'update_eta':
                if ($viewMode === 'carrier_awarded') $tripService->updateAwardedEta($trip['id'], $_POST['awarded_eta']);
                Utils::redirect("view_trip.php?uuid={$trip['uuid']}&status=eta_updated");
                break;
            case 'retract_awarded_trip':
                if ($viewMode === 'carrier_awarded') $tripService->retractAwardedTrip($trip['id'], $_POST['retraction_reason']);
                Utils::redirect("index.php?status=trip_retracted");
                break;
        }
    } catch (Exception $e) {
        $page_error = $e->getMessage();
    }
}

$phi = [];
$userCarrierId = Auth::user('entity_type') === 'carrier' ? Auth::user('entity_id') : null;
$myBid = null;
$hasUpdatedEta = false;

if (in_array($viewMode, ['facility', 'carrier_awarded'])) {
    $phi['first_name'] = $encryption->decrypt($trip['patient_first_name_encrypted']);
    $phi['last_name'] = $encryption->decrypt($trip['patient_last_name_encrypted']);
    $phi['dob'] = $encryption->decrypt($trip['patient_dob_encrypted']);
    $phi['ssn_last4'] = $encryption->decrypt($trip['patient_ssn_last4_encrypted']);
}

$phi['diagnosis'] = $encryption->decrypt($trip['diagnosis_encrypted']);
$phi['equipment'] = $encryption->decrypt($trip['special_equipment_encrypted']);
$phi['isolation'] = $encryption->decrypt($trip['isolation_precautions_encrypted']);
$phi['weight_kg'] = $encryption->decrypt($trip['patient_weight_kg_encrypted']);
$phi['height_in'] = $encryption->decrypt($trip['patient_height_in_encrypted']);

$phi['weight_lbs'] = $phi['weight_kg'] ? round($phi['weight_kg'] * 2.20462) : 'N/A';
$phi['height_formatted'] = $phi['height_in'] ? floor($phi['height_in'] / 12) . "' " . ($phi['height_in'] % 12) . '"' : 'N/A';

if ($viewMode === 'carrier_unawarded') {
    $myBid = $tripService->getBidByCarrier($trip['id'], $userCarrierId);
}

if ($viewMode === 'carrier_awarded') {
    $hasUpdatedEta = $tripService->hasCarrierUpdatedEta($trip['id'], $userCarrierId);
}

require_once 'header.php';
?>

<div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200 max-w-4xl mx-auto">
    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
        <div>
            <h2 class="text-xl font-semibold text-gray-800">Trip Details</h2>
            <p class="text-sm text-gray-500 font-mono"><?= Utils::e($trip['uuid']); ?></p>
        </div>
        <?= Utils::formatTripStatus($trip['status']); ?>
    </div>

    <div class="p-6 space-y-8">
        <?php if ($page_error): ?><div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert"><?= Utils::e($page_error) ?></div><?php endif; ?>
        <?php if ($page_message): ?><div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert"><?= Utils::e($page_message) ?></div><?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Route Details -->
            <div class="space-y-6">
                <h3 class="text-lg font-medium text-gray-900 border-b pb-2">Route Details</h3>
                <dl class="space-y-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Pick-up Address</dt>
                        <dd class="mt-1 text-base text-gray-900"><?= Utils::e($trip['origin_street']); ?><br><?= Utils::e($trip['origin_city'] . ', ' . $trip['origin_state'] . ' ' . $trip['origin_zip']); ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Drop-off Address</dt>
                        <dd class="mt-1 text-base text-gray-900"><?= Utils::e($trip['destination_street']); ?><br><?= Utils::e($trip['destination_city'] . ', ' . $trip['destination_state'] . ' ' . $trip['destination_zip']); ?></dd>
                    </div>
                     <div>
                        <dt class="text-sm font-medium text-gray-500">Appointment Time</dt>
                        <dd class="mt-1 text-base text-gray-900"><?= $trip['appointment_at'] ? Utils::formatUtcToUserTime($trip['appointment_at']) : 'ASAP'; ?></dd>
                    </div>
                </dl>
            </div>
            
            <!-- Patient & Trip Information -->
            <div class="space-y-6">
                <h3 class="text-lg font-medium text-gray-900 border-b pb-2">Patient & Trip Information</h3>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-6">
                    <?php if (in_array($viewMode, ['facility', 'carrier_awarded'])): ?>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Patient Name</dt>
                            <dd class="mt-1 text-base text-gray-900"><?= Utils::e($phi['first_name'] . ' ' . $phi['last_name']); ?></dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Date of Birth</dt>
                            <dd class="mt-1 text-base text-gray-900"><?= Utils::e($phi['dob'] ? (new DateTime($phi['dob']))->format('m/d/Y') : '[N/A]'); ?></dd>
                        </div>
                         <div>
                            <dt class="text-sm font-medium text-gray-500">SSN (Last 4)</dt>
                            <dd class="mt-1 text-base text-gray-900"><?= Utils::e($phi['ssn_last4'] ?: '[N/A]'); ?></dd>
                        </div>
                    <?php endif; ?>

                    <div>
                        <dt class="text-sm font-medium text-gray-500">Primary Diagnosis</dt>
                        <dd class="mt-1 text-base text-gray-900"><?= Utils::e($phi['diagnosis'] ?: 'Not Provided'); ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Special Equipment</dt>
                        <dd class="mt-1 text-base text-gray-900"><?= Utils::e($phi['equipment'] ?: 'None'); ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Isolation Precautions</dt>
                        <dd class="mt-1 text-base text-gray-900"><?= Utils::e($phi['isolation'] ?: 'None'); ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Height</dt>
                        <dd class="mt-1 text-base text-gray-900"><?= Utils::e($phi['height_formatted']); ?></dd>
                    </div>
                     <div>
                        <dt class="text-sm font-medium text-gray-500">Weight</dt>
                        <dd class="mt-1 text-base text-gray-900"><?= Utils::e($phi['weight_lbs']) . ' lbs'; ?></dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
    
    <!-- Action Forms Section -->
    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
        <!-- Forms for facility and carriers go here -->
    </div>
</div>

<!-- Modal and Script sections go here -->
<?php require_once 'footer.php'; ?>