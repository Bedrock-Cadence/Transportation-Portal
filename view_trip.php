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

// --- CORRECTED LOGIC ---
$viewMode = $tripService->determineViewMode($trip);

if ($viewMode === 'unauthorized') {
LoggingService::log(Auth::user('user_id'), null, 'unauthorized_trip_view', "User denied access to Trip ID: {$trip['id']}.");
Utils::redirect('index.php?error=unauthorized');
}

// Now that we know the user is authorized, log the successful view.
LoggingService::log(Auth::user('user_id'), null, 'trip_viewed', "User viewed Trip ID: {$trip['id']}.", ['trip_id' => $trip['id']]);

// --- 2. POST REQUEST HANDLING (ACTIONS) ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $action = $_POST['action'] ?? '';
        // Use a CSRF token check for all POST actions
        // if (!Utils::verifyCsrfToken($_POST['csrf_token'])) {
        //     throw new Exception("Invalid form submission.");
        // }

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

// --- 3. DATA PREPARATION FOR DISPLAY ---
$phi = [];
$userCarrierId = Auth::user('entity_type') === 'carrier' ? Auth::user('entity_id') : null;
$myBid = null;
$hasUpdatedEta = false; // Default value

// --- Start of Corrected Block ---

// --- A. Decrypt Sensitive PHI Fields ---
// This block is assumed to be working correctly per your debugging.
$phi = [];
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


// --- B. Fetch Carrier-Specific Information with DIRECT DEBUGGING ---
$myBid = null;
$hasUpdatedEta = false;

echo "<pre style='background: #eee; padding: 10px; border: 1px solid #ccc;'>";
echo "<strong>DEBUGGING OUTPUT:</strong>\n";
echo "Current viewMode: " . htmlspecialchars($viewMode) . "\n";

if ($viewMode === 'carrier_unawarded') {
    $userCarrierId = Auth::user('entity_type') === 'carrier' ? Auth::user('entity_id') : null;
    if ($userCarrierId) {
        echo "-> Calling getBidByCarrier for Trip ID: {$trip['id']} and Carrier ID: {$userCarrierId}...\n";
        
        // The script will die here if the method fails.
        $myBid = $tripService->getBidByCarrier($trip['id'], $userCarrierId);
        
        echo "-> Successfully completed getBidByCarrier call.\n";
        echo "-> Result of getBidByCarrier: ";
        var_dump($myBid);
    } else {
        echo "-> User is not a carrier or has no entity_id. Skipping getBidByCarrier.\n";
    }
}

if ($viewMode === 'carrier_awarded') {
    $userCarrierId = Auth::user('entity_type') === 'carrier' ? Auth::user('entity_id') : null;
    if ($userCarrierId) {
        echo "-> Calling hasCarrierUpdatedEta for Trip ID: {$trip['id']} and Carrier ID: {$userCarrierId}...\n";
        
        // The script will die here if the method fails.
        $hasUpdatedEta = $tripService->hasCarrierUpdatedEta($trip['id'], $userCarrierId);
        
        echo "-> Successfully completed hasCarrierUpdatedEta call.\n";
        echo "-> Result of hasCarrierUpdatedEta: ";
        var_dump($hasUpdatedEta);
    } else {
        echo "-> User is not a carrier or has no entity_id. Skipping hasCarrierUpdatedEta.\n";
    }
}

echo "\n<strong>END OF DEBUGGING.</strong> Script halted intentionally.";
echo "</pre>";
die(); // Stop the script here to see the output.


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
        <?php if ($page_message === 'eta_updated'): ?><div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">ETA successfully updated.</div><?php endif; ?>
        <?php if ($page_message === 'bid_placed'): ?><div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">Your bid has been successfully submitted.</div><?php endif; ?>
        <?php if ($page_message === 'bid_retracted'): ?><div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative" role="alert">Your bid has been retracted.</div><?php endif; ?>


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
    
    <!-- Action Forms Section -->
    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">

        <!-- FACILITY ACTIONS -->
        <?php if ($viewMode === 'facility' && in_array($trip['status'], ['bidding', 'awarded'])): ?>
            <div class="flex justify-end items-center space-x-3">
                <a href="edit_trip.php?uuid=<?= Utils::e($trip['uuid']) ?>" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md font-semibold">Edit Trip</a>
                <form action="view_trip.php?uuid=<?= Utils::e($trip['uuid']) ?>" method="post" onsubmit="return confirm('Are you sure you want to cancel this trip? This action cannot be undone.');">
                    <input type="hidden" name="action" value="cancel_trip">
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md font-semibold">Cancel Trip</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- CARRIER BIDDING FORM -->
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

        <!-- CARRIER AWARDED FORM -->
        <?php if ($viewMode === 'carrier_awarded' && $trip['status'] === 'awarded'): ?>
            <div class="max-w-lg mx-auto">
                <form action="view_trip.php?uuid=<?= Utils::e($trip['uuid']) ?>" method="post">
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Manage Trip ETA</h3>
                    <?php if (!$hasUpdatedEta): ?>
                        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4" role="alert">
                            <p class="font-bold">Important Notice</p>
                            <p>You may only update your ETA once. If the new ETA is not suitable, the scheduling facility may retract this trip and make it biddable again.</p>
                        </div>
                        <div class="sm:flex sm:items-end sm:space-x-3">
                            <div class="w-full sm:w-auto flex-grow">
                                <label for="awarded_eta" class="sr-only">Amend Your ETA (Local Time)</label>
                                <input type="datetime-local" id="awarded_eta" name="awarded_eta" value="<?= Utils::formatUtcToUserTime($trip['awarded_eta'], 'Y-m-d\TH:i') ?>" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            </div>
                            <input type="hidden" name="action" value="update_eta">
                            <button type="submit" class="mt-2 sm:mt-0 w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">Update ETA</button>
                        </div>
                    <?php else: ?>
                         <p class="text-gray-600">Your ETA has already been updated for this trip.</p>
                    <?php endif; ?>
                </form>

                <div class="mt-6 text-center">
                    <button onclick="document.getElementById('retraction-modal').style.display='block'" class="text-red-600 hover:text-red-800 font-semibold">Retract Awarded Trip</button>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Retraction Modal -->
<div id="retraction-modal" class="hidden fixed z-10 inset-0 overflow-y-auto">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form action="view_trip.php?uuid=<?= Utils::e($trip['uuid']) ?>" method="post">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                Retract Awarded Trip
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500">
                                    Are you sure you want to retract this trip? This will re-broadcast the trip for bidding and you will not be able to bid on it again. Please provide a reason for the retraction.
                                </p>
                                <textarea name="retraction_reason" required class="mt-3 w-full rounded-md border-gray-300 shadow-sm" placeholder="e.g., Vehicle mechanical issue"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <input type="hidden" name="action" value="retract_awarded_trip">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 sm:ml-3 sm:w-auto sm:text-sm">
                        Confirm Retraction
                    </button>
                    <button type="button" onclick="document.getElementById('retraction-modal').style.display='none'" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    const biddingSection = document.getElementById('bidding-section');
    if (!biddingSection) return;

    const countdownElement = document.getElementById('countdown-timer');
    const biddingForm = document.getElementById('bidding-form');
    // Important: The 'Z' or 'UTC' is crucial for JavaScript to interpret the date as UTC
    const biddingClosesAt = new Date("<?= Utils::e($trip['bidding_closes_at']) ?> UTC".replace(/-/g, '/'));

    function updateCountdown() {
        const now = new Date();
        const timeLeft = biddingClosesAt - now;

        if (timeLeft <= 0) {
            countdownElement.innerHTML = "Bidding has closed.";
            countdownElement.classList.remove('text-gray-800');
            countdownElement.classList.add('text-red-600');
            // Disable form elements
            Array.from(biddingForm.elements).forEach(el => el.disabled = true);
            clearInterval(timerInterval);
            return;
        }

        const hours = Math.floor((timeLeft % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);

        countdownElement.innerHTML = `Bidding closes in: ${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    }

    const timerInterval = setInterval(updateCountdown, 1000);
    updateCountdown(); // Initial call
});
</script>

<?php require_once 'footer.php'; ?>