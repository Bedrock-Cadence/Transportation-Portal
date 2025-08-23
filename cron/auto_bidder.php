<?php
// FILE: /public_html/portal/cron/auto_bidder.php
// PURPOSE: This script automatically places one single bid on one random trip per execution.

// --- SCRIPT INITIALIZATION & ERROR REPORTING ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(0);

if (php_sapi_name() !== 'cli') {
    die("Access Denied: This script can only be run from the command line.");
}

require_once __DIR__ . '/../../../app/init.php';

echo "Auto Bidder Cron Job Started: " . date('Y-m-d H:i:s') . "\n";

try {
    $db = Database::getInstance();
    $tripService = new TripService();

    // --- Step 1: Find all trips that are currently open for bidding ---
    echo "Step 1: Finding all open trips...\n";
    $openTripsSql = "SELECT id, facility_id, bidding_closes_at FROM trips WHERE status = 'bidding' AND bidding_closes_at > NOW()";
    $openTrips = $db->fetchAll($openTripsSql);

    if (empty($openTrips)) {
        echo "No open trips found for bidding. Exiting.\n";
        exit(0);
    }

    shuffle($openTrips);
    echo " -> Found " . count($openTrips) . " open trip(s). Processing in random order to place one bid.\n";

    // --- Step 2: Loop through the randomized trips until one successful bid is placed ---
    foreach ($openTrips as $trip) {
        $tripId = $trip['id'];
        echo "\nAttempting to place a bid for Trip ID: {$tripId}\n";

        // --- NEW EFFICIENT LOGIC ---

        // Step 2a: Get all active carrier IDs and shuffle them in PHP.
        $allCarriers = $db->fetchAll("SELECT id FROM carriers WHERE is_active = 1");
        if (empty($allCarriers)) {
            echo " -> No active carriers in the system. Trying next trip...\n";
            continue;
        }
        $shuffledCarrierIds = array_column($allCarriers, 'id');
        shuffle($shuffledCarrierIds);

        // Step 2b: Fetch all ineligible carrier IDs for this trip in a few simple queries.
        $blacklisted = $db->fetchAll("SELECT carrier_id FROM facility_carrier_preferences WHERE facility_id = ? AND preference_type = 'blacklisted'", [$trip['facility_id']]);
        $locked_out = $db->fetchAll("SELECT carrier_id FROM trip_bidding_lockouts WHERE trip_id = ?", [$tripId]);
        $has_bid = $db->fetchAll("SELECT carrier_id FROM bids WHERE trip_id = ?", [$tripId]);

        // Combine all ineligible IDs into a single lookup array for performance.
        $ineligibleIds = array_flip(array_merge(
            array_column($blacklisted, 'carrier_id'),
            array_column($locked_out, 'carrier_id'),
            array_column($has_bid, 'carrier_id')
        ));
        
        echo " -> Found " . count($ineligibleIds) . " ineligible carrier(s) for this trip.\n";

        // Step 2c: Loop through the shuffled carriers and find the first eligible one.
        $eligibleCarrierId = null;
        $eligibleUserId = null;

        foreach ($shuffledCarrierIds as $carrierId) {
            // Check eligibility in PHP, which is extremely fast.
            if (!isset($ineligibleIds[$carrierId])) {
                // This carrier is not ineligible. Do one final quick check for an active user.
                $user = $db->fetch("SELECT id FROM users WHERE entity_id = ? AND entity_type = 'carrier' AND is_active = 1 LIMIT 1", [$carrierId]);
                if ($user) {
                    $eligibleCarrierId = $carrierId;
                    $eligibleUserId = $user['id'];
                    break; // Found one, stop searching.
                }
            }
        }

        // Step 3: If an eligible carrier was found, place the bid and exit.
        if ($eligibleCarrierId) {
            echo " -> Found eligible carrier ID: {$eligibleCarrierId}. Placing bid...\n";

            $etaMinutes = rand(20, 120);
            $etaDateTime = new DateTime("now", new DateTimeZone('UTC'));
            $etaDateTime->modify("+{$etaMinutes} minutes");
            $utcEtaString = $etaDateTime->format('Y-m-d H:i:s');

            try {
                $tripService->placeBidForSystem(
                    $tripId,
                    $trip['bidding_closes_at'],
                    $eligibleCarrierId,
                    $eligibleUserId,
                    $utcEtaString
                );
                echo "    -> SUCCESS: Carrier ID {$eligibleCarrierId} bid on Trip ID {$tripId}.\n";
                echo "    -> Single bid placed. Script will now exit.\n";
                exit(0); // --- EXIT SCRIPT AFTER ONE SUCCESSFUL BID ---

            } catch (Exception $e) {
                echo "    -> ERROR placing bid for Carrier ID {$eligibleCarrierId}: " . $e->getMessage() . "\n";
                // Continue to the next trip to try again
            }
        } else {
            echo " -> No eligible carriers found for this trip. Trying next trip...\n";
        }
    }

    echo "\nLooped through all available trips but could not place a bid.\n";

} catch (Exception $e) {
    file_put_contents('php://stderr', "Auto Bidder Script Failed: " . $e->getMessage() . "\n");
    exit(1);
}

echo "\nAuto Bidder Cron Job Finished.\n";
exit(0);