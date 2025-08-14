<?php
$page_title = 'Master Trip List';
require_once 'header.php'; // Includes session_start()
require_once __DIR__ . '/../../app/db_connect.php';

// --- Permission Check for Bedrock Admin ---
if (!isset($_SESSION["loggedin"]) || $_SESSION['user_role'] !== 'bedrock_admin') {
    header("location: login.php");
    exit;
}

// --- Fetch all trips, joining with facilities and carriers to get names ---
$all_trips = [];
$sql = "SELECT 
            t.uuid, t.status, t.created_at, t.appointment_at, 
            f.name AS facility_name, 
            c.name AS carrier_name 
        FROM trips t
        JOIN facilities f ON t.facility_id = f.id
        LEFT JOIN carriers c ON t.carrier_id = c.id
        ORDER BY t.created_at DESC";

$result = $mysqli->query($sql);
if ($result) {
    while($row = $result->fetch_assoc()){
        $all_trips[] = $row;
    }
}
$mysqli->close();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Master Trip List</h2>
    </div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Trip ID</th>
                        <th>Status</th>
                        <th>Facility</th>
                        <th>Awarded Carrier</th>
                        <th>Appointment</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($all_trips)): ?>
                        <?php foreach ($all_trips as $trip): ?>
                            <tr>
                                <td><code><?php echo substr($trip['uuid'], 0, 8); ?>...</code></td>
                                <td>
                                    <?php 
                                    // Simple status badges using Bootstrap
                                    $status = htmlspecialchars($trip['status']);
                                    $badge_class = 'bg-secondary';
                                    if ($status == 'bidding') $badge_class = 'bg-primary';
                                    if ($status == 'awarded') $badge_class = 'bg-success';
                                    if ($status == 'completed') $badge_class = 'bg-dark';
                                    if ($status == 'cancelled') $badge_class = 'bg-danger';
                                    echo "<span class='badge {$badge_class}'>" . ucfirst($status) . "</span>";
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($trip['facility_name']); ?></td>
                                <td><?php echo htmlspecialchars($trip['carrier_name'] ?? 'N/A'); ?></td>
                                <td><?php echo date('M j, Y g:i A', strtotime($trip['appointment_at'])); ?></td>
                                <td>
                                    <?php 
                                    // Link to the correct details page based on status
                                    $link = ($trip['status'] == 'bidding') ? 'view_trip.php' : 'awarded_trip_details.php';
                                    ?>
                                    <a href="<?php echo $link; ?>?uuid=<?php echo $trip['uuid']; ?>" class="btn btn-sm btn-outline-primary">View Details</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center">No trips found in the system.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
require_once 'footer.php';
?>