<?php
// FILE: public/billing.php

require_once 'init.php';

// --- Permission Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION['user_role'] !== 'admin') {
    redirect('login.php');
}

$page_title = 'Billing Export';
$db = Database::getInstance();
$page_message = '';

// --- Placeholder for Zoho API call ---
function call_zoho_billing_api($line_items) {
    // THIS IS A SIMULATION.
    if (!empty($line_items)) {
        return ['status' => 'success', 'message' => 'Successfully exported ' . count($line_items) . ' line items to Zoho.'];
    }
    return ['status' => 'noop', 'message' => 'No new items to export for the selected date range.'];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $line_items = [];

    if (empty($start_date) || empty($end_date)) {
        $page_message = '<div class="alert alert-danger">Please select a valid start and end date.</div>';
    } else {
        try {
            // 1. Fetch completed trips in the date range
            $sql_trips = "SELECT id, carrier_id, facility_completed_at FROM trips WHERE status = 'completed' AND facility_completed_at BETWEEN ? AND ?";
            $completed_trips = $db->query($sql_trips, [$start_date, $end_date . ' 23:59:59'])->fetchAll();
            foreach ($completed_trips as $trip) {
                $line_items[] = [
                    'customer_id' => $trip['carrier_id'],
                    'item_id' => 'TRIP_COMPLETION_FEE',
                    'description' => 'Fee for completed trip #' . $trip['id'],
                    'date' => $trip['facility_completed_at']
                ];
            }

            // 2. Fetch insurance verifications in the date range
            $sql_ins = "SELECT carrier_id, created_at FROM insurance_verifications WHERE status = 'completed' AND created_at BETWEEN ? AND ?";
            $verifications = $db->query($sql_ins, [$start_date, $end_date . ' 23:59:59'])->fetchAll();
            foreach ($verifications as $ins) {
                $line_items[] = [
                    'customer_id' => $ins['carrier_id'],
                    'item_id' => 'INSURANCE_VERIFICATION_FEE',
                    'description' => 'Fee for insurance verification service',
                    'date' => $ins['created_at']
                ];
            }

            // 3. Send the aggregated data to Zoho
            $zoho_response = call_zoho_billing_api($line_items);
            $alert_class = $zoho_response['status'] == 'success' ? 'alert-success' : 'alert-info';
            $page_message = '<div class="alert ' . $alert_class . '">' . e($zoho_response['message']) . '</div>';

        } catch (Exception $e) {
            $page_message = '<div class="alert alert-danger">A database error occurred while generating the export.</div>';
            error_log("Billing Page Error: " . $e->getMessage());
        }
    }
}

require_once 'header.php';
?>

<h2 class="mb-4">Generate Billing Export to Zoho</h2>
<div class="card shadow-sm">
    <div class="card-body">
        <p>Select a date range to gather all billable items and export them to Zoho Billing.</p>
        <form action="billing.php" method="post">
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

        <?php if(!empty($page_message)): ?>
            <div class="mt-4"><?= $page_message; ?></div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'footer.php'; ?>