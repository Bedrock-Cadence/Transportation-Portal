<?php
// FILE: /public_html/portal/cron/trip_runner.php

// This script is intended to be run from the command line by a cron job.
// It will not be accessible from the web.
if (php_sapi_name() !== 'cli') {
    die("Access Denied");
}

require_once __DIR__ . '/../../../app/init.php';

$tripService = new TripService();
$configService = new ConfigService();
$db = Database::getInstance();

// Find trips that are ready to be awarded
$tripsToProcess = $db->fetchAll("SELECT * FROM trips WHERE status = 'bidding' AND bidding_closes_at <= NOW()");

foreach ($tripsToProcess as $trip) {
    $tripId = $trip['id'];
    $historyDetails = [];

    try {
        $bids = $db->fetchAll("SELECT * FROM bids WHERE trip_id = ? ORDER BY eta ASC", [$tripId]);

// --- NEW LOGIC FOR HANDLING NO BIDS ---
        if (empty($bids)) {
            // Check if we have already extended this trip once
            $extension_log = $db->fetch("SELECT id FROM trip_history WHERE trip_id = ? AND event_type = 'bidding_extended'", [$tripId]);

            if ($extension_log) {
                // If it has been extended before, log and cancel it.
                $tripService->logTripHistory($tripId, 'no_bids_after_extension', ['message' => 'No bids were received after a 20-minute extension. Cancelling trip.']);
                $db->query("UPDATE trips SET status = 'cancelled' WHERE id = ?", [$tripId]);

                // *** NEW NOTIFICATION LOGIC FOR CANCELLATION ***
                $facilityId = $db->fetch("SELECT facility_id FROM trips WHERE id = ?", [$tripId])['facility_id'];
                $message = "Trip ID: " . $tripId . " was canceled due to no bids after the extended bidding period.";
                $tripService->sendFacilityNotification($facilityId, $message);
            } else {
                // If this is the first time with no bids, extend the bidding time by 20 minutes.
                $newBiddingClosesAt = new DateTime('now', new DateTimeZone('UTC'));
                $newBiddingClosesAt->add(new DateInterval('PT20M'));
                $newBiddingClosesAtTimestamp = $newBiddingClosesAt->format('Y-m-d H:i:s');

                $db->query("UPDATE trips SET bidding_closes_at = ? WHERE id = ?", [$newBiddingClosesAtTimestamp, $tripId]);

                $tripService->logTripHistory($tripId, 'bidding_extended', [
                    'message' => 'No bids received. Bidding automatically extended by 20 minutes.',
                    'new_bidding_closes_at' => $newBiddingClosesAtTimestamp
                ]);

                // *** NEW NOTIFICATION LOGIC FOR EXTENDED BIDDING ***
                $facilityId = $db->fetch("SELECT facility_id FROM trips WHERE id = ?", [$tripId])['facility_id'];
                $message = "Trip ID: " . $tripId . " has not received any bids. Bidding has been automatically extended to " . $newBiddingClosesAt->format('Y-m-d H:i:s') . ".";
                $tripService->sendFacilityNotification($facilityId, $message);
            }
            continue; // Skip to the next trip
        }
        
        // --- Original Awarding Logic ---
        $tripService->logTripHistory($tripId, 'processing_started', ['message' => 'Bidding closed. Starting automatic awarding process.']);
        $historyDetails['bids_considered'] = $bids;

        $facilityConfig = $configService->getEntityConfig($trip['facility_id'], 'facility');
        $awardingPreference = $facilityConfig['config']['awarding_preference'] ?? 'fastest_eta';

        $tripService->logTripHistory($tripId, 'logic_applied', ['preference' => $awardingPreference]);

        $winningBid = null;

        if ($awardingPreference === 'closest_to_pickup' && !empty($trip['requested_pickup_time'])) {
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
            $winningBid = $bids[0];
            $historyDetails['decision_reason'] = 'Selected bid with the fastest ETA.';
        }

        if ($winningBid) {
            $tripService->awardTrip($tripId, $winningBid['carrier_id'], $winningBid['eta']);
                        // --- NEW BILLING TABLE LOGIC ---
            // After awarding the trip, add an entry to the billing table.
            $billingSql = "INSERT INTO billing (trip_id, trip_uuid, winning_user_id, carrier_eta) VALUES (?, ?, ?, ?)";
            $db->query($billingSql, [
                $tripId,
                $trip['uuid'], // Assuming the UUID is in the $trip array
                $winningBid['carrier_id'],
                $winningBid['eta']
            ]);
            // --- END NEW BILLING TABLE LOGIC ---
            
            $historyDetails['winning_bid'] = $winningBid;
            $tripService->logTripHistory($tripId, 'trip_awarded', $historyDetails);
        }

    } catch (Exception $e) {
        $tripService->logTripHistory($tripId, 'processing_error', ['error_message' => $e->getMessage()]);
    }
}

echo "Trip runner finished processing " . count($tripsToProcess) . " trips.\n";