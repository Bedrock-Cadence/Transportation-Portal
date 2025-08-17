<?php
// FILE: public/edit_trip_facility.php

require_once 'init.php';

// --- Permission Check & Data Fetch ---
if (!isset($_SESSION["loggedin"])) { redirect('login.php'); }
if (empty($_GET['uuid'])) { redirect('dashboard.php'); }

$page_title = 'Request Trip Update';
$db = Database::getInstance();
$uuid = $_GET['uuid'];
$trip = null;
$trip_phi = null;
$page_message = '';
$page_error = '';

try {
    // Fetch both trip and its PHI
    $trip = $db->query("SELECT * FROM trips WHERE uuid = ? LIMIT 1", [$uuid])->fetch();
    if ($trip) {
        $trip_phi = $db->query("SELECT * FROM trips_phi WHERE trip_id = ? LIMIT 1", [$trip['id']])->fetch();
    }

    if (!$trip || !$trip_phi) {
        redirect("dashboard.php?error=notfound");
    }

    $is_authorized = false;
    if ($_SESSION['user_role'] === 'admin' || (in_array($_SESSION['user_role'], ['user', 'superuser']) && $_SESSION['entity_id'] == $trip['facility_id'])) {
        $is_authorized = true;
    }
    if (!$is_authorized) {
        redirect("dashboard.php?error=unauthorized");
    }

} catch (Exception $e) {
    error_log("Edit Trip Facility (Load) Error: " . $e->getMessage());
    die("A database error occurred.");
}

// --- Handle Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && in_array($_SESSION['user_role'], ['user', 'superuser'])) {
    try {
        // --- Build Comparison Data ---
        $original_data = [
            'patient_first_name' => decrypt_data($trip_phi['patient_first_name_encrypted'], ENCRYPTION_KEY),
            'patient_last_name' => decrypt_data($trip_phi['patient_last_name_encrypted'], ENCRYPTION_KEY),
            'patient_weight_kg' => decrypt_data($trip_phi['patient_weight_kg_encrypted'], ENCRYPTION_KEY),
            'appointment_at' => date('Y-m-d\TH:i', strtotime($trip['appointment_at'])),
        ];
        
        $submitted_data = [
            'patient_first_name' => trim($_POST['patient_first_name']),
            'patient_last_name' => trim($_POST['patient_last_name']),
            'patient_weight_kg' => trim($_POST['patient_weight_kg']),
            'appointment_at' => trim($_POST['appointment_at']),
        ];

        // --- Whitelist of fields that a facility is allowed to request changes for ---
        $editable_fields = ['patient_first_name', 'patient_last_name', 'patient_weight_kg', 'appointment_at'];
        $changes = [];

        foreach ($editable_fields as $field) {
            if (isset($submitted_data[$field]) && $submitted_data[$field] !== $original_data[$field]) {
                $changes[$field] = ['old' => $original_data[$field], 'new' => $submitted_data[$field]];
            }
        }

        if (!empty($changes)) {
            $db->pdo()->beginTransaction();
            
            $proposed_data = json_encode($changes);
            $sql_request = "INSERT INTO trip_change_requests (trip_id, request_type, requested_by_user_id, proposed_data) VALUES (?, 'details_change', ?, ?)";
            $db->query($sql_request, [$trip['id'], $_SESSION['user_id'], $proposed_data]);
            $request_id = $db->pdo()->lastInsertId();

            // Notify the Carrier's superuser(s)
            $carrier_superusers = $db->query("SELECT id FROM users WHERE entity_id = ? AND entity_type = 'carrier' AND role = 'superuser'", [$trip['carrier_id']])->fetchAll();
            $message = "Facility requested a detail change for Trip ".substr($uuid, 0, 8).".";
            $link = "review_details_change.php?id=" . $request_id;
            
            foreach($carrier_superusers as $su) {
                $db->query("INSERT INTO notifications (user_id, event_type, message, link) VALUES (?, 'details_change_requested', ?, ?)", [$su['id'], $message, $link]);
            }

            $db->pdo()->commit();
            log_user_action('trip_change_requested', "Facility requested changes for trip ID {$trip['id']}.");
            redirect("dashboard.php?status=update_requested");
        } else {
            $page_message = 'No changes were detected.';
        }

    } catch (Exception $e) {
        if ($db->pdo()->inTransaction()) $db->pdo()->rollBack();
        $page_error = "Could not submit the update request.";
        error_log("Edit Trip Facility (Submit) Error: " . $e->getMessage());
    }
}

require_once 'header.php';
?>

<h2 class="mb-4">Request Trip Update</h2>
<p>Modify the details below. The assigned carrier will be required to approve these changes.</p>
<?php if($page_message): ?><div class="alert alert-info"><?= e($page_message); ?></div><?php endif; ?>
<?php if($page_error): ?><div class="alert alert-danger"><?= e($page_error); ?></div><?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="edit_trip_facility.php?uuid=<?= e($uuid); ?>" method="post">
            <fieldset class="mb-4">
                <legend class="fs-5 border-bottom mb-3 pb-2">Patient Information (PHI)</legend>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="patient_first_name" class="form-label">First Name</label>
                        <input type="text" name="patient_first_name" class="form-control" value="<?= e(decrypt_data($trip_phi['patient_first_name_encrypted'], ENCRYPTION_KEY)); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="patient_last_name" class="form-label">Last Name</label>
                        <input type="text" name="patient_last_name" class="form-control" value="<?= e(decrypt_data($trip_phi['patient_last_name_encrypted'], ENCRYPTION_KEY)); ?>" required>
                    </div>
                </div>
                 <div class="mb-3">
                    <label for="patient_weight_kg" class="form-label">Patient Weight (kg)</label>
                    <input type="number" name="patient_weight_kg" class="form-control" value="<?= e(decrypt_data($trip_phi['patient_weight_kg_encrypted'], ENCRYPTION_KEY)); ?>" required>
                </div>
            </fieldset>

            <fieldset class="mb-4">
                <legend class="fs-5 border-bottom mb-3 pb-2">Trip Details</legend>
                 <div class="mb-3">
                    <label for="appointment_at" class="form-label">Appointment Time</label>
                    <input type="datetime-local" name="appointment_at" class="form-control" value="<?= e(date('Y-m-d\TH:i', strtotime($trip['appointment_at']))); ?>" required>
                </div>
            </fieldset>

            <?php if (in_array($_SESSION['user_role'], ['user', 'superuser'])): ?>
            <div class="d-grid">
                <button type="submit" class="btn btn-warning btn-lg">Submit Update Request for Carrier Approval</button>
            </div>
            <?php else: ?>
            <div class="alert alert-warning"><b>Admin Read-Only View:</b> Form submission is disabled.</div>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php require_once 'footer.php'; ?>