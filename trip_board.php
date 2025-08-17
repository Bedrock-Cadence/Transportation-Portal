<?php
// FILE: public/trip_board.php

require_once 'init.php';

// --- Step 1: Universal Permission Check ---
$is_authorized = false;
if (isset($_SESSION["loggedin"])) {
    if ($_SESSION['user_role'] === 'admin' || in_array($_SESSION['user_role'], ['user', 'superuser'])) {
        $is_authorized = true;
    }
}
if (!$is_authorized) {
    redirect('login.php');
}

$page_title = 'Available Trips';
$db = Database::getInstance();
$available_trips = [];
$page_heading = '';

try {
    if ($_SESSION['user_role'] === 'admin') {
        $page_heading = "All Available Trips (Admin View)";
        $sql = "SELECT uuid, origin_name, destination_name, appointment_at FROM trips WHERE status = 'bidding' ORDER BY appointment_at ASC";
        $available_trips = $db->query($sql)->fetchAll();

    } else { // Must be a carrier user
        $page_heading = "Available Trips Board";
        $carrier_id = $_SESSION['entity_id'];
        
        $sql = "SELECT t.uuid, t.origin_name, t.destination_name, t.appointment_at
                FROM trips t
                WHERE t.status = 'bidding'
                AND t.facility_id NOT IN (
                    SELECT fcp.facility_id
                    FROM facility_carrier_preferences fcp
                    WHERE fcp.carrier_id = ? AND fcp.preference_type = 'blacklisted'
                )
                ORDER BY t.appointment_at ASC";

        $available_trips = $db->query($sql, [$carrier_id])->fetchAll();
    }
} catch (Exception $e) {
    $page_error = "A database error occurred while fetching available trips.";
    error_log("Trip Board Error: " . $e->getMessage());
}

require_once 'header.php';
?>

<h2 class="mb-4"><?= e($page_heading); ?></h2>

<?php if (isset($page_error)): ?>
    <div class="alert alert-danger" role="alert"><?= e($page_error); ?></div>
<?php endif; ?>

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
                                <td><?= e($trip['origin_name']); ?></td>
                                <td><?= e($trip['destination_name']); ?></td>
                                <td><?= e(date('M j, Y g:i A', strtotime($trip['appointment_at']))); ?></td>
                                <td>
                                    <a href="view_trip.php?uuid=<?= e($trip['uuid']); ?>" class="btn btn-sm btn-primary">View & Bid</a>
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