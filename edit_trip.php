<?php
// FILE: public_html/portal/edit_trip.php

require_once __DIR__ . '/../../app/init.php';

// --- 1. AUTHORIZATION & INITIAL DATA FETCH ---
if (!Auth::isLoggedIn() || !isset($_GET['uuid'])) {
    Utils::redirect('index.php');
}

$page_title = 'Edit Trip';
$page_error = '';
$tripService = new TripService();
$encryption = new EncryptionService(ENCRYPTION_KEY);

// Fetch the trip data
$trip = $tripService->getTripByUuid($_GET['uuid']);

// Redirect if trip doesn't exist
if (!$trip) {
    LoggingService::log(Auth::user('user_id'), null, 'trip_not_found', 'User attempted to edit non-existent UUID: ' . $_GET['uuid']);
    Utils::redirect('index.php?error=notfound');
}

// Security Check: Only the facility that owns the trip can edit it.
$viewMode = $tripService->determineViewMode($trip);
if ($viewMode !== 'facility') {
    LoggingService::log(Auth::user('user_id'), null, 'unauthorized_trip_edit_attempt', "User denied edit access to Trip ID: {$trip['id']}.");
    Utils::redirect('index.php?error=unauthorized');
}

// Security Check 2: Prevent editing of trips that are already cancelled or completed
if (in_array($trip['status'], ['completed', 'cancelled'])) {
    Utils::redirect("view_trip.php?uuid={$trip['uuid']}&error=already_finalized");
}

// --- 2. POST REQUEST HANDLING ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        // The service layer handles all logic: validation, comparison, updates, logging, and notifications.
        $tripService->updateTrip($trip['id'], $_POST);
        Utils::redirect("view_trip.php?uuid={$trip['uuid']}&status=trip_updated");
    } catch (Exception $e) {
        $page_error = $e->getMessage();
    }
}

// --- 3. DATA PREPARATION FOR DISPLAY ---
$formData = [];
// Decrypt all PHI to pre-populate the form
$formData['patient_first_name'] = $encryption->decrypt($trip['patient_first_name_encrypted']);
$formData['patient_last_name'] = $encryption->decrypt($trip['patient_last_name_encrypted']);
$formData['patient_dob'] = $encryption->decrypt($trip['patient_dob_encrypted']);
$formData['patient_ssn'] = $encryption->decrypt($trip['patient_ssn_last4_encrypted']);
$formData['primary_diagnosis'] = $encryption->decrypt($trip['diagnosis_encrypted']);
$formData['isolation_precautions'] = $encryption->decrypt($trip['isolation_precautions_encrypted']);
$decrypted_weight_kg = $encryption->decrypt($trip['patient_weight_kg_encrypted']);
$decrypted_height_in = $encryption->decrypt($trip['patient_height_in_encrypted']);
$decrypted_equipment = $encryption->decrypt($trip['special_equipment_encrypted']);

// Convert and format data for form fields
$formData['patient_weight'] = is_numeric($decrypted_weight_kg) ? round($decrypted_weight_kg * 2.20462) : '';
$formData['patient_height'] = is_numeric($decrypted_height_in) ? $decrypted_height_in : '';
$formData['asap'] = (bool)$trip['asap'];
$formData['pickup_time'] = $trip['requested_pickup_time'] ? Utils::formatUtcToUserTime($trip['requested_pickup_time'], 'Y-m-d\TH:i') : '';
$formData['appointment_time'] = $trip['appointment_at'] ? Utils::formatUtcToUserTime($trip['appointment_at'], 'Y-m-d\TH:i') : '';

// Pre-check equipment checkboxes
$formData['equipment_o2'] = strpos($decrypted_equipment, 'O2') !== false;
$formData['equipment_iv'] = strpos($decrypted_equipment, 'IV') !== false;
$formData['equipment_cardiac_monitor'] = strpos($decrypted_equipment, 'Cardiac Monitor') !== false;
$formData['equipment_ecmo'] = strpos($decrypted_equipment, 'ECMO') !== false;
$formData['equipment_vent'] = strpos($decrypted_equipment, 'Ventilator') !== false;
$formData['equipment_other'] = strpos($decrypted_equipment, 'Other Equipment') !== false;

// Pre-fill equipment details
if ($formData['equipment_o2'] && preg_match('/O2: (\d+(\.\d+)?) LPM via (\w+)/', $decrypted_equipment, $matches)) {
    $formData['o2_amount'] = $matches[1];
    $formData['o2_route'] = $matches[3];
}
if ($formData['equipment_iv'] && preg_match('/IV \((.*?)\)(?:: (.*))?/', $decrypted_equipment, $matches)) {
    $formData['iv_type'] = $matches[1];
    $formData['iv_medications'] = $matches[2] ?? '';
}
if ($formData['equipment_vent'] && preg_match('/Ventilator: (.*)/', $decrypted_equipment, $matches)) {
    $formData['ventilator_settings'] = $matches[1];
}

$states = ['AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware', 'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina', 'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming'];

require_once 'header.php';
?>

<h1 class="text-3xl font-bold text-gray-800 mb-6">Edit Transport Request</h1>

<div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200">
    <div class="p-6">
        <form action="edit_trip.php?uuid=<?= Utils::e($trip['uuid']); ?>" method="post" id="edit-trip-form" class="space-y-8">

            <?php if (!empty($page_error)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p class="font-bold">Error</p>
                    <p><?= Utils::e($page_error); ?></p>
                </div>
            <?php endif; ?>

            <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-800 p-4" role="alert">
                <p class="font-bold">Important Notice</p>
                <p>To modify a pick-up or drop-off address, you must cancel this trip and create a new one. This ensures all carriers are bidding on the correct mileage and location details.</p>
            </div>

            <fieldset class="opacity-75">
                <legend class="text-xl font-semibold text-gray-800 border-b border-gray-200 pb-2 mb-4">Route Details (Read-Only)</legend>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Pick-up Address</h3>
                        <p class="mt-1 text-base text-gray-900"><?= Utils::e($trip['origin_street']); ?>, <?= Utils::e($trip['origin_city']); ?>, <?= Utils::e($trip['origin_state']); ?> <?= Utils::e($trip['origin_zip']); ?></p>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Drop-off Address</h3>
                        <p class="mt-1 text-base text-gray-900"><?= Utils::e($trip['destination_street']); ?>, <?= Utils::e($trip['destination_city']); ?>, <?= Utils::e($trip['destination_state']); ?> <?= Utils::e($trip['destination_zip']); ?></p>
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend class="text-xl font-semibold text-gray-800 border-b border-gray-200 pb-2 mb-4">Patient Information</legend>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="patient_first_name" class="block text-sm font-medium text-gray-700">First Name</label>
                        <input type="text" name="patient_first_name" id="patient_first_name" value="<?= Utils::e($formData['patient_first_name'] ?? ''); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                    </div>
                    <div>
                        <label for="patient_last_name" class="block text-sm font-medium text-gray-700">Last Name</label>
                        <input type="text" name="patient_last_name" id="patient_last_name" value="<?= Utils::e($formData['patient_last_name'] ?? ''); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                    </div>
                    <div>
                        <label for="patient_dob" class="block text-sm font-medium text-gray-700">Date of Birth</label>
                        <input type="date" name="patient_dob" id="patient_dob" value="<?= Utils::e($formData['patient_dob'] ?? ''); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                    </div>
                    <div>
                        <label for="patient_ssn" class="block text-sm font-medium text-gray-700">Social Security Number (Last 4 Digits)</label>
                        <input type="text" name="patient_ssn" id="patient_ssn" value="<?= Utils::e($formData['patient_ssn'] ?? ''); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" pattern="\d{4}" maxlength="4" required>
                    </div>
                    <div>
                        <label for="patient_weight" class="block text-sm font-medium text-gray-700">Weight (in lbs)</label>
                        <input type="number" name="patient_weight" id="patient_weight" value="<?= Utils::e($formData['patient_weight'] ?? ''); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                    </div>
                    <div>
                        <label for="patient_height" class="block text-sm font-medium text-gray-700">Height (in inches)</label>
                        <input type="number" name="patient_height" id="patient_height" value="<?= Utils::e($formData['patient_height'] ?? ''); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend class="text-xl font-semibold text-gray-800 border-b border-gray-200 pb-2 mb-4">Pick-up and Appointment Time</legend>
                <div class="grid grid-cols-12 gap-6 items-center">
                    <div class="col-span-12 sm:col-span-4">
                        <div class="flex items-center">
                            <input id="asap_checkbox" name="asap_checkbox" type="checkbox" <?= $formData['asap'] ? 'checked' : ''; ?> class="h-4 w-4 rounded border-gray-300 accent-red-600">
                            <label for="asap_checkbox" class="ml-2 block text-base font-bold text-red-600">ASAP</label>
                        </div>
                    </div>
                    <div class="col-span-12 sm:col-span-4">
                        <label for="pickup_time" class="block text-sm font-medium text-gray-700">Pick-up Time</label>
                        <input type="datetime-local" id="pickup_time" name="pickup_time" value="<?= Utils::e($formData['pickup_time'] ?? ''); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                    <div class="col-span-12 sm:col-span-4">
                        <label for="appointment_time" class="block text-sm font-medium text-gray-700">Appointment Time</label>
                        <input type="datetime-local" id="appointment_time" name="appointment_time" value="<?= Utils::e($formData['appointment_time'] ?? ''); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                </div>
            </fieldset>

            <fieldset>
                 <legend class="text-xl font-semibold text-gray-800 border-b border-gray-200 pb-2 mb-4">Patient Clinical Details</legend>
                 <div class="grid grid-cols-12 gap-6">
                    <div class="col-span-12">
                        <label for="primary_diagnosis" class="block text-sm font-medium text-gray-700">Primary Diagnosis</label>
                        <input type="text" id="primary_diagnosis" name="primary_diagnosis" value="<?= Utils::e($formData['primary_diagnosis'] ?? ''); ?>" placeholder="e.g., Pneumonia, Congestive Heart Failure, STEMI" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                    <div class="col-span-12">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Special Equipment</label>
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-4">
                            <div class="flex items-center"><input id="equipment_o2" name="equipment_o2" type="checkbox" <?= $formData['equipment_o2'] ? 'checked' : ''; ?> class="h-4 w-4 text-indigo-600 border-gray-300 rounded"><label for="equipment_o2" class="ml-2 block text-sm">O2</label></div>
                            <div class="flex items-center"><input id="equipment_iv" name="equipment_iv" type="checkbox" <?= $formData['equipment_iv'] ? 'checked' : ''; ?> class="h-4 w-4 text-indigo-600 border-gray-300 rounded"><label for="equipment_iv" class="ml-2 block text-sm">IV</label></div>
                            <div class="flex items-center"><input id="equipment_cardiac_monitor" name="equipment_cardiac_monitor" type="checkbox" <?= $formData['equipment_cardiac_monitor'] ? 'checked' : ''; ?> class="h-4 w-4 text-indigo-600 border-gray-300 rounded"><label for="equipment_cardiac_monitor" class="ml-2 block text-sm">Cardiac Monitor</label></div>
                            <div class="flex items-center"><input id="equipment_ecmo" name="equipment_ecmo" type="checkbox" <?= $formData['equipment_ecmo'] ? 'checked' : ''; ?> class="h-4 w-4 text-indigo-600 border-gray-300 rounded"><label for="equipment_ecmo" class="ml-2 block text-sm">ECMO</label></div>
                            <div class="flex items-center"><input id="equipment_vent" name="equipment_vent" type="checkbox" <?= $formData['equipment_vent'] ? 'checked' : ''; ?> class="h-4 w-4 text-indigo-600 border-gray-300 rounded"><label for="equipment_vent" class="ml-2 block text-sm">Vent</label></div>
                            <div class="flex items-center"><input id="equipment_other" name="equipment_other" type="checkbox" <?= $formData['equipment_other'] ? 'checked' : ''; ?> class="h-4 w-4 text-indigo-600 border-gray-300 rounded"><label for="equipment_other" class="ml-2 block text-sm">Other</label></div>
                        </div>
                    </div>
                    <div id="o2_details" class="col-span-12 <?= $formData['equipment_o2'] ? '' : 'hidden'; ?>">
                        <div class="grid grid-cols-12 gap-6">
                            <div class="col-span-12 sm:col-span-6"><label for="o2_amount" class="block text-sm font-medium">Amount of O2 (LPM)</label><input type="number" step="0.1" id="o2_amount" name="o2_amount" value="<?= Utils::e($formData['o2_amount'] ?? ''); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></div>
                            <div class="col-span-12 sm:col-span-6"><label for="o2_route" class="block text-sm font-medium">Route</label><select id="o2_route" name="o2_route" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"><option value="">Select...</option><option value="nc" <?= ($formData['o2_route'] ?? '') == 'nc' ? 'selected' : ''; ?>>Nasal Cannula</option><option value="nrb" <?= ($formData['o2_route'] ?? '') == 'nrb' ? 'selected' : ''; ?>>Non-Rebreather</option><option value="bvm" <?= ($formData['o2_route'] ?? '') == 'bvm' ? 'selected' : ''; ?>>BVM</option><option value="other" <?= ($formData['o2_route'] ?? '') == 'other' ? 'selected' : ''; ?>>Other</option></select></div>
                        </div>
                    </div>
                    <div id="iv_details" class="col-span-12 <?= $formData['equipment_iv'] ? '' : 'hidden'; ?>">
                        <label class="block text-sm font-medium mb-2">IV Status</label>
                        <div class="flex items-center space-x-4">
                            <div class="flex items-center"><input id="iv_type_locked" name="iv_type" type="radio" value="locked" <?= ($formData['iv_type'] ?? '') == 'locked' ? 'checked' : ''; ?> class="h-4 w-4 text-indigo-600 border-gray-300"><label for="iv_type_locked" class="ml-2 block text-sm">Saline Locked</label></div>
                            <div class="flex items-center"><input id="iv_type_flowing" name="iv_type" type="radio" value="flowing" <?= ($formData['iv_type'] ?? '') == 'flowing' ? 'checked' : ''; ?> class="h-4 w-4 text-indigo-600 border-gray-300"><label for="iv_type_flowing" class="ml-2 block text-sm">Flowing Medication</label></div>
                        </div>
                        <div id="iv_meds" class="col-span-12 mt-4 <?= ($formData['iv_type'] ?? '') == 'flowing' ? '' : 'hidden'; ?>"><label for="iv_medications" class="block text-sm font-medium">Medication(s)</label><textarea id="iv_medications" name="iv_medications" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"><?= Utils::e($formData['iv_medications'] ?? ''); ?></textarea></div>
                    </div>
                    <div id="vent_details" class="col-span-12 <?= $formData['equipment_vent'] ? '' : 'hidden'; ?>"><label for="ventilator_settings" class="block text-sm font-medium">Ventilator Settings</label><textarea id="ventilator_settings" name="ventilator_settings" rows="3" placeholder="e.g., Mode, Rate, Tidal Volume, PEEP" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"><?= Utils::e($formData['ventilator_settings'] ?? ''); ?></textarea></div>
                    <div class="col-span-12"><label for="isolation_precautions" class="block text-sm font-medium">Isolation Precautions</label><select id="isolation_precautions" name="isolation_precautions" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"><option value="">Select...</option><option value="airborne" <?= ($formData['isolation_precautions'] ?? '') == 'airborne' ? 'selected' : ''; ?>>Airborne</option><option value="droplet" <?= ($formData['isolation_precautions'] ?? '') == 'droplet' ? 'selected' : ''; ?>>Droplet</option><option value="contact" <?= ($formData['isolation_precautions'] ?? '') == 'contact' ? 'selected' : ''; ?>>Contact</option><option value="standard" <?= ($formData['isolation_precautions'] ?? '') == 'standard' ? 'selected' : ''; ?>>Standard</option><option value="other" <?= ($formData['isolation_precautions'] ?? '') == 'other' ? 'selected' : ''; ?>>Other</option></select></div>
                </div>
            </fieldset>

            <div class="flex justify-end pt-5">
                <a href="view_trip.php?uuid=<?= Utils::e($trip['uuid']); ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-3 rounded-md font-semibold mr-4">Cancel</a>
                <button type="submit" class="w-full md:w-auto inline-flex justify-center py-3 px-6 border border-transparent shadow-sm text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const asapCheckbox = document.getElementById('asap_checkbox');
    const pickupTimeInput = document.getElementById('pickup_time');
    const appointmentTimeInput = document.getElementById('appointment_time');

    function updateDateTimeRequirements() {
        const isAsap = asapCheckbox.checked;
        const hasPickupTime = pickupTimeInput.value !== '';
        const hasAppointmentTime = appointmentTimeInput.value !== '';

        pickupTimeInput.disabled = isAsap;
        appointmentTimeInput.disabled = isAsap;

        if (isAsap) {
            pickupTimeInput.required = false;
            appointmentTimeInput.required = false;
            pickupTimeInput.value = '';
            appointmentTimeInput.value = '';
        } else {
            appointmentTimeInput.required = !hasPickupTime;
            pickupTimeInput.required = !hasAppointmentTime;
        }
    }

    asapCheckbox.addEventListener('change', updateDateTimeRequirements);
    pickupTimeInput.addEventListener('input', updateDateTimeRequirements);
    appointmentTimeInput.addEventListener('input', updateDateTimeRequirements);
    updateDateTimeRequirements();
});

const o2Checkbox = document.getElementById('equipment_o2');
const ivCheckbox = document.getElementById('equipment_iv');
const ventCheckbox = document.getElementById('equipment_vent');
const o2Details = document.getElementById('o2_details');
const ivDetails = document.getElementById('iv_details');
const ventDetails = document.getElementById('vent_details');
const ivRadioLocked = document.getElementById('iv_type_locked');
const ivRadioFlowing = document.getElementById('iv_type_flowing');
const ivMeds = document.getElementById('iv_meds');

o2Checkbox.addEventListener('change', () => o2Details.classList.toggle('hidden', !o2Checkbox.checked));
ivCheckbox.addEventListener('change', () => {
    ivDetails.classList.toggle('hidden', !ivCheckbox.checked);
    if (!ivCheckbox.checked) {
        ivRadioLocked.checked = false;
        ivRadioFlowing.checked = false;
        ivMeds.classList.add('hidden');
    }
});
ventCheckbox.addEventListener('change', () => ventDetails.classList.toggle('hidden', !ventCheckbox.checked));
ivRadioLocked.addEventListener('change', () => ivMeds.classList.add('hidden'));
ivRadioFlowing.addEventListener('change', () => ivMeds.classList.remove('hidden'));
</script>

<?php
require_once 'footer.php';
?>