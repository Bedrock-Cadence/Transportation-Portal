<?php
// FILE: public_html/portal/complete_trip.php

require_once __DIR__ . '/../../app/init.php';

// --- 1. AUTHORIZATION & INITIAL DATA FETCH ---
if (!Auth::isLoggedIn() || !isset($_GET['uuid'])) {
    Utils::redirect('index.php');
}

$page_title = 'Complete Trip';
$page_error = '';
$tripService = new TripService();
$trip = $tripService->getTripByUuid($_GET['uuid']);

// Redirect if trip doesn't exist or isn't assigned to the current carrier
if (!$trip || $trip['carrier_id'] !== Auth::user('entity_id')) {
    Utils::redirect('index.php?error=notfound');
}

// --- 2. POST REQUEST HANDLING ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $transportedAsAgreed = $_POST['transported_as_agreed'] ?? '';
        $issuesEncountered = $_POST['issues_encountered'] ?? '';
        $issueDescription = $_POST['issue_description'] ?? '';
        $notTransportedReason = $_POST['not_transported_reason'] ?? '';

        $issueReported = ($transportedAsAgreed === 'no' || $issuesEncountered === 'yes');
        $completionNotes = '';
        $ticketId = null;

        if ($issueReported) {
            // Flag the billing entry
            $db = Database::getInstance();
            $db->query("UPDATE billing SET is_flagged = 1 WHERE trip_id = ?", [$trip['id']]);

            // Prepare data for Zoho webhook
            $webhookData = [
                'trip_uuid' => $trip['uuid'],
                'carrier_id' => $trip['carrier_id'],
                'facility_id' => $trip['facility_id'],
                'reason' => ($transportedAsAgreed === 'no') ? $notTransportedReason : $issueDescription
            ];

            // Call the Zoho webhook
            $ch = curl_init('https://flow.zoho.com/896517302/flow/webhook/incoming?zapikey=1001.ca42e6278981b2406d552d8be3baf638.d0f0eb391b110d88f41a66fb1ce7fffe&isdebug=false');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($webhookData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            $response = curl_exec($ch);
            curl_close($ch);
            
            $responseData = json_decode($response, true);
            if (isset($responseData['ticket_id'])) {
                $ticketId = $responseData['ticket_id'];
                $completionNotes = "Issue reported. Ticket ID: " . $ticketId;
            } else {
                 $completionNotes = "Issue reported. Failed to create ticket.";
            }
             $tripService->logTripHistory($trip['id'], 'issue_reported', ['message' => $completionNotes]);
        }

        // Mark trip as completed
        $db->query("UPDATE trips SET status = 'completed' WHERE id = ?", [$trip['id']]);
        
        // TODO: Implement email functionality to send trip file to facility and carrier

        Utils::redirect("index.php?status=trip_completed");

    } catch (Exception $e) {
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
            <p>Once a trip is marked as completed, it will no longer be accessible by the carrier. The entire trip file will be emailed to the facility and to your secure email address on file.</p>
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
                        <label><input type="radio" name="transported_as_agreed" value="yes" required> Yes</label>
                        <label><input type="radio" name="transported_as_agreed" value="no" required> No</label>
                    </div>
                </div>

                <div id="not-transported-reason" class="hidden mt-4">
                    <label for="not_transported_reason" class="block text-sm font-medium text-gray-700">Why was the patient not transported?</label>
                    <textarea name="not_transported_reason" id="not_transported_reason" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></textarea>
                </div>

                <div id="trip-issues" class="hidden mt-4">
                    <label class="block text-sm font-medium text-gray-700">Were there any issues with the trip?</label>
                     <div class="mt-2 space-x-4">
                        <label><input type="radio" name="issues_encountered" value="yes"> Yes</label>
                        <label><input type="radio" name="issues_encountered" value="no"> No</label>
                    </div>
                </div>

                <div id="issue-description" class="hidden mt-4">
                    <label for="issue_description" class="block text-sm font-medium text-gray-700">Please provide an explanation of the issues:</label>
                    <textarea name="issue_description" id="issue_description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></textarea>
                </div>
            </fieldset>

            <div class="flex justify-end pt-5">
                <a href="view_trip.php?uuid=<?= Utils::e($trip['uuid']); ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-3 rounded-md font-semibold mr-4">Cancel</a>
                <button type="submit" class="w-full md:w-auto inline-flex justify-center py-3 px-6 border border-transparent shadow-sm text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">Mark as Completed</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const transportedRadios = document.querySelectorAll('input[name="transported_as_agreed"]');
    const issuesRadios = document.querySelectorAll('input[name="issues_encountered"]');
    const notTransportedReason = document.getElementById('not-transported-reason');
    const tripIssues = document.getElementById('trip-issues');
    const issueDescription = document.getElementById('issue-description');

    transportedRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'no') {
                notTransportedReason.classList.remove('hidden');
                tripIssues.classList.add('hidden');
                issueDescription.classList.add('hidden');
            } else {
                notTransportedReason.classList.add('hidden');
                tripIssues.classList.remove('hidden');
            }
        });
    });

    issuesRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'yes') {
                issueDescription.classList.remove('hidden');
            } else {
                issueDescription.classList.add('hidden');
            }
        });
    });
});
</script>

<?php
require_once 'footer.php';
?>