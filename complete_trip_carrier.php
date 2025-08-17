<?php
// FILE: public/complete_trip_carrier.php

require_once 'init.php';

// --- Permission Check & Input Validation ---
if (!isset($_SESSION["loggedin"])) { redirect('login.php'); }
if (empty($_GET['uuid'])) { redirect('dashboard.php'); }

$page_title = 'Complete Trip';
$db = Database::getInstance();
$uuid = $_GET['uuid'];
$trip = null;
$is_actionable = false;

try {
    // Fetch trip to verify ownership and status
    $sql_fetch = "SELECT id, carrier_id FROM trips WHERE uuid = ? LIMIT 1";
    $trip = $db->query($sql_fetch, [$uuid])->fetch();

    if (!$trip) {
        redirect("dashboard.php?error=notfound");
    }

    // Authorization: User must be an admin or the assigned carrier superuser/user
    if ($_SESSION['user_role'] === 'admin' || (in_array($_SESSION['user_role'], ['user', 'superuser']) && $_SESSION['entity_id'] == $trip['carrier_id'])) {
        $is_actionable = true; // Admins can view, but only carriers can submit
    } else {
        redirect("dashboard.php?error=unauthorized");
    }

} catch (Exception $e) {
    error_log("Complete Trip Carrier (Load) Error: " . $e->getMessage());
    die("A database error occurred while loading the page.");
}


// --- Handle Form Submission ---
if ($is_actionable && $_SERVER["REQUEST_METHOD"] == "POST" && $_SESSION['user_role'] !== 'admin') {
    $transported = $_POST['transported'] ?? null;
    $patient_ready = $_POST['patient_ready'] ?? null;

    try {
        if ($transported === null || $patient_ready === null) {
            throw new Exception("Please answer both questions to complete the report.");
        }

        $db->pdo()->beginTransaction();

        // 1. Update the trip record
        $sql_update = "UPDATE trips SET was_transported_by_carrier = ?, patient_was_ready = ?, carrier_completed_at = NOW() WHERE id = ?";
        $db->query($sql_update, [$transported, $patient_ready, $trip['id']]);

        // 2. If patient was not transported, automatically create a case for admin review
        if ($transported == 0) {
            $case_notes = "Carrier reported they did not transport the patient.";
            $sql_case = "INSERT INTO cases (trip_id, opened_by_user_id, case_type, initial_notes) VALUES (?, ?, 'Patient Not Transported', ?)";
            $db->query($sql_case, [$trip['id'], $_SESSION['user_id'], $case_notes]);
            log_user_action('case_created', "Auto-created case for trip ID {$trip['id']} due to non-transport.");
        }

        $db->pdo()->commit();
        log_user_action('trip_completed_carrier', "Carrier part of trip ID {$trip['id']} was marked complete.");
        redirect("dashboard.php?status=trip_completed");

    } catch (Exception $e) {
        if ($db->pdo()->inTransaction()) $db->pdo()->rollBack();
        $page_error = $e->getMessage();
        error_log("Complete Trip Carrier (Submit) Error: " . $e->getMessage());
    }
}

require_once 'header.php';
?>

<div class="container max-w-2xl mx-auto">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">Trip Completion Report</h2>
    <p class="mb-6">Please answer the following questions for Trip <b><?= e(substr($uuid, 0, 8)); ?>...</b></p>
    
    <?php if (isset($page_error)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p class="font-bold">Error</p>
            <p><?= e($page_error); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($is_actionable && $_SESSION['user_role'] !== 'admin'): ?>
        <form action="complete_trip_carrier.php?uuid=<?= e($uuid); ?>" method="post" class="bg-white shadow-md rounded-lg p-6 border border-gray-200 space-y-6">
            <fieldset>
                <legend class="text-lg font-medium text-gray-900">Did you transport the patient for this trip?</legend>
                <div class="mt-4 space-y-4">
                    <div class="flex items-center">
                        <input id="transported_yes" name="transported" type="radio" value="1" required class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300">
                        <label for="transported_yes" class="ml-3 block text-sm font-medium text-gray-700">Yes</label>
                    </div>
                    <div class="flex items-center">
                        <input id="transported_no" name="transported" type="radio" value="0" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300">
                        <label for="transported_no" class="ml-3 block text-sm font-medium text-gray-700">No (This will automatically open a case for review)</label>
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend class="text-lg font-medium text-gray-900">Was the patient ready within 15 minutes of your arrival?</legend>
                <div class="mt-4 space-y-4">
                    <div class="flex items-center">
                        <input id="ready_yes" name="patient_ready" type="radio" value="1" required class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300">
                        <label for="ready_yes" class="ml-3 block text-sm font-medium text-gray-700">Yes</label>
                    </div>
                    <div class="flex items-center">
                        <input id="ready_no" name="patient_ready" type="radio" value="0" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300">
                        <label for="ready_no" class="ml-3 block text-sm font-medium text-gray-700">No</label>
                    </div>
                </div>
            </fieldset>

            <div class="flex justify-end">
                <button type="submit" class="w-full sm:w-auto inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700">
                    Submit Completion Report
                </button>
            </div>
        </form>
    <?php else: ?>
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4" role="alert">
            <p class="font-bold">Admin Read-Only View</p>
            <p>This page is for the assigned carrier to complete their portion of the trip.</p>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>