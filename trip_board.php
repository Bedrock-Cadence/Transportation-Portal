<?php
$page_title = 'Available Trips';
require_once 'header.php';
require_once __DIR__ . '/../../app/db_connect.php';

// --- Step 1: Universal Permission Check ---
$is_authorized = false;
if (isset($_SESSION["loggedin"])) {
    if ($_SESSION['user_role'] === 'bedrock_admin' || in_array($_SESSION['user_role'], ['carrier_user', 'carrier_superuser'])) {
        $is_authorized = true;
    }
}

if (!$is_authorized) {
    header("location: login.php");
    exit;
}

// --- Step 2: Build the SQL Query Based on Role ---
$available_trips = [];
$page_heading = '';

if ($_SESSION['user_role'] === 'bedrock_admin') {
    // Admin View: Show all trips that are currently bidding
    $page_heading = "All Available Trips (Admin View)";
    $sql = "SELECT uuid, origin_name, destination_name, appointment_at FROM trips WHERE status = 'bidding' ORDER BY appointment_at ASC";
    $stmt = $mysqli->prepare($sql);

} else { // This must be a carrier user
    $page_heading = "Available Trips Board";
    $carrier_id = $_SESSION['entity_id'];
    
    // Carrier View: Show all bidding trips EXCEPT those from facilities that have blacklisted this carrier.
    $sql = "SELECT t.uuid, t.origin_name, t.destination_name, t.appointment_at 
            FROM trips t
            WHERE t.status = 'bidding' 
            AND t.facility_id NOT IN (
                SELECT fcp.facility_id 
                FROM facility_carrier_preferences fcp 
                WHERE fcp.carrier_id = ? AND fcp.preference_type = 'blacklisted'
            )
            ORDER BY t.appointment_at ASC";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $carrier_id);
}

// --- Step 3: Execute the Query and Fetch Results ---
if ($stmt) {
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $available_trips[] = $row;
            }
        }
        $result->free();
    } else {
        // In production, log this error instead of echoing.
        error_log("Error executing trip board query: " . $stmt->error);
    }
    $stmt->close();
}
$mysqli->close();
?>

<div class="toast-container position-fixed bottom-0 end-0 p-3">
  <div id="newTripToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="toast-header">
      <strong class="me-auto text-primary">New Trip Available!</strong>
      <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
    <div class="toast-body" id="newTripToastBody">
      A new trip has just been posted.
    </div>
  </div>
</div>

<h2 class="mb-4"><?php echo $page_heading; ?></h2>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Origin</th>
                        <th>Destination</th>
                        <th>Appointment Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($available_trips)): ?>
                        <?php foreach ($available_trips as $trip): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($trip['origin_name']); ?></td>
                                <td><?php echo htmlspecialchars($trip['destination_name']); ?></td>
                                <td><?php echo date('M j, Y g:i A', strtotime($trip['appointment_at'])); ?></td>
                                <td>
                                    <a href="view_trip.php?uuid=<?php echo htmlspecialchars($trip['uuid']); ?>" class="btn btn-sm btn-primary">View & Bid</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center p-4">No available trips at the moment.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>


<?php require_once 'footer.php'; ?>

<script>
    // Only run this for logged-in carriers
    <?php if (in_array($_SESSION['user_role'], ['carrier_user', 'carrier_superuser'])): ?>
        
        // Check if the browser supports Server-Sent Events
        if (typeof(EventSource) !== "undefined") {
            
            // Create a new EventSource object pointing to our server script
            var source = new EventSource("/../app/sse_server.php");
            
            // Define what to do when a "new_trip" event is received
            source.addEventListener('new_trip', function(event) {
                
                const trip = JSON.parse(event.data);
                
                const toastBody = document.getElementById('newTripToastBody');
                toastBody.innerHTML = `New trip from <b>${trip.origin_name}</b> to <b>${trip.destination_name}</b>. <a href='trip_board.php'>Refresh board to view.</a>`;
                
                const toastElement = document.getElementById('newTripToast');
                const toast = new bootstrap.Toast(toastElement);
                toast.show();

            });

        } else {
            console.log("Sorry, your browser does not support server-sent events.");
        }

    <?php endif; ?>
</script>