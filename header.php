<?php
// FILE: public_html/portal/header.php

// This single file bootstraps the entire application environment.
require_once __DIR__ . '/../../app/init.php';

// Generate a secure, single-use nonce for the Content Security Policy.
$cspNonce = bin2hex(random_bytes(16));

// Set the Content Security Policy header to enhance security.
// This tells the browser to only trust scripts with our specific nonce.
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$cspNonce}' https://challenges.cloudflare.com https://desk.zoho.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com;");

// Prepare user display information using the Auth service.
$companyName = Auth::user('entity_name', 'Bedrock Cadence');
$companyIcon = '';
$entityType = Auth::user('entity_type');

if ($entityType === 'carrier') {
    $companyIcon = '<i class="fa-solid fa-truck-medical text-blue-300 mr-2"></i>';
} elseif ($entityType === 'facility') {
    $companyIcon = '<i class="fa-solid fa-house-medical text-green-300 mr-2"></i>';
} elseif ($entityType === 'bedrock' && Auth::hasRole('admin')) {
    $companyName = 'Bedrock Cadence';
    $companyIcon = '<i class="fa-solid fa-user-shield text-purple-600 mr-2"></i>';
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <title><?= isset($page_title) ? Utils::e($page_title) . ' | Bedrock Cadence' : 'Bedrock Cadence Portal'; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="/assets/css/custom.css">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
</head>
<script nonce="<?= $cspNonce ?>" type="text/javascript" id="zohodeskasap">var d=document;s=d.createElement("script"),s.type="text/javascript",s.id="zohodeskasapscript",s.defer=!0,s.nonce="<?= $cspNonce ?>",s.src="https://desk.zoho.com/portal/api/web/asapApp/1170206000000378109?orgId=896515669",t=d.getElementsByTagName("script")[0],t.parentNode.insertBefore(s,t),window.ZohoDeskAsapReady=function(s){var e=window.ZohoDeskAsap__asyncalls=window.ZohoDeskAsap__asyncalls||[];window.ZohoDeskAsapReadyStatus?(s&&e.push(s),e.forEach(s=>s&&s()),window.ZohoDeskAsap__asyncalls=null):s&&e.push(s)};</script>

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
                        <?php if (Auth::isLoggedIn()): ?>
                            <a href="/index.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md font-medium">Dashboard</a>

                            <a href="/index.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md font-medium">Dashboard</a>
<a href="/notifications.php" id="nav-notifications-link" class="relative text-gray-300 hover:text-white px-3 py-2 rounded-md font-medium">
    <span>Notifications</span>
    <span id="notification-badge" class="hidden absolute -top-1 -right-1 w-5 h-5 bg-red-600 text-white text-xs font-bold rounded-full flex items-center justify-center"></span>
</a>
                            
                            <?php if (Auth::can('create_trip')): ?>
                                <a href="/create_trip.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md font-medium">Create Trip</a>
                            <?php endif; ?>

                            <?php if (Auth::can('view_admin_tools')): ?>
                                <div class="relative">
                                    <button data-dropdown-toggle="admin-menu" class="text-gray-300 hover:text-white px-3 py-2 rounded-md font-medium">
                                        Admin Tools <i class="fas fa-caret-down text-gray-400 ml-1"></i>
                                    </button>
                                    <div id="admin-menu" class="absolute left-0 mt-2 w-56 dropdown-menu-custom hidden">
                                        <div class="py-1">
                                            <a href="/user_management.php" class="dropdown-item-custom">User Management</a>
                                            <?php if (Auth::can('view_system_config')): ?>
                                                <a href="/system_configuration.php" class="dropdown-item-custom">System Configuration</a>
                                            <?php endif; ?>
                                            <?php if (Auth::can('manage_licensure')): ?>
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
                <?php if (Auth::isLoggedIn()): ?>
                    <div class="relative">
                        <button data-dropdown-toggle="user-menu" class="flex items-center space-x-2 text-white font-medium p-2 rounded-md hover:bg-gray-700">
                            <i class="fas fa-user-circle text-2xl text-gray-400"></i>
                            <div class="flex flex-col text-sm text-left">
                                <span><?= Utils::e(Auth::user('first_name') . ' ' . Auth::user('last_name')) ?></span>
                                <span class="text-xs text-gray-400 flex items-center">
                                    <?= $companyIcon ?>
                                    <?= Utils::e($companyName) ?>
                                </span>
                            </div>
                            <i class="fas fa-caret-down text-gray-400"></i>
                        </button>
                        <div id="user-menu" class="absolute right-0 mt-2 w-48 dropdown-menu-custom hidden">
                            <a href="user_profile.php?id=<?= Utils::e(Auth::user('user_uuid')) ?>" class="dropdown-item-custom">My Profile</a>
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
                    <svg id="menu-closed-icon" class="block h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                    <svg id="menu-open-icon" class="hidden h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
        </div>
    </div>
    
    <div class="md:hidden hidden" id="mobile-menu">
        <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
             <?php if (Auth::isLoggedIn()): ?>
                <a href="/index.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Dashboard</a>
                 <?php if (Auth::can('create_trip')): ?>
                    <a href="/create_trip.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Create Trip</a>
                 <?php endif; ?>
                 <?php else: ?>
                <a href="/login.php" class="text-gray-300 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Login</a>
             <?php endif; ?>
        </div>
    </div>
</nav>

<main class="container mx-auto p-4 sm:p-6 lg:p-8">