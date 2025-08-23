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
    $streets = ['123 Lone Star Blvd', '456 Bluebonnet Dr', '789 Alamo Rd', '101 Longhorn Way', '212 Oil Rig Ave',
    '323 Pecan Tree Ln', '545 Brazos River Rd', '767 Cowboy St', '989 Mesquite Blvd', '111 Armadillo Alley',
    '222 Big Bend Way', '333 Cattle Dr', '444 Chisholm Trail', '555 Cypress Creek Rd', '666 Frio River Dr',
    '777 Goliad Pl', '888 Hill Country Rd', '999 Juniper Creek Ct', '100 Live Oak St', '102 Magnolia Blvd',
    '104 Maverick Trail', '106 Mockingbird Ln', '108 Palo Duro Pl', '110 Panhandle Rd', '112 Pine Forest Way',
    '114 Ranchero Dr', '116 Rio Grande Dr', '118 San Jacinto Ct', '120 Shiner Bock Ln', '122 Southfork Ranch Rd',
    '124 Star Spangled Banner St', '126 Sunflower Dr', '128 Texas Bluebonnet St', '130 The Riverwalk', '132 Trinity River Rd',
    '134 Guadalupe River Way', '136 Comal Springs Cir', '138 Fiesta Dr', '140 Pecan St', '142 Cypress Ave',
    '144 Cactus Loop', '146 Mesquite St', '148 Cedar Bend', '150 Oak Creek Rd', '152 Sagebrush Ct',
    '154 Prairie Dog Pl', '156 Longhorn Ave', '158 San Jacinto St', '160 Red River Rd', '162 Cowboy Ct',  '123 Bayou St', '456 Mardi Gras Blvd', '789 Cajun Ct', '101 Gumbo Ln', '212 Riverboat Rd',
    '323 Creole Rd', '545 Alligator Alley', '767 Voodoo Dr', '989 Bourbon St', '111 Atchafalaya Way',
    '222 Cypress Swamp Ln', '333 Delta Dr', '444 French Quarter Rd', '555 Jazz Fest Ave', '666 King Cake Ct',
    '777 Magnolia Pl', '888 Pelican Point', '999 Praline St', '100 Red Stick Blvd', '102 Saint Charles Ave',
    '104 Shrimp Boat Ln', '106 Swamp Fox Trail', '108 Tabasco Ct', '110 Cane River Way', '112 Zydeco Dr',
    '114 Bayou Teche Rd', '116 Plantation Dr', '118 Cane Field Ln', '120 Alligator Bayou Rd', '122 Turtle Bayou Rd',
    '124 St. Tammany Pkwy', '126 Sabine River Dr', '128 Lake Pontchartrain Blvd', '130 Evangeline Blvd', '132 Jean Lafitte Pkwy',
    '134 Cajun Coast Ct', '136 Vacherie Plantation Rd', '138 Swamp Fox Ct', '140 Cypress Tree Dr', '142 Acadiana St',
    '144 Tiger Dr', '146 Creole Blvd', '148 Pelican Point Dr', '150 Red River Ct', '152 French Market Pl',
    '154 Jazz Alley', '156 Bourbon St', '158 Gator Trail', '160 Cajun Rd', '162 River Road', '123 Sooner Blvd', '456 Tornado Ave', '789 Cowboy St', '101 Red Earth Dr', '212 Cherokee Pl',
    '323 Thunder Blvd', '545 Prairie Dog Ln', '767 Oil Field Rd', '989 Boomer Sooner Dr', '111 Oklahoma River Ct',
    '222 Native Way', '333 Cowboy Way', '444 Sooner Trail', '555 Red Dirt Rd', '666 Black Mesa Ln',
    '777 Pioneer Blvd', '888 Dust Bowl Dr', '999 Indian Hills Rd', '100 Sooner Rd', '102 Bricktown St',
    '104 Route 66', '106 Keystone Ct', '108 Wichita Mountain Dr', '110 Arbuckle Pl', '112 Blue Jay Blvd',
    '114 Crimson Way', '116 Diamondback Dr', '118 Frontier Pl', '120 Great Plains Blvd', '122 Heartland Dr',
    '124 Mistletoe Ln', '126 Osage Trail', '128 Redbud Way', '130 Seminole St', '132 Thunder Alley',
    '134 Thunderbird Cir', '136 Tornado Ln', '138 Wagon Wheel Dr', '140 Western Ave', '142 Wildcat Way',
    '144 Yaupon Way', '146 Zenobia Dr', '148 Cottonwood Pl', '150 Cimarron Rd', '152 Chickasaw Blvd',
    '154 Cherokee Ln', '156 Okmulgee St', '158 Choctaw Dr', '160 Pawnee Trail', '162 Creek St'];
    $cities = ['Austin', 'Dallas', 'San Antonio', 'College Station', 'Houston',
    'Corpus Christi', 'Waco', 'Fort Worth', 'El Paso', 'Amarillo',
    'Alpine', 'Lubbock', 'Denton', 'San Marcos', 'Uvalde',
    'Victoria', 'Fredericksburg', 'Junction', 'Galveston', 'Laredo',
    'Abilene', 'Midland', 'Canyon', 'Pampa', 'Conroe',
    'Odessa', 'Del Rio', 'Pasadena', 'Shiner', 'Plano',
    'Fort Hood', 'Wichita Falls', 'Ennis', 'San Antonio', 'Arlington',
    'New Braunfels', 'New Braunfels', 'El Paso', 'Sherman', 'McAllen',
    'San Angelo', 'Grand Prairie', 'Denton', 'Kerrville', 'Plano',
    'Lubbock', 'Austin', 'Houston', 'Paris', 'Amarillo', 'New Orleans', 'Baton Rouge', 'Lafayette', 'Shreveport', 'Lake Charles',
    'Metairie', 'Alexandria', 'Slidell', 'New Orleans', 'Morgan City',
    'Hammond', 'Monroe', 'Gretna', 'New Orleans', 'Kenner',
    'Bossier City', 'Mandeville', 'Covington', 'Baton Rouge', 'New Orleans',
    'Houma', 'Thibodaux', 'Avery Island', 'Natchitoches', 'Opelousas',
    'St. Martinville', 'Plaquemine', 'Lafayette', 'St. Amant', 'Pierre Part',
    'Covington', 'Many', 'Slidell', 'New Iberia', 'Jean Lafitte',
    'Morgan City', 'Vacherie', 'Kenner', 'Minden', 'Crowley',
    'Baton Rouge', 'Lake Charles', 'Prairieville', 'Shreveport', 'New Orleans',
    'Baton Rouge', 'New Orleans', 'Hammond', 'Lafayette', 'St. Francisville', 'Norman', 'Oklahoma City', 'Stillwater', 'Tulsa', 'Lawton',
    'Edmond', 'Enid', 'Midwest City', 'Norman', 'Oklahoma City',
    'Bartlesville', 'Stillwater', 'Tulsa', 'Ada', 'Boise City',
    'Ardmore', 'Guymon', 'Norman', 'Tulsa', 'Oklahoma City',
    'El Reno', 'Sand Springs', 'Lawton', 'Davis', 'Jenks',
    'Norman', 'Enid', 'Elk City', 'Woodward', 'Mustang',
    'Broken Arrow', 'Pawhuska', 'Edmond', 'Seminole', 'Oklahoma City',
    'Norman', 'Moore', 'Claremore', 'Oklahoma City', 'Stillwater',
    'Yukon', 'Tulsa', 'Ponca City', 'Cushing', 'Chickasha',
    'Tahlequah', 'Okmulgee', 'Shawnee', 'Pawnee', 'Muskogee'];
    $states = ['TX', 'LA', 'OK'];
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