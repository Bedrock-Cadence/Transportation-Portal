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
    ['street' => '123 Lone Star Blvd', 'city' => 'Austin', 'state' => 'TX', 'zip' => '78701'],
    ['street' => '456 Bluebonnet Dr', 'city' => 'Dallas', 'state' => 'TX', 'zip' => '75201'],
    ['street' => '789 Alamo Rd', 'city' => 'San Antonio', 'state' => 'TX', 'zip' => '78205'],
    ['street' => '101 Longhorn Way', 'city' => 'College Station', 'state' => 'TX', 'zip' => '77840'],
    ['street' => '212 Oil Rig Ave', 'city' => 'Houston', 'state' => 'TX', 'zip' => '77002'],
    ['street' => '323 Pecan Tree Ln', 'city' => 'Corpus Christi', 'state' => 'TX', 'zip' => '78401'],
    ['street' => '545 Brazos River Rd', 'city' => 'Waco', 'state' => 'TX', 'zip' => '76701'],
    ['street' => '767 Cowboy St', 'city' => 'Fort Worth', 'state' => 'TX', 'zip' => '76102'],
    ['street' => '989 Mesquite Blvd', 'city' => 'El Paso', 'state' => 'TX', 'zip' => '79901'],
    ['street' => '111 Armadillo Alley', 'city' => 'Amarillo', 'state' => 'TX', 'zip' => '79101'],
    ['street' => '222 Big Bend Way', 'city' => 'Alpine', 'state' => 'TX', 'zip' => '79830'],
    ['street' => '333 Cattle Dr', 'city' => 'Lubbock', 'state' => 'TX', 'zip' => '79401'],
    ['street' => '444 Chisholm Trail', 'city' => 'Denton', 'state' => 'TX', 'zip' => '76201'],
    ['street' => '555 Cypress Creek Rd', 'city' => 'San Marcos', 'state' => 'TX', 'zip' => '78666'],
    ['street' => '666 Frio River Dr', 'city' => 'Uvalde', 'state' => 'TX', 'zip' => '78801'],
    ['street' => '777 Goliad Pl', 'city' => 'Victoria', 'state' => 'TX', 'zip' => '77901'],
    ['street' => '888 Hill Country Rd', 'city' => 'Fredericksburg', 'state' => 'TX', 'zip' => '78624'],
    ['street' => '999 Juniper Creek Ct', 'city' => 'Junction', 'state' => 'TX', 'zip' => '76849'],
    ['street' => '100 Live Oak St', 'city' => 'Galveston', 'state' => 'TX', 'zip' => '77550'],
    ['street' => '102 Magnolia Blvd', 'city' => 'Laredo', 'state' => 'TX', 'zip' => '78040'],
    ['street' => '104 Maverick Trail', 'city' => 'Abilene', 'state' => 'TX', 'zip' => '79601'],
    ['street' => '106 Mockingbird Ln', 'city' => 'Midland', 'state' => 'TX', 'zip' => '79701'],
    ['street' => '108 Palo Duro Pl', 'city' => 'Canyon', 'state' => 'TX', 'zip' => '79015'],
    ['street' => '110 Panhandle Rd', 'city' => 'Pampa', 'state' => 'TX', 'zip' => '79065'],
    ['street' => '112 Pine Forest Way', 'city' => 'Conroe', 'state' => 'TX', 'zip' => '77301'],
    ['street' => '114 Ranchero Dr', 'city' => 'Odessa', 'state' => 'TX', 'zip' => '79760'],
    ['street' => '116 Rio Grande Dr', 'city' => 'Del Rio', 'state' => 'TX', 'zip' => '78840'],
    ['street' => '118 San Jacinto Ct', 'city' => 'Pasadena', 'state' => 'TX', 'zip' => '77501'],
    ['street' => '120 Shiner Bock Ln', 'city' => 'Shiner', 'state' => 'TX', 'zip' => '77984'],
    ['street' => '122 Southfork Ranch Rd', 'city' => 'Plano', 'state' => 'TX', 'zip' => '75023'],
    ['street' => '124 Star Spangled Banner St', 'city' => 'Fort Hood', 'state' => 'TX', 'zip' => '76544'],
    ['street' => '126 Sunflower Dr', 'city' => 'Wichita Falls', 'state' => 'TX', 'zip' => '76301'],
    ['street' => '128 Texas Bluebonnet St', 'city' => 'Ennis', 'state' => 'TX', 'zip' => '75119'],
    ['street' => '130 The Riverwalk', 'city' => 'San Antonio', 'state' => 'TX', 'zip' => '78205'],
    ['street' => '132 Trinity River Rd', 'city' => 'Arlington', 'state' => 'TX', 'zip' => '76011'],
    ['street' => '134 Guadalupe River Way', 'city' => 'New Braunfels', 'state' => 'TX', 'zip' => '78130'],
    ['street' => '136 Comal Springs Cir', 'city' => 'New Braunfels', 'state' => 'TX', 'zip' => '78130'],
    ['street' => '138 Fiesta Dr', 'city' => 'El Paso', 'state' => 'TX', 'zip' => '79901'],
    ['street' => '140 Pecan St', 'city' => 'Sherman', 'state' => 'TX', 'zip' => '75090'],
    ['street' => '142 Cypress Ave', 'city' => 'McAllen', 'state' => 'TX', 'zip' => '78501'],
    ['street' => '144 Cactus Loop', 'city' => 'San Angelo', 'state' => 'TX', 'zip' => '76901'],
    ['street' => '146 Mesquite St', 'city' => 'Grand Prairie', 'state' => 'TX', 'zip' => '75050'],
    ['street' => '148 Cedar Bend', 'city' => 'Denton', 'state' => 'TX', 'zip' => '76201'],
    ['street' => '150 Oak Creek Rd', 'city' => 'Kerrville', 'state' => 'TX', 'zip' => '78028'],
    ['street' => '152 Sagebrush Ct', 'city' => 'Plano', 'state' => 'TX', 'zip' => '75024'],
    ['street' => '154 Prairie Dog Pl', 'city' => 'Lubbock', 'state' => 'TX', 'zip' => '79401'],
    ['street' => '156 Longhorn Ave', 'city' => 'Austin', 'state' => 'TX', 'zip' => '78701'],
    ['street' => '158 San Jacinto St', 'city' => 'Houston', 'state' => 'TX', 'zip' => '77002'],
    ['street' => '160 Red River Rd', 'city' => 'Paris', 'state' => 'TX', 'zip' => '75460'],
    ['street' => '162 Cowboy Ct', 'city' => 'Amarillo', 'state' => 'TX', 'zip' => '79101'],
    ['street' => '123 Bayou St', 'city' => 'New Orleans', 'state' => 'LA', 'zip' => '70112'],
    ['street' => '456 Mardi Gras Blvd', 'city' => 'Baton Rouge', 'state' => 'LA', 'zip' => '70801'],
    ['street' => '789 Cajun Ct', 'city' => 'Lafayette', 'state' => 'LA', 'zip' => '70501'],
    ['street' => '101 Gumbo Ln', 'city' => 'Shreveport', 'state' => 'LA', 'zip' => '71101'],
    ['street' => '212 Riverboat Rd', 'city' => 'Lake Charles', 'state' => 'LA', 'zip' => '70601'],
    ['street' => '323 Creole Rd', 'city' => 'Metairie', 'state' => 'LA', 'zip' => '70001'],
    ['street' => '545 Alligator Alley', 'city' => 'Alexandria', 'state' => 'LA', 'zip' => '71301'],
    ['street' => '767 Voodoo Dr', 'city' => 'Slidell', 'state' => 'LA', 'zip' => '70458'],
    ['street' => '989 Bourbon St', 'city' => 'New Orleans', 'state' => 'LA', 'zip' => '70116'],
    ['street' => '111 Atchafalaya Way', 'city' => 'Morgan City', 'state' => 'LA', 'zip' => '70380'],
    ['street' => '222 Cypress Swamp Ln', 'city' => 'Hammond', 'state' => 'LA', 'zip' => '70401'],
    ['street' => '333 Delta Dr', 'city' => 'Monroe', 'state' => 'LA', 'zip' => '71201'],
    ['street' => '444 French Quarter Rd', 'city' => 'Gretna', 'state' => 'LA', 'zip' => '70053'],
    ['street' => '555 Jazz Fest Ave', 'city' => 'New Orleans', 'state' => 'LA', 'zip' => '70119'],
    ['street' => '666 King Cake Ct', 'city' => 'Kenner', 'state' => 'LA', 'zip' => '70062'],
    ['street' => '777 Magnolia Pl', 'city' => 'Bossier City', 'state' => 'LA', 'zip' => '71111'],
    ['street' => '888 Pelican Point', 'city' => 'Mandeville', 'state' => 'LA', 'zip' => '70448'],
    ['street' => '999 Praline St', 'city' => 'Covington', 'state' => 'LA', 'zip' => '70433'],
    ['street' => '100 Red Stick Blvd', 'city' => 'Baton Rouge', 'state' => 'LA', 'zip' => '70801'],
    ['street' => '102 Saint Charles Ave', 'city' => 'New Orleans', 'state' => 'LA', 'zip' => '70130'],
    ['street' => '104 Shrimp Boat Ln', 'city' => 'Houma', 'state' => 'LA', 'zip' => '70360'],
    ['street' => '106 Swamp Fox Trail', 'city' => 'Thibodaux', 'state' => 'LA', 'zip' => '70301'],
    ['street' => '108 Tabasco Ct', 'city' => 'Avery Island', 'state' => 'LA', 'zip' => '70513'],
    ['street' => '110 Cane River Way', 'city' => 'Natchitoches', 'state' => 'LA', 'zip' => '71457'],
    ['street' => '112 Zydeco Dr', 'city' => 'Opelousas', 'state' => 'LA', 'zip' => '70570'],
    ['street' => '114 Bayou Teche Rd', 'city' => 'St. Martinville', 'state' => 'LA', 'zip' => '70582'],
    ['street' => '116 Plantation Dr', 'city' => 'Plaquemine', 'state' => 'LA', 'zip' => '70764'],
    ['street' => '118 Cane Field Ln', 'city' => 'Lafayette', 'state' => 'LA', 'zip' => '70501'],
    ['street' => '120 Alligator Bayou Rd', 'city' => 'St. Amant', 'state' => 'LA', 'zip' => '70774'],
    ['street' => '122 Turtle Bayou Rd', 'city' => 'Pierre Part', 'state' => 'LA', 'zip' => '70339'],
    ['street' => '124 St. Tammany Pkwy', 'city' => 'Covington', 'state' => 'LA', 'zip' => '70433'],
    ['street' => '126 Sabine River Dr', 'city' => 'Many', 'state' => 'LA', 'zip' => '71449'],
    ['street' => '128 Lake Pontchartrain Blvd', 'city' => 'Slidell', 'state' => 'LA', 'zip' => '70458'],
    ['street' => '130 Evangeline Blvd', 'city' => 'New Iberia', 'state' => 'LA', 'zip' => '70560'],
    ['street' => '132 Jean Lafitte Pkwy', 'city' => 'Jean Lafitte', 'state' => 'LA', 'zip' => '70067'],
    ['street' => '134 Cajun Coast Ct', 'city' => 'Morgan City', 'state' => 'LA', 'zip' => '70380'],
    ['street' => '136 Vacherie Plantation Rd', 'city' => 'Vacherie', 'state' => 'LA', 'zip' => '70090'],
    ['street' => '138 Swamp Fox Ct', 'city' => 'Kenner', 'state' => 'LA', 'zip' => '70062'],
    ['street' => '123 Sooner Blvd', 'city' => 'Norman', 'state' => 'OK', 'zip' => '73069'],
    ['street' => '456 Tornado Ave', 'city' => 'Oklahoma City', 'state' => 'OK', 'zip' => '73102'],
    ['street' => '789 Cowboy St', 'city' => 'Stillwater', 'state' => 'OK', 'zip' => '74074'],
    ['street' => '101 Red Earth Dr', 'city' => 'Tulsa', 'state' => 'OK', 'zip' => '74103'],
    ['street' => '212 Cherokee Pl', 'city' => 'Lawton', 'state' => 'OK', 'zip' => '73501'],
    ['street' => '323 Thunder Blvd', 'city' => 'Edmond', 'state' => 'OK', 'zip' => '73003'],
    ['street' => '545 Prairie Dog Ln', 'city' => 'Enid', 'state' => 'OK', 'zip' => '73701'],
    ['street' => '767 Oil Field Rd', 'city' => 'Midwest City', 'state' => 'OK', 'zip' => '73110'],
    ['street' => '989 Boomer Sooner Dr', 'city' => 'Norman', 'state' => 'OK', 'zip' => '73069'],
    ['street' => '111 Oklahoma River Ct', 'city' => 'Oklahoma City', 'state' => 'OK', 'zip' => '73104'],
    ['street' => '222 Native Way', 'city' => 'Bartlesville', 'state' => 'OK', 'zip' => '74003'],
    ['street' => '333 Cowboy Way', 'city' => 'Stillwater', 'state' => 'OK', 'zip' => '74074'],
    ['street' => '444 Sooner Trail', 'city' => 'Tulsa', 'state' => 'OK', 'zip' => '74105'],
    ['street' => '555 Red Dirt Rd', 'city' => 'Ada', 'state' => 'OK', 'zip' => '74820'],
    ['street' => '666 Black Mesa Ln', 'city' => 'Boise City', 'state' => 'OK', 'zip' => '73933'],
    ['street' => '777 Pioneer Blvd', 'city' => 'Ardmore', 'state' => 'OK', 'zip' => '73401'],
    ['street' => '888 Dust Bowl Dr', 'city' => 'Guymon', 'state' => 'OK', 'zip' => '73942'],
    ['street' => '999 Indian Hills Rd', 'city' => 'Norman', 'state' => 'OK', 'zip' => '73071'],
    ['street' => '100 Sooner Rd', 'city' => 'Tulsa', 'state' => 'OK', 'zip' => '74104'],
    ['street' => '102 Bricktown St', 'city' => 'Oklahoma City', 'state' => 'OK', 'zip' => '73129']
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


    // 1. Get two different random addresses
    $pickup = getRandomAddress();
    do {
        $dropoff = getRandomAddress();
    } while ($pickup === $dropoff);

    // Generate a valid random DOB between 20 and 80 years ago
    $dobTimestamp = time() - rand(1 * 365 * 24 * 60 * 60, 106 * 365 * 24 * 60 * 60);

    return [
        'patient_first_name' => $firstNames[array_rand($firstNames)],
        'patient_last_name' => $lastNames[array_rand($lastNames)],
        'patient_dob' => date('Y-m-d', $dobTimestamp),
        'patient_ssn' => sprintf('%04d', rand(1001, 9999)),
        'patient_weight' => rand(120, 350),
        'patient_height' => rand(60, 78),
        'primary_diagnosis' => $primary_dx[array_rand($primary_dx)],
        'isolation_precautions' => 'None',
        'asap_checkbox' => 1,
        'pickup_time' => null,
        'appointment_time' => null,

        // 2. Use the complete, valid address data
        'pickup_address_street' => $pickup['street'],
        'pickup_address_city' => $pickup['city'],
        'pickup_address_state' => $pickup['state'],
        'pickup_address_zip' => $pickup['zip'],

        'dropoff_address_street' => $dropoff['street'],
        'dropoff_address_city' => $dropoff['city'],
        'dropoff_address_state' => $dropoff['state'],
        'dropoff_address_zip' => $dropoff['zip'],
    ];
    // --- END OF FIX ---
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