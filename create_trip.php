<?php
$page_title = 'Create New Trip';
require_once 'header.php';
require_once __DIR__ . '/../../app/db_connect.php';

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

function encrypt_data_placeholder($data) {
    return base64_encode($data);
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
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Standardize the pickup address
    $pickup_standardized = standardize_address(
        $_POST['pickup_address_street'],
        $_POST['pickup_address_city'],
        $_POST['pickup_address_state'],
        $_POST['pickup_address_zip']
    );

    if (!$pickup_standardized['success']) {
        $trip_error = "Pickup Address Error: " . $pickup_standardized['error'];
    }

    // Standardize the drop-off address
    $dropoff_standardized = standardize_address(
        $_POST['dropoff_address_street'],
        $_POST['dropoff_address_city'],
        $_POST['dropoff_address_state'],
        $_POST['dropoff_address_zip']
    );

    if (!$dropoff_standardized['success']) {
        $trip_error .= " Drop-off Address Error: " . $dropoff_standardized['error'];
    }

    // If there are no errors, proceed with creating the trip
    if (empty($trip_error)) {
        // --- PROCEED WITH FORM SUBMISSION ---
        $pickup_address_formatted = $pickup_standardized['formatted_address'];
        $dropoff_address_formatted = $dropoff_standardized['formatted_address'];
        // ... rest of your submission logic ...
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
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="requested_pickup_time" class="form-label">Requested Pickup Time <small class="text-muted">(Optional)</small></label>
                        <input type="time" name="requested_pickup_time" id="requested_pickup_time" class="form-control">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="appointment_time" class="form-label">Appointment Time <small class="text-muted">(Optional)</small></label>
                        <input type="time" name="appointment_time" id="appointment_time" class="form-control">
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
</script>

<?php
require_once 'footer.php';
?>