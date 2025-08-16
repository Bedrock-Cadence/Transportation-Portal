<?php
// FILE: public/includes/header.php

// This is the ONLY line responsible for session management.
require_once __DIR__ . '/../../app/session_config.php';

// --- DATABASE QUERY REMOVED ---
// The company name is now fetched at login and stored in the session.

// Set the company name from the session, with a fallback to Bedrock Cadence.
$company_name = $_SESSION['entity_name'] ?? '';

// --- NEW LOGIC FOR DYNAMIC ICONS ---
$company_icon = '';
if (isset($_SESSION['entity_type'])) {
    if ($_SESSION['entity_type'] === 'carrier') {
        // A cute little ambulance for carriers
        $company_icon = '<i class="fa-solid fa-truck-medical text-blue-300 mr-2"></i>';
    } elseif ($_SESSION['entity_type'] === 'facility') {
        // A cute little hospital for facilities
        $company_icon = '<i class="fa-solid fa-house-medical text-green-300 mr-2"></i>';
    } elseif ($_SESSION['entity_type'] === 'bedrock' && $_SESSION['user_role'] === 'admin') {
        $company_name = 'Bedrock Cadence';
        $company_icon = '<i class="fa-solid fa-user-shield text-purple-600 mr-2"></i>';
    } }
// --- END NEW LOGIC ---

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' | Bedrock Cadence' : 'Bedrock Cadence Portal'; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="/assets/css/custom.css">
</head>
<script type="text/javascript" id="zohodeskasap">var d=document;s=d.createElement("script"),s.type="text/javascript",s.id="zohodeskasapscript",s.defer=!0,s.nonce="{place_your_nonce_value_here}",s.src="https://desk.zoho.com/portal/api/web/asapApp/1170206000000378109?orgId=896515669",t=d.getElementsByTagName("script")[0],t.parentNode.insertBefore(s,t),window.ZohoDeskAsapReady=function(s){var e=window.ZohoDeskAsap__asyncalls=window.ZohoDeskAsap__asyncalls||[];window.ZohoDeskAsapReadyStatus?(s&&e.push(s),e.forEach(s=>s&&s()),window.ZohoDeskAsap__asyncalls=null):s&&e.push(s)};</script>
<body class="bg-gray-100">

<nav class="bg-dark-gradient shadow-md sticky top-0 z-50">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <div class="flex items-center">
                <a class="flex-shrink-0" href="/index.php">
                    <span class="text-3xl font-extrabold text-gradient">Bedrock Cadence</span>
                </a>
                <div class="hidden md:block">
                    <div class="ml-10 flex items-baseline space-x-4">
                        <?php if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
                            <a href="/index.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md font-medium">Dashboard</a>
                            
                            <?php
                            if ( (in_array($_SESSION['user_role'], ['user', 'superuser']) && $_SESSION['entity_type'] === 'facility') || $_SESSION['user_role'] === 'admin' ): ?>
                                <a href="/create_trip.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md font-medium">Create Trip</a>
                            <?php endif; ?>

                            <?php
                            if ( (in_array($_SESSION['user_role'], ['superuser', 'admin']))): ?>
                                <div class="relative">
                                    <button data-dropdown-toggle="admin-menu" class="text-gray-300 hover:text-white px-3 py-2 rounded-md font-medium">
                                        Admin Tools <i class="fas fa-caret-down text-gray-400 ml-1"></i>
                                    </button>
                                    <div id="admin-menu" class="absolute left-0 mt-2 w-56 dropdown-menu-custom hidden">
                                        <div class="py-1">
                                            <a href="/user_management.php" class="dropdown-item-custom">User Management</a>
                                            <?php if (in_array($_SESSION['user_role'], ['superuser', 'admin'])) : ?>
                                                <a href="/system_configuration.php" class="dropdown-item-custom">System Configuration</a>
                                            <?php endif; ?>
                                            <?php
                            if ( (in_array($_SESSION['user_role'], ['superuser']) && $_SESSION['entity_type'] === 'carrier')): ?>
                                                <a href="/licensure.php" class="dropdown-item-custom">Licensure</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="hidden md:flex items-center ml-4 md:ml-6 space-x-4">
                <span class="text-gray-300 font-medium text-sm" id="liveClock"></span>
                <?php if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
                    <div class="relative">
                        <button data-dropdown-toggle="user-menu" class="flex items-center space-x-2 text-white font-medium p-2 rounded-md hover:bg-gray-700">
                            <i class="fas fa-user-circle text-2xl text-gray-400"></i>
                            <div class="flex flex-col text-sm text-left">
                                <span><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></span>
                                <span class="text-xs text-gray-400 flex items-center">
                                    <?php echo $company_icon; ?>
                                    <?php echo htmlspecialchars($company_name); ?>
                                </span>
                            </div>
                            <i class="fas fa-caret-down text-gray-400"></i>
                        </button>
                        <div id="user-menu" class="absolute right-0 mt-2 w-48 dropdown-menu-custom hidden">
                            <a href="user_profile.php?id=<?php echo $_SESSION["user_uuid"] ?>" class="dropdown-item-custom">My Profile</a>
                            <div class="border-t border-gray-600 my-1"></div>
                            <a href="/logout.php" class="block px-4 py-2 text-sm text-red-500 hover:bg-gray-700 hover:text-red-400">Log Out</a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="/login.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md font-medium">Login</a>
                <?php endif; ?>
            </div>

            <div class="-mr-2 flex md:hidden">
                <button id="mobile-menu-button" type="button" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-800 focus:ring-white" aria-controls="mobile-menu" aria-expanded="false">
                    <span class="sr-only">Open main menu</span>
                    <svg id="menu-closed-icon" class="block h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg id="menu-open-icon" class="hidden h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
    
    <div class="md:hidden hidden" id="mobile-menu">
        <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
             <?php if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
                 <a href="/index.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Dashboard</a>
                 
                 <?php // Carrier Links
                 if (in_array($_SESSION['user_role'], ['carrier_user', 'carrier_superuser'])): ?>
                     <a href="/view_open_trips.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">View Open Trips</a>
                     <a href="/view_our_trips.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">View Our Trips</a>
                 <?php endif; ?>

                 <?php // Facility Links
                 if (in_array($_SESSION['user_role'], ['facility_user', 'facility_superuser'])): ?>
                     <a href="/create_trip.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Create Trip</a>
                     <a href="/view_our_trips.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Our Trips</a>
                 <?php endif; ?>
             <?php else: ?>
                 <a href="/login.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Login</a>
             <?php endif; ?>
        </div>
    </div>
</nav>

<main class="container mx-auto p-4 sm:p-6 lg:p-8">