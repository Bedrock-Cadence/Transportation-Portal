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

    // --- MODIFICATION: Shuffle the trips to process them in a random order ---
    shuffle($openTrips);
    echo " -> Found " . count($openTrips) . " open trip(s). Processing in random order to place one bid.\n";

    // --- Step 2: Loop through the randomized trips until one successful bid is placed ---
    foreach ($openTrips as $trip) {
        $tripId = $trip['id'];
        echo "\nAttempting to place a bid for Trip ID: {$tripId}\n";

        // --- Step 2a: Find ONE random eligible carrier for this trip ---
        // The `ORDER BY RAND() LIMIT 1` is efficient for finding a single random row.
        $eligibleCarrierSql = "
            SELECT
                c.id AS carrier_id,
                (SELECT u.id FROM users u WHERE u.entity_id = c.id AND u.entity_type = 'carrier' AND u.is_active = 1 LIMIT 1) AS user_id
            FROM
                carriers c
            LEFT JOIN facility_carrier_preferences fcp ON c.id = fcp.carrier_id
                AND fcp.facility_id = :facility_id
                AND fcp.preference_type = 'blacklisted'
            LEFT JOIN trip_bidding_lockouts tblo ON c.id = tblo.carrier_id
                AND tblo.trip_id = :trip_id
            LEFT JOIN bids b ON c.id = b.carrier_id
                AND b.trip_id = :trip_id
            WHERE
                c.is_active = 1
                AND fcp.carrier_id IS NULL
                AND tblo.carrier_id IS NULL
                AND b.carrier_id IS NULL
            HAVING
                user_id IS NOT NULL
            ORDER BY
                RAND()
            LIMIT 1
        ";

        $carrier = $db->fetch($eligibleCarrierSql, [
            ':facility_id' => $trip['facility_id'],
            ':trip_id' => $tripId
        ]);

        // --- Step 3: If a carrier was found, place the bid and exit ---
        if ($carrier) {
            echo " -> Found eligible carrier ID: {$carrier['carrier_id']}. Placing bid...\n";

            $etaMinutes = rand(20, 120);
            $etaDateTime = new DateTime("now", new DateTimeZone('UTC'));
            $etaDateTime->modify("+{$etaMinutes} minutes");
            $utcEtaString = $etaDateTime->format('Y-m-d H:i:s');

            try {
                $tripService->placeBidForSystem(
                    $tripId,
                    $trip['bidding_closes_at'],
                    $carrier['carrier_id'],
                    $carrier['user_id'],
                    $utcEtaString
                );
                echo "    -> SUCCESS: Carrier ID {$carrier['carrier_id']} bid on Trip ID {$tripId}.\n";
                echo "    -> Single bid placed. Script will now exit.\n";
                
                // --- EXIT SCRIPT AFTER ONE SUCCESSFUL BID ---
                exit(0);

            } catch (Exception $e) {
                echo "    -> ERROR placing bid for Carrier ID {$carrier['carrier_id']}: " . $e->getMessage() . "\n";
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