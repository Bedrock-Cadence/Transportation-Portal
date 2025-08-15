<?php
$page_title = 'Dashboard';
require_once 'header.php';

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// Get current user role for use in the display
$user_role = $_SESSION['user_role'];
?>

<div class="p-5 mb-4 bg-white rounded-3 shadow-sm">
    <div class="container-fluid py-5">
        <h1 class="display-5 fw-bold">Howdy, Partner!</h1>
        <p class="col-md-8 fs-4">Welcome to the Bedrock Cadence Portal.</p>
        <p>
            <b>Your Role:</b> <?php echo htmlspecialchars($user_role); ?>
        </p>
    </div>
</div>

<div class="row" id="dashboard-content">
    <div class="col-12 text-center">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2">Loading your dashboard...</p>
    </div>
</div>

<?php
// --- FIX: Removed the unnecessary $mysqli->close(); call ---
require_once 'footer.php';
?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const dashboardContent = document.getElementById('dashboard-content');
    // We get the user role from the session on the server, but the JS still needs to know which view to render.
    const userRole = '<?php echo $user_role; ?>';

    /**
     * Renders the HTML for the Carrier's dashboard.
     * @param {Array} openTrips - An array of trips open for bidding.
     * @param {Array} awardedTrips - An array of trips awarded to the carrier.
     */
    function renderCarrierDashboard(openTrips, awardedTrips) {
        let contentHtml = '';

        // Open Trips Table
        contentHtml += `
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header fw-bold">Open Trips for Bidding</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Trip ID</th>
                                        <th>Origin</th>
                                        <th>Destination</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>`;
        if (openTrips && openTrips.length > 0) {
            openTrips.forEach(trip => {
                contentHtml += `
                        <tr>
                            <td>${trip.uuid.substring(0, 8)}...</td>
                            <td>${trip.origin_name}</td>
                            <td>${trip.destination_name}</td>
                            <td><a href="trip_details.php?uuid=${trip.uuid}" class="btn btn-primary btn-sm">Bid Now</a></td>
                        </tr>`;
            });
        } else {
            contentHtml += `<tr><td colspan="4" class="text-center">No open trips at this time.</td></tr>`;
        }
        contentHtml += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>`;

        // Awarded Trips Table
        contentHtml += `
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header fw-bold">My Awarded Trips</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Trip ID</th>
                                        <th>Origin</th>
                                        <th>Destination</th>
                                        <th>ETA</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>`;
        if (awardedTrips && awardedTrips.length > 0) {
            awardedTrips.forEach(trip => {
                const awarded_eta = new Date(trip.awarded_eta + 'Z').toLocaleString('en-US', { timeZone: 'America/Chicago', month: 'short', day: 'numeric', hour: 'numeric', minute: 'numeric', hour12: true });
                contentHtml += `
                        <tr>
                            <td>${trip.uuid.substring(0, 8)}...</td>
                            <td>${trip.origin_name}</td>
                            <td>${trip.destination_name}</td>
                            <td>${awarded_eta}</td>
                            <td><a href="awarded_trip_details.php?uuid=${trip.uuid}" class="btn btn-success btn-sm">View Details</a></td>
                        </tr>`;
            });
        } else {
            contentHtml += `<tr><td colspan="5" class="text-center">No trips have been awarded to you yet.</td></tr>`;
        }
        contentHtml += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>`;
        
        dashboardContent.innerHTML = contentHtml;
    }

    /**
     * Renders the HTML for the Facility's dashboard.
     * @param {Array} recentTrips - An array of trips created by the facility in the last 24 hours.
     */
    function renderFacilityDashboard(recentTrips) {
        let contentHtml = '';

        // Recent Trips Table
        contentHtml += `
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header fw-bold">My Trips (Last 24 Hours)</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Trip ID</th>
                                        <th>Status</th>
                                        <th>Origin</th>
                                        <th>Destination</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>`;
        if (recentTrips && recentTrips.length > 0) {
            recentTrips.forEach(trip => {
                // Capitalize the first letter of the status for better display
                const status = trip.status.charAt(0).toUpperCase() + trip.status.slice(1);
                contentHtml += `
                        <tr>
                            <td>${trip.uuid.substring(0, 8)}...</td>
                            <td>${status}</td>
                            <td>${trip.origin_name}</td>
                            <td>${trip.destination_name}</td>
                            <td><a href="view_trip.php?uuid=${trip.uuid}" class="btn btn-info btn-sm">View</a></td>
                        </tr>`;
            });
        } else {
            contentHtml += `<tr><td colspan="5" class="text-center">No trips created in the last 24 hours.</td></tr>`;
        }
        contentHtml += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>`;

        dashboardContent.innerHTML = contentHtml;
    }

    /**
     * Renders the HTML for the Admin's dashboard.
     * @param {Array} activityFeed - An array of recent activity logs.
     */
    function renderAdminDashboard(activityFeed) {
        let contentHtml = '';

        // Real-time Activity Feed
        contentHtml += `
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header fw-bold">Real-time Portal Activity</div>
                    <ul class="list-group list-group-flush" id="activity-feed">`;
        if (activityFeed && activityFeed.length > 0) {
            activityFeed.forEach(activity => {
                const timestamp = new Date(activity.timestamp + 'Z').toLocaleString('en-US', { timeZone: 'America/Chicago', month: 'short', day: 'numeric', hour: 'numeric', minute: 'numeric', hour12: true });
                const userIdentifier = activity.email ? activity.email : 'An unknown user';
                contentHtml += `
                        <li class="list-group-item">
                            <small class="text-muted">${timestamp}</small><br>
                            <strong>${userIdentifier}</strong>: ${activity.activity_description}
                        </li>`;
            });
        } else {
            contentHtml += `<li class="list-group-item text-center">No recent activity.</li>`;
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
            // --- CHANGE: Switched to GET and removed the body, as the server now uses the session ---
            const response = await fetch('api/dashboard_data.php', {
                method: 'GET', // Or 'POST' with an empty body if you prefer
                headers: { 'Accept': 'application/json' },
            });

            if (!response.ok) {
                throw new Error(`Server responded with status: ${response.status}`);
            }
            
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
                dashboardContent.innerHTML = `<div class="alert alert-danger" role="alert">Error loading dashboard data: ${data.error}</div>`;
            }

        } catch (error) {
            console.error('Failed to fetch dashboard data:', error);
            dashboardContent.innerHTML = '<div class="alert alert-danger" role="alert">A network error occurred or the server returned an invalid response.</div>';
        } finally {
             // --- IMPROVEMENT: Use setTimeout to schedule the next update after this one completes ---
            setTimeout(updateDashboard, 10000); // Poll every 10 seconds
        }
    }

    // Call the function once on page load
    updateDashboard();
});
</script>