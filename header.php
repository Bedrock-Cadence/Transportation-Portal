<?php
// FILE: header.php

// This is now the ONLY line responsible for session management.
// It sets the domain-wide cookie and starts the session.
require_once __DIR__ . '/../../app/session_config.php';

// We need a database connection to get the company name.
require_once __DIR__ . '/../../app/db_connect.php';

$company_name = 'Bedrock Cadence'; // Default to the portal name

// If the user is logged in, grab their company name from the database.
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    $entity_type = $_SESSION['entity_type'];
    $entity_id = $_SESSION['entity_id'];
    $table_name = '';

    // Determine which table to query based on the entity type
    if ($entity_type === 'carrier') {
        $table_name = 'carriers';
    } elseif ($entity_type === 'facility') {
        $table_name = 'facilities';
    }

    if ($table_name !== '') {
        $sql = "SELECT name FROM {$table_name} WHERE id = ? LIMIT 1";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("i", $entity_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $company_name = $row['name'];
            }
            $stmt->close();
        }
    }
}

// Close the database connection to free up resources.
$mysqli->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Bedrock Cadence Portal'; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" xintegrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <style>
        /* Custom styles for the navigation bar */
        .nav-link.logout-link {
            color: #dc3545 !important; /* A bright red color for the log out link */
        }
        .nav-link.logout-link:hover {
            color: #bd2130 !important; /* A slightly darker red for hover state */
        }
        .nav-link.user-profile {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
            padding: 0.5rem 1rem; /* Adjust padding for better spacing */
        }
        .nav-link.user-profile .company-name {
            font-size: 0.8em;
            color: rgba(255, 255, 255, 0.75);
        }
        .dropdown-menu {
            right: 0;
            left: auto;
        }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="index.php">Bedrock Cadence</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNavbar">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <?php if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
            <li class="nav-item">
                <a class="nav-link" href="index.php">Dashboard</a>
            </li>
            <?php // Carrier User Navigation
            if (in_array($_SESSION['user_role'], ['carrier_user', 'carrier_superuser'])): ?>
                <li class="nav-item"><a class="nav-link" href="view_open_trips.php">View Open Trips</a></li>
                <li class="nav-item"><a class="nav-link" href="view_our_trips.php">View Our Trips</a></li>
            <?php endif; ?>
            <?php // Facility User Navigation
            if (in_array($_SESSION['user_role'], ['facility_user', 'facility_superuser'])): ?>
                <li class="nav-item"><a class="nav-link" href="create_trip.php">Create Trip</a></li>
                <li class="nav-item"><a class="nav-link" href="view_our_trips.php">Our Trips</a></li>
            <?php endif; ?>

            <?php // Admin and Superuser Navigation
            if(in_array($_SESSION['user_role'], ['carrier_superuser', 'facility_superuser', 'bedrock_admin'])) : ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Admin Tools
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                        <li><a class="dropdown-item" href="user_management.php">User Management</a></li>
                        <?php // System Configuration for superusers
                        if(in_array($_SESSION['user_role'], ['carrier_superuser', 'facility_superuser'])) : ?>
                            <li><a class="dropdown-item" href="system_configuration.php">System Configuration</a></li>
                        <?php endif; ?>
                        <?php // Licensure for carrier superusers only
                        if($_SESSION['user_role'] == 'carrier_superuser') : ?>
                            <li><a class="dropdown-item" href="licensure.php">Licensure</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
            <?php endif; ?>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
        <li class="nav-item">
            <span class="nav-link text-white" id="liveClock"></span>
        </li>
        <?php if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
            <!-- User Profile Dropdown -->
            <li class="nav-item dropdown">
                <a class="nav-link user-profile dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span><?php echo htmlspecialchars($_SESSION['user_first_name'] . ' ' . $_SESSION['user_last_name']); ?></span>
                    <span class="company-name"><?php echo htmlspecialchars($company_name); ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                    <?php if (in_array($_SESSION['user_role'], ['carrier_user', 'carrier_superuser'])): ?>
                        <li><a class="dropdown-item" href="carrier_profile.php">My Profile</a></li>
                    <?php endif; ?>
                    <?php if (in_array($_SESSION['user_role'], ['facility_user', 'facility_superuser'])): ?>
                        <li><a class="dropdown-item" href="facility_profile.php">My Profile</a></li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item logout-link" href="logout.php">Log Out</a></li>
                </ul>
            </li>
        <?php else: ?>
            <li class="nav-item">
                <a class="nav-link" href="login.php">Login</a>
            </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<main class="container mt-4">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" xintegrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const liveClock = document.getElementById('liveClock');

        // Function to update the clock
        function updateClock() {
            const now = new Date();
            // Format the date and time for the 'America/Chicago' timezone
            const formattedDate = now.toLocaleString('en-US', {
                timeZone: 'America/Chicago',
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: 'numeric',
                minute: 'numeric',
                second: 'numeric',
                hour12: true
            });
            liveClock.textContent = formattedDate;
        }

        // Update the clock every second
        setInterval(updateClock, 1000);
        
        // Call the function once to avoid a 1-second delay on page load
        updateClock();
    });
</script>