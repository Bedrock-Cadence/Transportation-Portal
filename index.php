<?php
// FILE: index.php

// 1. Set the page title for the header.
$page_title = 'Dashboard';

// 2. Include the header, which also handles session startup.
require_once 'header.php';

// 3. Security Check: If the user isn't logged in, send them to the login page.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// 4. Get necessary user data from the session for the JavaScript below.
$user_role = $_SESSION['user_role'] ?? 'guest';
$entity_name = $_SESSION['entity_name'] ?? 'Bedrock Cadence';

?>

<div id="dashboard-container">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Dashboard</h1>
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

    /**
     * Renders the HTML for the Carrier's dashboard using Tailwind CSS classes.
     */
    function renderCarrierDashboard(openTrips, awardedTrips) {
        let contentHtml = `
            <div class="grid grid-cols-1 gap-8">
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
                </div>
            </div>`;
        dashboardContent.innerHTML = contentHtml;
    }

    /**
     * Renders the HTML for the Facility's dashboard using Tailwind CSS classes.
     */
    function renderFacilityDashboard(recentTrips) {
        let contentHtml = `
            <div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200">
                <div class="px-6 py-4 border-b bg-gray-50">
                    <h2 class="text-xl font-semibold text-gray-800">My Trips (Last 24 Hours)</h2>
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
        dashboardContent.innerHTML = contentHtml;
    }

    /**
     * Renders the HTML for the Admin's dashboard.
     */
    function renderAdminDashboard(activityFeed) {
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
            const data = await response.json();
            
            if (data.success) {
                switch (userRole) {
                    case 'carrier_user':
                    case 'carrier_superuser':
                        renderCarrierDashboard(data.data.openTrips, data.data.awardedTrips);
                        break;
                    case 'facility_user':
                    case 'facility_superuser':
                        renderFacilityDashboard(data.data.recentTrips);
                        break;
                    case 'bedrock_admin':
                        renderAdminDashboard(data.data.activityFeed);
                        break;
                }
            } else {
                dashboardContent.innerHTML = `<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Error: ${data.error}</div>`;
            }
        } catch (error) {
            console.error('Failed to fetch dashboard data:', error);
            dashboardContent.innerHTML = `<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">A network error occurred. Please try again later.</div>`;
        } finally {
            // Schedule the next update
            setTimeout(updateDashboard, 10000);
        }
    }

    // Initial call to load the dashboard
    updateDashboard();
});
</script>