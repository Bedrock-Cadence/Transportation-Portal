<?php
// FILE: public_html/portal/index.php

require_once __DIR__ . '/../../app/init.php';

// Security Check: Use our central Auth service for a clean check.
if (!Auth::isLoggedIn()) {
    Utils::redirect('login.php');
}

$page_title = 'Dashboard';

// Prepare User Data for JavaScript. Pass it to a global JS variable.
$userDataForJs = json_encode([
    'userRole' => Auth::user('user_role'),
    'entityType' => Auth::user('entity_type'),
    'userTimezone' => USER_TIMEZONE, // From secrets.php
    // ADDED: Define the absolute base URL for your API
    'apiBaseUrl' => 'https://www.bedrockcadence.com/api' 
]);

require_once 'header.php';
?>

<div id="dashboard-container">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Dashboard</h1>
        <p id="last-updated" class="text-sm text-gray-500"></p>
    </div>
    
    <div id="dashboard-content">
        <div class="text-center py-10">
            <div class="spinner border-t-4 border-blue-500 border-solid rounded-full w-12 h-12 animate-spin"></div>
            <p class="mt-4 text-gray-600">Loading your dashboard...</p>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>

<script nonce="<?= $cspNonce ?>">
    // Make the user data available to our external script
    const userData = <?= $userDataForJs ?>;
</script>
<script nonce="<?= $cspNonce ?>" src="/assets/js/dashboard.js" defer></script>