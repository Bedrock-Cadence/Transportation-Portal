<?php
// FILE: advanced_debug_view_trip.php
// PURPOSE: To execute the logic of view_trip.php while allowing the user to force a specific view mode to test different UI features.

// --- 1. SETUP DEBUG & FORCING ENVIRONMENT ---
error_reporting(E_ALL);
ini_set('display_errors', 1);

// All possible view modes for the dropdown selector.
$possibleViewModes = ['facility', 'carrier_awarded', 'carrier_unawarded', 'admin'];
// Get the forced view mode from the URL, if provided.
$forcedViewMode = $_GET['force_view_mode'] ?? null;

require_once __DIR__ . '/../../app/init.php';

// --- 2. AUTHORIZATION & DATA FETCHING (MODIFIED FOR DEBUGGING) ---
if (!Auth::isLoggedIn() || !isset($_GET['uuid'])) {
    // For the debugger, we won't redirect. We'll just stop.
    die("Authentication failed or no UUID provided. Please log in and provide a trip UUID in the URL. e.g., ?uuid=xxxx-xxxx-xxxx");
}

$page_title = 'Trip Details (Advanced Debugger)';
$page_message = $_GET['status'] ?? '';
$page_error = '';
$tripService = new TripService();
$encryption = new EncryptionService(ENCRYPTION_KEY);

$trip = $tripService->getTripByUuid($_GET['uuid']);

if (!$trip) {
    die("Trip not found for UUID: " . htmlspecialchars($_GET['uuid']));
}

// Determine the *actual* view mode for comparison.
$actualViewMode = $tripService->determineViewMode($trip);

// OVERRIDE: Use the forced view mode if it's set and valid, otherwise use the actual one.
$viewMode = in_array($forcedViewMode, $possibleViewModes) ? $forcedViewMode : $actualViewMode;


// --- POST REQUEST HANDLING (from view_trip.php) ---
// This section is copied directly to ensure actions like placing bids or updating ETAs can be tested.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $action = $_POST['action'] ?? '';
        switch ($action) {
            case 'cancel_trip':
                if ($viewMode === 'facility') $tripService->cancelTrip($trip['id']);
                Utils::redirect("advanced_debug_view_trip.php?uuid={$trip['uuid']}&force_view_mode={$viewMode}&status=trip_cancelled");
                break;
            case 'place_or_update_bid':
                if ($viewMode === 'carrier_unawarded') $tripService->placeOrUpdateBid($trip['id'], $trip['bidding_closes_at'], $_POST['eta']);
                Utils::redirect("advanced_debug_view_trip.php?uuid={$trip['uuid']}&force_view_mode={$viewMode}&status=bid_placed");
                break;
            case 'retract_bid':
                 if ($viewMode === 'carrier_unawarded') $tripService->retractBid($trip['id']);
                 Utils::redirect("advanced_debug_view_trip.php?uuid={$trip['uuid']}&force_view_mode={$viewMode}&status=bid_retracted");
                break;
            case 'update_eta':
                if ($viewMode === 'carrier_awarded') $tripService->updateAwardedEta($trip['id'], $_POST['awarded_eta']);
                Utils::redirect("advanced_debug_view_trip.php?uuid={$trip['uuid']}&force_view_mode={$viewMode}&status=eta_updated");
                break;
            case 'retract_awarded_trip':
                if ($viewMode === 'carrier_awarded') $tripService->retractAwardedTrip($trip['id'], $_POST['retraction_reason']);
                Utils::redirect("index.php?status=trip_retracted"); // Redirect to index after this major action.
                break;
        }
    } catch (Exception $e) {
        $page_error = $e->getMessage();
    }
}


// --- 3. DATA PREPARATION FOR DISPLAY (from view_trip.php) ---
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

// We still need the header for styling and layout.
require_once 'header.php';
?>

<!-- --- 4. DEBUGGER UI --- -->
<div class="max-w-4xl mx-auto my-4 p-4 bg-blue-100 border-2 border-blue-400 rounded-lg shadow-lg">
    <h2 class="text-xl font-bold text-blue-800">Advanced Debugger Controls</h2>
    <div class="mt-2 text-sm">
        <p><strong>Actual View Mode:</strong> <span class="font-mono bg-blue-200 px-2 py-1 rounded"><?= Utils::e($actualViewMode); ?></span></p>
        <p class="mt-1"><strong>Current (Forced) View Mode:</strong> <span class="font-mono bg-green-200 px-2 py-1 rounded"><?= Utils::e($viewMode); ?></span></p>
    </div>
    <form method="GET" action="advanced_debug_view_trip.php" class="mt-3">
        <input type="hidden" name="uuid" value="<?= Utils::e($trip['uuid']); ?>">
        <label for="force_view_mode" class="block text-sm font-medium text-gray-700">Force View Mode:</label>
        <div class="flex items-center space-x-2 mt-1">
            <select name="force_view_mode" id="force_view_mode" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                <?php foreach ($possibleViewModes as $mode): ?>
                    <option value="<?= $mode ?>" <?= ($viewMode === $mode) ? 'selected' : '' ?>>
                        <?= ucfirst(str_replace('_', ' ', $mode)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md font-semibold">Apply</button>
        </div>
    </form>
</div>


<!-- --- 5. PAGE CONTENT (from view_trip.php) --- -->
<!-- This HTML is copied from view_trip.php and will now render based on the FORCED $viewMode variable -->
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
        <?php if ($page_message): ?><div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">Success: <?= Utils::e($page_message) ?></div><?php endif; ?>

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
                    <?php if (in_array($viewMode, ['facility', 'carrier_awarded', 'admin'])): ?>
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
                    <?php else: ?>
                        <div class="col-span-2 bg-yellow-100 p-3 rounded-md text-sm text-yellow-800">PHI is hidden in '<?= Utils::e($viewMode) ?>' mode.</div>
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
        <!-- The logic inside these blocks will now execute based on the FORCED view mode -->
        <?php if ($viewMode === 'facility' && in_array($trip['status'], ['bidding', 'awarded'])): ?>
            <!-- FACILITY ACTIONS -->
        <?php elseif ($viewMode === 'carrier_unawarded' && $trip['status'] === 'bidding'): ?>
            <!-- CARRIER BIDDING FORM -->
        <?php elseif ($viewMode === 'carrier_awarded' && $trip['status'] === 'awarded'): ?>
            <!-- CARRIER AWARDED FORM -->
        <?php else: ?>
            <p class="text-center text-gray-500">No actions available for the '<?= Utils::e($viewMode) ?>' view mode or current trip status ('<?= Utils::e($trip['status']) ?>').</p>
        <?php endif; ?>
    </div>
</div>

<!-- Modal and Script tags would be here, copied from view_trip.php -->
<!-- For brevity, they are omitted but should be included for full functionality -->

<?php require_once 'footer.php'; ?>
