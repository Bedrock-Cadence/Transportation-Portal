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

        // Find all active carriers who are NOT blacklisted by the facility,
        // NOT locked out from this specific trip, and have NOT already placed a bid.
        $eligibleCarriersSql = "
            SELECT u.id as user_id, u.entity_id as carrier_id
            FROM users u
            WHERE u.entity_type = 'carrier'
              AND u.is_active = 1
              -- Exclude blacklisted carriers
              AND NOT EXISTS (
                  SELECT 1 FROM facility_carrier_preferences
                  WHERE facility_id = :facility_id AND carrier_id = u.entity_id AND preference_type = 'blacklisted'
              )
              -- Exclude carriers locked out from this trip
              AND NOT EXISTS (
                  SELECT 1 FROM trip_bidding_lockouts
                  WHERE trip_id = :trip_id AND carrier_id = u.entity_id
              )
              -- Exclude carriers who have already bid
              AND NOT EXISTS (
                  SELECT 1 FROM bids
                  WHERE trip_id = :trip_id AND carrier_id = u.entity_id
              )
        ";

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

            // Generate a random ETA between 20 and 120 minutes from now
            $etaMinutes = rand(20, 120);
            $etaDateTime = new DateTime("now", new DateTimeZone(USER_TIMEZONE)); // Assumes USER_TIMEZONE is defined
            $etaDateTime->modify("+{$etaMinutes} minutes");
            $localEtaString = $etaDateTime->format('Y-m-d H:i:s');

            try {
                // Call the dedicated service method to place the bid
                $tripService->placeBidForSystem(
                    $tripId,
                    $trip['bidding_closes_at'],
                    $carrier['carrier_id'],
                    $carrier['user_id'],
                    $localEtaString
                );
                echo " -> SUCCESS: Carrier ID {$carrier['carrier_id']} bid with ETA: {$localEtaString}\n";
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