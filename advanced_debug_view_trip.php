<?php
// FILE: public_html/portal/view_trip.php

require_once __DIR__ . '/../../app/init.php';

// --- 1. AUTHORIZATION & DATA FETCHING ---
if (!Auth::isLoggedIn() || !isset($_GET['uuid'])) {
    Utils::redirect('index.php');
}

$page_title = 'Trip Details';
$tripService = new TripService();
$encryption = new EncryptionService(ENCRYPTION_KEY);

$trip = $tripService->getTripByUuid($_GET['uuid']);

if (!$trip) {
    // Redirect if the trip doesn't exist
    Utils::redirect('index.php?error=notfound');
}

// Determine the user's role and view mode
$user = Auth::user();
$viewMode = 'unauthorized';

echo $viewMode;

if ($user['entity_type'] === 'facility' && $trip['facility_id'] == $user['entity_id']) {
    $viewMode = 'facility';
} elseif ($user['entity_type'] === 'carrier') {
    if ($trip['status'] === 'bidding') {
        $viewMode = 'bidding_carrier';
    } elseif ($trip['status'] === 'awarded' && $trip['carrier_id'] == $user['entity_id']) {
        $viewMode = 'awarded_carrier';
    }
} elseif ($user['entity_type'] === 'bedrock') { // Assuming 'bedrock' is the entity_type for admins
    $viewMode = 'admin';
}

if ($viewMode === 'unauthorized') {
    Utils::redirect('index.php?error=unauthorized');
}

echo "I made it here!";

// --- 2. DATA PREPARATION FOR DISPLAY ---
$phi = [];
$displayData = [];

// Decrypt data based on view mode
// Fields accessible to almost everyone
$displayData['pickup_address'] = Utils::e($trip['origin_street'] . '<br>' . $trip['origin_city'] . ', ' . $trip['origin_state'] . ' ' . $trip['origin_zip']);
$displayData['dropoff_address'] = Utils::e($trip['destination_street'] . '<br>' . $trip['destination_city'] . ', ' . $trip['destination_state'] . ' ' . $trip['destination_zip']);
$displayData['distance'] = Utils::e($trip['distance']) . ' miles';
$displayData['diagnosis'] = !empty($trip['diagnosis_encrypted']) ? $encryption->decrypt($trip['diagnosis_encrypted']) : 'N/A';
$displayData['special_equipment'] = !empty($trip['special_equipment_encrypted']) ? $encryption->decrypt($trip['special_equipment_encrypted']) : 'N/A';
$displayData['isolation_precautions'] = !empty($trip['isolation_precautions_encrypted']) ? $encryption->decrypt($trip['isolation_precautions_encrypted']) : 'N/A';

if ($trip['asap']) {
    $displayData['timing'] = 'ASAP';
} elseif (!empty($trip['appointment_at'])) {
    $displayData['timing'] = 'Appointment at: ' . Utils::formatUtcToUserTime($trip['appointment_at']);
} else {
    $displayData['timing'] = 'Pick-up at: ' . Utils::formatUtcToUserTime($trip['requested_pickup_time']);
}


// PHI - only for facility and awarded carrier
if ($viewMode === 'facility' || $viewMode === 'awarded_carrier') {
    $phi['patient_last_name'] = !empty($trip['patient_last_name_encrypted']) ? $encryption->decrypt($trip['patient_last_name_encrypted']) : 'N/A';
    $decrypted_dob = !empty($trip['patient_dob_encrypted']) ? $encryption->decrypt($trip['patient_dob_encrypted']) : null;
    $phi['patient_yob'] = $decrypted_dob ? date('Y', strtotime($decrypted_dob)) : 'N/A';
    
    // For awarded carrier, we show everything
    if($viewMode === 'awarded_carrier') {
        $phi['patient_first_name'] = !empty($trip['patient_first_name_encrypted']) ? $encryption->decrypt($trip['patient_first_name_encrypted']) : 'N/A';
        $phi['patient_full_dob'] = $decrypted_dob ? date('m/d/Y', strtotime($decrypted_dob)) : 'N/A';
        $decrypted_weight_kg = !empty($trip['patient_weight_kg_encrypted']) ? $encryption->decrypt($trip['patient_weight_kg_encrypted']) : null;
        $decrypted_height_in = !empty($trip['patient_height_in_encrypted']) ? $encryption->decrypt($trip['patient_height_in_encrypted']) : null;
        $phi['weight_lbs'] = is_numeric($decrypted_weight_kg) ? round($decrypted_weight_kg * 2.20462) : 'N/A';
        if (is_numeric($decrypted_height_in) && $decrypted_height_in > 0) {
            $phi['height_formatted'] = floor($decrypted_height_in / 12) . "' " . ($decrypted_height_in % 12) . '"';
        } else {
            $phi['height_formatted'] = 'N/A';
        }
    }
}

// Bidding carrier can see weight and height
if ($viewMode === 'bidding_carrier') {
    $decrypted_weight_kg = !empty($trip['patient_weight_kg_encrypted']) ? $encryption->decrypt($trip['patient_weight_kg_encrypted']) : null;
    $decrypted_height_in = !empty($trip['patient_height_in_encrypted']) ? $encryption->decrypt($trip['patient_height_in_encrypted']) : null;
    $displayData['weight_lbs'] = is_numeric($decrypted_weight_kg) ? round($decrypted_weight_kg * 2.20462) . ' lbs' : 'N/A';
    if (is_numeric($decrypted_height_in) && $decrypted_height_in > 0) {
        $displayData['height_formatted'] = floor($decrypted_height_in / 12) . "' " . ($decrypted_height_in % 12) . '"';
    } else {
        $displayData['height_formatted'] = 'N/A';
    }
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
        
        <!-- FACILITY VIEW -->
        <?php if ($viewMode === 'facility'): ?>
        <h3 class="text-lg font-medium text-gray-900 border-b pb-2">Facility View</h3>
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
            <div><dt class="font-medium text-gray-500">Pick-up Address</dt><dd class="text-gray-900 mt-1"><?= $displayData['pickup_address'] ?></dd></div>
            <div><dt class="font-medium text-gray-500">Drop-off Address</dt><dd class="text-gray-900 mt-1"><?= $displayData['dropoff_address'] ?></dd></div>
            <div><dt class="font-medium text-gray-500">Patient Last Name</dt><dd class="text-gray-900 mt-1"><?= Utils::e($phi['patient_last_name']) ?></dd></div>
            <div><dt class="font-medium text-gray-500">Patient Year of Birth</dt><dd class="text-gray-900 mt-1"><?= Utils::e($phi['patient_yob']) ?></dd></div>
            <div><dt class="font-medium text-gray-500">Diagnosis</dt><dd class="text-gray-900 mt-1"><?= Utils::e($displayData['diagnosis']) ?></dd></div>
            <div><dt class="font-medium text-gray-500">Special Equipment</dt><dd class="text-gray-900 mt-1"><?= Utils::e($displayData['special_equipment']) ?></dd></div>
            <div><dt class="font-medium text-gray-500">Isolation Precautions</dt><dd class="text-gray-900 mt-1"><?= Utils::e($displayData['isolation_precautions']) ?></dd></div>
            <div><dt class="font-medium text-gray-500">Distance</dt><dd class="text-gray-900 mt-1"><?= $displayData['distance'] ?></dd></div>
            <div><dt class="font-medium text-gray-500">Requested Time</dt><dd class="text-gray-900 mt-1"><?= $displayData['timing'] ?></dd></div>
        </dl>
        <div class="flex justify-end space-x-3 pt-4 border-t">
            <button type="button" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md font-semibold">Edit Trip</button>
            <button type="button" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md font-semibold">Cancel Trip</button>
        </div>
        <?php endif; ?>

        <!-- BIDDING CARRIER VIEW -->
        <?php if ($viewMode === 'bidding_carrier'): ?>
        <h3 class="text-lg font-medium text-gray-900 border-b pb-2">Bidding Carrier View</h3>
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
            <div><dt class="font-medium text-gray-500">Pick-up Address</dt><dd class="text-gray-900 mt-1"><?= $displayData['pickup_address'] ?></dd></div>
            <div><dt class="font-medium text-gray-500">Drop-off Address</dt><dd class="text-gray-900 mt-1"><?= $displayData['dropoff_address'] ?></dd></div>
            <div><dt class="font-medium text-gray-500">Distance</dt><dd class="text-gray-900 mt-1"><?= $displayData['distance'] ?></dd></div>
            <div><dt class="font-medium text-gray-500">Patient Weight</dt><dd class="text-gray-900 mt-1"><?= Utils::e($displayData['weight_lbs']) ?></dd></div>
            <div><dt class="font-medium text-gray-500">Patient Height</dt><dd class="text-gray-900 mt-1"><?= Utils::e($displayData['height_formatted']) ?></dd></div>
            <div><dt class="font-medium text-gray-500">Diagnosis</dt><dd class="text-gray-900 mt-1"><?= Utils::e($displayData['diagnosis']) ?></dd></div>
            <div><dt class="font-medium text-gray-500">Special Equipment</dt><dd class="text-gray-900 mt-1"><?= Utils::e($displayData['special_equipment']) ?></dd></div>
            <div><dt class="font-medium text-gray-500">Isolation Precautions</dt><dd class="text-gray-900 mt-1"><?= Utils::e($displayData['isolation_precautions']) ?></dd></div>
            <div><dt class="font-medium text-gray-500">Requested Time</dt><dd class="text-gray-900 mt-1"><?= $displayData['timing'] ?></dd></div>
        </dl>
        <div class="pt-4 border-t">
            <div class="sm:flex sm:items-end sm:space-x-3">
                <div class="w-full sm:w-auto flex-grow">
                    <label for="eta" class="block text-sm font-medium text-gray-700">Proposed Pick-up Date and Time ETA</label>
                    <?php 
                        $defaultEta = new DateTime('now', new DateTimeZone(USER_TIMEZONE));
                        $defaultEta->add(new DateInterval('PT45M'));
                    ?>
                    <input type="datetime-local" id="eta" name="eta" value="<?= $defaultEta->format('Y-m-d\TH:i') ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <button type="submit" class="mt-2 sm:mt-0 w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 border border-transparent text-base font-medium rounded-md text-white bg-green-600 hover:bg-green-700">Submit Bid</button>
            </div>
        </div>
        <?php endif; ?>

        <!-- AWARDED CARRIER VIEW -->
        <?php if ($viewMode === 'awarded_carrier'): ?>
        <h3 class="text-lg font-medium text-gray-900 border-b pb-2">Awarded Carrier View</h3>
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
            <div><dt class="font-medium text-gray-500">Pick-up Address</dt><dd class="text-gray-900 mt-1"><?= $displayData['pickup_address'] ?></dd></div>
            <div><dt class="font-medium text-gray-500">Drop-off Address</dt><dd class="text-gray-900 mt-1"><?= $displayData['dropoff_address'] ?></dd></div>
            <div><dt class="font-medium text-gray-500">Patient Name</dt><dd class="text-gray-900 mt-1"><?= Utils::e($phi['patient_first_name'] . ' ' . $phi['patient_last_name']) ?></dd></div>
            <div><dt class="font-medium text-gray-500">Patient DOB</dt><dd class="text-gray-900 mt-1"><?= Utils::e($phi['patient_full_dob']) ?></dd></div>
            <div><dt class="font-medium text-gray-500">Diagnosis</dt><dd class="text-gray-900 mt-1"><?= Utils::e($displayData['diagnosis']) ?></dd></div>
            <div><dt class="font-medium text-gray-500">Special Equipment</dt><dd class="text-gray-900 mt-1"><?= Utils::e($displayData['special_equipment']) ?></dd></div>
            <div><dt class="font-medium text-gray-500">Isolation Precautions</dt><dd class="text-gray-900 mt-1"><?= Utils::e($displayData['isolation_precautions']) ?></dd></div>
            <div><dt class="font-medium text-gray-500">Distance</dt><dd class="text-gray-900 mt-1"><?= $displayData['distance'] ?></dd></div>
            <div><dt class="font-medium text-gray-500">Patient Weight</dt><dd class="text-gray-900 mt-1"><?= Utils::e($phi['weight_lbs']) ?> lbs</dd></div>
            <div><dt class="font-medium text-gray-500">Patient Height</dt><dd class="text-gray-900 mt-1"><?= Utils::e($phi['height_formatted']) ?></dd></div>
            <div><dt class="font-medium text-gray-500">Requested Time</dt><dd class="text-gray-900 mt-1"><?= $displayData['timing'] ?></dd></div>
        </dl>
        <div class="flex justify-end pt-4 border-t">
            <button type="button" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-md font-semibold">Retract ETA</button>
        </div>
        <?php endif; ?>

        <!-- ADMIN VIEW -->
        <?php if ($viewMode === 'admin'): ?>
        <h3 class="text-lg font-medium text-gray-900 border-b pb-2">Admin View (Non-PHI)</h3>
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
            <div><dt class="font-medium text-gray-500">Pick-up Address</dt><dd class="text-gray-900 mt-1"><?= $displayData['pickup_address'] ?></dd></div>
            <div><dt class="font-medium text-gray-500">Drop-off Address</dt><dd class="text-gray-900 mt-1"><?= $displayData['dropoff_address'] ?></dd></div>
            <div><dt class="font-medium text-gray-500">Diagnosis</dt><dd class="text-gray-900 mt-1"><?= Utils::e($displayData['diagnosis']) ?></dd></div>
            <div><dt class="font-medium text-gray-500">Special Equipment</dt><dd class="text-gray-900 mt-1"><?= Utils::e($displayData['special_equipment']) ?></dd></div>
            <div><dt class="font-medium text-gray-500">Isolation Precautions</dt><dd class="text-gray-900 mt-1"><?= Utils::e($displayData['isolation_precautions']) ?></dd></div>
            <div><dt class="font-medium text-gray-500">Distance</dt><dd class="text-gray-900 mt-1"><?= $displayData['distance'] ?></dd></div>
            <div><dt class="font-medium text-gray-500">Requested Time</dt><dd class="text-gray-900 mt-1"><?= $displayData['timing'] ?></dd></div>
        </dl>
        <?php if (in_array($trip['status'], ['bidding', 'awarded'])): ?>
        <div class="flex justify-end pt-4 border-t">
            <button type="button" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md font-semibold">Cancel Trip (Admin)</button>
        </div>
        <?php endif; ?>
        <?php endif; ?>

    </div>
</div>

<?php require_once 'footer.php'; ?>