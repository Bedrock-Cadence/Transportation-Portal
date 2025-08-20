<?php
// FILE: public_html/portal/create_trip.php

require_once __DIR__ . '/../../app/init.php';

// Use our central Auth service for a clean permission check.
if (!Auth::can('create_trip')) {
    Utils::redirect('index.php');
}

$page_title = 'Create New Trip';
$page_error = '';
$page_success = '';

// Array of US states for cleaner, more maintainable dropdown menus
$states = [
    'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California',
    'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware', 'FL' => 'Florida', 'GA' => 'Georgia',
    'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa',
    'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
    'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi', 'MO' => 'Missouri',
    'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey',
    'NM' => 'New Mexico', 'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio',
    'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
    'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont',
    'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming'
];

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        // The TripService now handles everything: validation, APIs, encryption, DB inserts.
        $tripService = new TripService();
        $tripUuid = $tripService->createTrip($_POST);
        
        // On success, redirect to the new trip's detail page.
        Utils::redirect("view_trip.php?uuid=" . urlencode($tripUuid));
        
    } catch (Exception $e) {
        // Any failure in the service will be caught here.
        $page_error = $e->getMessage();
    }
}

// Pre-populate data for the form view
$db = Database::getInstance();
$preloadData = [
    'facilities' => Auth::hasRole('admin') ? $db->fetchAll("SELECT id, name FROM facilities WHERE is_active = 1 ORDER BY name ASC") : [],
    'facility_address' => $db->fetch("SELECT address_street, address_city, address_state, address_zip FROM facilities WHERE id = :id", [':id' => Auth::user('entity_id')]) ?: []
];

require_once 'header.php';
?>

<h1 class="text-3xl font-bold text-gray-800 mb-6">Create a New Transport Request</h1>

<div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200">
    <div class="p-6">
        <form action="create_trip.php" method="post" id="create-trip-form" class="space-y-8">
            
            <?php if (!empty($page_error)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p class="font-bold">Error</p>
                    <p><?= Utils::e($page_error); ?></p>
                </div>
            <?php endif; ?>

            <?php if (Auth::hasRole('admin')) : ?>
                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4" role="alert">
                    <p class="font-bold">Admin Mode</p>
                    <p>You are creating this trip on behalf of a facility. Please select one from the list below.</p>
                </div>
                <div>
                    <label for="facility_id" class="block text-sm font-medium text-gray-700">Create Trip For Facility:</label>
                    <select name="facility_id" id="facility_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required>
                        <option value="">-- Select a Facility --</option>
                        <?php foreach ($preloadData['facilities'] as $facility) : ?>
                            <option value="<?= Utils::e($facility['id']); ?>"><?= Utils::e($facility['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <hr class="border-gray-200">
            <?php endif; ?>

            <fieldset>
                <legend class="text-xl font-semibold text-gray-800 border-b border-gray-200 pb-2 mb-4">Patient Information</legend>
                <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-6" role="alert">
                    <p class="mb-0"><strong>IMPORTANT:</strong> This form collects Protected Health Information (PHI) and will be encrypted.</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="patient_first_name" class="block text-sm font-medium text-gray-700">First Name</label>
                        <input type="text" name="patient_first_name" id="patient_first_name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required>
                    </div>
                    <div>
                        <label for="patient_last_name" class="block text-sm font-medium text-gray-700">Last Name</label>
                        <input type="text" name="patient_last_name" id="patient_last_name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required>
                    </div>
                    <div>
                        <label for="patient_dob" class="block text-sm font-medium text-gray-700">Date of Birth</label>
                        <input type="date" name="patient_dob" id="patient_dob" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required>
                    </div>
                    <div>
                        <label for="patient_ssn" class="block text-sm font-medium text-gray-700">Social Security Number (Last 4 Digits)</label>
                        <input type="text" name="patient_ssn" id="patient_ssn" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" pattern="\d{4}" maxlength="4" required>
                    </div>
                    <div>
                        <label for="patient_weight" class="block text-sm font-medium text-gray-700">Weight (in lbs)</label>
                        <input type="number" name="patient_weight" id="patient_weight" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required>
                    </div>
                    <div>
                        <label for="patient_height" class="block text-sm font-medium text-gray-700">Height (in inches)</label>
                        <input type="number" name="patient_height" id="patient_height" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required>
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend class="text-xl font-semibold text-gray-800 border-b border-gray-200 pb-2 mb-4">Pickup Details</legend>
                <div class="grid grid-cols-12 gap-6">
                    <div class="col-span-12 sm:col-span-9">
                        <label for="pickup_address_street" class="block text-sm font-medium text-gray-700">Street Address</label>
                        <input type="text" id="pickup_address_street" name="pickup_address_street" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" value="<?= Utils::e($preloadData['facility_address']['address_street'] ?? '') ?>" required>
                    </div>
                    <div class="col-span-12 sm:col-span-3">
                        <label for="pickup_address_room" class="block text-sm font-medium text-gray-700">Room/Apt #</label>
                        <input type="text" name="pickup_address_room" id="pickup_address_room" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                    <div class="col-span-12 sm:col-span-6">
                        <label for="pickup_address_city" class="block text-sm font-medium text-gray-700">City</label>
                        <input type="text" name="pickup_address_city" id="pickup_address_city" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" value="<?= Utils::e($preloadData['facility_address']['address_city'] ?? '') ?>" required>
                    </div>
                    <div class="col-span-12 sm:col-span-3">
                        <label for="pickup_address_state" class="block text-sm font-medium text-gray-700">State</label>
                        <select name="pickup_address_state" id="pickup_address_state" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required>
                            <option value="">Choose...</option>
                            <?php foreach ($states as $abbreviation => $name) : ?>
                                <option value="<?= Utils::e($abbreviation) ?>" <?= (($preloadData['facility_address']['address_state'] ?? '') == $abbreviation) ? 'selected' : '' ?>>
                                    <?= Utils::e($name) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-span-12 sm:col-span-3">
                        <label for="pickup_address_zip" class="block text-sm font-medium text-gray-700">Zip Code</label>
                        <input type="text" name="pickup_address_zip" id="pickup_address_zip" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" value="<?= Utils::e($preloadData['facility_address']['address_zip'] ?? '') ?>" required>
                    </div>
                </div>
            </fieldset>

<fieldset>
    <legend class="text-xl font-semibold text-gray-800 border-b border-gray-200 pb-2 mb-4">Patient Clinical Details</legend>
    <div class="grid grid-cols-12 gap-6">

        <!-- Primary Diagnosis -->
        <div class="col-span-12">
            <label for="primary_diagnosis" class="block text-sm font-medium text-gray-700">Primary Diagnosis</label>
            <input type="text" id="primary_diagnosis" name="primary_diagnosis" placeholder="e.g., Pneumonia, Congestive Heart Failure, STEMI" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
        </div>

        <!-- Special Equipment Checkboxes -->
        <div class="col-span-12">
            <label class="block text-sm font-medium text-gray-700 mb-2">Special Equipment</label>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-4">
                <div class="flex items-center">
                    <input id="equipment_o2" name="equipment_o2" type="checkbox" class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                    <label for="equipment_o2" class="ml-2 block text-sm text-gray-900">O2</label>
                </div>
                <div class="flex items-center">
                    <input id="equipment_iv" name="equipment_iv" type="checkbox" class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                    <label for="equipment_iv" class="ml-2 block text-sm text-gray-900">IV</label>
                </div>
                <div class="flex items-center">
                    <input id="equipment_cardiac_monitor" name="equipment_cardiac_monitor" type="checkbox" class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                    <label for="equipment_cardiac_monitor" class="ml-2 block text-sm text-gray-900">Cardiac Monitor</label>
                </div>
                <div class="flex items-center">
                    <input id="equipment_ecmo" name="equipment_ecmo" type="checkbox" class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                    <label for="equipment_ecmo" class="ml-2 block text-sm text-gray-900">ECMO</label>
                </div>
                <div class="flex items-center">
                    <input id="equipment_vent" name="equipment_vent" type="checkbox" class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                    <label for="equipment_vent" class="ml-2 block text-sm text-gray-900">Vent</label>
                </div>
                <div class="flex items-center">
                    <input id="equipment_other" name="equipment_other" type="checkbox" class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                    <label for="equipment_other" class="ml-2 block text-sm text-gray-900">Other</label>
                </div>
            </div>
        </div>

        <!-- Conditional O2 Details -->
        <div id="o2_details" class="col-span-12 hidden">
            <div class="grid grid-cols-12 gap-6">
                <div class="col-span-12 sm:col-span-6">
                    <label for="o2_amount" class="block text-sm font-medium text-gray-700">Amount of O2 (LPM)</label>
                    <input type="number" step="0.1" id="o2_amount" name="o2_amount" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
                <div class="col-span-12 sm:col-span-6">
                    <label for="o2_route" class="block text-sm font-medium text-gray-700">Route</label>
                    <select id="o2_route" name="o2_route" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="">Select...</option>
                        <option value="nc">Nasal Cannula (NC)</option>
                        <option value="nrb">Non-Rebreather Mask (NRB)</option>
                        <option value="bvm">Bag-Valve Mask (BVM)</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Conditional IV Details -->
        <div id="iv_details" class="col-span-12 hidden">
            <label class="block text-sm font-medium text-gray-700 mb-2">IV Status</label>
            <div class="flex items-center space-x-4">
                <div class="flex items-center">
                    <input id="iv_type_locked" name="iv_type" type="radio" value="locked" class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                    <label for="iv_type_locked" class="ml-2 block text-sm text-gray-900">Saline Locked</label>
                </div>
                <div class="flex items-center">
                    <input id="iv_type_flowing" name="iv_type" type="radio" value="flowing" class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                    <label for="iv_type_flowing" class="ml-2 block text-sm text-gray-900">Flowing Medication</label>
                </div>
            </div>
            <!-- Conditional IV Medication Input -->
            <div id="iv_meds" class="col-span-12 mt-4 hidden">
                <label for="iv_medications" class="block text-sm font-medium text-gray-700">Medication(s)</label>
                <textarea id="iv_medications" name="iv_medications" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
            </div>
        </div>

        <!-- Conditional Vent Details -->
        <div id="vent_details" class="col-span-12 hidden">
            <label for="ventilator_settings" class="block text-sm font-medium text-gray-700">Ventilator Settings</label>
            <textarea id="ventilator_settings" name="ventilator_settings" rows="3" placeholder="e.g., Mode, Rate, Tidal Volume, PEEP" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
        </div>

        <!-- Isolation Precautions -->
        <div class="col-span-12">
            <label for="isolation_precautions" class="block text-sm font-medium text-gray-700">Isolation Precautions</label>
            <select id="isolation_precautions" name="isolation_precautions" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                <option value="">Select...</option>
                <option value="airborne">Airborne</option>
                <option value="droplet">Droplet</option>
                <option value="contact">Contact</option>
                <option value="standard">Standard</option>
                <option value="other">Other</option>
            </select>
        </div>

    </div>
</fieldset>            
            
            <div class="cf-turnstile" data-sitekey="0x4AAAAAABsE3bLaSnTnuUzR"></div>
            
            <div class="flex justify-end pt-5">
                <button type="submit" class="w-full md:w-auto inline-flex justify-center py-3 px-6 border border-transparent shadow-sm text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">Submit Trip Request</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Get the checkboxes and conditional sections
    const o2Checkbox = document.getElementById('equipment_o2');
    const ivCheckbox = document.getElementById('equipment_iv');
    const ventCheckbox = document.getElementById('equipment_vent');

    const o2Details = document.getElementById('o2_details');
    const ivDetails = document.getElementById('iv_details');
    const ventDetails = document.getElementById('vent_details');

    // Get the IV radio buttons and medication input
    const ivRadioLocked = document.getElementById('iv_type_locked');
    const ivRadioFlowing = document.getElementById('iv_type_flowing');
    const ivMeds = document.getElementById('iv_meds');

    // Event listeners to toggle conditional sections
    o2Checkbox.addEventListener('change', () => {
        o2Details.classList.toggle('hidden', !o2Checkbox.checked);
    });

    ivCheckbox.addEventListener('change', () => {
        ivDetails.classList.toggle('hidden', !ivCheckbox.checked);
        if (!ivCheckbox.checked) {
            ivRadioLocked.checked = false;
            ivRadioFlowing.checked = false;
            ivMeds.classList.add('hidden');
        }
    });

    ventCheckbox.addEventListener('change', () => {
        ventDetails.classList.toggle('hidden', !ventCheckbox.checked);
    });

    // Event listener for IV radio buttons
    ivRadioLocked.addEventListener('change', () => {
        ivMeds.classList.add('hidden');
    });

    ivRadioFlowing.addEventListener('change', () => {
        ivMeds.classList.remove('hidden');
    });
</script>

<?php
require_once 'footer.php';
?>