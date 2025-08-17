<?php
// FILE: public/complete_trip_facility.php

require_once 'init.php';

// --- Permission Check & Input Validation ---
if (!isset($_SESSION["loggedin"])) { redirect('login.php'); }
if (empty($_GET['uuid'])) { redirect('dashboard.php'); }

$page_title = 'Confirm Trip Completion';
$db = Database::getInstance();
$uuid = $_GET['uuid'];
$trip = null;
$is_actionable = false;

try {
    // Fetch trip, ensuring it's in the correct state (carrier has already completed their part)
    $sql_fetch = "SELECT id, facility_id FROM trips WHERE uuid = ? AND carrier_completed_at IS NOT NULL AND facility_completed_at IS NULL LIMIT 1";
    $trip = $db->query($sql_fetch, [$uuid])->fetch();

    if (!$trip) {
        redirect("dashboard.php?error=notfound_or_notready");
    }

    if ($_SESSION['user_role'] === 'admin') {
        $is_actionable = false; // Admins can view but not action
    } elseif (in_array($_SESSION['user_role'], ['user', 'superuser']) && $_SESSION['entity_id'] == $trip['facility_id']) {
        $is_actionable = true;
    } else {
        redirect("dashboard.php?error=unauthorized");
    }

} catch (Exception $e) {
    error_log("Complete Trip Facility (Load) Error: " . $e->getMessage());
    die("A database error occurred while loading the page.");
}

// --- Handle Form Submission ---
if ($is_actionable && $_SERVER["REQUEST_METHOD"] == "POST") {
    $had_issues = $_POST['had_issues'] ?? null;
    $issue_notes = trim($_POST['issue_notes'] ?? '');

    try {
        if ($had_issues === null) {
            throw new Exception("Please answer the question to complete the confirmation.");
        }
        if ($had_issues == 1 && empty($issue_notes)) {
            throw new Exception("Please describe the issues you experienced.");
        }

        $db->pdo()->beginTransaction();

        // 1. Update the trip record to mark it fully complete
        $db->query("UPDATE trips SET facility_completed_at = NOW(), status = 'completed' WHERE id = ?", [$trip['id']]);

        // 2. If issues were reported, create a case
        if ($had_issues == 1) {
            $sql_case = "INSERT INTO cases (trip_id, opened_by_user_id, case_type, initial_notes) VALUES (?, ?, 'Crew Issue Reported by Facility', ?)";
            $db->query($sql_case, [$trip['id'], $_SESSION['user_id'], $issue_notes]);
            log_user_action('case_created', "Facility reported issue for trip ID {$trip['id']}.");
        }

        $db->pdo()->commit();
        log_user_action('trip_completed_facility', "Facility confirmed completion of trip ID {$trip['id']}.");
        redirect("dashboard.php?status=trip_fully_completed");

    } catch (Exception $e) {
        if ($db->pdo()->inTransaction()) $db->pdo()->rollBack();
        $page_error = $e->getMessage();
        error_log("Complete Trip Facility (Submit) Error: " . $e->getMessage());
    }
}

require_once 'header.php';
?>

<div class="container max-w-2xl mx-auto">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">Final Trip Confirmation</h2>
    <p class="mb-6">Please confirm completion and report any issues for Trip <b><?= e(substr($uuid, 0, 8)); ?>...</b></p>

    <?php if (isset($page_error)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p class="font-bold">Error</p>
            <p><?= e($page_error); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($is_actionable): ?>
        <form action="complete_trip_facility.php?uuid=<?= e($uuid); ?>" method="post" class="bg-white shadow-md rounded-lg p-6 border border-gray-200 space-y-6">
            <fieldset>
                <legend class="text-lg font-medium text-gray-900">Were there any issues with the transport or crew?</legend>
                <div class="mt-4 space-y-4">
                    <div class="flex items-center">
                        <input id="issues_no" name="had_issues" type="radio" value="0" required class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300" onclick="toggleNotes(false)">
                        <label for="issues_no" class="ml-3 block text-sm font-medium text-gray-700">No</label>
                    </div>
                    <div class="flex items-center">
                        <input id="issues_yes" name="had_issues" type="radio" value="1" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300" onclick="toggleNotes(true)">
                        <label for="issues_yes" class="ml-3 block text-sm font-medium text-gray-700">Yes</label>
                    </div>
                </div>
            </fieldset>

            <div id="issue_notes_fieldset" style="display:none;">
                <label for="issue_notes" class="block text-sm font-medium text-gray-700">Please describe the issues</label>
                <textarea name="issue_notes" id="issue_notes" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="w-full sm:w-auto inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                    Submit Final Confirmation
                </button>
            </div>
        </form>
    <?php else: ?>
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4" role="alert">
            <p class="font-bold">Admin Read-Only View</p>
            <p>This page is for the originating facility to confirm trip completion.</p>
        </div>
    <?php endif; ?>
</div>

<script>
    function toggleNotes(show) {
        document.getElementById('issue_notes_fieldset').style.display = show ? 'block' : 'none';
        document.getElementById('issue_notes').required = show;
    }
</script>

<?php require_once 'footer.php'; ?>