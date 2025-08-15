<?php
require_once __DIR__ . '/../../app/session_config.php';
require_once __DIR__ . '/../../app/db_connect.php';

// --- Universal Check: Is user logged in and is a trip UUID provided? ---
if (!isset($_SESSION["loggedin"])) { header("location: login.php"); exit; }
if (!isset($_GET['uuid'])) { header("location: dashboard.php"); exit; }

$uuid = $_GET['uuid'];
$trip = null;
$is_authorized = false;
$is_actionable = false; // To determine if the form should be shown

// --- Step 1: Fetch trip data based on UUID alone ---
$sql_fetch = "SELECT id, carrier_id FROM trips WHERE uuid = ?";
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
    header("location: dashboard.php?error=notfound");
    exit;
}

// --- Step 2: Authorize the logged-in user ---
if ($_SESSION['user_role'] === 'bedrock_admin') {
    $is_authorized = true;
} elseif (in_array($_SESSION['user_role'], ['carrier_user', 'carrier_superuser']) && $_SESSION['entity_id'] == $trip['carrier_id']) {
    $is_authorized = true;
    $is_actionable = true; // Only the carrier can take action
}

if (!$is_authorized) {
    header("location: dashboard.php?error=unauthorized");
    exit;
}

// --- Step 3: Handle Form Submission ---
if ($is_actionable && $_SERVER["REQUEST_METHOD"] == "POST") {
    $transported = $_POST['transported'];
    $patient_ready = $_POST['patient_ready'];
    $trip_id = $trip['id'];

    $mysqli->begin_transaction();
    try {
        // 1. Update the trip record
        $sql_update = "UPDATE trips SET was_transported_by_carrier = ?, patient_was_ready = ?, carrier_completed_at = NOW() WHERE id = ?";
        $stmt_update = $mysqli->prepare($sql_update);
        $stmt_update->bind_param("iii", $transported, $patient_ready, $trip_id);
        $stmt_update->execute();
        $stmt_update->close();

        // 2. If patient was not transported, create a case
        if ($transported == 0) {
            $case_notes = "Carrier reported they did not transport the patient.";
            $sql_case = "INSERT INTO cases (trip_id, opened_by_user_id, case_type, initial_notes) VALUES (?, ?, 'Patient Not Transported', ?)";
            $stmt_case = $mysqli->prepare($sql_case);
            $stmt_case->bind_param("iis", $trip_id, $_SESSION['user_id'], $case_notes);
            $stmt_case->execute();
            $stmt_case->close();
        }

        $mysqli->commit();
        header("location: dashboard.php?status=trip_completed");
        exit;
    } catch (mysqli_sql_exception $exception) {
        $mysqli->rollback();
        die("An error occurred while completing the trip.");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Complete Trip</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: sans-serif; padding: 2em; }
        .container { max-width: 700px; margin: 0 auto; }
        fieldset { border: 1px solid #ddd; padding: 1em; margin-bottom: 1em; }
        legend { font-weight: bold; }
        button { width: 100%; padding: 15px; font-size: 1.2em; background-color: #28a745; color: white; border:none; cursor: pointer; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Trip Completion Report</h2>
        

[Image of a clipboard with a checklist]

        <p>Please answer the following questions for Trip <b><?php echo substr($uuid, 0, 8); ?>...</b></p>
        
        <?php if ($is_actionable): ?>
            <form action="<?php echo htmlspecialchars($_SERVER["REQUEST_URI"]); ?>" method="post">
                <fieldset>
                    <legend>Did you transport the patient for this trip?</legend>
                    <input type="radio" name="transported" value="1" id="transported_yes" required> <label for="transported_yes">Yes</label><br>
                    <input type="radio" name="transported" value="0" id="transported_no"> <label for="transported_no">No</label>
                </fieldset>

                <fieldset>
                    <legend>Was the patient ready within 15 minutes of your arrival?</legend>
                    <input type="radio" name="patient_ready" value="1" id="ready_yes" required> <label for="ready_yes">Yes</label><br>
                    <input type="radio" name="patient_ready" value="0" id="ready_no"> <label for="ready_no">No</label>
                </fieldset>

                <button type="submit">Submit Completion Report</button>
            </form>
        <?php else: ?>
            <p style="text-align:center; background-color: #ffc107; padding: 0.5em;"><b>Admin Read-Only View</b></p>
            <p>This page is for the assigned carrier to complete the trip.</p>
        <?php endif; ?>
    </div>
</body>
</html>