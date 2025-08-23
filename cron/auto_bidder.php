<?php
// FILE: /public_html/portal/cron/auto_bidder.php
// PURPOSE: This script automatically places bids on behalf of carriers for development purposes.

// This script is intended to be run from the command line by a cron job.
//if (php_sapi_name() !== 'cli') {
    //die("Access Denied: This script can only be run from the command line.");
//}

require_once __DIR__ . '/../../../app/init.php';

// --- CONFIGURATION ---
// This number controls the probability of a carrier placing a bid.
// A value of 2 means a carrier has a 1 in 2 (50%) chance of bidding on an available trip.
const BIDDING_CHANCE_FACTOR = 2;
// --- END CONFIGURATION ---

echo "Auto Bidder Cron Job Started: " . date('Y-m-d H:i:s') . "\n";

try {
    $db = Database::getInstance();
    $tripService = new TripService();

    // --- Step 1: Find all trips that are currently open for bidding ---
    $openTripsSql = "SELECT id, facility_id, bidding_closes_at FROM trips WHERE status = 'bidding' AND bidding_closes_at > NOW()";
    $openTrips = $db->fetchAll($openTripsSql);

    if (empty($openTrips)) {
        echo "No open trips found for bidding.\n";
        exit(0);
    }

    echo "Found " . count($openTrips) . " open trip(s) for bidding.\n";

    // --- Step 2: Loop through each open trip and find eligible carriers ---
    foreach ($openTrips as $trip) {
        $tripId = $trip['id'];
        echo "\nProcessing Trip ID: {$tripId}\n";

        // --- OPTIMIZED QUERY START ---
        // This query is optimized to start from the `carriers` table first, which is a much smaller dataset
        // than the `users` table. It finds eligible carriers and then gets ONE active user for each.
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
                AND fcp.carrier_id IS NULL -- Ensures the carrier is NOT blacklisted
                AND tblo.carrier_id IS NULL -- Ensures the carrier is NOT locked out for this trip
                AND b.carrier_id IS NULL    -- Ensures the carrier has NOT already bid
            GROUP BY
                c.id
            HAVING
                user_id IS NOT NULL -- Ensures we only get carriers that have at least one active user
        ";
        // --- OPTIMIZED QUERY END ---

        $eligibleCarriers = $db->fetchAll($eligibleCarriersSql, [
            ':facility_id' => $trip['facility_id'],
            ':trip_id' => $tripId
        ]);

        if (empty($eligibleCarriers)) {
            echo " -> No eligible carriers found for this trip.\n";
            continue;
        }

        echo " -> Found " . count($eligibleCarriers) . " eligible carrier(s).\n";

        // --- Step 3: Loop through eligible carriers and randomly place bids ---
        foreach ($eligibleCarriers as $carrier) {
            // Use the chance factor to decide if this carrier will bid
            if (rand(1, BIDDING_CHANCE_FACTOR) !== 1) {
                echo " -> Carrier ID {$carrier['carrier_id']} will not bid this time.\n";
                continue;
            }

            // --- MODIFICATION START ---
            // Generate a random ETA between 20 and 120 minutes from now.
            // Use a hardcoded timezone ('UTC') to avoid issues with undefined constants in the cron environment.
            $etaMinutes = rand(20, 120);
            $etaDateTime = new DateTime("now", new DateTimeZone('UTC'));
            $etaDateTime->modify("+{$etaMinutes} minutes");
            $utcEtaString = $etaDateTime->format('Y-m-d H:i:s');
            // --- MODIFICATION END ---

            try {
                // Call the dedicated service method to place the bid
                $tripService->placeBidForSystem(
                    $tripId,
                    $trip['bidding_closes_at'],
                    $carrier['carrier_id'],
                    $carrier['user_id'],
                    $utcEtaString // Pass the reliable UTC time string
                );
                echo " -> SUCCESS: Carrier ID {$carrier['carrier_id']} bid with ETA: {$utcEtaString} (UTC)\n";
            } catch (Exception $e) {
                echo " -> ERROR placing bid for Carrier ID {$carrier['carrier_id']}: " . $e->getMessage() . "\n";
            }
        }
    }

} catch (Exception $e) {
    file_put_contents('php://stderr', "Auto Bidder Script Failed: " . $e->getMessage() . "\n");
    exit(1); // Exit with an error code
}

echo "\nAuto Bidder Cron Job Finished.\n";
exit(0); // Success