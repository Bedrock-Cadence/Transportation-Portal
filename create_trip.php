<?php
$page_title = 'Create New Trip';
require_once 'header.php';
require_once __DIR__ . '/../../app/db_connect.php';

// --- Corrected Permission Check ---
// Now allows facility users OR an admin.
if (!isset($_SESSION["loggedin"]) || !(in_array($_SESSION['user_role'], ['facility_user', 'facility_superuser']) || $_SESSION['user_role'] === 'bedrock_admin')) {
    header("location: login.php");
    exit;
}

function encrypt_data_placeholder($data) {
    return base64_encode($data);
}

// PHP logic to fetch facility address and prepopulate the form.
$facility_address = [
    'street' => '',
    'city' => '',
    'state' => '',
    'zip' => ''
];

// Determine the facility_id based on the user's role
if (isset($_SESSION['user_role'])) {
    $facility_id_to_fetch = ($_SESSION['user_role'] === 'bedrock_admin' && isset($_POST['facility_id'])) ? $_POST['facility_id'] : $_SESSION['entity_id'];

    if (!empty($facility_id_to_fetch)) {
        // Prepare a SQL query to fetch the facility's address
        $sql = "SELECT street, city, state, zip_code FROM facilities WHERE id = ?";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("i", $facility_id_to_fetch);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $facility_address['street'] = htmlspecialchars($row['street']);
                    $facility_address['city'] = htmlspecialchars($row['city']);
                    $facility_address['state'] = htmlspecialchars($row['state']);
                    $facility_address['zip'] = htmlspecialchars($row['zip_code']);
                }
            }
            $stmt->close();
        }
    }
}

$trip_error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Determine the facility_id based on the user's role
    if ($_SESSION['user_role'] === 'bedrock_admin') {
        // For admins, get the ID from the new dropdown in the form
        if (empty($_POST['facility_id'])) {
            $trip_error = "Admin must select a facility to create a trip for.";
        } else {
            $facility_id = $_POST['facility_id'];
        }
    } else {
        // For facility users, get the ID from their session
        $facility_id = $_SESSION['entity_id'];
    }

    if (empty($trip_error)) {
        // All other form processing logic remains the same...
        $origin_name = htmlspecialchars(trim($_POST['origin_name']));
        $destination_name = htmlspecialchars(trim($_POST['destination_name']));
        $appointment_at = $_POST['appointment_at'];
        $patient_first_name = trim($_POST['patient_first_name']);
        $patient_last_name = trim($_POST['patient_last_name']);

        // ENCRYPT ALL PHI
        $patient_first_name_encrypted = encrypt_data_placeholder($patient_first_name);
        $patient_last_name_encrypted = encrypt_data_placeholder($patient_last_name);

        $sql = "INSERT INTO trips (facility_id, created_by_user_id, /* ... other fields ... */) VALUES (?, ?, ?, ?, ?, ?)";
        
        if ($stmt = $mysqli->prepare($sql)) {
            $user_id = $_SESSION['user_id'];
            $bidding_closes_at = date('Y-m-d H:i:s', strtotime($appointment_at . ' -1 hour'));

            // Bind all parameters
            $stmt->bind_param("iissssss...", $facility_id, $user_id, $patient_first_name_encrypted, $patient_last_name_encrypted /* etc. */);
            
            if ($stmt->execute()) {
                header("location: dashboard.php?status=trip_created");
                exit;
            } else {
                $trip_error = "Database error: Could not create trip. " . $stmt->error;
            }
            $stmt->close();
        }
    }
}
?>

<h2 class="mb-4">Create a New Transport Request</h2>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <?php if(!empty($trip_error)){ echo '<div class="alert alert-danger">' . $trip_error . '</div>'; } ?>

            <?php
            // --- NEW ADMIN-ONLY SECTION ---
            // This dropdown only appears if the user is an admin
            if ($_SESSION['user_role'] === 'bedrock_admin'):
                // Fetch all active facilities for the dropdown
                $facilities = [];
                $sql_facilities = "SELECT id, name FROM facilities WHERE is_active = 1 ORDER BY name ASC";
                if ($result = $mysqli->query($sql_facilities)) {
                    while($row = $result->fetch_assoc()) {
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
                        <?php foreach($facilities as $facility): ?>
                            <option value="<?php echo $facility['id']; ?>"><?php echo htmlspecialchars($facility['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <hr class="mb-4">
            <?php endif; ?>

            <fieldset class="mb-4">
                <legend class="fs-5 border-bottom mb-3 pb-2">Patient Information</legend>
                <div class="alert alert-warning" role="alert">
                    <p><strong>IMPORTANT: This form collects Protected Health Information (PHI).</strong></p>
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

            <!-- Pickup Address Fieldset -->
            <fieldset class="mb-4">
                <legend class="fs-5 border-bottom mb-3 pb-2">Pickup Details</legend>
                <div class="row">
                    <div class="col-md-9 mb-3">
                        <label for="pickup_address_street" class="form-label">Street Address</label>
                        <!-- Pre-populate from PHP variable -->
                        <input type="text" name="pickup_address_street" id="pickup_address_street" class="form-control" value="<?php echo $facility_address['street']; ?>" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="pickup_address_room" class="form-label">Room/Apt #</label>
                        <input type="text" name="pickup_address_room" id="pickup_address_room" class="form-control">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="pickup_address_city" class="form-label">City</label>
                        <!-- Pre-populate from PHP variable -->
                        <input type="text" name="pickup_address_city" id="pickup_address_city" class="form-control" value="<?php echo $facility_address['city']; ?>" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="pickup_address_state" class="form-label">State</label>
                        <!-- Pre-populate and select the correct option -->
                        <select name="pickup_address_state" id="pickup_address_state" class="form-select" required>
                            <option selected disabled value="">Choose...</option>
                            <option value="TX" <?php echo ($facility_address['state'] == 'TX') ? 'selected' : ''; ?>>Texas</option>
                            <!-- Add all other US states here to match previous version -->
                            <option value="AL" <?php echo ($facility_address['state'] == 'AL') ? 'selected' : ''; ?>>Alabama</option>
                            <option value="AK" <?php echo ($facility_address['state'] == 'AK') ? 'selected' : ''; ?>>Alaska</option>
                            <option value="AZ" <?php echo ($facility_address['state'] == 'AZ') ? 'selected' : ''; ?>>Arizona</option>
                            <option value="AR" <?php echo ($facility_address['state'] == 'AR') ? 'selected' : ''; ?>>Arkansas</option>
                            <option value="CA" <?php echo ($facility_address['state'] == 'CA') ? 'selected' : ''; ?>>California</option>
                            <option value="CO" <?php echo ($facility_address['state'] == 'CO') ? 'selected' : ''; ?>>Colorado</option>
                            <option value="CT" <?php echo ($facility_address['state'] == 'CT') ? 'selected' : ''; ?>>Connecticut</option>
                            <option value="DE" <?php echo ($facility_address['state'] == 'DE') ? 'selected' : ''; ?>>Delaware</option>
                            <option value="FL" <?php echo ($facility_address['state'] == 'FL') ? 'selected' : ''; ?>>Florida</option>
                            <option value="GA" <?php echo ($facility_address['state'] == 'GA') ? 'selected' : ''; ?>>Georgia</option>
                            <option value="HI" <?php echo ($facility_address['state'] == 'HI') ? 'selected' : ''; ?>>Hawaii</option>
                            <option value="ID" <?php echo ($facility_address['state'] == 'ID') ? 'selected' : ''; ?>>Idaho</option>
                            <option value="IL" <?php echo ($facility_address['state'] == 'IL') ? 'selected' : ''; ?>>Illinois</option>
                            <option value="IN" <?php echo ($facility_address['state'] == 'IN') ? 'selected' : ''; ?>>Indiana</option>
                            <option value="IA" <?php echo ($facility_address['state'] == 'IA') ? 'selected' : ''; ?>>Iowa</option>
                            <option value="KS" <?php echo ($facility_address['state'] == 'KS') ? 'selected' : ''; ?>>Kansas</option>
                            <option value="KY" <?php echo ($facility_address['state'] == 'KY') ? 'selected' : ''; ?>>Kentucky</option>
                            <option value="LA" <?php echo ($facility_address['state'] == 'LA') ? 'selected' : ''; ?>>Louisiana</option>
                            <option value="ME" <?php echo ($facility_address['state'] == 'ME') ? 'selected' : ''; ?>>Maine</option>
                            <option value="MD" <?php echo ($facility_address['state'] == 'MD') ? 'selected' : ''; ?>>Maryland</option>
                            <option value="MA" <?php echo ($facility_address['state'] == 'MA') ? 'selected' : ''; ?>>Massachusetts</option>
                            <option value="MI" <?php echo ($facility_address['state'] == 'MI') ? 'selected' : ''; ?>>Michigan</option>
                            <option value="MN" <?php echo ($facility_address['state'] == 'MN') ? 'selected' : ''; ?>>Minnesota</option>
                            <option value="MS" <?php echo ($facility_address['state'] == 'MS') ? 'selected' : ''; ?>>Mississippi</option>
                            <option value="MO" <?php echo ($facility_address['state'] == 'MO') ? 'selected' : ''; ?>>Missouri</option>
                            <option value="MT" <?php echo ($facility_address['state'] == 'MT') ? 'selected' : ''; ?>>Montana</option>
                            <option value="NE" <?php echo ($facility_address['state'] == 'NE') ? 'selected' : ''; ?>>Nebraska</option>
                            <option value="NV" <?php echo ($facility_address['state'] == 'NV') ? 'selected' : ''; ?>>Nevada</option>
                            <option value="NH" <?php echo ($facility_address['state'] == 'NH') ? 'selected' : ''; ?>>New Hampshire</option>
                            <option value="NJ" <?php echo ($facility_address['state'] == 'NJ') ? 'selected' : ''; ?>>New Jersey</option>
                            <option value="NM" <?php echo ($facility_address['state'] == 'NM') ? 'selected' : ''; ?>>New Mexico</option>
                            <option value="NY" <?php echo ($facility_address['state'] == 'NY') ? 'selected' : ''; ?>>New York</option>
                            <option value="NC" <?php echo ($facility_address['state'] == 'NC') ? 'selected' : ''; ?>>North Carolina</option>
                            <option value="ND" <?php echo ($facility_address['state'] == 'ND') ? 'selected' : ''; ?>>North Dakota</option>
                            <option value="OH" <?php echo ($facility_address['state'] == 'OH') ? 'selected' : ''; ?>>Ohio</option>
                            <option value="OK" <?php echo ($facility_address['state'] == 'OK') ? 'selected' : ''; ?>>Oklahoma</option>
                            <option value="OR" <?php echo ($facility_address['state'] == 'OR') ? 'selected' : ''; ?>>Oregon</option>
                            <option value="PA" <?php echo ($facility_address['state'] == 'PA') ? 'selected' : ''; ?>>Pennsylvania</option>
                            <option value="RI" <?php echo ($facility_address['state'] == 'RI') ? 'selected' : ''; ?>>Rhode Island</option>
                            <option value="SC" <?php echo ($facility_address['state'] == 'SC') ? 'selected' : ''; ?>>South Carolina</option>
                            <option value="SD" <?php echo ($facility_address['state'] == 'SD') ? 'selected' : ''; ?>>South Dakota</option>
                            <option value="TN" <?php echo ($facility_address['state'] == 'TN') ? 'selected' : ''; ?>>Tennessee</option>
                            <option value="UT" <?php echo ($facility_address['state'] == 'UT') ? 'selected' : ''; ?>>Utah</option>
                            <option value="VT" <?php echo ($facility_address['state'] == 'VT') ? 'selected' : ''; ?>>Vermont</option>
                            <option value="VA" <?php echo ($facility_address['state'] == 'VA') ? 'selected' : ''; ?>>Virginia</option>
                            <option value="WA" <?php echo ($facility_address['state'] == 'WA') ? 'selected' : ''; ?>>Washington</option>
                            <option value="WV" <?php echo ($facility_address['state'] == 'WV') ? 'selected' : ''; ?>>West Virginia</option>
                            <option value="WI" <?php echo ($facility_address['state'] == 'WI') ? 'selected' : ''; ?>>Wisconsin</option>
                            <option value="WY" <?php echo ($facility_address['state'] == 'WY') ? 'selected' : ''; ?>>Wyoming</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="pickup_address_zip" class="form-label">Zip Code</label>
                        <!-- Pre-populate from PHP variable -->
                        <input type="number" name="pickup_address_zip" id="pickup_address_zip" class="form-control" value="<?php echo $facility_address['zip']; ?>" required>
                    </div>
                </div>
            </fieldset>

            <!-- Drop-off Address Fieldset -->
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
                            Looks like this might be a facility. Remember to enter a room or apartment number if applicable.
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
                            <option selected disabled value="">Choose...</option>
                            <!-- Add all US states here -->
                            <option value="TX">Texas</option>
                            <option value="AL">Alabama</option>
                            <option value="AK">Alaska</option>
                            <option value="AZ">Arizona</option>
                            <option value="AR">Arkansas</option>
                            <option value="CA">California</option>
                            <option value="CO">Colorado</option>
                            <option value="CT">Connecticut</option>
                            <option value="DE">Delaware</option>
                            <option value="FL">Florida</option>
                            <option value="GA">Georgia</option>
                            <option value="HI">Hawaii</option>
                            <option value="ID">Idaho</option>
                            <option value="IL">Illinois</option>
                            <option value="IN">Indiana</option>
                            <option value="IA">Iowa</option>
                            <option value="KS">Kansas</option>
                            <option value="KY">Kentucky</option>
                            <option value="LA">Louisiana</option>
                            <option value="ME">Maine</option>
                            <option value="MD">Maryland</option>
                            <option value="MA">Massachusetts</option>
                            <option value="MI">Michigan</option>
                            <option value="MN">Minnesota</option>
                            <option value="MS">Mississippi</option>
                            <option value="MO">Missouri</option>
                            <option value="MT">Montana</option>
                            <option value="NE">Nebraska</option>
                            <option value="NV">Nevada</option>
                            <option value="NH">New Hampshire</option>
                            <option value="NJ">New Jersey</option>
                            <option value="NM">New Mexico</option>
                            <option value="NY">New York</option>
                            <option value="NC">North Carolina</option>
                            <option value="ND">North Dakota</option>
                            <option value="OH">Ohio</option>
                            <option value="OK">Oklahoma</option>
                            <option value="OR">Oregon</option>
                            <option value="PA">Pennsylvania</option>
                            <option value="RI">Rhode Island</option>
                            <option value="SC">South Carolina</option>
                            <option value="SD">South Dakota</option>
                            <option value="TN">Tennessee</option>
                            <option value="UT">Utah</option>
                            <option value="VT">Vermont</option>
                            <option value="VA">Virginia</option>
                            <option value="WA">Washington</option>
                            <option value="WV">West Virginia</option>
                            <option value="WI">Wisconsin</option>
                            <option value="WY">Wyoming</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="dropoff_address_zip" class="form-label">Zip Code</label>
                        <input type="number" name="dropoff_address_zip" id="dropoff_address_zip" class="form-control" required>
                    </div>
                </div>
            </fieldset>

            <!-- Time and Appointment Details -->
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
        const copyCheckbox = document.getElementById('copy_pickup_address');
        const pickupStreet = document.getElementById('pickup_address_street');
        const pickupCity = document.getElementById('pickup_address_city');
        const pickupState = document.getElementById('pickup_address_state');
        const pickupZip = document.getElementById('pickup_address_zip');
        const pickupRoom = document.getElementById('pickup_address_room');

        const dropoffStreet = document.getElementById('dropoff_address_street');
        const dropoffCity = document.getElementById('dropoff_address_city');
        const dropoffState = document.getElementById('dropoff_address_state');
        const dropoffZip = document.getElementById('dropoff_address_zip');
        const dropoffRoom = document.getElementById('dropoff_address_room');

        const roomAlert = document.getElementById('room-number-alert');

        // Checkbox to copy address
        copyCheckbox.addEventListener('change', function() {
            if (this.checked) {
                dropoffStreet.value = pickupStreet.value;
                dropoffCity.value = pickupCity.value;
                dropoffState.value = pickupState.value;
                dropoffZip.value = pickupZip.value;
                dropoffRoom.value = pickupRoom.value;
            } else {
                dropoffStreet.value = '';
                dropoffCity.value = '';
                dropoffState.value = '';
                dropoffZip.value = '';
                dropoffRoom.value = '';
            }
        });

        // Backend check for drop-off address type
        const debounce = (func, delay) => {
            let timeoutId;
            return (...args) => {
                clearTimeout(timeoutId);
                timeoutId = setTimeout(() => {
                    func.apply(this, args);
                }, delay);
            };
        };
        
        const checkAddressType = async () => {
            const street = dropoffStreet.value;
            const city = dropoffCity.value;
            const state = dropoffState.value;
            const zip = dropoffZip.value;

            // Only proceed if all required fields are filled out
            if (street && city && state && zip) {
                try {
                    const response = await fetch('check_address.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ street, city, state, zip }),
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const data = await response.json();
                    
                    // --- DEBUGGING LOGS ADDED HERE ---
                    console.log('API response data:', data);
                    console.log('Dropoff Room value:', dropoffRoom.value.trim());

                    // Standardize the addresses using the API response
                    if (data.standardized_street) {
                        dropoffStreet.value = data.standardized_street;
                    }
                    if (data.standardized_city) {
                        dropoffCity.value = data.standardized_city;
                    }
                    if (data.standardized_state) {
                        dropoffState.value = data.standardized_state;
                    }
                    if (data.standardized_zip) {
                        dropoffZip.value = data.standardized_zip;
                    }

                    // Show the alert if it's a facility and no room number is entered
                    if (data.is_facility && dropoffRoom.value.trim() === '') {
                        roomAlert.classList.remove('d-none');
                    } else {
                        roomAlert.classList.add('d-none');
                    }
                } catch (error) {
                    console.error('Error checking address:', error);
                    // You might want to add a visible alert for the user here
                }
            }
        };

        // Listen for changes on all drop-off address fields to trigger the check
        [dropoffStreet, dropoffCity, dropoffState, dropoffZip].forEach(input => {
            input.addEventListener('blur', debounce(checkAddressType, 500));
        });

        // Hide the alert if the user starts typing in the room number field
        dropoffRoom.addEventListener('input', function() {
            if (this.value.trim() !== '') {
                roomAlert.classList.add('d-none');
            }
        });
    });
</script>

<?php
require_once 'footer.php';
?>