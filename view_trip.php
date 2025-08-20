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
    Utils::redirect('view_trip.php?error=notfound');
}

$viewMode = $tripService->determineViewMode($trip);

if ($viewMode === 'unauthorized') {
    LoggingService::log(Auth::user('user_id'), null, 'unauthorized_trip_view', "User denied access to Trip ID: {$trip['id']}.");
    Utils::redirect('view_trip.php?error=unauthorized');
}

// --- 2. POST REQUEST HANDLING (ACTIONS) ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $action = $_POST['action'] ?? '';
        switch ($action) {
            case 'cancel_trip':
                if ($viewMode === 'facility') $tripService->cancelTrip($trip['id']);
                Utils::redirect("view_trip.php?status=trip_cancelled");
                break;
            case 'place_bid':
                if ($viewMode === 'carrier_unawarded') $tripService->placeBid($trip['id'], $trip['bidding_closes_at'], $_POST['eta']);
                Utils::redirect("view_trip.php?status=bid_placed");
                break;
            case 'update_eta':
                if ($viewMode === 'carrier_awarded') $tripService->updateAwardedEta($trip['id'], $_POST['awarded_eta']);
                Utils::redirect("view_trip.php?uuid={$trip['uuid']}&status=eta_updated");
                break;
        }
    } catch (Exception $e) {
        $page_error = $e->getMessage();
    }
}

// --- 3. DATA PREPARATION FOR DISPLAY ---
$phi = [];
$phi['last_name'] = $encryption->decrypt($trip['patient_last_name_encrypted']);
$phi['dob'] = $encryption->decrypt($trip['patient_dob_encrypted']);
// Decrypt other fields as needed for display...

$biddingIsOpen = new DateTime() < new DateTime($trip['bidding_closes_at']);

require_once 'header.php';
?>

<div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200">
    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
        <div>
            <h2 class="text-xl font-semibold text-gray-800">Trip Details</h2>
            <p class="text-sm text-gray-500 font-mono"><?= Utils::e($trip['uuid']); ?></p>
        </div>
        <?= Utils::formatTripStatus($trip['status']); ?>
    </div>

    <div class="p-6 space-y-8">
        <?php if ($page_error): ?><div class="bg-red-100 text-red-700 p-4 rounded"><?= Utils::e($page_error) ?></div><?php endif; ?>
        <?php if ($page_message === 'eta_updated'): ?><div class="bg-green-100 text-green-700 p-4 rounded">ETA successfully updated.</div><?php endif; ?>

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
                </dl>
            </div>
            
            <div class="space-y-6">
                <h3 class="text-lg font-medium text-gray-900 border-b pb-2">Patient & Trip Information</h3>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-6">
                    <?php if (in_array($viewMode, ['facility', 'carrier_awarded', 'admin'])): ?>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Patient Last Name</dt>
                            <dd class="mt-1 text-base text-gray-900"><?= Utils::e($phi['last_name'] ?: '[Encrypted]'); ?></dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Patient Year of Birth</dt>
                            <dd class="mt-1 text-base text-gray-900"><?= Utils::e($phi['dob'] ? (new DateTime($phi['dob']))->format('Y') : '[N/A]'); ?></dd>
                        </div>
                    <?php endif; ?>
                     <div>
                        <dt class="text-sm font-medium text-gray-500">Appointment Time</dt>
                        <dd class="mt-1 text-base text-gray-900"><?= $trip['appointment_at'] ? Utils::formatUtcToUserTime($trip['appointment_at']) : 'ASAP'; ?></dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
    
    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
        <?php if ($viewMode === 'facility' && $trip['status'] === 'bidding'): ?>
            <div class="flex justify-end items-center space-x-3">
                <form action="view_trip.php?uuid=<?= Utils::e($trip['uuid']) ?>" method="post" onsubmit="return confirm('Are you sure you want to cancel this trip?');">
                    <input type="hidden" name="action" value="cancel_trip">
                    <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded">Cancel Trip</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($viewMode === 'carrier_unawarded' && $trip['status'] === 'bidding' && $biddingIsOpen): ?>
            <form action="view_trip.php?uuid=<?= Utils::e($trip['uuid']) ?>" method="post">
                <h3 class="text-lg font-medium text-gray-900 mb-2">Place Your Bid</h3>
                <div class="sm:flex sm:items-end sm:space-x-3">
                    <div class="w-full sm:w-auto flex-grow">
                        <label for="eta">Submit Your ETA (Local Time)</label>
                        <input type="datetime-local" id="eta" name="eta" required class="mt-1 block w-full rounded-md border-gray-300">
                    </div>
                    <input type="hidden" name="action" value="place_bid">
                    <button type="submit" class="mt-2 sm:mt-0 bg-green-600 text-white px-4 py-2 rounded">Submit Bid</button>
                </div>
            </form>
        <?php endif; ?>

        <?php if ($viewMode === 'carrier_awarded' && in_array($trip['status'], ['awarded'])): ?>
            <form action="view_trip.php?uuid=<?= Utils::e($trip['uuid']) ?>" method="post">
                <h3 class="text-lg font-medium text-gray-900 mb-2">Manage Trip</h3>
                <div class="sm:flex sm:items-end sm:space-x-3">
                    <div class="w-full sm:w-auto flex-grow">
                        <label for="awarded_eta">Amend Your ETA (Local Time)</label>
                        <input type="datetime-local" id="awarded_eta" name="awarded_eta" value="<?= Utils::formatUtcToUserTime($trip['awarded_eta'], 'Y-m-d\TH:i') ?>" required class="mt-1 block w-full rounded-md border-gray-300">
                    </div>
                    <input type="hidden" name="action" value="update_eta">
                    <button type="submit" class="mt-2 sm:mt-0 bg-blue-600 text-white px-4 py-2 rounded">Update ETA</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'footer.php'; ?>