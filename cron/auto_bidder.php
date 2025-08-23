<?php
// FILE: /public_html/portal/cron/auto_bidder.php
// PURPOSE: This script automatically places bids on behalf of carriers for development purposes.

// --- SCRIPT INITIALIZATION & ERROR REPORTING ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(0);

//if (php_sapi_name() !== 'cli') {
    //die("Access Denied: This script can only be run from the command line.");
//}

require_once __DIR__ . '/../../../app/init.php';

// --- CONFIGURATION ---
const BIDDING_CHANCE_FACTOR = 2;
const BATCH_SIZE = 100; // Process 100 carriers at a time to keep queries small and fast.
// --- END CONFIGURATION ---

echo "Auto Bidder Cron Job Started: " . date('Y-m-d H:i:s') . "\n";

try {
    $db = Database::getInstance();
    $tripService = new TripService();

    // --- Step 1: Find all trips that are currently open for bidding ---
    echo "Step 1: Finding open trips...\n";
    $openTripsSql = "SELECT id, facility_id, bidding_closes_at FROM trips WHERE status = 'bidding' AND bidding_closes_at > NOW()";
    $openTrips = $db->fetchAll($openTripsSql);

    if (empty($openTrips)) {
        echo "No open trips found for bidding.\n";
        exit(0);
    }

    echo " -> Found " . count($openTrips) . " open trip(s) for bidding.\n";

    // --- Step 2: Loop through each open trip ---
    foreach ($openTrips as $trip) {
        $tripId = $trip['id'];
        $totalBidsPlaced = 0;
        echo "\nProcessing Trip ID: {$tripId}\n";

        // --- NEW BATCHING LOGIC ---

        // Step 2a: Get a simple list of ALL active carrier IDs. This is a very fast query.
        echo " -> Step 2a: Fetching all active carrier IDs...\n";
        $allCarriers = $db->fetchAll("SELECT id FROM carriers WHERE is_active = 1");
        if (empty($allCarriers)) {
            echo " -> No active carriers in the system to process.\n";
            continue;
        }
        $carrierIds = array_column($allCarriers, 'id');
        echo " -> Found " . count($carrierIds) . " total active carriers.\n";

        // Step 2b: Process the carrier IDs in small batches.
        for ($i = 0; $i < count($carrierIds); $i += BATCH_SIZE) {
            $batchIds = array_slice($carrierIds, $i, BATCH_SIZE);
            $placeholders = implode(',', array_fill(0, count($batchIds), '?'));

            echo " -> Processing batch " . ($i / BATCH_SIZE + 1) . "... (Carrier IDs " . $batchIds[0] . " to " . end($batchIds) . ")\n";

            // Step 2c: Run the complex filtering query ONLY on the current small batch.
            $eligibleCarriersSql = "
                SELECT
                    c.id AS carrier_id,
                    (SELECT u.id FROM users u WHERE u.entity_id = c.id AND u.entity_type = 'carrier' AND u.is_active = 1 LIMIT 1) AS user_id
                FROM
                    carriers c
                LEFT JOIN facility_carrier_preferences fcp ON c.id = fcp.carrier_id
                    AND fcp.facility_id = ?
                    AND fcp.preference_type = 'blacklisted'
                LEFT JOIN trip_bidding_lockouts tblo ON c.id = tblo.carrier_id
                    AND tblo.trip_id = ?
                LEFT JOIN bids b ON c.id = b.carrier_id
                    AND b.trip_id = ?
                WHERE
                    c.id IN ($placeholders)
                    AND fcp.carrier_id IS NULL
                    AND tblo.carrier_id IS NULL
                    AND b.carrier_id IS NULL
                GROUP BY c.id
                HAVING user_id IS NOT NULL
            ";

            $params = array_merge([$trip['facility_id'], $tripId, $tripId], $batchIds);
            $eligibleCarriersInBatch = $db->fetchAll($eligibleCarriersSql, $params);

            if (empty($eligibleCarriersInBatch)) {
                continue; // No eligible carriers in this batch, move to the next.
            }

            // --- Step 3: Loop through the eligible carriers found in THIS BATCH ---
            foreach ($eligibleCarriersInBatch as $carrier) {
                if (rand(1, BIDDING_CHANCE_FACTOR) !== 1) {
                    continue;
                }

                $etaMinutes = rand(20, 120);
                $etaDateTime = new DateTime("now", new DateTimeZone('UTC'));
                $etaDateTime->modify("+{$etaMinutes} minutes");
                $utcEtaString = $etaDateTime->format('Y-m-d H:i:s');

                try {
                    $tripService->placeBidForSystem(
                        $tripId, $trip['bidding_closes_at'], $carrier['carrier_id'], $carrier['user_id'], $utcEtaString
                    );
                    echo "    -> SUCCESS: Carrier ID {$carrier['carrier_id']} bid.\n";
                    $totalBidsPlaced++;
                } catch (Exception $e) {
                    echo "    -> ERROR for Carrier ID {$carrier['carrier_id']}: " . $e->getMessage() . "\n";
                }
            }
        }
        echo " -> Finished processing for Trip ID {$tripId}. Placed {$totalBidsPlaced} bid(s).\n";
    }

} catch (Exception $e) {
    file_put_contents('php://stderr', "Auto Bidder Script Failed: " . $e->getMessage() . "\n");
    exit(1);
}

echo "\nAuto Bidder Cron Job Finished.\n";
exit(0);