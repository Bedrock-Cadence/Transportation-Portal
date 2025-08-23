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


function getRandomAddress(): array {
    $addresses = [
        ['street' => '110 E 2nd St', 'city' => 'Austin', 'state' => 'TX', 'zip' => '78701'],
        ['street' => '601 N Lamar Blvd', 'city' => 'Austin', 'state' => 'TX', 'zip' => '78703'],
        ['street' => '300 W Martin Luther King Jr Blvd', 'city' => 'Austin', 'state' => 'TX', 'zip' => '78701'],
        ['street' => '231 W 3rd St', 'city' => 'Dallas', 'state' => 'TX', 'zip' => '75208'],
        ['street' => '2200 N Lamar St', 'city' => 'Dallas', 'state' => 'TX', 'zip' => '75202'],
        ['street' => '1300 Robert B Cullum Blvd', 'city' => 'Dallas', 'state' => 'TX', 'zip' => '75210'],
        ['street' => '1 AT&T Way', 'city' => 'Arlington', 'state' => 'TX', 'zip' => '76011'],
        ['street' => '1201 Houston St', 'city' => 'Fort Worth', 'state' => 'TX', 'zip' => '76102'],
        ['street' => '301 W Bagdad Ave', 'city' => 'Round Rock', 'state' => 'TX', 'zip' => '78664'],
        ['street' => '500 Crawford St', 'city' => 'Houston', 'state' => 'TX', 'zip' => '77002'],
        ['street' => '1515 Hermann Dr', 'city' => 'Houston', 'state' => 'TX', 'zip' => '77004'],
        ['street' => '301 E Crockett St', 'city' => 'San Antonio', 'state' => 'TX', 'zip' => '78205'],
        ['street' => '100 Montana St', 'city' => 'San Antonio', 'state' => 'TX', 'zip' => '78203'],
        ['street' => '200 E Grayson St', 'city' => 'San Antonio', 'state' => 'TX', 'zip' => '78215'],
        ['street' => '1001 E Oklahoma Ave', 'city' => 'Norman', 'state' => 'OK', 'zip' => '73071'],
        ['street' => '100 W Reno Ave', 'city' => 'Oklahoma City', 'state' => 'OK', 'zip' => '73102'],
        ['street' => '200 S Denver Ave W', 'city' => 'Tulsa', 'state' => 'OK', 'zip' => '74103'],
        ['street' => '1501 Dave Dixon Dr', 'city' => 'New Orleans', 'state' => 'LA', 'zip' => '70113'],
        ['street' => '701 N Rampart St', 'city' => 'New Orleans', 'state' => 'LA', 'zip' => '70116'],
        ['street' => 'N Stadium Rd', 'city' => 'Baton Rouge', 'state' => 'LA', 'zip' => '70802'],
    ];

    return $addresses[array_rand($addresses)];
}

/**
 * Generates a random set of valid trip data.
 * @return array
 */
function generateMockTripData(): array {
$firstNames = [
    'Alexander', 'Benjamin', 'Christopher', 'Daniel', 'Ethan', 'Frederick', 'George', 'Henry', 'Isaac', 'Jacob',
    'Kevin', 'Liam', 'Matthew', 'Noah', 'Oliver', 'Patrick', 'Quinn', 'Ryan', 'Samuel', 'Thomas',
    'William', 'Xavier', 'Yusuf', 'Zachary', 'Aaron', 'Adam', 'Brian', 'Charles', 'Derek', 'Eric',
    'Frank', 'Gary', 'Harold', 'Ivan', 'James', 'Justin', 'Keith', 'Leo', 'Michael', 'Nathan',
    'Owen', 'Paul', 'Richard', 'Robert', 'Steven', 'Timothy', 'Vincent', 'Walter', 'Wayne', 'Mark',
    'Andrew', 'Anthony', 'Caleb', 'Cole', 'Dennis', 'Edward', 'Felix', 'Gordon', 'Harrison', 'Ian',
    'Jack', 'Jeremy', 'Jordan', 'Kyle', 'Lucas', 'Mason', 'Nicholas', 'Oscar', 'Philip', 'Russell',
    'Scott', 'Stephen', 'Todd', 'Trevor', 'Victor', 'Warren', 'Zachary', 'Zane', 'Barry', 'Bruce',
    'Adrian', 'Alan', 'Albert', 'Arthur', 'Austin', 'Brandon', 'Cameron', 'Carl', 'Carlos', 'Chad',
    'Curtis', 'Damon', 'Douglas', 'Dwight', 'Edward', 'Felix', 'Gabriel', 'Greg', 'Harrison', 'Harvey',
    'Hector', 'Hugh', 'Isaac', 'Jeffery', 'Jerry', 'Joel', 'Jonathan', 'Joshua', 'Julian', 'Kenneth',
    'Kurt', 'Lawrence', 'Leonard', 'Lewis', 'Louis', 'Marcus', 'Martin', 'Max', 'Mitchell', 'Ned',
    'Neil', 'Omar', 'Pablo', 'Perry', 'Peter', 'Randy', 'Raymond', 'Rex', 'Ron', 'Roy'];    
$lastNames = [
    'Smith', 'Johnson', 'Williams', 'Jones', 'Brown', 'Davis', 'Miller', 'Wilson', 'Moore', 'Taylor',
    'Anderson', 'Thomas', 'Jackson', 'White', 'Harris', 'Martin', 'Thompson', 'Garcia', 'Martinez', 'Robinson',
    'Clark', 'Rodriguez', 'Lewis', 'Lee', 'Walker', 'Hall', 'Allen', 'Young', 'Hernandez', 'King',
    'Wright', 'Lopez', 'Hill', 'Scott', 'Green', 'Adams', 'Baker', 'Nelson', 'Carter', 'Perez',
    'Evans', 'Turner', 'Cruz', 'Morris', 'Russell', 'Morgan', 'Hughes', 'Price', 'Bell', 'Coleman',
    'Bailey', 'Edwards', 'Stewart', 'Flores', 'Cooper', 'Ramirez', 'Cox', 'Howard', 'Ward', 'Torres',
    'Peterson', 'Gray', 'Ramirez', 'Cook', 'Brooks', 'Phillips', 'Watson', 'Sanders', 'Bennett', 'Russell',
    'Hayes', 'Powell', 'Barnes', 'Ross', 'Henderson', 'Coleman', 'Simmons', 'Patterson', 'Brooks', 'Reed',
    'Hughes', 'Price', 'Bell', 'Coleman', 'Bailey', 'Edwards', 'Stewart', 'Flores', 'Cooper', 'Ramirez',
    'Cox', 'Howard', 'Ward', 'Torres', 'Peterson', 'Gray', 'Ramirez', 'Cook', 'Brooks', 'Phillips',
    'Watson', 'Sanders', 'Bennett', 'Russell', 'Hayes', 'Powell', 'Barnes', 'Ross', 'Henderson', 'Coleman',
    'Simmons', 'Patterson', 'Brooks', 'Reed', 'Morgan', 'Hughes', 'Price', 'Bell', 'Coleman', 'Bailey',
    'Edwards', 'Stewart', 'Flores', 'Cooper', 'Ramirez', 'Cox', 'Howard', 'Ward', 'Torres', 'Peterson',
    'Gray', 'Ramirez', 'Cook', 'Brooks', 'Phillips', 'Watson', 'Sanders', 'Bennett', 'Russell', 'Hayes'];
    $primary_dx = [
    'Hypertension', 'Diabetes Mellitus', 'Coronary Artery Disease', 'Asthma', 'Chronic Obstructive Pulmonary Disease',
    'Depression', 'Anxiety Disorder', 'Arthritis', 'Hyperlipidemia', 'Obesity',
    'Gastroesophageal Reflux Disease', 'Irritable Bowel Syndrome', 'Migraine', 'Allergies', 'Chronic Kidney Disease',
    'Thyroid Disease', 'Alzheimers Disease', 'Parkinsons Disease', 'Multiple Sclerosis', 'Epilepsy',
    'Stroke', 'Schizophrenia', 'Bipolar Disorder', 'Cystic Fibrosis', 'Sickle Cell Anemia',
    'Lupus', 'Fibromyalgia', 'Chronic Fatigue Syndrome', 'Polycystic Ovary Syndrome', 'Endometriosis',
    'Urinary Tract Infection', 'Pneumonia', 'Bronchitis', 'Influenza', 'Common Cold',
    'Dementia', 'Celiac Disease', 'Crohns Disease', 'Ulcerative Colitis', 'Hepatitis',
    'HIV/AIDS', 'Mononucleosis', 'Tuberculosis', 'Meningitis', 'Psoriasis',
    'Eczema', 'Acne', 'Shingles', 'Gout', 'Kidney Stones',
    'Gallstones', 'Herniated Disc', 'Scoliosis', 'Carpal Tunnel Syndrome', 'Tinnitus',
    'Vertigo', 'Glaucoma', 'Cataracts', 'Macular Degeneration', 'Hearing Loss',
    'Sleep Apnea', 'Narcolepsy', 'Anemia', 'Appendicitis', 'Hypoglycemia',
    'Hyperglycemia', 'Sepsis', 'Anaphylaxis', 'Osteoporosis', 'Heart Failure'];

    // Generate a valid random DOB between 20 and 80 years ago
    $dobTimestamp = time() - rand(1 * 365 * 24 * 60 * 60, 106 * 365 * 24 * 60 * 60);

    return [
        'patient_first_name' => $firstNames[array_rand($firstNames)],
        'patient_last_name' => $lastNames[array_rand($lastNames)],
        'patient_dob' => date('Y-m-d', $dobTimestamp),
        'patient_ssn' => sprintf('%04d', rand(1000, 9999)),
        'patient_weight' => rand(120, 350),
        'patient_height' => rand(60, 78),
        'primary_diagnosis' => $primary_dx[array_rand($primary_dx)],
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

echo "Attempting to create a new trip for Facility ID: {$facilityId} by User ID: {$systemUserId}...\n";

try {
    $tripService = new TripService();
    $mockData = generateMockTripData();

    // *** NEW: Add the creator and facility IDs directly to the data payload ***
    $mockData['cron_user_id'] = $systemUserId;
    $mockData['cron_facility_id'] = $facilityId;

    // Call the simplified service method with just the data array
    $tripUuid = $tripService->createTripForCron($mockData);

    echo "Successfully created trip with UUID: {$tripUuid}\n";

} catch (Exception $e) {
    // Log the error to stderr for cron logs
    file_put_contents('php://stderr', "Error creating trip: " . $e->getMessage() . "\n");
    echo "Failed to create trip. See error log for details.\n";
    exit(1); // Exit with an error code
}

exit(0); // Success