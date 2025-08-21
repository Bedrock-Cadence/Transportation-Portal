<?php
// FILE: /cron/trip_runner.php

// This script is intended to be run from the command line by a cron job.
// It will not be accessible from the web.
if (php_sapi_name() !== 'cli') {
    die("Access Denied");
}

require_once __DIR__ . '/../../app/init.php';

$tripService = new TripService();
$configService = new ConfigService();
$db = Database::getInstance();

// Find trips that are ready to be awarded
$tripsToProcess = $db->fetchAll("SELECT * FROM trips WHERE status = 'bidding' AND bidding_closes_at <= NOW()");

foreach ($tripsToProcess as $trip) {
    $tripId = $trip['id'];
    $historyDetails = [];

    try {
        // Log the start of processing for this trip
        $tripService->logTripHistory($tripId, 'processing_started', ['message' => 'Bidding closed. Starting automatic awarding process.']);

        $bids = $db->fetchAll("SELECT * FROM bids WHERE trip_id = ? ORDER BY eta ASC", [$tripId]);

        if (empty($bids)) {
            $tripService->logTripHistory($tripId, 'no_bids', ['message' => 'No bids were received for this trip.']);
            // Optionally, handle what happens to trips with no bids (e.g., set status to 'unawarded')
            $db->query("UPDATE trips SET status = 'cancelled' WHERE id = ?", [$tripId]); // Example: cancel the trip
            continue;
        }

        $historyDetails['bids_considered'] = $bids;

        // Get facility-specific configuration
        $facilityConfig = $configService->getEntityConfig($trip['facility_id'], 'facility');
        $awardingPreference = $facilityConfig['config']['awarding_preference'] ?? 'fastest_eta'; // Default to fastest_eta

        $tripService->logTripHistory($tripId, 'logic_applied', ['preference' => $awardingPreference]);

        $winningBid = null;

        if ($awardingPreference === 'closest_to_pickup' && !empty($trip['requested_pickup_time'])) {
            // Logic to find the bid closest to the requested pickup time
            $pickupTime = new DateTime($trip['requested_pickup_time']);
            $closestBid = null;
            $smallestDiff = PHP_INT_MAX;

            foreach ($bids as $bid) {
                $etaTime = new DateTime($bid['eta']);
                $diff = abs($pickupTime->getTimestamp() - $etaTime->getTimestamp());
                if ($diff < $smallestDiff) {
                    $smallestDiff = $diff;
                    $closestBid = $bid;
                }
            }
            $winningBid = $closestBid;
            $historyDetails['decision_reason'] = 'Selected bid closest to requested pickup time.';
        } else {
            // Default to the fastest ETA (the first bid in the sorted list)
            $winningBid = $bids[0];
            $historyDetails['decision_reason'] = 'Selected bid with the fastest ETA.';
        }

        if ($winningBid) {
            // Award the trip
            $tripService->awardTrip($tripId, $winningBid['carrier_id'], $winningBid['eta']);
            $historyDetails['winning_bid'] = $winningBid;
            $tripService->logTripHistory($tripId, 'trip_awarded', $historyDetails);
        }

    } catch (Exception $e) {
        // Log any errors during processing
        $tripService->logTripHistory($tripId, 'processing_error', ['error_message' => $e->getMessage()]);
    }
}

echo "Trip runner finished processing " . count($tripsToProcess) . " trips.\n";