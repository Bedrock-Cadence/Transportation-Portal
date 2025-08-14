<?php
session_start();
require_once __DIR__ . '/../../app/db_connect.php';

// --- Universal Check: Is user logged in and is a trip UUID provided? ---
if (!isset($_SESSION["loggedin"])) { header("location: login.php"); exit; }
if (!isset($_GET['uuid'])) { header("location: dashboard.php"); exit; }

$uuid = $_GET['uuid'];
$trip = null;
$is_authorized = false;
$is_actionable = false; // To determine if the form should be shown

// --- Step 1: Fetch trip data based on UUID alone ---
// Also ensure it's in the correct state for this page to be accessed
$sql_fetch = "SELECT id, facility_id FROM trips WHERE uuid = ? AND carrier_completed_at IS NOT NULL AND facility_completed_at IS NULL";
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
    header("location: dashboard.php?error=notfound_or_notready");
    exit;
}

// --- Step 2: Authorize the logged-in user ---
if ($_SESSION['user_role'] === 'bedrock_admin') {
    $is_authorized = true;
} elseif (in_array($_SESSION['user_role'], ['facility_user', 'facility_superuser']) && $_SESSION['entity_id'] == $trip['facility_id']) {
    $is_authorized = true;
    $is_actionable = true; // Only the facility user can take action
}

if (!$is_authorized) {
    header("location: dashboard.php?error=unauthorized");
    exit;
}

// --- Step 3: Handle Form Submission ---
if ($is_actionable && $_SERVER["REQUEST_METHOD"] == "POST") {
    $issues_reported = $_POST['had_issues'];
    $issue_notes = trim($_POST['issue_notes']);
    $trip_id = $trip['id'];

    $mysqli->begin_transaction();
    try {
        // 1. Update the trip record to mark it complete
        $sql_update = "UPDATE trips SET facility_completed_at = NOW() WHERE id = ?";
        $stmt_update = $mysqli->prepare($sql_update);
        $stmt_update->bind_param("i", $trip_id);
        $stmt_update->execute();
        $stmt_update->close();

        // 2. If issues were reported, create a case
        if ($issues_reported == 1 && !empty($issue_notes)) {
            $sql_case = "INSERT INTO cases (trip_id, opened_by_user_id, case_type, initial_notes) VALUES (?, ?, 'Crew Issue Reported by Facility', ?)";
            $stmt_case = $mysqli->prepare($sql_case);
            $stmt_case->bind_param("iis", $trip_id, $_SESSION['user_id'], $issue_notes);
            $stmt_case->execute();
            $stmt_case->close();
        }

        // 3. Trigger the final archival process
        // trigger_final_archival($trip_id); // This would be the call to the background job

        $mysqli->commit();
        header("location: dashboard.php?status=trip_fully_completed");
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
    <title>Confirm Trip Completion</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: sans-serif; padding: 2em; }
        .container { max-width: 700px; margin: 0 auto; }
        fieldset { border: 1px solid #ddd; padding: 1em; margin-bottom: 1em; }
        legend { font-weight: bold; }
        button { width: 100%; padding: 15px; font-size: 1.2em; background-color: #007bff; color: white; border:none; cursor: pointer; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Final Trip Confirmation</h2>
        
        <p>Please confirm completion and report any issues for Trip <b><?php echo substr($uuid, 0, 8); ?>...</b></p>
        
        <?php if ($is_actionable): ?>
            <form action="<?php echo htmlspecialchars($_SERVER["REQUEST_URI"]); ?>" method="post">
                <fieldset>
                    <legend>Were there any issues with the transport or crew?</legend>
                    <input type="radio" name="had_issues" value="0" id="issues_no" required onclick="toggleNotes(false)"> <label for="issues_no">No</label><br>
                    <input type="radio" name="had_issues" value="1" id="issues_yes" onclick="toggleNotes(true)"> <label for="issues_yes">Yes</label>
                </fieldset>

                <fieldset id="issue_notes_fieldset" style="display:none;">
                    <legend>Please describe the issues</legend>
                    <textarea name="issue_notes" style="width:100%; height: 100px;"></textarea>
                </fieldset>

                <button type="submit">Submit Final Confirmation</button>
            </form>
        <?php else: // Admin read-only view ?>
            <p style="text-align:center; background-color: #ffc107; padding: 0.5em;"><b>Admin Read-Only View</b></p>
            <p>This page is for the originating facility to complete the trip.</p>
        <?php endif; ?>

        <script>
            function toggleNotes(show) {
                document.getElementById('issue_notes_fieldset').style.display = show ? 'block' : 'none';
            }
        </script>
    </div>
</body>
</html>