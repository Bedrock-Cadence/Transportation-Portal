<?php

/**
 * Migration Script: Restructure 'trips' table and create 'trips_phi' table.
 *
 * This script performs the following actions in a single database transaction:
 * 1. Creates the new `trips_phi` table to store encrypted patient data.
 * 2. Creates a temporary `trips_new` table with the final, non-PHI structure.
 * 3. Copies non-PHI data from the old `trips` table to `trips_new`.
 * 4. Copies PHI data from the old `trips` table to `trips_phi`, linking it by trip ID.
 * 5. Drops the original `trips` table (after disabling foreign key checks).
 * 6. Renames `trips_new` to `trips`.
 * 7. Re-establishes all necessary foreign key constraints on the new tables.
 *
 * @version 1.0
 * @author Bedrock Cadence
 * @date 2025-08-15
 */

// --- Database Connection ---
// Ensure this path is correct for your project structure.
require_once __DIR__ . '/../../app/db_connect.php'; // Or your actual connection file

// --- Migration Logic ---
echo "<h1>Database Migration: Segregate PHI</h1>";

// Check if the new trips_phi table already exists to prevent re-running the migration
$result = $mysqli->query("SHOW TABLES LIKE 'trips_phi'");
if ($result->num_rows > 0) {
    die("<p style='color: red; font-weight: bold;'>ERROR: The 'trips_phi' table already exists. Migration has likely already been run. Halting script to prevent data loss.</p>");
}


// Start a transaction. If anything fails, we can roll back all changes.
$mysqli->begin_transaction();

echo "<p>Starting migration transaction...</p>";

try {
    // Step 1: Disable foreign key checks to allow table modifications.
    $mysqli->query("SET FOREIGN_KEY_CHECKS=0;");
    echo "<p>Step 1: Foreign key checks disabled.</p>";

    // Step 2: Create the new `trips_phi` table.
    $create_phi_table_sql = "
    CREATE TABLE `trips_phi` (
      `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      `trip_id` bigint(20) UNSIGNED NOT NULL COMMENT 'FK to the trips table',
      `patient_first_name_encrypted` varbinary(512) NOT NULL,
      `patient_last_name_encrypted` varbinary(512) NOT NULL,
      `patient_dob_encrypted` varbinary(512) NOT NULL,
      `patient_ssn_last4_encrypted` varbinary(512) NULL COMMENT 'Added for new form field',
      `patient_weight_kg_encrypted` varbinary(512) DEFAULT NULL,
      `patient_height_in_encrypted` varbinary(512) NULL COMMENT 'Added for new form field',
      `diagnosis_encrypted` blob DEFAULT NULL,
      `special_equipment_encrypted` blob DEFAULT NULL,
      `isolation_precautions_encrypted` blob DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `trip_id` (`trip_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores encrypted PHI for trips';";
    if (!$mysqli->query($create_phi_table_sql)) {
        throw new Exception("Error creating trips_phi table: " . $mysqli->error);
    }
    echo "<p>Step 2: `trips_phi` table created successfully.</p>";

    // Step 3: Create the new `trips` table structure as `trips_new`.
    $create_trips_new_table_sql = "
    CREATE TABLE `trips_new` (
      `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      `uuid` char(36) NOT NULL,
      `facility_id` bigint(20) UNSIGNED NOT NULL,
      `carrier_id` bigint(20) UNSIGNED DEFAULT NULL,
      `created_by_user_id` bigint(20) UNSIGNED NOT NULL,
      `origin_name` varchar(255) NOT NULL,
      `origin_street` varchar(255) NOT NULL,
      `origin_city` varchar(100) NOT NULL,
      `origin_state` varchar(50) NOT NULL,
      `origin_zip` varchar(20) NOT NULL,
      `destination_name` varchar(255) NOT NULL,
      `destination_street` varchar(255) NOT NULL,
      `destination_city` varchar(100) NOT NULL,
      `destination_state` varchar(50) NOT NULL,
      `destination_zip` varchar(20) NOT NULL,
      `appointment_at` datetime DEFAULT NULL,
      `asap` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 for ASAP, 0 for scheduled',
      `requested_pickup_time` time DEFAULT NULL,
      `status` enum('bidding','awarded','completed','cancelled') NOT NULL DEFAULT 'bidding',
      `awarded_eta` datetime DEFAULT NULL,
      `carrier_completed_at` datetime DEFAULT NULL,
      `facility_completed_at` datetime DEFAULT NULL,
      `bidding_closes_at` datetime NOT NULL,
      `was_transported_by_carrier` tinyint(1) DEFAULT NULL,
      `patient_was_ready` tinyint(1) DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `uuid` (`uuid`),
      KEY `idx_trips_status` (`status`),
      KEY `facility_id` (`facility_id`),
      KEY `carrier_id` (`carrier_id`),
      KEY `created_by_user_id` (`created_by_user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    if (!$mysqli->query($create_trips_new_table_sql)) {
        throw new Exception("Error creating trips_new table: " . $mysqli->error);
    }
    echo "<p>Step 3: `trips_new` table created successfully.</p>";

    // Step 4: Copy non-PHI data from `trips` to `trips_new`.
    $copy_non_phi_sql = "
    INSERT INTO `trips_new` (id, uuid, facility_id, carrier_id, created_by_user_id, origin_name, origin_street, origin_city, origin_state, origin_zip, destination_name, destination_street, destination_city, destination_state, destination_zip, appointment_at, status, awarded_eta, carrier_completed_at, facility_completed_at, bidding_closes_at, was_transported_by_carrier, patient_was_ready, created_at, updated_at)
    SELECT id, uuid, facility_id, carrier_id, created_by_user_id, origin_name, origin_street, origin_city, origin_state, origin_zip, destination_name, destination_street, destination_city, destination_state, destination_zip, appointment_at, status, awarded_eta, carrier_completed_at, facility_completed_at, bidding_closes_at, was_transported_by_carrier, patient_was_ready, created_at, updated_at
    FROM `trips`;";
    if (!$mysqli->query($copy_non_phi_sql)) {
        throw new Exception("Error copying non-PHI data: " . $mysqli->error);
    }
    echo "<p>Step 4: Non-PHI data copied to `trips_new`.</p>";

    // Step 5: Copy PHI data from `trips` to `trips_phi`.
    // Note: We are selecting the old PHI columns and inserting them into the new PHI table.
    // We are setting new fields like ssn and height to NULL as they didn't exist before.
    $copy_phi_sql = "
    INSERT INTO `trips_phi` (trip_id, patient_first_name_encrypted, patient_last_name_encrypted, patient_dob_encrypted, patient_ssn_last4_encrypted, patient_weight_kg_encrypted, patient_height_in_encrypted, diagnosis_encrypted, special_equipment_encrypted, isolation_precautions_encrypted, created_at, updated_at)
    SELECT id, patient_first_name_encrypted, patient_last_name_encrypted, patient_dob_encrypted, NULL, patient_weight_kg_encrypted, NULL, medical_conditions_encrypted, equipment_needs_encrypted, isolation_precautions_encrypted, created_at, updated_at
    FROM `trips`;";
    if (!$mysqli->query($copy_phi_sql)) {
        throw new Exception("Error copying PHI data: " . $mysqli->error);
    }
    echo "<p>Step 5: PHI data copied to `trips_phi`.</p>";

    // Step 6: Drop the old `trips` table.
    if (!$mysqli->query("DROP TABLE `trips`;")) {
        throw new Exception("Error dropping old trips table: " . $mysqli->error);
    }
    echo "<p>Step 6: Old `trips` table dropped.</p>";

    // Step 7: Rename `trips_new` to `trips`.
    if (!$mysqli->query("RENAME TABLE `trips_new` TO `trips`;")) {
        throw new Exception("Error renaming trips_new to trips: " . $mysqli->error);
    }
    echo "<p>Step 7: `trips_new` renamed to `trips`.</p>";

    // Step 8: Re-establish foreign key constraints.
    $add_fk_trips_sql = "
    ALTER TABLE `trips`
    ADD CONSTRAINT `trips_ibfk_1` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`id`),
    ADD CONSTRAINT `trips_ibfk_2` FOREIGN KEY (`carrier_id`) REFERENCES `carriers` (`id`),
    ADD CONSTRAINT `trips_ibfk_3` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`);";
    if (!$mysqli->query($add_fk_trips_sql)) {
        throw new Exception("Error adding foreign keys to trips table: " . $mysqli->error);
    }

    $add_fk_phi_sql = "
    ALTER TABLE `trips_phi`
    ADD CONSTRAINT `trips_phi_ibfk_1` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE CASCADE;";
    if (!$mysqli->query($add_fk_phi_sql)) {
        throw new Exception("Error adding foreign key to trips_phi table: " . $mysqli->error);
    }
    echo "<p>Step 8: Foreign key constraints re-established.</p>";

    // Step 9: Re-enable foreign key checks.
    $mysqli->query("SET FOREIGN_KEY_CHECKS=1;");
    echo "<p>Step 9: Foreign key checks re-enabled.</p>";

    // If we made it this far, commit the transaction.
    $mysqli->commit();
    echo "<h2 style='color: green;'>Migration successful!</h2>";
    echo "<p>The database has been updated. You should now delete this migration script from your server.</p>";

} catch (Exception $e) {
    // If any step failed, roll back the entire transaction.
    $mysqli->rollback();
    echo "<h2 style='color: red;'>Migration FAILED!</h2>";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<p>The transaction has been rolled back. Your database is in its original state. Please review the error and try again.</p>";
    // Re-enable foreign key checks on failure as well.
    $mysqli->query("SET FOREIGN_KEY_CHECKS=1;");
}

// Close the connection.
$mysqli->close();

?>