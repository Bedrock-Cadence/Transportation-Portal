<?php
// FILE: /public_html/portal/cron/auto_bidder.php
// PURPOSE: This script automatically places one single bid on one random trip per execution.

// --- SCRIPT INITIALIZATION & ERROR REPORTING ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(0);

//if (php_sapi_name() !== 'cli') {
    //die("Access Denied: This script can only be run from the command line.");
//}

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

        // --- NEW LOGIC: AVOID `ORDER BY RAND()` ---

        // Step 2a: Get all active carrier IDs. This is a fast query.
        $allCarriers = $db->fetchAll("SELECT id FROM carriers WHERE is_active = 1");
        if (empty($allCarriers)) {
            echo " -> No active carriers in the system. Trying next trip...\n";
            continue;
        }
        $carrierIds = array_column($allCarriers, 'id');
        shuffle($carrierIds); // Randomize the carriers in PHP, which is very fast.

        // Step 2b: Loop through the shuffled carrier IDs and check eligibility one by one.
        echo " -> Checking " . count($carrierIds) . " randomized carriers for eligibility...\n";
        foreach ($carrierIds as $carrierId) {
            // This is a very fast, targeted query for a single carrier.
            $checkSql = "
                SELECT
                    (SELECT 1 FROM facility_carrier_preferences WHERE carrier_id = :cid AND facility_id = :fid AND preference_type = 'blacklisted' LIMIT 1) as is_blacklisted,
                    (SELECT 1 FROM trip_bidding_lockouts WHERE carrier_id = :cid AND trip_id = :tid LIMIT 1) as is_locked_out,
                    (SELECT 1 FROM bids WHERE carrier_id = :cid AND trip_id = :tid LIMIT 1) as has_bid,
                    (SELECT id FROM users WHERE entity_id = :cid AND entity_type = 'carrier' AND is_active = 1 LIMIT 1) as user_id
            ";
            $eligibility = $db->fetch($checkSql, [
                ':cid' => $carrierId,
                ':fid' => $trip['facility_id'],
                ':tid' => $tripId
            ]);

            // If the carrier is eligible, place the bid and exit.
            if ($eligibility && !$eligibility['is_blacklisted'] && !$eligibility['is_locked_out'] && !$eligibility['has_bid'] && $eligibility['user_id']) {
                echo " -> Found eligible carrier ID: {$carrierId}. Placing bid...\n";

                $etaMinutes = rand(20, 120);
                $etaDateTime = new DateTime("now", new DateTimeZone('UTC'));
                $etaDateTime->modify("+{$etaMinutes} minutes");
                $utcEtaString = $etaDateTime->format('Y-m-d H:i:s');

                try {
                    $tripService->placeBidForSystem(
                        $tripId,
                        $trip['bidding_closes_at'],
                        $carrierId,
                        $eligibility['user_id'],
                        $utcEtaString
                    );
                    echo "    -> SUCCESS: Carrier ID {$carrierId} bid on Trip ID {$tripId}.\n";
                    echo "    -> Single bid placed. Script will now exit.\n";
                    exit(0); // --- EXIT SCRIPT AFTER ONE SUCCESSFUL BID ---

                } catch (Exception $e) {
                    echo "    -> ERROR placing bid for Carrier ID {$carrierId}: " . $e->getMessage() . "\n";
                    // Continue to the next trip to try again
                }
            }
        }
        echo " -> No eligible carriers found for this trip. Trying next trip...\n";
    }

    echo "\nLooped through all available trips but could not place a bid.\n";

} catch (Exception $e) {
    file_put_contents('php://stderr', "Auto Bidder Script Failed: " . $e->getMessage() . "\n");
    exit(1);
}

echo "\nAuto Bidder Cron Job Finished.\n";
exit(0);