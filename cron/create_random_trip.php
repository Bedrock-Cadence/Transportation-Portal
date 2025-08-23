<?php
// FILE: /public_html/portal/cron/create_random_trip.php

// This script is intended to be run from the command line by a cron job.
//if (php_sapi_name() !== 'cli') {
    //die("Access Denied: This script can only be run from the command line.");
//}

require_once __DIR__ . '/../../../app/init.php';

// --- CONFIGURATION ---
// IMPORTANT: You must create a "system" or "cron" user in your database.

// This number controls the probability.
// On average, a trip will be created 1 out of every CHANCE_FACTOR runs.
// If the cron runs every minute, a value of 10 means about 1 trip every 10 minutes.
const CHANCE_FACTOR = 10;
// --- END CONFIGURATION ---

// Decide if we should create a trip this time
//if (rand(1, CHANCE_FACTOR) !== 1) {
    //echo "Not creating a trip this time.\n";
    //exit;
//}

// *** NEW: Get a random facility from the database ***
try {
    $db = Database::getInstance();
    // This query finds a random, active user whose entity_type is 'facility'
    // and returns both their ID and their facility's ID (entity_id).
    $sql = "SELECT id AS user_id, entity_id AS facility_id 
            FROM users 
            WHERE entity_type = 'facility' AND is_active = 1 
            ORDER BY RAND() 
            LIMIT 1";
            
    $creator = $db->fetch($sql);

    if (!$creator || empty($creator['user_id']) || empty($creator['facility_id'])) {
        throw new Exception("No active facility users found in the database to create a trip.");
    }

    $systemUserId = (int)$creator['user_id'];
    $facilityId = (int)$creator['facility_id'];

} catch (Exception $e) {
    file_put_contents('php://stderr', "Database Error: Could not fetch a random facility user. " . $e->getMessage() . "\n");
    exit(1); // Exit with an error code
}
// *** END NEW SECTION ***


/**
 * Generates a random set of valid trip data.
 * @return array
 */
function generateMockTripData(): array {
    $firstNames = ['John', 'Jane', 'Peter', 'Mary', 'David', 'Susan'];
    $lastNames = ['Smith', 'Doe', 'Jones', 'Williams', 'Brown', 'Davis'];
    $streets = ['123 Main St', '456 Oak Ave', '789 Pine Ln', '101 Maple Dr', '212 Elm Ct'];
    $cities = ['Anytown', 'Springfield', 'Riverdale', 'Metropolis', 'Gotham'];
    $states = ['TX', 'CA', 'NY', 'FL', 'IL'];

    // Generate a valid random DOB between 20 and 80 years ago
    $dobTimestamp = time() - rand(20 * 365 * 24 * 60 * 60, 80 * 365 * 24 * 60 * 60);

    return [
        'patient_first_name' => $firstNames[array_rand($firstNames)],
        'patient_last_name' => $lastNames[array_rand($lastNames)],
        'patient_dob' => date('Y-m-d', $dobTimestamp),
        'patient_ssn' => sprintf('%04d', rand(1000, 9999)),
        'patient_weight' => rand(120, 350),
        'patient_height' => rand(60, 78),
        'primary_diagnosis' => 'General Observation',
        'isolation_precautions' => 'None',
        'asap_checkbox' => 1, // Make it an ASAP trip for simplicity
        'pickup_time' => null,
        'appointment_time' => null,

        'pickup_address_street' => $streets[array_rand($streets)],
        'pickup_address_city' => $cities[array_rand($cities)],
        'pickup_address_state' => $states[array_rand($states)],
        'pickup_address_zip' => sprintf('%05d', rand(10000, 99999)),

        'dropoff_address_street' => $streets[array_rand($streets)],
        'dropoff_address_city' => $cities[array_rand($cities)],
        'dropoff_address_state' => $states[array_rand($states)],
        'dropoff_address_zip' => sprintf('%05d', rand(10000, 99999)),
    ];
}

echo "Attempting to create a new trip for Facility ID: {$facilityId}...\n";

try {
    $tripService = new TripService();
    $mockData = generateMockTripData();

    // Pass the random facility ID to the service method
    $tripUuid = $tripService->createTripForCron($mockData, SYSTEM_USER_ID, $facilityId);

    echo "Successfully created trip with UUID: {$tripUuid}\n";

} catch (Exception $e) {
    // Log the error to stderr for cron logs
    file_put_contents('php://stderr', "Error creating trip: " . $e->getMessage() . "\n");
    echo "Failed to create trip. See error log for details.\n";
    exit(1); // Exit with an error code
}

exit(0); // Success