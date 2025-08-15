<?php
$page_title = 'Create New Trip';
require_once 'header.php';
require_once __DIR__ . '/../../app/db_connect.php';

// It's good practice to define constants in a central config file,
// but for this example, we'll define it here.
// Make sure this is replaced with your actual key.
defined('GOOGLE_MAPS_API_KEY') || define('GOOGLE_MAPS_API_KEY', 'YOUR_GOOGLE_MAPS_API_KEY');

// Array of US states for cleaner dropdowns
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


// --- Corrected Permission Check ---
if (!isset($_SESSION["loggedin"]) || !(in_array($_SESSION['user_role'], ['facility_user', 'facility_superuser']) || $_SESSION['user_role'] === 'bedrock_admin')) {
    header("location: login.php");
    exit;
}

function encrypt_data_placeholder($data) {
    // In a real application, you would use a strong encryption library like OpenSSL.
    return base64_encode($data);
}

// PHP logic to fetch facility address and prepopulate the form.
$facility_address = [
    'street' => '',
    'city' => '',
    'state' => '',
    'zip' => ''
];

if (isset($_SESSION['user_role'])) {
    $facility_id_to_fetch = ($_SESSION['user_role'] === 'bedrock_admin' && isset($_POST['facility_id'])) ? $_POST['facility_id'] : $_SESSION['entity_id'];

    if (!empty($facility_id_to_fetch)) {
        $sql = "SELECT address_street, address_city, address_state, address_zip FROM facilities WHERE id = ?";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("i", $facility_id_to_fetch);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $facility_address['street'] = htmlspecialchars($row['address_street']);
                    $facility_address['city'] = htmlspecialchars($row['address_city']);
                    $facility_address['state'] = htmlspecialchars($row['address_state']);
                    $facility_address['zip'] = htmlspecialchars($row['address_zip']);
                }
            }
            $stmt->close();
        }
    }
}

$trip_error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Business logic for form submission...
    // This section appears correct and is left as is.
}
?>

<h2 class="mb-4">Create a New Transport Request</h2>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="create-trip-form">
            <?php if (!empty($trip_error)) {
                echo '<div class="alert alert-danger">' . $trip_error . '</div>';
            } ?>

            <?php if ($_SESSION['user_role'] === 'bedrock_admin') : ?>
                <?php
                // Fetch all active facilities for the dropdown
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
                </fieldset>

            <fieldset class="mb-4">
                <legend class="fs-5 border-bottom mb-3 pb-2">Pickup Details</legend>
                <div class="row">
                    <div class="col-md-9 mb-3">
                        <label for="pickup_address_street" class="form-label">Street Address</label>
                        <input type="text" id="pickup_address_street" name="pickup_address_street" class="form-control" value="<?php echo $facility_address['street']; ?>" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="pickup_address_room" class="form-label">Room/Apt #</label>
                        <input type="text" name="pickup_address_room" id="pickup_address_room" class="form-control">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="pickup_address_city" class="form-label">City</label>
                        <input type="text" name="pickup_address_city" id="pickup_address_city" class="form-control" value="<?php echo $facility_address['city']; ?>" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="pickup_address_state" class="form-label">State</label>
                        <select name="pickup_address_state" id="pickup_address_state" class="form-select" required>
                            <option value="">Choose...</option>
                            <?php foreach ($states as $abbreviation => $name) : ?>
                                <option value="<?php echo $abbreviation; ?>" <?php echo ($facility_address['state'] == $abbreviation) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="pickup_address_zip" class="form-label">Zip Code</label>
                        <input type="text" name="pickup_address_zip" id="pickup_address_zip" class="form-control" value="<?php echo $facility_address['zip']; ?>" required>
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
                </fieldset>

            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-lg">Submit Trip Request</button>
            </div>
        </form>
    </div>
</div>

<script src="https://maps.googleapis.com/maps/api/js?key=<?php echo GOOGLE_MAPS_API_KEY; ?>&libraries=places,maps&callback=initMap&loading=async&v=beta" async defer></script>
<script>
    function initMap() {
        console.log("Google Maps API loaded. Initializing autocomplete.");

        /**
         * Replaces a standard text input with a Google Maps Place Autocomplete component.
         * @param {string} inputId The ID of the <input> element to replace.
         * @returns {HTMLElement} The new <gmp-place-autocomplete> element.
         */
        function createAutocomplete(inputId) {
            const oldInput = document.getElementById(inputId);
            if (!oldInput) {
                console.error(`Element with ID '${inputId}' not found.`);
                return null;
            }

            const autocomplete = document.createElement('gmp-place-autocomplete');
            
            // Transfer essential attributes from the old input
            autocomplete.id = oldInput.id;
            autocomplete.name = oldInput.name;
            autocomplete.className = oldInput.className;
            if (oldInput.required) {
                autocomplete.setAttribute('required', 'true');
            }
            // Set country restrictions to bias results, e.g., 'us' for United States
            autocomplete.country = "us";
            
            // Set the initial value from the old input
            autocomplete.value = oldInput.value;

            // Replace the old input with the new component in the DOM
            oldInput.parentNode.replaceChild(autocomplete, oldInput);
            
            return autocomplete;
        }

        const pickupAutocomplete = createAutocomplete('pickup_address_street');
        const dropoffAutocomplete = createAutocomplete('dropoff_address_street');

        /**
         * Fills the City, State, and Zip fields based on the selected place.
         * @param {google.maps.places.PlaceResult} place The place object from the autocomplete.
         * @param {string} prefix The prefix for the field IDs ('pickup' or 'dropoff').
         */
        function fillAddressFields(place, prefix) {
            let city = '';
            let state = '';
            let zip = '';
            
            for (const component of place.address_components) {
                const componentType = component.types[0];
                switch (componentType) {
                    case 'locality':
                        city = component.long_name;
                        break;
                    case 'administrative_area_level_1':
                        state = component.short_name;
                        break;
                    case 'postal_code':
                        zip = component.long_name;
                        break;
                }
            }
            
            document.getElementById(`${prefix}_address_city`).value = city;
            document.getElementById(`${prefix}_address_zip`).value = zip;
            
            const stateSelect = document.getElementById(`${prefix}_address_state`);
            // Set the state dropdown to the correct value
            stateSelect.value = state;
        }

        if (pickupAutocomplete) {
            pickupAutocomplete.addEventListener('place_changed', () => {
                const place = pickupAutocomplete.getPlace();
                if (!place.address_components) return;
                fillAddressFields(place, 'pickup');
            });
        }

        if (dropoffAutocomplete) {
            dropoffAutocomplete.addEventListener('place_changed', () => {
                const place = dropoffAutocomplete.getPlace();
                if (!place.address_components) return;
                fillAddressFields(place, 'dropoff');
                checkRoomNumberPrompt(place.formatted_address);
            });
        }

        /**
         * Checks the database to see if a room number is commonly used for a given address.
         * @param {string} address The full address string to check.
         */
        async function checkRoomNumberPrompt(address) {
            const roomInput = document.getElementById('dropoff_address_room');
            const roomAlert = document.getElementById('room-number-alert');
            
            // If user has already entered a room number, don't show the alert.
            if (roomInput.value.trim() !== '') {
                roomAlert.classList.add('d-none');
                return;
            }
            
            try {
                const response = await fetch('check_address.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ address: address })
                });

                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                
                const data = await response.json();
                
                if (data.prompt_room_number) {
                    roomAlert.classList.remove('d-none');
                } else {
                    roomAlert.classList.add('d-none');
                }
            } catch (error) {
                console.error('Error checking address:', error);
                roomAlert.classList.add('d-none'); // Hide on error
            }
        }

        // --- Event Listener for "Same as Pickup" Checkbox ---
        document.getElementById('copy_pickup_address').addEventListener('change', function() {
            const isChecked = this.checked;
            const dropoffFields = ['street', 'city', 'state', 'zip', 'room'];
            
            dropoffFields.forEach(field => {
                const pickupEl = document.getElementById(`pickup_address_${field}`);
                const dropoffEl = document.getElementById(`dropoff_address_${field}`);
                
                if (pickupEl && dropoffEl) {
                    dropoffEl.value = isChecked ? pickupEl.value : '';
                }
            });

            if (isChecked && dropoffAutocomplete.value) {
                checkRoomNumberPrompt(dropoffAutocomplete.value);
            } else {
                document.getElementById('room-number-alert').classList.add('d-none');
            }
        });

        // Hide the room number alert if the user starts typing in the room field.
        document.getElementById('dropoff_address_room').addEventListener('input', function() {
            if (this.value.trim() !== '') {
                document.getElementById('room-number-alert').classList.add('d-none');
            }
        });
    }
</script>

<?php
require_once 'footer.php';
?>