<?php
// FILE: /public_html/portal/cron/auto_bidder.php
// PURPOSE: This script automatically places bids on behalf of carriers for development purposes.

// Prevent the script from timing out on long-running queries.
set_time_limit(0);

// This script is intended to be run from the command line by a cron job.
//if (php_sapi_name() !== 'cli') {
    //die("Access Denied: This script can only be run from the command line.");
//}

require_once __DIR__ . '/../../../app/init.php';

// --- SCRIPT INITIALIZATION & ERROR REPORTING ---
// Force display of all errors for robust debugging in the cron environment.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- CONFIGURATION ---
const BIDDING_CHANCE_FACTOR = 2;
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

    // --- Step 2: Loop through each open trip and find eligible carriers ---
    foreach ($openTrips as $trip) {
        $tripId = $trip['id'];
        echo "\nProcessing Trip ID: {$tripId}\n";

        // --- MODIFICATION: PREVENT DATABASE LOCKS ---
        // By setting the isolation level to READ UNCOMMITTED, this query will not wait for other
        // transactions to finish, preventing it from hanging on locked tables.
        $db->execute("SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;");

        $eligibleCarriersSql = "
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
            GROUP BY
                c.id
            HAVING
                user_id IS NOT NULL
        ";

        echo " -> Step 2a: Preparing to fetch eligible carriers...\n";
        $eligibleCarriers = $db->fetchAll($eligibleCarriersSql, [
            ':facility_id' => $trip['facility_id'],
            ':trip_id' => $tripId
        ]);
        echo " -> Step 2b: Finished fetching carriers.\n";

        // It's good practice to reset the isolation level after the query.
        $db->execute("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ;");


        if (empty($eligibleCarriers)) {
            echo " -> No eligible carriers found for this trip.\n";
            continue;
        }

        echo " -> Found " . count($eligibleCarriers) . " eligible carrier(s).\n";

        // --- Step 3: Loop through eligible carriers and randomly place bids ---
        foreach ($eligibleCarriers as $carrier) {
            if (rand(1, BIDDING_CHANCE_FACTOR) !== 1) {
                // echo " -> Carrier ID {$carrier['carrier_id']} will not bid this time.\n"; // Optional: uncomment for very verbose logging
                continue;
            }

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
                echo " -> SUCCESS: Carrier ID {$carrier['carrier_id']} bid with ETA: {$utcEtaString} (UTC)\n";
            } catch (Exception $e) {
                echo " -> ERROR placing bid for Carrier ID {$carrier['carrier_id']}: " . $e->getMessage() . "\n";
            }
        }
    }

} catch (Exception $e) {
    file_put_contents('php://stderr', "Auto Bidder Script Failed: " . $e->getMessage() . "\n");
    exit(1);
}

echo "\nAuto Bidder Cron Job Finished.\n";
exit(0);