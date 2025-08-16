<?php
// FILE: index.php

// 1. Set the page title for the header.
$page_title = 'Dashboard';

// 2. Include the header, which also handles session startup and database connection.
require_once 'header.php';

// 3. Security Check: If the user isn't logged in, send them to the login page.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// NOTE: The database connection is already established by header.php -> init.php.
// The following line is redundant and has been removed to prevent errors.
// require_once __DIR__ . '/../../app/db_connect.php';

// 4. Get necessary user data from the session for the JavaScript below.
$user_role = $_SESSION['user_role'];
$entity_type = $_SESSION['entity_type'];
$entity_name = $_SESSION['entity_name'];
$user_timezone = USER_TIMEZONE;

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


<?php
// This includes the footer and necessary closing tags.
require_once 'footer.php';
?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const dashboardContent = document.getElementById('dashboard-content');
    const userRole = '<?php echo $user_role; ?>';
    const userEntityType = '<?php echo $entity_type; ?>';
    const lastUpdatedElement = document.getElementById('last-updated');
    const userTimeZone = '<?php echo $user_timezone; ?>';

    /**
     * Renders the HTML for the Carrier's dashboard using Tailwind CSS classes.
     * Can either set the innerHTML of the dashboard directly or return the HTML string.
     */
    function renderCarrierDashboard(openTrips, awardedTrips, returnHtml = false) {
        let contentHtml = `
            <div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200">
                <div class="px-6 py-4 border-b bg-gray-50">
                    <h2 class="text-xl font-semibold text-gray-800">Open Trips for Bidding</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trip ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Origin</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Destination</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">`;
        if (openTrips && openTrips.length > 0) {
            openTrips.forEach(trip => {
                contentHtml += `
                    <tr class="hover:bg-gray-50 transition-colors duration-150 ease-in-out">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 font-mono">${trip.uuid.substring(0, 8)}...</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">${trip.origin_name}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">${trip.destination_name}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">
                            <a href="trip_details.php?uuid=${trip.uuid}" class="inline-block font-bold py-2 px-4 rounded-md text-sm text-white transition-transform transform hover:scale-105 shadow-md focus:outline-none focus:ring-2 focus:ring-offset-2 bg-blue-600 hover:bg-blue-700 focus:ring-blue-500">Bid Now</a>
                        </td>
                    </tr>`;
            });
        } else {
            contentHtml += `<tr><td colspan="4" class="text-center py-6 text-gray-500">No open trips at this time.</td></tr>`;
        }
        contentHtml += `
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200">
                <div class="px-6 py-4 border-b bg-gray-50">
                    <h2 class="text-xl font-semibold text-gray-800">My Awarded Trips</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trip ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Origin</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Destination</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ETA</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">`;
        if (awardedTrips && awardedTrips.length > 0) {
            awardedTrips.forEach(trip => {
                const awarded_eta = new Date(trip.awarded_eta + 'Z').toLocaleString('en-US', { timeZone: 'America/Chicago', month: 'short', day: 'numeric', hour: 'numeric', minute: 'numeric', hour12: true });
                contentHtml += `
                    <tr class="hover:bg-gray-50 transition-colors duration-150 ease-in-out">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 font-mono">${trip.uuid.substring(0, 8)}...</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">${trip.origin_name}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">${trip.destination_name}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">${awarded_eta}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">
                            <a href="awarded_trip_details.php?uuid=${trip.uuid}" class="inline-block font-bold py-2 px-4 rounded-md text-sm text-white transition-transform transform hover:scale-105 shadow-md focus:outline-none focus:ring-2 focus:ring-offset-2 bg-green-600 hover:bg-green-700 focus:ring-green-500">View Details</a>
                        </td>
                    </tr>`;
            });
        } else {
            contentHtml += `<tr><td colspan="5" class="text-center py-6 text-gray-500">No trips have been awarded to you yet.</td></tr>`;
        }
        contentHtml += `
                        </tbody>
                    </table>
                </div>
            </div>`;
        
        if (returnHtml) {
            return `<div class="grid grid-cols-1 gap-8">${contentHtml}</div>`;
        }
        dashboardContent.innerHTML = `<div class="grid grid-cols-1 gap-8">${contentHtml}</div>`;
    }

    /**
     * Renders the HTML for the Facility's dashboard using Tailwind CSS classes.
     * Can either set the innerHTML of the dashboard directly or return the HTML string.
     */
    function renderFacilityDashboard(recentTrips, returnHtml = false) {
        let contentHtml = `
            <div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200">
                <div class="px-6 py-4 border-b bg-gray-50">
                    <h2 class="text-xl font-semibold text-gray-800">Company Trips (Last 24 Hours)</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trip ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Origin</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Destination</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">`;
        if (recentTrips && recentTrips.length > 0) {
            recentTrips.forEach(trip => {
                const status = trip.status.charAt(0).toUpperCase() + trip.status.slice(1);
                contentHtml += `
                    <tr class="hover:bg-gray-50 transition-colors duration-150 ease-in-out">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 font-mono">${trip.uuid.substring(0, 8)}...</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">${status}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">${trip.origin_name}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">${trip.destination_name}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">
                            <a href="view_trip.php?uuid=${trip.uuid}" class="btn btn-info">View</a>
                        </td>
                    </tr>`;
            });
        } else {
            contentHtml += `<tr><td colspan="5" class="text-center py-6 text-gray-500">No trips created in the last 24 hours.</td></tr>`;
        }
        contentHtml += `
                        </tbody>
                    </table>
                </div>
            </div>`;
        
        if (returnHtml) {
            return contentHtml;
        }
        dashboardContent.innerHTML = contentHtml;
    }

    /**
     * Renders the HTML for the Admin's activity feed.
     * Can either set the innerHTML of the dashboard directly or return the HTML string.
     */
    function renderAdminDashboard(activityFeed, returnHtml = false) {
        let contentHtml = `
            <div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200">
                <div class="px-6 py-4 border-b bg-gray-50">
                    <h2 class="text-xl font-semibold text-gray-800">Real-time Portal Activity</h2>
                </div>
                <div>
                    <ul class="divide-y divide-gray-200">`;
        if (activityFeed && activityFeed.length > 0) {
            activityFeed.forEach(activity => {
                const timestamp = new Date(activity.timestamp + 'Z').toLocaleString('en-US', { timeZone: 'America/Chicago', month: 'short', day: 'numeric', hour: 'numeric', minute: 'numeric', hour12: true });
                const userIdentifier = activity.email ? activity.email : 'An unknown user';
                contentHtml += `
                                <li class="px-6 py-4 hover:bg-gray-50 transition-colors duration-150 ease-in-out">
                                    <p class="text-sm text-gray-800"><strong>${userIdentifier}</strong>: ${activity.message}</p>
                                    <p class="text-xs text-gray-500 mt-1">${timestamp}</p>
                                </li>`;
            });
        } else {
            contentHtml += `<li class="text-center py-6 text-gray-500">No recent activity.</li>`;
        }
        contentHtml += `
                    </ul>
                </div>
            </div>`;

        if (returnHtml) {
            return contentHtml;
        }
        dashboardContent.innerHTML = contentHtml;
    }

    /**
     * Fetches data from the server and updates the dashboard.
     */
    async function updateDashboard() {
        try {
            const response = await fetch('https://bedrockcadence.com/api/dashboard_data.php', {
                method: 'POST',
                // FIX: This crucial line tells the browser to send cookies with the request.
                credentials: 'include', 
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (!response.ok) throw new Error(`Server responded with status: ${response.status}`);
            const result = await response.json();
            
            if (result.success) {
                const data = result.data;
                // --- FIX: Use 'bedrock' for the entity_type check for admins ---
                if (userEntityType === 'bedrock') {
                    let adminHtml = '<div class="space-y-8">';
                    // Admin sees all relevant sections
                    if (data.recentTrips) {
                        adminHtml += renderFacilityDashboard(data.recentTrips, true);
                    }
                    if (data.openTrips || data.awardedTrips) {
                        adminHtml += renderCarrierDashboard(data.openTrips, data.awardedTrips, true);
                    }
                    if (data.activityFeed) {
                        adminHtml += renderAdminDashboard(data.activityFeed, true);
                    }
                    adminHtml += '</div>';
                    dashboardContent.innerHTML = adminHtml;

                } else if (userEntityType === 'facility') {
                    renderFacilityDashboard(data.recentTrips);

                } else if (userEntityType === 'carrier') {
                    renderCarrierDashboard(data.openTrips, data.awardedTrips);

                } else {
                    dashboardContent.innerHTML = `<div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative" role="alert">Your user role does not have a dashboard view.</div>`;
                }
            } else {
                dashboardContent.innerHTML = `<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Error: ${result.error}</div>`;
            }
        } catch (error) {
            console.error('Failed to fetch dashboard data:', error);
            dashboardContent.innerHTML = `<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">A network error occurred. Please try again later.</div>`;
        } finally {
            if (lastUpdatedElement) {
                lastUpdatedElement.textContent = `Last Update: ${new Date().toLocaleTimeString('en-US', { timeZone: userTimeZone, hour: '2-digit', minute: '2-digit', second: '2-digit', timeZoneName: 'short', hour12: false })}`;
            }
            // Schedule the next update
            setTimeout(updateDashboard, 10000);
        }   
    }

    // Initial call to load the dashboard
    updateDashboard();
});
</script>