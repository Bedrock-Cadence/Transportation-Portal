<?php
$page_title = 'Billing Export';
require_once 'header.php';
require_once __DIR__ . '/../../app/db_connect.php';

// --- Permission Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION['user_role'] !== 'bedrock_admin') {
    header("location: login.php");
    exit;
}

// --- Placeholder for Zoho API call ---
function call_zoho_billing_api($line_items) {
    // THIS IS A SIMULATION.
    if (count($line_items) > 0) {
        return ['status' => 'success', 'message' => 'Successfully exported ' . count($line_items) . ' line items to Zoho.'];
    }
    return ['status' => 'noop', 'message' => 'No new items to export for the selected date range.'];
}


$page_message = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $line_items = [];

    // 1. Fetch completed trips in the date range
    $sql_trips = "SELECT id, carrier_id, facility_completed_at FROM trips WHERE status = 'completed' AND facility_completed_at BETWEEN ? AND ?";
    if($stmt_trips = $mysqli->prepare($sql_trips)) {
        $stmt_trips->bind_param("ss", $start_date, $end_date);
        $stmt_trips->execute();
        $result_trips = $stmt_trips->get_result();
        while($trip = $result_trips->fetch_assoc()) {
            $line_items[] = [
                'customer_id' => $trip['carrier_id'],
                'item_id' => 'TRIP_COMPLETION_FEE',
                'description' => 'Fee for completed trip #' . $trip['id'],
                'date' => $trip['facility_completed_at']
            ];
        }
        $stmt_trips->close();
    }

    // 2. Fetch insurance verifications in the date range
    $sql_ins = "SELECT carrier_id, created_at FROM insurance_verifications WHERE status = 'completed' AND created_at BETWEEN ? AND ?";
    if($stmt_ins = $mysqli->prepare($sql_ins)) {
        $stmt_ins->bind_param("ss", $start_date, $end_date);
        $stmt_ins->execute();
        $result_ins = $stmt_ins->get_result();
        while($ins = $result_ins->fetch_assoc()) {
            $line_items[] = [
                'customer_id' => $ins['carrier_id'],
                'item_id' => 'INSURANCE_VERIFICATION_FEE',
                'description' => 'Fee for insurance verification service',
                'date' => $ins['created_at']
            ];
        }
        $stmt_ins->close();
    }

    // 3. Send the aggregated data to Zoho
    $zoho_response = call_zoho_billing_api($line_items);
    if ($zoho_response['status'] == 'success') {
        $page_message = '<div class="alert alert-success">' . $zoho_response['message'] . '</div>';
    } else {
        $page_message = '<div class="alert alert-info">' . $zoho_response['message'] . '</div>';
    }
}
?>

<h2 class="mb-4">Generate Billing Export to Zoho</h2>
<div class="card shadow-sm">
    <div class="card-body">
        <p>Select a date range to gather all billable items and export them to Zoho Billing.</p>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="row align-items-end">
                <div class="col-md-5">
                    <label for="start_date" class="form-label">Start Date:</label>
                    <input type="date" name="start_date" class="form-control" required>
                </div>
                <div class="col-md-5">
                    <label for="end_date" class="form-label">End Date:</label>
                    <input type="date" name="end_date" class="form-control" required>
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary">Generate & Export</button>
                </div>
            </div>
        </form>

        <?php if(!empty($page_message)) {
            echo '<div class="mt-4">' . $page_message . '</div>';
        } ?>
    </div>
</div>

<?php
require_once 'footer.php';
?>