<?php
// FILE: /public_html/portal/cron/seed_users.php
// PURPOSE: A command-line script to seed the database with random, active users.

require_once __DIR__ . '/../../../app/init.php';

// --- CONFIGURATION ---
const NUM_FACILITY_USERS_TO_CREATE = 30;
const NUM_CARRIER_USERS_TO_CREATE = 10;
const DEFAULT_PASSWORD = 'Password123!'; // Default password for all created users.
// --- END CONFIGURATION ---

/**
 * A simple class to generate plausible fake user data.
 */
class FakeDataGenerator {
    private static $firstNames = ['John', 'Jane', 'Peter', 'Mary', 'David', 'Susan', 'Michael', 'Linda', 'Robert', 'Patricia', 'James', 'Jennifer'];
    private static $lastNames = ['Smith', 'Doe', 'Jones', 'Williams', 'Brown', 'Davis', 'Miller', 'Wilson', 'Moore', 'Taylor', 'Anderson', 'Thomas'];

    public static function getFirstName(): string {
        return self::$firstNames[array_rand(self::$firstNames)];
    }

    public static function getLastName(): string {
        return self::$lastNames[array_rand(self::$lastNames)];
    }

    public static function getEmail(string $firstName, string $lastName): string {
        $domain = "faker.bedrockcadence.com"; // Using a fake subdomain of your company
        $randomNumber = rand(100, 999);
        return strtolower($firstName . '.' . $lastName . $randomNumber . '@' . $domain);
    }

    public static function getPhoneNumber(): string {
        return rand(200, 999) . '-' . rand(200, 999) . '-' . sprintf('%04d', rand(0, 9999));
    }
}

try {
    $db = Database::getInstance();
    $passwordHash = password_hash(DEFAULT_PASSWORD, PASSWORD_ARGON2ID);

    // 1. Fetch existing facilities and carriers to assign users to them.
    $facilityIds = array_column($db->fetchAll("SELECT id FROM facilities WHERE is_active = 1"), 'id');
    $carrierIds = array_column($db->fetchAll("SELECT id FROM carriers WHERE is_active = 1"), 'id');

    if (empty($facilityIds)) {
        throw new Exception("Cannot seed facility users because no active facilities were found.");
    }
    if (empty($carrierIds)) {
        throw new Exception("Cannot seed carrier users because no active carriers were found.");
    }

    echo "Starting user seeder...\n";
    echo "Default password for all users is: " . DEFAULT_PASSWORD . "\n\n";

    // 2. Create Facility Users
    echo "Creating " . NUM_FACILITY_USERS_TO_CREATE . " facility users...\n";
    for ($i = 0; $i < NUM_FACILITY_USERS_TO_CREATE; $i++) {
        $firstName = FakeDataGenerator::getFirstName();
        $lastName = FakeDataGenerator::getLastName();
        $email = FakeDataGenerator::getEmail($firstName, $lastName);
        
        $params = [
            ':email' => $email,
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':phone_number' => FakeDataGenerator::getPhoneNumber(),
            ':role' => 'user', // Assign a default role
            ':entity_id' => $facilityIds[array_rand($facilityIds)], // Assign to a random facility
            ':entity_type' => 'facility',
            ':password_hash' => $passwordHash
        ];

        $sql = "INSERT INTO users (uuid, email, first_name, last_name, phone_number, role, entity_id, entity_type, is_active, password_hash)
                VALUES (UUID(), :email, :first_name, :last_name, :phone_number, :role, :entity_id, :entity_type, 1, :password_hash)";
        
        $db->execute($sql, $params);
        echo "  - Created facility user: {$firstName} {$lastName} ({$email})\n";
    }
    echo "Facility user creation complete.\n\n";

    // 3. Create Carrier Users
    echo "Creating " . NUM_CARRIER_USERS_TO_CREATE . " carrier users...\n";
    for ($i = 0; $i < NUM_CARRIER_USERS_TO_CREATE; $i++) {
        $firstName = FakeDataGenerator::getFirstName();
        $lastName = FakeDataGenerator::getLastName();
        $email = FakeDataGenerator::getEmail($firstName, $lastName);
        
        $params = [
            ':email' => $email,
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':phone_number' => FakeDataGenerator::getPhoneNumber(),
            ':role' => 'user', // Assign a default role
            ':entity_id' => $carrierIds[array_rand($carrierIds)], // Assign to a random carrier
            ':entity_type' => 'carrier',
            ':password_hash' => $passwordHash
        ];

        $sql = "INSERT INTO users (uuid, email, first_name, last_name, phone_number, role, entity_id, entity_type, is_active, password_hash)
                VALUES (UUID(), :email, :first_name, :last_name, :phone_number, :role, :entity_id, :entity_type, 1, :password_hash)";
        
        $db->execute($sql, $params);
        echo "  - Created carrier user: {$firstName} {$lastName} ({$email})\n";
    }
    echo "Carrier user creation complete.\n\n";

    echo "Seeding process finished successfully.\n";

} catch (Exception $e) {
    file_put_contents('php://stderr', "Error during seeding: " . $e->getMessage() . "\n");
    exit(1); // Exit with an error code
}

exit(0); // Success
