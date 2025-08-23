<?php
/**
 * SCRIPT: seed_database.php
 * DESCRIPTION:
 * This script populates the database with randomly generated test data for facilities and carriers.
 * It is designed to be run from a web browser.
 *
 * REQUIREMENTS:
 * - This script must be placed in a web-accessible directory of your project.
 * - It requires the application's init file to be correctly included.
 *
 * USAGE:
 * 1. Place this file in your project's root directory.
 * 2. Navigate to this file's URL in your web browser (e.g., https://your-domain.com/seed_database.php).
 * 3. Click the "Seed Database" button.
 */

// --- Bootstrap Application ---
// Initializes your application's environment, including the database connection and class autoloaders.
require_once __DIR__ . '/../../../app/init.php';

/**
 * Generates a random entity record.
 * This function replaces the need for the Faker library by using predefined arrays.
 *
 * @param string $type The type of entity to create ('facility' or 'carrier').
 * @return array The generated data array for the entity.
 */
function generateRandomEntityData(string $type): array {
    // Predefined arrays for generating random data
    $companyPrefixes = ['Apex', 'Global', 'United', 'Prestige', 'Keystone', 'Summit', 'Pioneer', 'Elite', 'Vanguard', 'Dynamic'];
    $medicalSuffixes = ['Health', 'Medical Center', 'Clinic', 'Care', 'Hospital', 'Wellness Group'];
    $transportSuffixes = ['Transport', 'Logistics', 'Ambulance', 'Medical Transport', 'EMS', 'Carriers'];
    $streetNames = ['Main', 'Oak', 'Pine', 'Maple', 'Cedar', 'Elm', 'Washington', 'Lake', 'Hill', 'Park'];
    $streetTypes = ['St', 'Ave', 'Blvd', 'Rd', 'Ln', 'Dr'];
    $cities = [
        ['city' => 'Dallas', 'state' => 'TX', 'zip' => '75201'],
        ['city' => 'Houston', 'state' => 'TX', 'zip' => '77002'],
        ['city' => 'Austin', 'state' => 'TX', 'zip' => '78701'],
        ['city' => 'San Antonio', 'state' => 'TX', 'zip' => '78205'],
        ['city' => 'Fort Worth', 'state' => 'TX', 'zip' => '76102'],
        ['city' => 'Oklahoma City', 'state' => 'OK', 'zip' => '73102'],
        ['city' => 'Tulsa', 'state' => 'OK', 'zip' => '74103'],
        ['city' => 'New Orleans', 'state' => 'LA', 'zip' => '70112'],
        ['city' => 'Shreveport', 'state' => 'LA', 'zip' => '71101'],
        ['city' => 'Little Rock', 'state' => 'AR', 'zip' => '72201']
    ];

    // Select random elements from the arrays
    $prefix = $companyPrefixes[array_rand($companyPrefixes)];
    $location = $cities[array_rand($cities)];
    $name = $prefix . ' ' . ($type === 'facility' ? $medicalSuffixes[array_rand($medicalSuffixes)] : $transportSuffixes[array_rand($transportSuffixes)]);
    
    // Generate the data array
    return [
        'customer_id'    => mt_rand(100000, 999999),
        'name'           => $name,
        'address_street' => mt_rand(100, 9999) . ' ' . $streetNames[array_rand($streetNames)] . ' ' . $streetTypes[array_rand($streetTypes)],
        'address_city'   => $location['city'],
        'address_state'  => $location['state'],
        'address_zip'    => $location['zip'],
        'phone_number'   => mt_rand(200, 999) . '-' . mt_rand(200, 999) . '-' . mt_rand(1000, 9999),
        'is_active'      => '1',
        'type'           => $type
    ];
}

/**
 * Processes the database seeding and returns the output as a string.
 *
 * @return string HTML formatted output of the seeding process.
 */
function runSeeder(): string {
    $output = "<h3>Seeding Process Started...</h3>";
    $entityService = new EntityService();

    // --- Seed Facilities ---
    $facilityCount = 15;
    $output .= "<h4>Attempting to create {$facilityCount} random facilities...</h4>";
    $output .= "<ul>";
    for ($i = 0; $i < $facilityCount; $i++) {
        $facilityData = generateRandomEntityData('facility');
        try {
            $result = $entityService->createEntity($facilityData);
            $output .= "<li style='color: green;'><strong>SUCCESS:</strong> Created Facility '{$facilityData['name']}' with ID: {$result['id']}</li>";
        } catch (Exception $e) {
            $output .= "<li style='color: red;'><strong>ERROR:</strong> Could not create facility '{$facilityData['name']}'. Reason: " . $e->getMessage() . "</li>";
        }
    }
    $output .= "</ul>";

    // --- Seed Carriers ---
    $carrierCount = 15;
    $output .= "<h4>Attempting to create {$carrierCount} random carriers...</h4>";
    $output .= "<ul>";
    for ($i = 0; $i < $carrierCount; $i++) {
        $carrierData = generateRandomEntityData('carrier');
        try {
            $result = $entityService->createEntity($carrierData);
            $output .= "<li style='color: green;'><strong>SUCCESS:</strong> Created Carrier '{$carrierData['name']}' with ID: {$result['id']}</li>";
        } catch (Exception $e) {
            $output .= "<li style='color: red;'><strong>ERROR:</strong> Could not create carrier '{$carrierData['name']}'. Reason: " . $e->getMessage() . "</li>";
        }
    }
    $output .= "</ul>";
    $output .= "<h3>Seeding Process Finished.</h3>";

    return $output;
}

$outputLog = '';
// Check if the form has been submitted to start the seeding process.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $outputLog = runSeeder();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Seeder</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            background-color: #f4f7f9;
            color: #333;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            max-width: 800px;
            width: 100%;
            text-align: center;
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        p {
            color: #7f8c8d;
            margin-bottom: 30px;
        }
        .seed-button {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 15px 30px;
            font-size: 16px;
            font-weight: bold;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }
        .seed-button:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }
        .output-log {
            margin-top: 30px;
            padding: 20px;
            background-color: #ecf0f1;
            border-radius: 8px;
            text-align: left;
            font-family: "Courier New", Courier, monospace;
            font-size: 14px;
            line-height: 1.6;
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #bdc3c7;
        }
        .output-log h3, .output-log h4 {
            margin-top: 0;
            color: #2c3e50;
        }
        .output-log ul {
            padding-left: 20px;
            list-style-type: none;
        }
        .output-log li {
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Database Seeding Tool</h1>
        <p>Click the button below to populate the database with 15 new random facilities and 15 new random carriers for testing purposes.</p>
        
        <form method="POST" action="">
            <button type="submit" class="seed-button">Seed Database</button>
        </form>

        <?php if (!empty($outputLog)): ?>
            <div class="output-log">
                <?php echo $outputLog; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>