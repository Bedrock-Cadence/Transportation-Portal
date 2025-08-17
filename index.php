<?php
// FILE: public/index.php

require_once 'init.php';

// --- Security Check: If the user isn't logged in, send them to the login page. ---
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    redirect('login.php');
}

$page_title = 'Dashboard';

// --- Prepare User Data for JavaScript ---
// json_encode is the secure and correct way to pass PHP data to JavaScript
$userData = json_encode([
    'userRole' => $_SESSION['user_role'] ?? '',
    'entityType' => $_SESSION['entity_type'] ?? '',
    'userTimezone' => USER_TIMEZONE // From init.php
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
            <div class="spinner"></div>
            <p class="mt-4 text-gray-600">Loading your dashboard...</p>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const dashboardContent = document.getElementById('dashboard-content');
    const lastUpdatedElement = document.getElementById('last-updated');
    const userData = JSON.parse('<?= $userData; ?>');

    // This function would contain the HTML rendering logic from your original file
    function renderDashboard(data) {
        // Based on userData.entityType, call your renderCarrierDashboard,
        // renderFacilityDashboard, or renderAdminDashboard functions here.
        // Example:
        if (userData.entityType === 'carrier') {
            // dashboardContent.innerHTML = renderCarrierDashboard(data.openTrips, data.awardedTrips);
        } else if (userData.entityType === 'facility') {
            // dashboardContent.innerHTML = renderFacilityDashboard(data.recentTrips);
        } else if (userData.entityType === 'bedrock') { // Assuming 'admin' users have entity_type 'bedrock'
            // dashboardContent.innerHTML = renderAdminDashboard(data.activityFeed);
        } else {
             dashboardContent.innerHTML = `<div class="bg-yellow-100 p-4 rounded-md">Your user role does not have a dashboard view.</div>`;
        }
    }

    /**
     * Fetches data from a dedicated API endpoint and updates the dashboard.
     */
    async function updateDashboard() {
        try {
            // In a production app, this would be a dedicated API file (e.g., /api/dashboard.php)
            // For now, it points to a placeholder.
            const response = await fetch('/api/dashboard_data.php', {
                method: 'GET', // Or POST if needed
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            
            if (!response.ok) {
                throw new Error(`Server responded with status: ${response.status}`);
            }
            const result = await response.json();
            
            if (result.success) {
                renderDashboard(result.data);
            } else {
                dashboardContent.innerHTML = `<div class="bg-red-100 p-4 rounded-md">Error: ${result.error || 'Could not load dashboard data.'}</div>`;
            }
        } catch (error) {
            console.error('Failed to fetch dashboard data:', error);
            dashboardContent.innerHTML = `<div class="bg-red-100 p-4 rounded-md">A network error occurred. Please try again later.</div>`;
        } finally {
            if (lastUpdatedElement) {
                const now = new Date();
                lastUpdatedElement.textContent = `Last Update: ${now.toLocaleTimeString('en-US', { timeZone: userData.userTimezone })}`;
            }
            // Refresh every 30 seconds
            setTimeout(updateDashboard, 30000);
        }   
    }

    // Initial call to load the dashboard
    updateDashboard();
});
</script>