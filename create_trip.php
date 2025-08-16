<?php
// FILE: create_new_trip.php

// Start output buffering to prevent "headers already sent" errors.
ob_start();

// 1. Set the page title for the header.
$page_title = 'Create New Trip';

// 2. Include the header and necessary services.
require_once 'header.php';
require_once __DIR__ . '/../../app/db_connect.php';
require_once __DIR__ . '/../../app/encryption_service.php';
require_once __DIR__ . '/../../app/logging_service.php';

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

// Permission Check: A user is authorized if they are an admin, OR if they are a user/superuser for a facility.
$is_authorized = false;
if (isset($_SESSION["loggedin"])) {
    if ($_SESSION['user_role'] === 'admin') {
        $is_authorized = true;
    } elseif (in_array($_SESSION['user_role'], ['user', 'superuser']) && $_SESSION['entity_type'] === 'facility') {
        $is_authorized = true;
    }
}
if (!$is_authorized) {
    // Log the unauthorized access attempt before redirecting.
    $attempted_user_id = $_SESSION['user_id'] ?? 0; // Use 0 if user_id isn't set
    $log_message = "Unauthorized access attempt to create_new_trip.php.";
    log_activity($mysqli, $attempted_user_id, 'unauthorized_access', $log_message);

    // Now, redirect the user.
    header("location: index.php");
    exit;
}

define('SYSTEM_TIMEZONE', 'UTC');

/**
 * Converts a date/time string from a specified timezone to UTC.
 * @param string $datetime_string The date/time string to convert.
 * @param string $source_timezone The original timezone of the string.
 * @return string The converted date/time string in UTC, formatted for MySQL.
 */
function convert_to_utc($datetime_string, $source_timezone) {
    if (empty($datetime_string)) {
        return null;
    }
    try {
        $datetime_obj = new DateTime($datetime_string, new DateTimeZone($source_timezone));
        $datetime_obj->setTimezone(new DateTimeZone(SYSTEM_TIMEZONE));
        return $datetime_obj->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        // Log the error and return null to prevent script failure
        error_log("Timezone conversion error: " . $e->getMessage());
        return null;
    }
}

/**
 * Calculates the driving distance between two points using the Google Distance Matrix API.
 * * @param string $origin The origin address string.
 * @param string $destination The destination address string.
 * @return array An array with 'distance_miles' on success, or 'error' on failure.
 */
function get_distance_from_api($origin, $destination) {
    $api_key = GOOGLE_MAPS_API_KEY; // This constant is defined elsewhere in your application
    $url = "https://maps.googleapis.com/maps/api/distancematrix/json?origins=" . urlencode($origin) . "&destinations=" . urlencode($destination) . "&key=" . $api_key;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code != 200 || $response === false) {
        return ['error' => 'Could not connect to the distance calculation service.'];
    }

    $data = json_decode($response, true);
    
    if (isset($data['status']) && $data['status'] === 'OK' && !empty($data['rows'][0]['elements'][0]['distance'])) {
        $distance_meters = $data['rows'][0]['elements'][0]['distance']['value'];
        $distance_miles = round($distance_meters * 0.000621371, 2);
        return ['distance_miles' => $distance_miles];
    } else {
        return ['error' => 'Could not calculate distance. Please check the addresses.'];
    }
}


// Fetch facility address to pre-populate the form
$facility_address = ['address_street' => '', 'address_city' => '', 'address_state' => '', 'address_zip' => ''];
if (isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['user', 'superuser', 'admin'])) {
    $facility_id_to_fetch = ($_SESSION['user_role'] === 'admin' && isset($_POST['facility_id'])) ? $_POST['facility_id'] : $_SESSION['entity_id'];
    if (!empty($facility_id_to_fetch)) {
        $sql = "SELECT address_street, address_city, address_state, address_zip FROM facilities WHERE id = ?";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("i", $facility_id_to_fetch);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $facility_address = array_map('htmlspecialchars', $row);
                }
            }
            $stmt->close();
        }
    }
}

// Form submission logic
$trip_error = '';
$trip_success = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Cloudflare Turnstile verification
    $turnstile_response = $_POST['cf-turnstile-response'] ?? null;
    $secretKey = CLOUD_FLARE_SECRET;
    $ip = $_SERVER['REMOTE_ADDR'];

    $postData = [
        'secret'   => $secretKey,
        'response' => $turnstile_response,
        'remoteip' => $ip,
    ];

    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    $response = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($response, true);

    if (!isset($result['success']) || !$result['success']) {
        $trip_error = "Security check failed. Please try again.";
        log_activity($mysqli, $_SESSION['user_id'], 'create_trip_failure', "Security check failed.");
    } else {
        // --- Start a database transaction ---
        $mysqli->begin_transaction();

        try {
            // --- DATA COLLECTION AND VALIDATION ---
            $facility_id = ($_SESSION['user_role'] === 'admin') ? (int)$_POST['facility_id'] : (int)$_SESSION['entity_id'];
            $created_by_user_id = (int)$_SESSION['user_id'];
            $asap = isset($_POST['asap_checkbox']) ? 1 : 0;
            
            // 2. ASAP & TIME VALIDATION
            if (!$asap && empty($_POST['requested_pickup_time']) && empty($_POST['appointment_time'])) {
                throw new Exception("If the trip is not ASAP, you must provide either a requested pickup time or an appointment time.");
            }
            
            // --- DISTANCE CALCULATION ---
            $origin_address = $_POST['pickup_address_street'] . ', ' . $_POST['pickup_address_city'] . ', ' . $_POST['pickup_address_state'] . ' ' . $_POST['pickup_address_zip'];
            $destination_address = $_POST['dropoff_address_street'] . ', ' . $_POST['dropoff_address_city'] . ', ' . $_POST['dropoff_address_state'] . ' ' . $_POST['dropoff_address_zip'];
            $distance_result = get_distance_from_api($origin_address, $destination_address);

            if (isset($distance_result['error'])) {
                throw new Exception("Distance calculation failed: " . $distance_result['error']);
            }
            $trip_distance_miles = $distance_result['distance_miles'];

            // 3. DYNAMIC BIDDING WINDOW
            $bidding_minutes = 15; // Default short-trip value
            $sql_config = "SELECT config_settings FROM facilities WHERE id = ?";
            if ($stmt_config = $mysqli->prepare($sql_config)) {
                $stmt_config->bind_param("i", $facility_id);
                if ($stmt_config->execute()) {
                    $result_config = $stmt_config->get_result();
                    if ($row_config = $result_config->fetch_assoc()) {
                        if (!empty($row_config['config_settings'])) {
                            $config = json_decode($row_config['config_settings'], true);
                            if ($trip_distance_miles >= 150) {
                                $bidding_minutes = $config['long_bid_duration'] ?? 30; // Use long bid duration
                            } else {
                                $bidding_minutes = $config['short_bid_duration'] ?? 15; // Use short bid duration
                            }
                        }
                    }
                }
                $stmt_config->close();
            }
            $bidding_closes_at = date('Y-m-d H:i:s', strtotime("+{$bidding_minutes} minutes"));

            // Collect special equipment details into a single string
            $special_equipment_details = [];
            if (!empty($_POST['special_equipment'])) {
                foreach ($_POST['special_equipment'] as $equipment) {
                    $detail = $equipment;
                    if ($equipment === 'Oxygen' && !empty($_POST['oxygen_notes'])) {
                        $detail .= ': ' . trim($_POST['oxygen_notes']);
                    } elseif ($equipment === 'IV') {
                        if (!empty($_POST['iv_notes'])) {
                            $detail .= ': ' . $_POST['iv_notes'];
                            if ($_POST['iv_notes'] === 'Flowing medications' && !empty($_POST['iv_meds'])) {
                                $detail .= ' - ' . trim($_POST['iv_meds']);
                            }
                        }
                    } elseif ($equipment === 'Ventilator' && !empty($_POST['ventilator_notes'])) {
                        $detail .= ': ' . trim($_POST['ventilator_notes']);
                    } elseif ($equipment === 'Other' && !empty($_POST['other_notes'])) {
                        $detail .= ': ' . trim($_POST['other_notes']);
                    }
                    $special_equipment_details[] = $detail;
                }
            }
            $special_equipment_string = implode('; ', $special_equipment_details);

            // Convert lbs to kg for patient weight
            $patient_weight_lbs = filter_var($_POST['patient_weight'], FILTER_VALIDATE_FLOAT);
            $patient_weight_kg = $patient_weight_lbs ? $patient_weight_lbs * 0.453592 : null;

            // --- ENCRYPT SENSITIVE DATA ---
            $encrypted_first_name = encrypt_data($_POST['patient_first_name'], ENCRYPTION_KEY);
            $encrypted_last_name = encrypt_data($_POST['patient_last_name'], ENCRYPTION_KEY);
            $encrypted_dob = encrypt_data($_POST['patient_dob'], ENCRYPTION_KEY);
            $encrypted_ssn = encrypt_data($_POST['patient_ssn'], ENCRYPTION_KEY);
            $encrypted_weight = encrypt_data((string)$patient_weight_kg, ENCRYPTION_KEY);
            $encrypted_height = encrypt_data($_POST['patient_height'], ENCRYPTION_KEY);
            $encrypted_diagnosis = encrypt_data($_POST['diagnosis'], ENCRYPTION_KEY);
            $encrypted_equipment = encrypt_data($special_equipment_string, ENCRYPTION_KEY);
            $encrypted_isolation = encrypt_data($_POST['isolation_precautions'], ENCRYPTION_KEY);
            
            if (!$encrypted_first_name || !$encrypted_last_name || !$encrypted_dob || !$encrypted_ssn) {
                throw new Exception("A critical error occurred while securing patient data. Please try again.");
            }

            // --- INSERT INTO 'trips' TABLE ---
$trip_uuid = null;
// Add the new columns to the query
$sql_trip = "INSERT INTO trips (uuid, facility_id, created_by_user_id, origin_name, origin_street, origin_room, origin_city, origin_state, origin_zip, destination_name, destination_street, destination_room, destination_city, destination_state, destination_zip, appointment_at, asap, requested_pickup_time, status, bidding_closes_at, distance) VALUES (UUID(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'bidding', ?, ?)";

if ($stmt_trip = $mysqli->prepare($sql_trip)) {
    
    // Convert times to UTC
    $appointment_at_utc = convert_to_utc($_POST['appointment_time'], USER_TIMEZONE);
    $requested_pickup_time_utc = convert_to_utc($_POST['requested_pickup_time'], USER_TIMEZONE);
    
    $origin_name = "Pickup Location";
    $destination_name = "Dropoff Location";

    // Add the room numbers to bind_param. Note the two new 's' types.
    $stmt_trip->bind_param(
        "iisssssssssssssisssd", 
        $facility_id, 
        $created_by_user_id, 
        $origin_name, 
        $_POST['pickup_address_street'], 
        $_POST['pickup_address_room'], // New
        $_POST['pickup_address_city'], 
        $_POST['pickup_address_state'], 
        $_POST['pickup_address_zip'], 
        $destination_name, 
        $_POST['dropoff_address_street'],
        $_POST['dropoff_address_room'], // New
        $_POST['dropoff_address_city'], 
        $_POST['dropoff_address_state'], 
        $_POST['dropoff_address_zip'], 
        $appointment_at_utc, 
        $asap, 
        $requested_pickup_time_utc, 
        $bidding_closes_at, 
        $trip_distance_miles
    );

    if (!$stmt_trip->execute()) {
        throw new Exception("Failed to create the trip record: " . $stmt_trip->error);
    }
    $trip_id = $mysqli->insert_id;
    $stmt_trip->close();

} else {
    throw new Exception("Database error preparing the trip record: " . $mysqli->error);
}

            // --- INSERT INTO 'TRIPS_PHI' TABLE ---
            $sql_patient = "INSERT INTO trips_phi (trip_id, uuid, patient_first_name_encrypted, patient_last_name_encrypted, patient_dob_encrypted, patient_ssn_last4_encrypted, patient_weight_kg_encrypted, patient_height_in_encrypted, diagnosis_encrypted, special_equipment_encrypted, isolation_precautions_encrypted) VALUES (?, UUID(), ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            if ($stmt_patient = $mysqli->prepare($sql_patient)) {
                $stmt_patient->bind_param("isssssssss", $trip_id, $encrypted_first_name, $encrypted_last_name, $encrypted_dob, $encrypted_ssn, $encrypted_weight, $encrypted_height, $encrypted_diagnosis, $encrypted_equipment, $encrypted_isolation);
                if (!$stmt_patient->execute()) {
                    throw new Exception("Failed to save patient details: " . $stmt_patient->error);
                }
                $stmt_patient->close();
            } else {
                throw new Exception("Database error preparing patient details: " . $mysqli->error);
            }

            // --- LOG THE ACTIVITY ---
            $log_message = "User successfully created a new trip request (Trip ID: {$trip_id}) for Facility ID: {$facility_id}.";
            if (!log_activity($mysqli, $created_by_user_id, 'create_trip_success', $log_message)) {
                error_log("CRITICAL: Failed to log successful trip creation for Trip ID: {$trip_id}.");
            }
            
            // --- COMMIT AND REDIRECT ---
            $mysqli->commit();

            // Redirect to the view trip page
            $sql_get_uuid = "SELECT uuid FROM trips WHERE id = ? LIMIT 1";
            if ($stmt_uuid = $mysqli->prepare($sql_get_uuid)) {
                $stmt_uuid->bind_param("i", $trip_id);
                $stmt_uuid->execute();
                $result_uuid = $stmt_uuid->get_result();
                if ($row_uuid = $result_uuid->fetch_assoc()) {
                    $trip_uuid = $row_uuid['uuid'];
                    header("Location: view_trip.php?uuid=" . urlencode($trip_uuid));
                    exit();
                }
                $stmt_uuid->close();
            }
            // Fallback in case UUID retrieval fails, show a success message on the current page
            $trip_success = "Trip request created successfully! The trip ID is #{$trip_id}.";

} catch (Exception $e) {
    $mysqli->rollback();
    
    // Log the detailed system error for IT review.
    $user_id_on_fail = $_SESSION['user_id'] ?? 0;
    $system_error_message = "System Error on trip creation: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine();
    log_activity($mysqli, $user_id_on_fail, 'create_trip_failure', $system_error_message);

    // Provide a generic, user-friendly error message.
    $trip_error = "We're sorry, but there was a problem creating the trip. Our technical team has been notified. Please try again later.";
}
    }
}
?>

<h1 class="text-3xl font-bold text-gray-800 mb-6">Create a New Transport Request</h1>

<div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200">
    <div class="p-6">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="create-trip-form" class="space-y-8">
            <?php if (!empty($trip_error)) {
                echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert"><p class="font-bold">Error</p><p>' . trim($trip_error) . '</p></div>';
            } ?>

            <?php if ($_SESSION['user_role'] === 'admin') : ?>
                <?php
                $facilities = [];
                $sql_facilities = "SELECT id, name FROM facilities WHERE is_active = 1 ORDER BY name ASC";
                if ($result = $mysqli->query($sql_facilities)) {
                    while ($row = $result->fetch_assoc()) {
                        $facilities[] = $row;
                    }
                }
                ?>
                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4" role="alert">
                    <p class="font-bold">Admin Mode</p>
                    <p>You are creating this trip on behalf of a facility. Please select one from the list below.</p>
                </div>
                <div>
                    <label for="facility_id" class="block text-sm font-medium text-gray-700">Create Trip For Facility:</label>
                    <select name="facility_id" id="facility_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required>
                        <option value="">-- Select a Facility --</option>
                        <?php foreach ($facilities as $facility) : ?>
                            <option value="<?php echo $facility['id']; ?>"><?php echo htmlspecialchars($facility['name']); ?></option>
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
                        <input type="text" id="pickup_address_street" name="pickup_address_street" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" value="<?php echo $facility_address['address_street']; ?>" required>
                    </div>
                    <div class="col-span-12 sm:col-span-3">
                        <label for="pickup_address_room" class="block text-sm font-medium text-gray-700">Room/Apt #</label>
                        <input type="text" name="pickup_address_room" id="pickup_address_room" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                    <div class="col-span-12 sm:col-span-6">
                        <label for="pickup_address_city" class="block text-sm font-medium text-gray-700">City</label>
                        <input type="text" name="pickup_address_city" id="pickup_address_city" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" value="<?php echo $facility_address['address_city']; ?>" required>
                    </div>
                    <div class="col-span-12 sm:col-span-3">
                        <label for="pickup_address_state" class="block text-sm font-medium text-gray-700">State</label>
                        <select name="pickup_address_state" id="pickup_address_state" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required>
                            <option value="">Choose...</option>
                            <?php foreach ($states as $abbreviation => $name) : ?>
                                <option value="<?php echo $abbreviation; ?>" <?php echo ($facility_address['address_state'] == $abbreviation) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-span-12 sm:col-span-3">
                        <label for="pickup_address_zip" class="block text-sm font-medium text-gray-700">Zip Code</label>
                        <input type="text" name="pickup_address_zip" id="pickup_address_zip" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" value="<?php echo $facility_address['address_zip']; ?>" required>
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend class="text-xl font-semibold text-gray-800 border-b border-gray-200 pb-2 mb-4">Drop-off Details</legend>
                <div class="relative flex items-start mb-4">
                    <div class="flex h-5 items-center">
                        <input id="copy_pickup_address" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="copy_pickup_address" class="font-medium text-gray-700">Same as Pickup Address</label>
                    </div>
                </div>
                <div class="grid grid-cols-12 gap-6">
                    <div class="col-span-12 sm:col-span-9">
                        <label for="dropoff_address_street" class="block text-sm font-medium text-gray-700">Street Address</label>
                        <input type="text" name="dropoff_address_street" id="dropoff_address_street" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required>
                        <div id="room-number-alert" class="hidden mt-2 bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-3 text-sm" role="alert">
                            Based on past trips to this address, a room or apartment number is often needed.
                        </div>
                    </div>
                    <div class="col-span-12 sm:col-span-3">
                        <label for="dropoff_address_room" class="block text-sm font-medium text-gray-700">Room/Apt #</label>
                        <input type="text" name="dropoff_address_room" id="dropoff_address_room" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                    <div class="col-span-12 sm:col-span-6">
                        <label for="dropoff_address_city" class="block text-sm font-medium text-gray-700">City</label>
                        <input type="text" name="dropoff_address_city" id="dropoff_address_city" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required>
                    </div>
                    <div class="col-span-12 sm:col-span-3">
                        <label for="dropoff_address_state" class="block text-sm font-medium text-gray-700">State</label>
                        <select name="dropoff_address_state" id="dropoff_address_state" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required>
                            <option value="">Choose...</option>
                            <?php foreach ($states as $abbreviation => $name) : ?>
                                <option value="<?php echo $abbreviation; ?>"><?php echo htmlspecialchars($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-span-12 sm:col-span-3">
                        <label for="dropoff_address_zip" class="block text-sm font-medium text-gray-700">Zip Code</label>
                        <input type="text" name="dropoff_address_zip" id="dropoff_address_zip" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required>
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend class="text-xl font-semibold text-gray-800 border-b border-gray-200 pb-2 mb-4">Time and Appointment</legend>
                 <div class="flex items-center mb-4">
                    <input class="h-4 w-4 rounded border-gray-300 text-red-600 focus:ring-red-500" type="checkbox" name="asap_checkbox" id="asap_checkbox" value="1" checked>
                    <label class="ml-2 block text-sm font-bold text-red-600" for="asap_checkbox">ASAP</label>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="requested_pickup_time" class="block text-sm font-medium text-gray-700">Requested Pickup Time <small class="text-gray-500">(Optional)</small></label>
                        <input type="time" name="requested_pickup_time" id="requested_pickup_time" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm disabled:bg-gray-100" disabled>
                    </div>
                    <div>
                        <label for="appointment_time" class="block text-sm font-medium text-gray-700">Appointment Time <small class="text-gray-500">(Optional)</small></label>
                        <input type="time" name="appointment_time" id="appointment_time" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm disabled:bg-gray-100" disabled>
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend class="text-xl font-semibold text-gray-800 border-b border-gray-200 pb-2 mb-4">Medical Information</legend>
                <div class="space-y-6">
                    <div>
                        <label for="diagnosis" class="block text-sm font-medium text-gray-700">Primary Diagnosis</label>
                        <input type="text" name="diagnosis" id="diagnosis" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="E.g., Congestive Heart Failure, COPD Exacerbation" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Special Equipment Needed</label>
                        <div class="mt-2 space-y-4">
                            <div class="relative flex items-start">
                                <div class="flex h-5 items-center"><input id="equipment_oxygen" name="special_equipment[]" type="checkbox" value="Oxygen" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"></div>
                                <div class="ml-3 text-sm"><label for="equipment_oxygen" class="font-medium text-gray-700">Oxygen</label></div>
                            </div>
                            <div id="oxygen_details" class="hidden ml-7"><input type="text" name="oxygen_notes" id="oxygen_notes" class="block w-full sm:w-1/2 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="E.g., 2L via NC"></div>
                            
                            <div class="relative flex items-start">
                                <div class="flex h-5 items-center"><input id="equipment_monitor" name="special_equipment[]" type="checkbox" value="Cardiac Monitor" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"></div>
                                <div class="ml-3 text-sm"><label for="equipment_monitor" class="font-medium text-gray-700">Cardiac Monitor</label></div>
                            </div>

                            <div class="relative flex items-start">
                                <div class="flex h-5 items-center"><input id="equipment_iv" name="special_equipment[]" type="checkbox" value="IV" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"></div>
                                <div class="ml-3 text-sm"><label for="equipment_iv" class="font-medium text-gray-700">IV</label></div>
                            </div>
                            <div id="iv_details" class="hidden ml-7 space-y-2">
                                <div class="flex items-center"><input id="iv_locked" name="iv_notes" type="radio" value="Saline locked" class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500"><label for="iv_locked" class="ml-3 block text-sm font-medium text-gray-700">Saline locked</label></div>
                                <div class="flex items-center"><input id="iv_flowing" name="iv_notes" type="radio" value="Flowing medications" class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500"><label for="iv_flowing" class="ml-3 block text-sm font-medium text-gray-700">Flowing medication(s)</label></div>
                                <div id="iv_meds_container" class="hidden ml-7"><input type="text" name="iv_meds" id="iv_meds" class="block w-full sm:w-1/2 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="E.g., Dopamine, Norepinephrine"></div>
                            </div>
                            
                            <div class="relative flex items-start">
                                <div class="flex h-5 items-center"><input id="equipment_ventilator" name="special_equipment[]" type="checkbox" value="Ventilator" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"></div>
                                <div class="ml-3 text-sm"><label for="equipment_ventilator" class="font-medium text-gray-700">Ventilator</label></div>
                            </div>
                            <div id="ventilator_details" class="hidden ml-7"><input type="text" name="ventilator_notes" id="ventilator_notes" class="block w-full sm:w-1/2 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="E.g., A/C 12, PEEP 5, FiO2 40%"></div>
                            
                            <div class="relative flex items-start">
                                <div class="flex h-5 items-center"><input id="equipment_ecmo" name="special_equipment[]" type="checkbox" value="ECMO" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"></div>
                                <div class="ml-3 text-sm"><label for="equipment_ecmo" class="font-medium text-gray-700">ECMO</label></div>
                            </div>

                            <div class="relative flex items-start">
                                <div class="flex h-5 items-center"><input id="equipment_other" name="special_equipment[]" type="checkbox" value="Other" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"></div>
                                <div class="ml-3 text-sm"><label for="equipment_other" class="font-medium text-gray-700">Other</label></div>
                            </div>
                            <div id="other_details" class="hidden ml-7"><input type="text" name="other_notes" id="other_notes" class="block w-full sm:w-1/2 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="Please specify other equipment"></div>
                        </div>
                    </div>
                     <div>
                         <label for="isolation_precautions" class="block text-sm font-medium text-gray-700">Medical Isolation Precautions</label>
                         <input type="text" name="isolation_precautions" id="isolation_precautions" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="E.g., Airborne, Droplet, Contact, None">
                     </div>
                </div>
            </fieldset>

            <div class="cf-turnstile" data-sitekey="0x4AAAAAABsE3bLaSnTnuUzR"></div>

            <div class="pt-5">
                <div class="flex justify-end">
                    <button type="submit" class="w-full md:w-auto inline-flex justify-center py-3 px-6 border border-transparent shadow-sm text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">Submit Trip Request</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const roomAlert = document.getElementById('room-number-alert');

    const toggleElementVisibility = (element, show) => {
        element.classList.toggle('hidden', !show);
    };

    async function checkRoomNumberPrompt(address) {
        if (!address) return;
        const roomInput = document.getElementById('dropoff_address_room');
        if (roomInput.value.trim() !== '') {
            toggleElementVisibility(roomAlert, false);
            return;
        }
        try {
            const response = await fetch('check_address.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ address })
            });
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const data = await response.json();
            toggleElementVisibility(roomAlert, data.prompt_room_number);
        } catch (error) {
            console.error('Error checking address:', error);
            toggleElementVisibility(roomAlert, false);
        }
    }

    function triggerDropoffAddressCheck() {
        const street = document.getElementById('dropoff_address_street').value.trim();
        const city = document.getElementById('dropoff_address_city').value.trim();
        const state = document.getElementById('dropoff_address_state').value.trim();
        const zip = document.getElementById('dropoff_address_zip').value.trim();
        if (street && city && state) {
            const fullAddress = `${street}, ${city}, ${state} ${zip}`;
            checkRoomNumberPrompt(fullAddress);
        }
    }

    const dropoffFields = ['dropoff_address_street', 'dropoff_address_city', 'dropoff_address_state', 'dropoff_address_zip'];
    dropoffFields.forEach(id => {
        document.getElementById(id).addEventListener('blur', triggerDropoffAddressCheck);
    });

    document.getElementById('copy_pickup_address').addEventListener('change', function() {
        const isChecked = this.checked;
        const fields = ['street', 'city', 'state', 'zip', 'room'];
        fields.forEach(field => {
            const pickupEl = document.getElementById(`pickup_address_${field}`);
            const dropoffEl = document.getElementById(`dropoff_address_${field}`);
            if(pickupEl && dropoffEl) {
                dropoffEl.value = isChecked ? pickupEl.value : '';
            }
        });
        if (isChecked) {
            triggerDropoffAddressCheck();
        } else {
            toggleElementVisibility(roomAlert, false);
        }
    });

    document.getElementById('dropoff_address_room').addEventListener('input', function() {
        if (this.value.trim() !== '') {
            toggleElementVisibility(roomAlert, false);
        }
    });

    const asapCheckbox = document.getElementById('asap_checkbox');
    const pickupTimeInput = document.getElementById('requested_pickup_time');
    const appointmentTimeInput = document.getElementById('appointment_time');

    asapCheckbox.addEventListener('change', function() {
        const isDisabled = this.checked;
        pickupTimeInput.disabled = isDisabled;
        appointmentTimeInput.disabled = isDisabled;
    });

    const setupConditionalDisplay = (checkboxId, detailsId) => {
        const checkbox = document.getElementById(checkboxId);
        const details = document.getElementById(detailsId);
        if (checkbox && details) {
            checkbox.addEventListener('change', () => toggleElementVisibility(details, checkbox.checked));
        }
    };
    
    setupConditionalDisplay('equipment_oxygen', 'oxygen_details');
    setupConditionalDisplay('equipment_iv', 'iv_details');
    setupConditionalDisplay('equipment_ventilator', 'ventilator_details');
    setupConditionalDisplay('equipment_other', 'other_details');

    const ivFlowingRadio = document.getElementById('iv_flowing');
    const ivMedsContainer = document.getElementById('iv_meds_container');
    if(ivFlowingRadio && ivMedsContainer){
        document.querySelectorAll('input[name="iv_notes"]').forEach(radio => {
            radio.addEventListener('change', function() {
                toggleElementVisibility(ivMedsContainer, ivFlowingRadio.checked);
            });
        });
    }
});
</script>

<?php
require_once 'footer.php';
// Flush the output buffer and send content to the browser.
ob_end_flush();
?>