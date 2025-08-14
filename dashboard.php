<?php
$page_title = 'Dashboard';
require_once 'header.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

require_once __DIR__ . '/../../app/db_connect.php';
?>

<div class="p-5 mb-4 bg-white rounded-3 shadow-sm">
    <div class="container-fluid py-5">
        <h1 class="display-5 fw-bold">Howdy, Partner!</h1>
        <p class="col-md-8 fs-4">Welcome to the Bedrock Cadence Portal.</p>
        <p>
            <b>Your Role:</b> <?php echo htmlspecialchars($_SESSION["user_role"]); ?>
        </p>
    </div>
</div>

<div class="row">
    <?php
    // --- ADMIN SECTION ---
    if ($_SESSION['user_role'] === 'bedrock_admin') {
        echo '<div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header"><h5>Master Views</h5></div>
                    <div class="card-body">
                        <p class="card-text">View all platform activity from a global perspective.</p>
                        <a href="master_trip_list.php" class="btn btn-primary">Master Trip List</a>
                        <a href="trip_board.php" class="btn btn-info">Live Bidding Board</a>
                    </div>
                </div>
              </div>';
        echo '<div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header"><h5>Administrative Tools</h5></div>
                     <ul class="list-group list-group-flush">
                        <li class="list-group-item"><a href="invite_user.php">Invite New User</a></li>
                        <li class="list-group-item"><a href="verify_carriers.php">Verify Carriers</a></li>
                        <li class="list-group-item"><a href="case_management.php">Manage Cases</a></li>
                        <li class="list-group-item"><a href="billing.php">Generate Billing Export</a></li>
                    </ul>
                </div>
              </div>';
    }

    // --- FACILITY USER SECTION ---
    if (in_array($_SESSION['user_role'], ['facility_user', 'facility_superuser', 'bedrock_admin'])) {
        $facility_id = $_SESSION['entity_id'];

        echo '<div class="col-12 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Facility Tools</h5>
                        <p class="card-text">Create and manage your transport requests.</p>
                        <a href="create_trip.php" class="btn btn-primary">+ Create New Trip</a>
                    </div>
                </div>
              </div>';

        $sql_requests = "SELECT tcr.id, t.uuid FROM trip_change_requests tcr JOIN trips t ON tcr.trip_id = t.id WHERE t.facility_id = ? AND tcr.status = 'pending'";
        if ($stmt_requests = $mysqli->prepare($sql_requests)) {
            $stmt_requests->bind_param("i", $facility_id);
            $stmt_requests->execute();
            $result_requests = $stmt_requests->get_result();
            if ($result_requests->num_rows > 0) {
                echo '<div class="col-12 mb-4"><div class="card"><div class="card-header fw-bold">Pending Change Requests</div><ul class="list-group list-group-flush">';
                while ($request = $result_requests->fetch_assoc()) {
                    echo '<li class="list-group-item d-flex justify-content-between align-items-center">A carrier has requested an ETA Change for Trip ' . substr($request['uuid'], 0, 8) . '... <a href="review_change.php?id=' . $request['id'] . '" class="btn btn-warning btn-sm">Review Now</a></li>';
                }
                echo '</ul></div></div>';
            }
            $stmt_requests->close();
        }
        
        $sql_awaiting = "SELECT uuid FROM trips WHERE facility_id = ? AND carrier_completed_at IS NOT NULL AND facility_completed_at IS NULL";
        if ($stmt_awaiting = $mysqli->prepare($sql_awaiting)) {
            $stmt_awaiting->bind_param("i", $facility_id);
            $stmt_awaiting->execute();
            $result_awaiting = $stmt_awaiting->get_result();
             if ($result_awaiting->num_rows > 0) {
                echo '<div class="col-12 mb-4"><div class="card"><div class="card-header fw-bold">Trips Awaiting Your Confirmation</div><ul class="list-group list-group-flush">';
                while ($trip = $result_awaiting->fetch_assoc()) {
                    echo '<li class="list-group-item d-flex justify-content-between align-items-center">Trip ' . substr($trip['uuid'], 0, 8) . '... has been marked complete by the carrier. <a href="complete_trip_facility.php?uuid=' . $trip['uuid'] . '" class="btn btn-info btn-sm">Confirm Now</a></li>';
                }
                echo '</ul></div></div>';
            }
            $stmt_awaiting->close();
        }
    }

    // --- CARRIER USER SECTION ---
    if (in_array($_SESSION['user_role'], ['carrier_user', 'carrier_superuser', 'bedrock_admin'])) {
        $carrier_id = $_SESSION['entity_id'];
        
        echo '<div class="col-12 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Carrier Tools</h5>
                        <p class="card-text">View available trips and manage your awarded transports.</p>
                        <a href="trip_board.php" class="btn btn-primary">View Trip Board</a>
                    </div>
                </div>
              </div>';
        
        $sql_detail_changes = "SELECT tcr.id, t.uuid FROM trip_change_requests tcr JOIN trips t ON tcr.trip_id = t.id WHERE t.carrier_id = ? AND tcr.request_type = 'details_change' AND tcr.status = 'pending'";
        if ($stmt_details = $mysqli->prepare($sql_detail_changes)) {
            $stmt_details->bind_param("i", $carrier_id);
            $stmt_details->execute();
            $result_details = $stmt_details->get_result();
            if ($result_details->num_rows > 0) {
                echo '<div class="col-12 mb-4"><div class="card border-warning"><div class="card-header fw-bold text-warning">Action Required: Trip Updates</div><ul class="list-group list-group-flush">';
                while ($request = $result_details->fetch_assoc()) {
                    echo '<li class="list-group-item d-flex justify-content-between align-items-center">A facility has requested to update details for Trip ' . substr($request['uuid'], 0, 8) . '... <a href="review_details_change.php?id=' . $request['id'] . '" class="btn btn-warning btn-sm">Review Changes</a></li>';
                }
                echo '</ul></div></div>';
            }
            $stmt_details->close();
        }

        $sql_awarded = "SELECT uuid, origin_name, destination_name, awarded_eta FROM trips WHERE carrier_id = ? AND status = 'awarded' ORDER BY awarded_eta ASC";
        if ($stmt_awarded = $mysqli->prepare($sql_awarded)) {
            $stmt_awarded->bind_param("i", $carrier_id);
            $stmt_awarded->execute();
            $result_awarded = $stmt_awarded->get_result();
            if ($result_awarded->num_rows > 0) {
                 echo '<div class="col-12 mb-4"><div class="card"><div class="card-header fw-bold">My Awarded Trips</div><ul class="list-group list-group-flush">';
                while ($trip = $result_awarded->fetch_assoc()) {
                    echo '<li class="list-group-item d-flex justify-content-between align-items-center">
                            <div><b>' . htmlspecialchars($trip['origin_name']) . ' to ' . htmlspecialchars($trip['destination_name']) . '</b><br><small class="text-muted">ETA: ' . date('M j, g:i A', strtotime($trip['awarded_eta'])) . '</small></div>
                            <a href="awarded_trip_details.php?uuid=' . htmlspecialchars($trip['uuid']) . '" class="btn btn-success btn-sm">View Details</a>
                          </li>';
                }
                echo "</ul></div></div>";
            }
            $stmt_awarded->close();
        }
    }
    ?>
</div>

<?php
$mysqli->close();
require_once 'footer.php';
?>