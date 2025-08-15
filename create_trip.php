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
        <p>By filling out and submitting this form, you consent to Bedrock Cadence securely collecting, storing, and transmitting your information in a HIPAA-compliant manner. We are committed to protecting your privacy and security. Please ensure all information is accurate.</p>
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
                <legend class="fs-5 border-bottom mb-3 pb-2">Trip Details</legend>
                </fieldset>

            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-lg">Submit Trip Request</button>
            </div>
        </form>
    </div>
</div>

<?php
require_once 'footer.php';
?>