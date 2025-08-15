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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Bedrock Cadence Portal'; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .text-gradient {
            background: linear-gradient(to right, #C60021, #6A008A);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .nav-item-hover:hover {
            color: #C60021;
        }
        .bg-dark-gradient {
            background: linear-gradient(135deg, #2D2D2D 0%, #1A1A1A 100%);
        }
        .dropdown-menu-custom {
            background-color: #2D2D2D;
            border-radius: 0.5rem;
            border: 1px solid #444;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transition: transform 0.2s ease-in-out, opacity 0.2s ease-in-out;
            transform-origin: top right;
        }
        .dropdown-item-custom {
            color: white;
            padding: 0.75rem 1rem;
            transition: background-color 0.2s;
        }
        .dropdown-item-custom:hover {
            background-color: #444;
        }
    </style>
</head>
<body class="bg-gray-100">

<nav class="bg-dark-gradient shadow-md sticky top-0 z-50">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <div class="flex items-center">
                <a class="flex-shrink-0" href="index.php">
                    <span class="text-3xl font-extrabold text-gradient">Bedrock Cadence</span>
                </a>
                <div class="hidden md:block">
                    <div class="ml-10 flex items-baseline space-x-4">
                        <?php if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
                            <a href="index.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md font-medium">Dashboard</a>
                            
                            <?php // Carrier User Navigation
                            if (in_array($_SESSION['user_role'], ['carrier_user', 'carrier_superuser'])): ?>
                                <a href="view_open_trips.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md font-medium">View Open Trips</a>
                                <a href="view_our_trips.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md font-medium">View Our Trips</a>
                            <?php endif; ?>
                            
                            <?php // Facility User Navigation
                            if (in_array($_SESSION['user_role'], ['facility_user', 'facility_superuser'])): ?>
                                <a href="create_trip.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md font-medium">Create Trip</a>
                                <a href="view_our_trips.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md font-medium">Our Trips</a>
                            <?php endif; ?>

                            <?php // Admin and Superuser Dropdown
                            if(in_array($_SESSION['user_role'], ['carrier_superuser', 'facility_superuser', 'bedrock_admin'])) : ?>
                                <div class="relative group">
                                    <button class="text-gray-300 hover:text-white px-3 py-2 rounded-md font-medium">
                                        Admin Tools <i class="fas fa-caret-down text-gray-400"></i>
                                    </button>
                                    <div class="absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-dark-gradient ring-1 ring-black ring-opacity-5 hidden group-hover:block transition ease-out duration-200 opacity-0 group-hover:opacity-100 transform scale-95 group-hover:scale-100">
                                        <div class="py-1">
                                            <a href="user_management.php" class="block px-4 py-2 text-sm text-gray-300 hover:text-white hover:bg-gray-700">User Management</a>
                                            <?php // System Configuration for superusers
                                            if(in_array($_SESSION['user_role'], ['carrier_superuser', 'facility_superuser'])) : ?>
                                                <a href="system_configuration.php" class="block px-4 py-2 text-sm text-gray-300 hover:text-white hover:bg-gray-700">System Configuration</a>
                                            <?php endif; ?>
                                            <?php // Licensure for carrier superusers only
                                            if($_SESSION['user_role'] == 'carrier_superuser') : ?>
                                                <a href="licensure.php" class="block px-4 py-2 text-sm text-gray-300 hover:text-white hover:bg-gray-700">Licensure</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="hidden md:block">
                <div class="ml-4 flex items-center md:ml-6 space-x-4">
                    <span class="text-gray-300 font-medium text-sm" id="liveClock"></span>
                    <?php if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
                        <div class="relative group">
                            <button class="flex items-center space-x-2 text-white font-medium">
                                <i class="fas fa-user-circle text-2xl text-gray-400"></i>
                                <div class="flex flex-col text-sm text-left">
                                    <span><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></span>
                                    <span class="text-xs text-gray-400"><?php echo htmlspecialchars($company_name); ?></span>
                                </div>
                                <i class="fas fa-caret-down text-gray-400"></i>
                            </button>
                            <div class="absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-dark-gradient ring-1 ring-black ring-opacity-5 hidden group-hover:block transition ease-out duration-200 opacity-0 group-hover:opacity-100 transform scale-95 group-hover:scale-100">
                                <a href="<?php echo in_array($_SESSION['user_role'], ['carrier_user', 'carrier_superuser']) ? 'carrier_profile.php' : 'facility_profile.php'; ?>" class="block px-4 py-2 text-sm text-gray-300 hover:text-white hover:bg-gray-700">My Profile</a>
                                <div class="border-t border-gray-600 my-1"></div>
                                <a href="logout.php" class="block px-4 py-2 text-sm text-red-500 hover:text-red-400">Log Out</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md font-medium">Login</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Mobile menu button -->
            <div class="-mr-2 flex md:hidden">
                <button type="button" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-800 focus:ring-white" aria-controls="mobile-menu" aria-expanded="false">
                    <span class="sr-only">Open main menu</span>
                    <!-- Icon when menu is closed -->
                    <svg class="block h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <!-- Icon when menu is open -->
                    <svg class="hidden h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
</nav>

<main class="container mx-auto px-4 mt-8">
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