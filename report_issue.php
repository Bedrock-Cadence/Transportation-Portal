<?php
// FILE: public_html/portal/report_issue.php

require_once __DIR__ . '/../../app/init.php';

// --- 1. AUTHORIZATION & INITIAL DATA FETCH ---
if (!Auth::isLoggedIn() || !isset($_GET['uuid'])) {
    Utils::redirect('index.php');
}

$page_title = 'Report an Issue';
$page_error = '';
$tripService = new TripService();
$trip = $tripService->getTripByUuid($_GET['uuid']);

// --- 2. SECURITY & BUSINESS LOGIC CHECKS ---

// Authorization Check: User must be from the facility or the awarded carrier.
$userEntityType = Auth::user('entity_type');
$userEntityId = Auth::user('entity_id');
$isAuthorized = ($userEntityType === 'facility' && $trip && $userEntityId == $trip['facility_id']) || 
                ($userEntityType === 'carrier' && $trip && $userEntityId == $trip['carrier_id']);

if (!$trip || !$isAuthorized) {
    LoggingService::log(Auth::user('user_id'), null, 'unauthorized_issue_report_attempt', 'User attempted to access issue report page for a trip they are not associated with.');
    Utils::redirect('index.php?error=unauthorized');
}

// Status Check: Can only report on trips that have been awarded or were previously completed.
if (!in_array($trip['status'], ['awarded', 'completed'])) {
    Utils::redirect("view_trip.php?uuid={$trip['uuid']}&error=invalid_status_for_report");
}

// Time Limit Check: Must be within 7 calendar days of the trip's awarded ETA.
try {
    if (!empty($trip['awarded_eta'])) {
        $tripDate = new DateTime($trip['awarded_eta']);
        $tripDate->setTime(0, 0, 0); // Set to beginning of the day.
        $deadline = $tripDate->modify('+8 days'); // Report is allowed on the 7th day. Deadline is start of 8th.
        
        if (new DateTime() >= $deadline) {
             Utils::redirect("view_trip.php?uuid={$trip['uuid']}&error=report_deadline_passed");
        }
    } else {
        // Fallback for an unlikely edge case where an awarded trip has no ETA.
        Utils::redirect("view_trip.php?uuid={$trip['uuid']}&error=cannot_report_issue_no_date");
    }
} catch (Exception $e) {
    LoggingService::log(null, null, 'date_error_report_issue', 'Could not parse awarded_eta for Trip ID ' . $trip['id']);
    Utils::redirect("view_trip.php?uuid={$trip['uuid']}&error=internal_date_error");
}


// --- 3. POST REQUEST HANDLING ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        // All logic is now handled by the new reportIssue method in TripService
        $tripService->reportIssue($trip['id'], $_POST);
        
        // On success, redirect to the trip view page with a success message.
        Utils::redirect("view_trip.php?uuid={$trip['uuid']}&status=issue_reported");

    } catch (Exception $e) {
        // Any failure in the service will be caught and displayed to the user.
        $page_error = $e->getMessage();
    }
}

// --- 4. DEFINE DYNAMIC FORM OPTIONS ---
$issueTypes = [];
if ($userEntityType === 'facility') {
    $issueTypes = [
        "Carrier Late for Pickup",
        "Carrier Unprofessionalism",
        "Unsafe/Unclean Vehicle",
        "Patient Complaint Regarding Carrier",
        "Carrier No-Show",
        "Other"
    ];
} else { // 'carrier'
    $issueTypes = [
        "Facility Not Ready for Pickup",
        "Inaccurate Patient Information (Weight, Clinical, etc.)",
        "Excessive Wait Time at Facility",
        "Patient Refused Transport",
        "Unsafe Scene / Conditions at Location",
        "Other"
    ];
}


require_once 'header.php';
?>

<h1 class="text-3xl font-bold text-gray-800 mb-6">Report Issue for Trip #<?= Utils::e($trip['sid']); ?></h1>

<div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200">
    <div class="p-6">

        <?php if (!empty($page_error)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p class="font-bold">Error</p>
                <p><?= Utils::e($page_error); ?></p>
            </div>
        <?php endif; ?>
        
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-800 p-4 mb-6" role="alert">
            <p><strong class="font-bold">Please Note:</strong> Submitting this form will flag the trip for administrative review and will pause any related billing processes. Please be as detailed as possible.</p>
        </div>


        <form action="report_issue.php?uuid=<?= Utils::e($trip['uuid']); ?>" method="post" id="report-issue-form" class="space-y-6">

            <div>
                <label for="issue_type" class="block text-sm font-medium text-gray-700">Type of Issue</label>
                <select id="issue_type" name="issue_type" required class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                    <option value="" disabled selected>-- Select an issue type --</option>
                    <?php foreach ($issueTypes as $type): ?>
                        <option value="<?= Utils::e($type); ?>"><?= Utils::e($type); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="issue_details" class="block text-sm font-medium text-gray-700">Detailed Description</label>
                <p class="text-xs text-gray-500 mb-1">Please provide a detailed, factual account of the issue. Include times, names, and specific observations where possible.</p>
                <textarea name="issue_details" id="issue_details" rows="5" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
            </div>


            <div class="flex justify-end pt-5 border-t border-gray-200">
                <a href="view_trip.php?uuid=<?= Utils::e($trip['uuid']); ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-3 rounded-md font-semibold mr-4">Cancel</a>
                <button type="submit" class="w-full md:w-auto inline-flex justify-center py-3 px-6 border border-transparent shadow-sm text-base font-medium rounded-md text-white bg-red-600 hover:bg-red-700">
                    Submit Report
                </button>
            </div>
        </form>
    </div>
</div>

<?php
require_once 'footer.php';
?>