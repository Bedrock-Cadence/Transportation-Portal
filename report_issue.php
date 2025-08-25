<?php
// FILE: public_html/portal/complete_trip.php

require_once __DIR__ . '/../../app/init.php';

// --- 1. AUTHORIZATION & INITIAL DATA FETCH ---
if (!Auth::isLoggedIn() || !isset($_GET['uuid'])) {
    Utils::redirect('index.php');
}

$page_title = 'Complete Trip';
$page_error = '';
$tripService = new TripService(); // Instantiate the service
$trip = $tripService->getTripByUuid($_GET['uuid']);

// Security Check: Redirect if trip doesn't exist or isn't assigned to the current carrier.
// This also implicitly checks if the user is a carrier.
if (!$trip || $trip['carrier_id'] !== Auth::user('entity_id')) {
    LoggingService::log(Auth::user('user_id'), null, 'unauthorized_complete_attempt', 'User attempted to complete a trip they were not awarded.');
    Utils::redirect('index.php?error=unauthorized');
}

// Security Check 2: Prevent completing a trip that is not in 'awarded' status.
if ($trip['status'] !== 'awarded') {
    Utils::redirect("view_trip.php?uuid={$trip['uuid']}&error=not_awarded");
}


// --- 2. POST REQUEST HANDLING ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        // All logic is now handled by the TripService
        $tripService->completeTrip($trip['id'], $_POST);
        
        // On success, redirect to the dashboard with a success message.
        Utils::redirect("index.php?status=trip_completed");

    } catch (Exception $e) {
        // Any failure in the service will be caught here.
        $page_error = $e->getMessage();
    }
}


require_once 'header.php';
?>

<h1 class="text-3xl font-bold text-gray-800 mb-6">Complete Trip</h1>

<div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200">
    <div class="p-6">

        <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-800 p-4 mb-6" role="alert">
            <p class="font-bold">Important Notice</p>
            <p>Once a trip is marked as completed, it will no longer be accessible by you. The entire trip file will be emailed to the facility and to your secure email address on file.</p>
        </div>

        <?php if (!empty($page_error)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p class="font-bold">Error</p>
                <p><?= Utils::e($page_error); ?></p>
            </div>
        <?php endif; ?>

        <form action="complete_trip.php?uuid=<?= Utils::e($trip['uuid']); ?>" method="post" id="complete-trip-form" class="space-y-8">

            <fieldset>
                <legend class="text-xl font-semibold text-gray-800 border-b border-gray-200 pb-2 mb-4">Post-Trip Report</legend>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700">Was the patient transported as agreed?</label>
                    <div class="mt-2 space-x-4">
                        <label class="inline-flex items-center"><input type="radio" name="transported_as_agreed" value="yes" required class="form-radio h-4 w-4 text-indigo-600"> <span class="ml-2">Yes</span></label>
                        <label class="inline-flex items-center"><input type="radio" name="transported_as_agreed" value="no" required class="form-radio h-4 w-4 text-indigo-600"> <span class="ml-2">No</span></label>
                    </div>
                </div>

                <div id="not-transported-reason" class="hidden mt-4">
                    <label for="not_transported_reason" class="block text-sm font-medium text-gray-700">Why was the patient not transported?</label>
                    <textarea name="not_transported_reason" id="not_transported_reason" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                </div>

                <div id="trip-issues" class="hidden mt-4">
                    <label class="block text-sm font-medium text-gray-700">Were there any issues with the trip?</label>
                     <div class="mt-2 space-x-4">
                        <label class="inline-flex items-center"><input type="radio" name="issues_encountered" value="yes" class="form-radio h-4 w-4 text-indigo-600"> <span class="ml-2">Yes</span></label>
                        <label class="inline-flex items-center"><input type="radio" name="issues_encountered" value="no" checked class="form-radio h-4 w-4 text-indigo-600"> <span class="ml-2">No</span></label>
                    </div>
                </div>

                <div id="issue-description" class="hidden mt-4">
                    <label for="issue_description" class="block text-sm font-medium text-gray-700">Please provide an explanation of the issues:</label>
                    <textarea name="issue_description" id="issue_description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                </div>
            </fieldset>

            <div class="flex justify-end pt-5">
                <a href="index.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-3 rounded-md font-semibold mr-4">Cancel</a>
                <button type="submit" class="w-full md:w-auto inline-flex justify-center py-3 px-6 border border-transparent shadow-sm text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">Mark as Completed</button>
            </div>
        </form>
    </div>
</div>

<script nonce="<?= $cspNonce ?>">
document.addEventListener('DOMContentLoaded', function () {
    const transportedRadios = document.querySelectorAll('input[name="transported_as_agreed"]');
    const issuesRadios = document.querySelectorAll('input[name="issues_encountered"]');
    const notTransportedReasonDiv = document.getElementById('not-transported-reason');
    const tripIssuesDiv = document.getElementById('trip-issues');
    const issueDescriptionDiv = document.getElementById('issue-description');
    const notTransportedTextarea = document.getElementById('not_transported_reason');
    const issueDescriptionTextarea = document.getElementById('issue_description');

    function toggleSections() {
        const transportedValue = document.querySelector('input[name="transported_as_agreed"]:checked')?.value;
        const issuesValue = document.querySelector('input[name="issues_encountered"]:checked')?.value;

        if (transportedValue === 'no') {
            notTransportedReasonDiv.classList.remove('hidden');
            notTransportedTextarea.required = true;
            tripIssuesDiv.classList.add('hidden');
            issueDescriptionDiv.classList.add('hidden');
            issueDescriptionTextarea.required = false;
        } else if (transportedValue === 'yes') {
            notTransportedReasonDiv.classList.add('hidden');
            notTransportedTextarea.required = false;
            tripIssuesDiv.classList.remove('hidden');
            
            if (issuesValue === 'yes') {
                issueDescriptionDiv.classList.remove('hidden');
                issueDescriptionTextarea.required = true;
            } else {
                issueDescriptionDiv.classList.add('hidden');
                issueDescriptionTextarea.required = false;
            }
        } else {
             notTransportedReasonDiv.classList.add('hidden');
             tripIssuesDiv.classList.add('hidden');
             issueDescriptionDiv.classList.add('hidden');
        }
    }

    transportedRadios.forEach(radio => radio.addEventListener('change', toggleSections));
    issuesRadios.forEach(radio => radio.addEventListener('change', toggleSections));

    // Initial check on page load
    toggleSections();
});
</script>

<?php
require_once 'footer.php';
?>