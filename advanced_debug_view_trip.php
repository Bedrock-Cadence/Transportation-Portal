<?php
// FILE: public_html/portal/view_trip.php

require_once __DIR__ . '/../../app/init.php';

// --- 1. AUTHORIZATION & DATA FETCHING ---
if (!Auth::isLoggedIn() || !isset($_GET['uuid'])) {
    Utils::redirect('index.php');
}

$page_title = 'Trip Details';
$page_message = $_GET['status'] ?? ''; // For success messages on redirect
$page_error = '';
$tripService = new TripService();
$encryption = new EncryptionService(ENCRYPTION_KEY);

$trip = $tripService->getTripByUuid($_GET['uuid']);

if (!$trip) {
    LoggingService::log(Auth::user('user_id'), null, 'trip_not_found', 'User attempted to view non-existent UUID: ' . $_GET['uuid']);
    Utils::redirect('index.php?error=notfound');
}

$viewMode = $tripService->determineViewMode($trip);

// Log the view event after authorization is confirmed
LoggingService::log(Auth::user('user_id'), null, 'trip_viewed', "User viewed Trip ID: {$trip['id']}.", ['trip_id' => $trip['id']]);

if ($viewMode === 'unauthorized') {
    LoggingService::log(Auth::user('user_id'), null, 'unauthorized_trip_view', "User denied access to Trip ID: {$trip['id']}.");
    Utils::redirect('index.php?error=unauthorized');
}

// --- 2. POST REQUEST HANDLING (ACTIONS) ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // This section is skipped on page load
}

// --- 3. DATA PREPARATION FOR DISPLAY ---
$phi = [];
$userCarrierId = Auth::user('entity_type') === 'carrier' ? Auth::user('entity_id') : null;
$myBid = null;
$hasUpdatedEta = false; 

if (in_array($viewMode, ['facility', 'carrier_awarded'])) {
    $phi['first_name'] = $encryption->decrypt($trip['patient_first_name_encrypted']);
    $phi['last_name'] = $encryption->decrypt($trip['patient_last_name_encrypted']);
    $decrypted_dob = $encryption->decrypt($trip['patient_dob_encrypted']);
    $phi['ssn_last4'] = $encryption->decrypt($trip['patient_ssn_last4_encrypted']);
    if (!empty($decrypted_dob) && ($timestamp = strtotime($decrypted_dob)) !== false) {
        $phi['dob_formatted'] = date('m/d/Y', $timestamp);
    } else {
        $phi['dob_formatted'] = '[N/A]';
    }
}

$phi['diagnosis'] = $encryption->decrypt($trip['diagnosis_encrypted']);
$phi['equipment'] = $encryption->decrypt($trip['special_equipment_encrypted']);
$phi['isolation'] = $encryption->decrypt($trip['isolation_precautions_encrypted']);
$decrypted_weight_kg = $encryption->decrypt($trip['patient_weight_kg_encrypted']);
$decrypted_height_in = $encryption->decrypt($trip['patient_height_in_encrypted']);

$phi['weight_lbs'] = is_numeric($decrypted_weight_kg) ? round($decrypted_weight_kg * 2.20462) : 'N/A';
if (is_numeric($decrypted_height_in) && $decrypted_height_in > 0) {
    $phi['height_formatted'] = floor($decrypted_height_in / 12) . "' " . ($decrypted_height_in % 12) . '"';
} else {
    $phi['height_formatted'] = 'N/A';
}

if ($viewMode === 'carrier_unawarded') {
    $myBid = $tripService->getBidByCarrier($trip['id'], $userCarrierId);
}
if ($viewMode === 'carrier_awarded') {
    $hasUpdatedEta = $tripService->hasCarrierUpdatedEta($trip['id'], $userCarrierId);
}

// CHECKPOINT 1
die("DEBUG: Data preparation is complete. If you see this, the error is in the HTML below.");

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
        <?php if ($page_message): ?><div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">Success messages here...</div><?php endif; ?>


        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
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
            
            <?php // CHECKPOINT 2
            die("DEBUG: Route Details rendered. The error is in the Patient Info block."); ?>

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
                            <dd class="mt-1 text-base text-gray-900"><?= Utils::e($phi['dob_formatted']); ?></dd>
                        </div>
                         <div>
                            <dt class="text-sm font-medium text-gray-500">SSN (Last 4)</dt>
                            <dd class="mt-1 text-base text-gray-900"><?= Utils::e($phi['ssn_last4'] ?: '[N/A]'); ?></dd>
                        </div>
                    <?php endif; ?>

                    <?php // All authorized viewers can see this information ?>
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

    <?php // CHECKPOINT 3
    die("DEBUG: Patient Info rendered. The error is in the Action Forms block below."); ?>
    
    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
        <?php if ($viewMode === 'carrier_unawarded' && $trip['status'] === 'bidding'): ?>
            <div id="bidding-section" class="text-center">
                <div id="countdown-timer" class="text-2xl font-bold text-gray-800 mb-4"></div>
                <form id="bidding-form" action="view_trip.php?uuid=<?= Utils::e($trip['uuid']) ?>" method="post" class="max-w-lg mx-auto">
                    <h3 class="text-lg font-medium text-gray-900 mb-2"><?= $myBid ? 'Update Your Bid' : 'Place Your Bid' ?></h3>
                    <div class="sm:flex sm:items-end sm:space-x-3">
                        <div class="w-full sm:w-auto flex-grow">
                            <label for="eta" class="sr-only">Submit Your ETA (Local Time)</label>
                            <input type="datetime-local" id="eta" name="eta" value="<?= (!empty($myBid) && !empty($myBid['eta'])) ? Utils::formatUtcToUserTime($myBid['eta'], 'Y-m-d\TH:i') : '' ?>" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <input type="hidden" name="action" value="place_or_update_bid">
                        <button type="submit" class="mt-2 sm:mt-0 w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 border border-transparent text-base font-medium rounded-md text-white bg-green-600 hover:bg-green-700"><?= $myBid ? 'Update Bid' : 'Submit Bid' ?></button>
                    </div>
                </form>
                <?php if ($myBid): ?>
                <form action="view_trip.php?uuid=<?= Utils::e($trip['uuid']) ?>" method="post" onsubmit="return confirm('Are you sure you want to retract your bid?');" class="mt-3">
                     <input type="hidden" name="action" value="retract_bid">
                     <button type="submit" class="text-sm text-red-600 hover:text-red-800">Retract Bid</button>
                </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php // CHECKPOINT 4
die("DEBUG: Action Forms rendered. The error is in the modal, script, or footer."); ?>


<script>
// Javascript would go here
</script>

<?php require_once 'footer.php'; ?>