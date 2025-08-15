<?php
$page_title = 'Dashboard';
require_once 'header.php';

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// Ensure the db_connect.php is included
require_once __DIR__ . '/../../app/db_connect.php';

// Get current user role and entity ID from the session for use in JavaScript
$user_role = $_SESSION['user_role'];
$user_id = $_SESSION['entity_id'];
?>

<!-- Main content container -->
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
    <!-- This is where the user-specific content will be loaded -->
</div>

<?php
// Close the database connection
$mysqli->close();
require_once 'footer.php';
?>

<!-- This script handles the real-time data fetching and display -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const dashboardContent = document.getElementById('dashboard-content');
    const userRole = '<?php echo $user_role; ?>';
    const userId = '<?php echo $user_id; ?>';

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
        if (openTrips.length > 0) {
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
        if (awardedTrips.length > 0) {
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
        if (recentTrips.length > 0) {
            recentTrips.forEach(trip => {
                contentHtml += `
                    <tr>
                        <td>${trip.uuid.substring(0, 8)}...</td>
                        <td>${trip.status}</td>
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
        if (activityFeed.length > 0) {
            activityFeed.forEach(activity => {
                const timestamp = new Date(activity.timestamp + 'Z').toLocaleString('en-US', { timeZone: 'America/Chicago', month: 'short', day: 'numeric', hour: 'numeric', minute: 'numeric', hour12: true });
                contentHtml += `
                    <li class="list-group-item">
                        <small class="text-muted">${timestamp}</small><br>
                        <strong>User ${activity.user_id}</strong>: ${activity.activity_description}
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
        const payload = {
            role: userRole,
            userId: userId
        };

        try {
            const response = await fetch('api/dashboard_data.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await response.json();
            
            // Check for success or error from the server
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
                // If the server returned an error, display it
                dashboardContent.innerHTML = `<div class="alert alert-danger" role="alert">Error loading dashboard data: ${data.error}</div>`;
            }

        } catch (error) {
            // Handle network or JSON parsing errors
            console.error('Failed to fetch dashboard data:', error);
            dashboardContent.innerHTML = '<div class="alert alert-danger" role="alert">A network error occurred or the server returned an invalid response.</div>';
        }
    }

    // Call the function once on page load
    updateDashboard();

    // Set an interval to update the dashboard every 10 seconds
    setInterval(updateDashboard, 10000);
});
</script>