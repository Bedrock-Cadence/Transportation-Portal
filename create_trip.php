<?php
$page_title = 'Create New Trip';
require_once 'header.php';
require_once __DIR__ . '/../../app/db_connect.php';
require_once __DIR__ . '/../../app/encryption_service.php'; // Include our new service
require_once __DIR__ . '/../../app/logging_service.php';   // Include our new service


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

// Permission Check
if (!isset($_SESSION["loggedin"]) || !(in_array($_SESSION['user_role'], ['facility_user', 'facility_superuser']) || $_SESSION['user_role'] === 'bedrock_admin')) {
    header("location: login.php");
    exit;
}

/**
 * Standardizes an address using the Google Geocoding API.
 *
 * @param string $street
 * @param string $city
 * @param string $state
 * @param string $zip
 * @return array An array containing the success status and either the formatted address or an error message.
 */
function standardize_address($street, $city, $state, $zip) {
    $street = trim($street);
    $city = trim($city);
    $state = trim($state);
    $zip = trim($zip);

    if (empty($street) || empty($city) || empty($state)) {
        return ['success' => false, 'error' => 'Incomplete address provided.'];
    }

    $address_string = urlencode("$street, $city, $state, $zip");
    $api_key = GOOGLE_MAPS_API_KEY; // This constant is defined elsewhere in your application
    $url = "https://maps.googleapis.com/maps/api/geocode/json?address={$address_string}&key={$api_key}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code != 200 || $response === false) {
        return ['success' => false, 'error' => 'Could not connect to the address verification service.'];
    }

    $data = json_decode($response, true);

    if ($data['status'] == 'OK') {
        $result = $data['results'][0];
        return [
            'success' => true,
            'formatted_address' => $result['formatted_address'],
            'latitude' => $result['geometry']['location']['lat'],
            'longitude' => $result['geometry']['location']['lng'],
            'place_id' => $result['place_id']
        ];
    } else {
        return ['success' => false, 'error' => 'Address could not be verified. Please check for errors.'];
    }
}


// Fetch facility address to pre-populate the form
$facility_address = ['address_street' => '', 'address_city' => '', 'address_state' => '', 'address_zip' => ''];
if (isset($_SESSION['user_role'])) {
    $facility_id_to_fetch = ($_SESSION['user_role'] === 'bedrock_admin' && isset($_POST['facility_id'])) ? $_POST['facility_id'] : $_SESSION['entity_id'];
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

    // --- Start a database transaction ---
    $mysqli->begin_transaction();

    try {
        // --- DATA COLLECTION AND VALIDATION ---
        $facility_id = ($_SESSION['user_role'] === 'bedrock_admin') ? (int)$_POST['facility_id'] : (int)$_SESSION['entity_id'];
        $created_by_user_id = (int)$_SESSION['user_id'];
        $asap = isset($_POST['asap_checkbox']) ? 1 : 0;
        
        // 2. ASAP & TIME VALIDATION
        if (!$asap && empty($_POST['requested_pickup_time']) && empty($_POST['appointment_time'])) {
            throw new Exception("If the trip is not ASAP, you must provide either a requested pickup time or an appointment time.");
        }

        // 3. DYNAMIC BIDDING WINDOW
        $bidding_minutes = 10; // Default value
        $sql_config = "SELECT config_settings FROM facilities WHERE id = ?";
        if ($stmt_config = $mysqli->prepare($sql_config)) {
            $stmt_config->bind_param("i", $facility_id);
            if ($stmt_config->execute()) {
                $result_config = $stmt_config->get_result();
                if ($row_config = $result_config->fetch_assoc()) {
                    if (!empty($row_config['config_settings'])) {
                        $config = json_decode($row_config['config_settings'], true);
                        if (isset($config['bidding_window_minutes']) && is_numeric($config['bidding_window_minutes'])) {
                            $bidding_minutes = (int)$config['bidding_window_minutes'];
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
        $trip_uuid = null; // Initialize variable to hold the new UUID
        $sql_trip = "INSERT INTO trips (uuid, facility_id, created_by_user_id, origin_name, origin_street, origin_city, origin_state, origin_zip, destination_name, destination_street, destination_city, destination_state, destination_zip, appointment_at, asap, requested_pickup_time, status, bidding_closes_at) VALUES (UUID(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'bidding', ?)";

        if ($stmt_trip = $mysqli->prepare($sql_trip)) {
            $appointment_at = (!$asap && !empty($_POST['appointment_time'])) ? date('Y-m-d H:i:s', strtotime($_POST['appointment_time'])) : null;
            $requested_pickup_time = (!$asap && !empty($_POST['requested_pickup_time'])) ? $_POST['requested_pickup_time'] : null;
            $origin_name = "Pickup Location"; 
            $destination_name = "Dropoff Location"; 

            $stmt_trip->bind_param("iisssssssssssiss", $facility_id, $created_by_user_id, $origin_name, $_POST['pickup_address_street'], $_POST['pickup_address_city'], $_POST['pickup_address_state'], $_POST['pickup_address_zip'], $destination_name, $_POST['dropoff_address_street'], $_POST['dropoff_address_city'], $_POST['dropoff_address_state'], $_POST['dropoff_address_zip'], $appointment_at, $asap, $requested_pickup_time, $bidding_closes_at);

            if (!$stmt_trip->execute()) {
                throw new Exception("Failed to create the trip record: " . $stmt_trip->error);
            }
            $trip_id = $mysqli->insert_id;
            $stmt_trip->close();

        } else {
            throw new Exception("Database error preparing the trip record: " . $mysqli->error);
        }

        // --- INSERT INTO 'TRIPS_PHI' TABLE ---
        // 4. PHI TABLE UUID
        $sql_patient = "INSERT INTO TRIPS_PHI (trip_id, uuid, patient_first_name_encrypted, patient_last_name_encrypted, patient_dob_encrypted, patient_ssn_last4_encrypted, patient_weight_kg_encrypted, patient_height_in_encrypted, diagnosis_encrypted, special_equipment_encrypted, isolation_precautions_encrypted) VALUES (?, UUID(), ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        if ($stmt_patient = $mysqli->prepare($sql_patient)) {
            // Note the new bind_param string starts with 'i' for trip_id, then 10 's' strings for the encrypted data
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

        // 1. REDIRECT LOGIC: Get the UUID of the trip we just created
        $sql_get_uuid = "SELECT uuid FROM trips WHERE id = ? LIMIT 1";
        if ($stmt_uuid = $mysqli->prepare($sql_get_uuid)) {
            $stmt_uuid->bind_param("i", $trip_id);
            $stmt_uuid->execute();
            $result_uuid = $stmt_uuid->get_result();
            if ($row_uuid = $result_uuid->fetch_assoc()) {
                $trip_uuid = $row_uuid['uuid'];
                // Redirect to the view trip page
                header("Location: view_trip.php?uuid=" . urlencode($trip_uuid));
                exit();
            }
            $stmt_uuid->close();
        }
        // Fallback in case UUID retrieval fails, show a success message on the current page
        $trip_success = "Trip request created successfully! The trip ID is #{$trip_id}.";


    } catch (Exception $e) {
        $mysqli->rollback();
        $trip_error = "Error creating trip: " . $e->getMessage();
        
        log_activity($mysqli, $_SESSION['user_id'], 'create_trip_failure', 'Error: ' . $e->getMessage());
    }
}
?>

<h2 class="mb-4">Create a New Transport Request</h2>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="create-trip-form">
            <?php if (!empty($trip_error)) {
                echo '<div class="alert alert-danger">' . trim($trip_error) . '</div>';
            } ?>

            <?php if ($_SESSION['user_role'] === 'bedrock_admin') : ?>
                <?php
                $facilities = [];
                $sql_facilities = "SELECT id, name FROM facilities WHERE is_active = 1 ORDER BY name ASC";
                if ($result = $mysqli->query($sql_facilities)) {
                    while ($row = $result->fetch_assoc()) {
                        $facilities[] = $row;
                    }
                }
                ?>
                <div class="alert alert-warning">
                    <h5 class="alert-heading">Admin Mode</h5>
                    <p class="mb-0">You are creating this trip on behalf of a facility. Please select one from the list below.</p>
                </div>
                <div class="mb-4">
                    <label for="facility_id" class="form-label fw-bold">Create Trip For Facility:</label>
                    <select name="facility_id" id="facility_id" class="form-select" required>
                        <option value="">-- Select a Facility --</option>
                        <?php foreach ($facilities as $facility) : ?>
                            <option value="<?php echo $facility['id']; ?>"><?php echo htmlspecialchars($facility['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <hr class="mb-4">
            <?php endif; ?>

            <fieldset class="mb-4">
                <legend class="fs-5 border-bottom mb-3 pb-2">Patient Information</legend>
                <div class="alert alert-info" role="alert">
                    <p class="mb-0"><strong>IMPORTANT:</strong> This form collects Protected Health Information (PHI) and will be encrypted.</p>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="patient_first_name" class="form-label">First Name</label>
                        <input type="text" name="patient_first_name" id="patient_first_name" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="patient_last_name" class="form-label">Last Name</label>
                        <input type="text" name="patient_last_name" id="patient_last_name" class="form-control" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="patient_dob" class="form-label">Date of Birth</label>
                        <input type="date" name="patient_dob" id="patient_dob" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="patient_ssn" class="form-label">Social Security Number (Last 4 Digits)</label>
                        <input type="text" name="patient_ssn" id="patient_ssn" class="form-control" pattern="\d{4}" maxlength="4" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="patient_weight" class="form-label">Weight (in lbs)</label>
                        <input type="number" name="patient_weight" id="patient_weight" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="patient_height" class="form-label">Height (in inches)</label>
                        <input type="number" name="patient_height" id="patient_height" class="form-control" required>
                    </div>
                </div>
            </fieldset>

            <fieldset class="mb-4">
                <legend class="fs-5 border-bottom mb-3 pb-2">Pickup Details</legend>
                <div class="row">
                    <div class="col-md-9 mb-3">
                        <label for="pickup_address_street" class="form-label">Street Address</label>
                        <input type="text" id="pickup_address_street" name="pickup_address_street" class="form-control" value="<?php echo $facility_address['address_street']; ?>" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="pickup_address_room" class="form-label">Room/Apt #</label>
                        <input type="text" name="pickup_address_room" id="pickup_address_room" class="form-control">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="pickup_address_city" class="form-label">City</label>
                        <input type="text" name="pickup_address_city" id="pickup_address_city" class="form-control" value="<?php echo $facility_address['address_city']; ?>" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="pickup_address_state" class="form-label">State</label>
                        <select name="pickup_address_state" id="pickup_address_state" class="form-select" required>
                            <option value="">Choose...</option>
                            <?php foreach ($states as $abbreviation => $name) : ?>
                                <option value="<?php echo $abbreviation; ?>" <?php echo ($facility_address['address_state'] == $abbreviation) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="pickup_address_zip" class="form-label">Zip Code</label>
                        <input type="text" name="pickup_address_zip" id="pickup_address_zip" class="form-control" value="<?php echo $facility_address['address_zip']; ?>" required>
                    </div>
                </div>
            </fieldset>

            <fieldset class="mb-4">
                <legend class="fs-5 border-bottom mb-3 pb-2">Drop-off Details</legend>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="copy_pickup_address">
                    <label class="form-check-label" for="copy_pickup_address">Same as Pickup Address</label>
                </div>
                <div class="row">
                    <div class="col-md-9 mb-3">
                        <label for="dropoff_address_street" class="form-label">Street Address</label>
                        <input type="text" name="dropoff_address_street" id="dropoff_address_street" class="form-control" required>
                        <div id="room-number-alert" class="alert alert-warning mt-2 d-none" role="alert">
                            Based on past trips to this address, a room or apartment number is often needed.
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="dropoff_address_room" class="form-label">Room/Apt #</label>
                        <input type="text" name="dropoff_address_room" id="dropoff_address_room" class="form-control">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="dropoff_address_city" class="form-label">City</label>
                        <input type="text" name="dropoff_address_city" id="dropoff_address_city" class="form-control" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="dropoff_address_state" class="form-label">State</label>
                        <select name="dropoff_address_state" id="dropoff_address_state" class="form-select" required>
                            <option value="">Choose...</option>
                            <?php foreach ($states as $abbreviation => $name) : ?>
                                <option value="<?php echo $abbreviation; ?>"><?php echo htmlspecialchars($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="dropoff_address_zip" class="form-label">Zip Code</label>
                        <input type="text" name="dropoff_address_zip" id="dropoff_address_zip" class="form-control" required>
                    </div>
                </div>
            </fieldset>

<fieldset class="mb-4">
    <legend class="fs-5 border-bottom mb-3 pb-2">Time and Appointment</legend>
    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="asap_checkbox" id="asap_checkbox" value="1" checked>
        <label class="form-check-label text-danger" for="asap_checkbox">ASAP</label>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="requested_pickup_time" class="form-label">Requested Pickup Time <small class="text-muted">(Optional)</small></label>
            <input type="time" name="requested_pickup_time" id="requested_pickup_time" class="form-control" disabled>
        </div>
        <div class="col-md-6 mb-3">
            <label for="appointment_time" class="form-label">Appointment Time <small class="text-muted">(Optional)</small></label>
            <input type="time" name="appointment_time" id="appointment_time" class="form-control" disabled>
        </div>
    </div>
</fieldset>

<!-- Medical Information Fieldset -->
<fieldset class="mb-4">
    <legend class="fs-5 border-bottom mb-3 pb-2">Medical Information</legend>
    <div class="row">
        <div class="col-md-12 mb-3">
            <label for="diagnosis" class="form-label">Primary Diagnosis</label>
            <input type="text" name="diagnosis" id="diagnosis" class="form-control" placeholder="E.g., Congestive Heart Failure, COPD Exacerbation" required>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12 mb-3">
            <label for="special_equipment" class="form-label">Special Equipment Needed</label>
            <div class="form-check">
                <input class="form-check-input special-equipment" type="checkbox" value="Oxygen" id="equipment_oxygen" name="special_equipment[]">
                <label class="form-check-label" for="equipment_oxygen">Oxygen</label>
            </div>
            <div id="oxygen_details" class="ms-4 mt-2 mb-3" style="display: none;">
                <label for="oxygen_notes" class="form-label">Oxygen Details</label>
                <input type="text" name="oxygen_notes" id="oxygen_notes" class="form-control" placeholder="E.g., 2L via NC">
            </div>

            <div class="form-check">
                <input class="form-check-input special-equipment" type="checkbox" value="Cardiac Monitor" id="equipment_monitor" name="special_equipment[]">
                <label class="form-check-label" for="equipment_monitor">Cardiac Monitor</label>
            </div>

            <div class="form-check">
                <input class="form-check-input special-equipment" type="checkbox" value="IV" id="equipment_iv" name="special_equipment[]">
                <label class="form-check-label" for="equipment_iv">IV</label>
            </div>
            <div id="iv_details" class="ms-4 mt-2 mb-3" style="display: none;">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="iv_notes" id="iv_locked" value="Saline locked">
                    <label class="form-check-label" for="iv_locked">Saline locked</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="iv_notes" id="iv_flowing" value="Flowing medications">
                    <label class="form-check-label" for="iv_flowing">Flowing medication(s)</label>
                </div>
                <div id="iv_meds_container" class="ms-4 mt-2" style="display: none;">
                    <label for="iv_meds" class="form-label">Medication(s)</label>
                    <input type="text" name="iv_meds" id="iv_meds" class="form-control" placeholder="E.g., Dopamine, Norepinephrine">
                </div>
            </div>

            <div class="form-check">
                <input class="form-check-input special-equipment" type="checkbox" value="Ventilator" id="equipment_ventilator" name="special_equipment[]">
                <label class="form-check-label" for="equipment_ventilator">Ventilator</label>
            </div>
            <div id="ventilator_details" class="ms-4 mt-2 mb-3" style="display: none;">
                <label for="ventilator_notes" class="form-label">Vent Settings</label>
                <input type="text" name="ventilator_notes" id="ventilator_notes" class="form-control" placeholder="E.g., A/C 12, PEEP 5, FiO2 40%">
            </div>

            <div class="form-check">
                <input class="form-check-input special-equipment" type="checkbox" value="ECMO" id="equipment_ecmo" name="special_equipment[]">
                <label class="form-check-label" for="equipment_ecmo">ECMO</label>
            </div>
            
            <div class="form-check">
                <input class="form-check-input special-equipment" type="checkbox" value="Other" id="equipment_other" name="special_equipment[]">
                <label class="form-check-label" for="equipment_other">Other</label>
            </div>
            <div id="other_details" class="ms-4 mt-2 mb-3" style="display: none;">
                <label for="other_notes" class="form-label">Other Equipment Notes</label>
                <input type="text" name="other_notes" id="other_notes" class="form-control" placeholder="Please specify other equipment">
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12 mb-3">
            <label for="isolation_precautions" class="form-label">Medical Isolation Precautions</label>
            <input type="text" name="isolation_precautions" id="isolation_precautions" class="form-control" placeholder="E.g., Airborne, Droplet, Contact, None">
        </div>
    </div>
</fieldset>

            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-lg">Submit Trip Request</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const roomAlert = document.getElementById('room-number-alert');

    async function checkRoomNumberPrompt(address) {
        if (!address) return;
        const roomInput = document.getElementById('dropoff_address_room');
        if (roomInput.value.trim() !== '') {
            roomAlert.classList.add('d-none');
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
            roomAlert.classList.toggle('d-none', !data.prompt_room_number);
        } catch (error) {
            console.error('Error checking address:', error);
            roomAlert.classList.add('d-none');
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
            dropoffEl.value = isChecked ? pickupEl.value : '';
        });
        if (isChecked) {
            triggerDropoffAddressCheck();
        } else {
            roomAlert.classList.add('d-none');
        }
    });

    document.getElementById('dropoff_address_room').addEventListener('input', function() {
        if (this.value.trim() !== '') {
            roomAlert.classList.add('d-none');
        }
    });
});

// Function to handle the ASAP checkbox
const asapCheckbox = document.getElementById('asap_checkbox');
const pickupTimeInput = document.getElementById('requested_pickup_time');
const appointmentTimeInput = document.getElementById('appointment_time');

asapCheckbox.addEventListener('change', function() {
    if (this.checked) {
        pickupTimeInput.disabled = true;
        appointmentTimeInput.disabled = true;
    } else {
        pickupTimeInput.disabled = false;
        appointmentTimeInput.disabled = false;
    }
});

// --- Special Equipment Conditional Logic ---
const oxygenCheckbox = document.getElementById('equipment_oxygen');
const oxygenDetails = document.getElementById('oxygen_details');

const ivCheckbox = document.getElementById('equipment_iv');
const ivDetails = document.getElementById('iv_details');
const ivFlowingRadio = document.getElementById('iv_flowing');
const ivMedsContainer = document.getElementById('iv_meds_container');

const ventilatorCheckbox = document.getElementById('equipment_ventilator');
const ventilatorDetails = document.getElementById('ventilator_details');

const otherCheckbox = document.getElementById('equipment_other');
const otherDetails = document.getElementById('other_details');

oxygenCheckbox.addEventListener('change', function() {
    oxygenDetails.style.display = this.checked ? 'block' : 'none';
});

ivCheckbox.addEventListener('change', function() {
    ivDetails.style.display = this.checked ? 'block' : 'none';
});

ivFlowingRadio.addEventListener('change', function() {
    ivMedsContainer.style.display = this.checked ? 'block' : 'none';
});

ventilatorCheckbox.addEventListener('change', function() {
    ventilatorDetails.style.display = this.checked ? 'block' : 'none';
});

otherCheckbox.addEventListener('change', function() {
    otherDetails.style.display = this.checked ? 'block' : 'none';
});
</script>

<?php
require_once 'footer.php';
?>