<?php
session_start();
require_once __DIR__ . '/../../app/db_connect.php';

// --- Universal Check: Is user logged in and is a trip UUID provided? ---
if (!isset($_SESSION["loggedin"])) { header("location: login.php"); exit; }
if (!isset($_GET['uuid'])) { header("location: trip_board.php"); exit; }

// --- Re-using placeholder functions ---
function decrypt_data_placeholder($data) {
    // THIS IS NOT REAL DECRYPTION. IT IS A SIMULATION.
    return base64_decode($data);
}
function call_insurance_api($patient_data) {
    // THIS IS A SIMULATION of a 3rd party API call.
    return [ 'status' => 'completed', 'summary' => 'Active - Commercial Payer', 'details' => json_encode(['payer_name' => 'BlueCross', 'policy_id' => 'X12345', 'is_active' => true]) ];
}

$uuid = $_GET['uuid'];
$trip = null;
$is_authorized = false;
$page_message = '';
$insurance_result = null;

// --- Step 1: Fetch trip data based on UUID alone ---
$sql_fetch = "SELECT * FROM trips WHERE uuid = ? AND status = 'bidding' LIMIT 1";
if ($stmt_fetch = $mysqli->prepare($sql_fetch)) {
    $stmt_fetch->bind_param("s", $uuid);
    $stmt_fetch->execute();
    $result = $stmt_fetch->get_result();
    if ($result->num_rows == 1) {
        $trip = $result->fetch_assoc();
    }
    $stmt_fetch->close();
}

if (!$trip) {
    header("location: trip_board.php?error=notfound");
    exit;
}

// --- Step 2: Now, authorize the logged-in user ---
if ($_SESSION['user_role'] === 'bedrock_admin') {
    $is_authorized = true;
} elseif (in_array($_SESSION['user_role'], ['carrier_user', 'carrier_superuser'])) {
    $carrier_id = $_SESSION['entity_id'];
    $facility_id = $trip['facility_id'];
    $is_blacklisted = false;
    
    $sql_check_blacklist = "SELECT 1 FROM facility_carrier_preferences WHERE facility_id = ? AND carrier_id = ? AND preference_type = 'blacklisted' LIMIT 1";
    if ($stmt_check = $mysqli->prepare($sql_check_blacklist)) {
        $stmt_check->bind_param("ii", $facility_id, $carrier_id);
        $stmt_check->execute();
        $stmt_check->store_result();
        if ($stmt_check->num_rows == 1) {
            $is_blacklisted = true;
        }
        $stmt_check->close();
    }
    
    if (!$is_blacklisted) {
        $is_authorized = true;
    }
}

if (!$is_authorized) {
    header("location: trip_board.php?error=unauthorized");
    exit;
}

// --- Step 3: Handle POST requests (Bids & Insurance) only if it's a carrier ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && in_array($_SESSION['user_role'], ['carrier_user', 'carrier_superuser'])) {
    
    if (isset($_POST['place_bid'])) {
        $eta = trim($_POST['eta']);
        $trip_id = $trip['id'];
        $carrier_id = $_SESSION['entity_id'];

        if (empty($eta)) {
            $page_message = '<p class="error">You must provide an ETA to place a bid.</p>';
        } else {
            $sql_bid = "INSERT INTO bids (trip_id, carrier_id, eta) VALUES (?, ?, ?)";
            if ($stmt_bid = $mysqli->prepare($sql_bid)) {
                $stmt_bid->bind_param("iis", $trip_id, $carrier_id, $eta);
                if ($stmt_bid->execute()) {
                    header("location: trip_board.php?status=bid_placed");
                    exit;
                } else {
                    if ($mysqli->errno == 1062) {
                        $page_message = '<p class="error">You have already placed a bid on this trip.</p>';
                    } else {
                        $page_message = '<p class="error">An error occurred. Please try again.</p>';
                    }
                }
                $stmt_bid->close();
            }
        }
    } elseif (isset($_POST['verify_insurance'])) {
        $trip_id = $trip['id'];
        $carrier_id = $_SESSION['entity_id'];

        $sql_check = "SELECT result_summary FROM insurance_verifications WHERE trip_id = ? AND carrier_id = ?";
        $stmt_check = $mysqli->prepare($sql_check);
        $stmt_check->bind_param("ii", $trip_id, $carrier_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        if($result_check->num_rows == 0) {
            $sql_get_phi = "SELECT patient_first_name_encrypted, patient_last_name_encrypted, patient_dob_encrypted FROM trips WHERE id = ?";
            $patient_data = ['first' => 'John', 'last' => 'Doe', 'dob' => '1960-01-15']; // Placeholder data
            $api_response = call_insurance_api($patient_data);
            $encrypted_details = $api_response['details'];

            $sql_log = "INSERT INTO insurance_verifications (trip_id, carrier_id, requested_by_user_id, status, result_summary, result_details_encrypted) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_log = $mysqli->prepare($sql_log);
            $stmt_log->bind_param("iiisss", $trip_id, $carrier_id, $_SESSION['user_id'], $api_response['status'], $api_response['summary'], $encrypted_details);
            $stmt_log->execute();
            $stmt_log->close();
            
            $insurance_result = $api_response['summary'];
            $page_message = '<p class="success">Insurance verification complete.</p>';
        } else {
             $insurance_result = $result_check->fetch_assoc()['result_summary'];
             $page_message = '<p class="success">Verification was already run for this trip.</p>';
        }
        $stmt_check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View & Bid on Trip</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: sans-serif; padding: 2em; }
        .container { max-width: 700px; margin: 0 auto; border: 1px solid #ccc; padding: 2em; border-radius: 5px; }
        dl { border: 1px solid #eee; padding: 1em; }
        dt { font-weight: bold; color: #555; }
        dd { margin-left: 2em; margin-bottom: 1em; }
        form { margin-top: 2em; border-top: 2px solid #007bff; padding-top: 1.5em; }
        fieldset { margin-top: 2em; border-top: 2px solid #6c757d; padding-top: 1.5em; }
        input, button { width: 100%; padding: 8px; margin-bottom: 10px; box-sizing: border-box; }
        button { padding: 10px 15px; color: white; border: none; cursor: pointer; font-size: 1.1em; }
        .error { color: red; }
        .success { color: green; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Trip Details</h2>
        <?php if ($_SESSION['user_role'] === 'bedrock_admin'): ?>
            <p style="text-align:center; background-color: #ffc107; padding: 0.5em;"><b>Admin Read-Only View</b></p>
        <?php endif; ?>
        
        <?php if(!empty($page_message)) { echo $page_message; } ?>

        <dl>
            <dt>Origin</dt>
            <dd><?php echo htmlspecialchars($trip['origin_name']); ?></dd>

            <dt>Destination</dt>
            <dd><?php echo htmlspecialchars($trip['destination_name']); ?></dd>

            <dt>Appointment Time</dt>
            <dd><?php echo date('M j, Y g:i A', strtotime($trip['appointment_at'])); ?></dd>

            <dt>Patient Weight</dt>
            <dd><?php echo htmlspecialchars(decrypt_data_placeholder($trip['patient_weight_kg_encrypted'])); ?> kg</dd>
            
            <dt>Equipment Needs</dt>
            <dd><?php echo htmlspecialchars(decrypt_data_placeholder($trip['equipment_needs_encrypted'])); ?></dd>
        </dl>
        
        <?php
        // Only show action forms to authorized carriers, not admins.
        if (in_array($_SESSION['user_role'], ['carrier_user', 'carrier_superuser'])):
        ?>
            <form action="<?php echo htmlspecialchars($_SERVER["REQUEST_URI"]); ?>" method="post">
                <h3>Place Your Bid</h3>
                <label for="eta">Your Best Estimated Time of Arrival (ETA)</label>
                <input type="datetime-local" name="eta" id="eta" required>
                <button type="submit" name="place_bid" value="1" style="background-color: #28a745;">Submit Bid</button>
            </form>
            
            <fieldset>
                <legend>Insurance Verification</legend>
                <?php if ($insurance_result): ?>
                    <p><b>Result:</b> <?php echo htmlspecialchars($insurance_result); ?></p>
                <?php else: ?>
                    <form action="<?php echo htmlspecialchars($_SERVER["REQUEST_URI"]); ?>" method="post">
                        <p>You can perform a one-time, billable national insurance search for this patient.</p>
                        <button type="submit" name="verify_insurance" value="1" style="background-color: #17a2b8;">Request Insurance Verification</button>
                    </form>
                <?php endif; ?>
            </fieldset>
        <?php endif; ?>
    </div>
</body>
</html>