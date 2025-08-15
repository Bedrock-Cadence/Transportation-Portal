<?php
// The page title for the header
$page_title = 'Dashboard';
// This includes our session_config.php and starts the session
require_once 'header.php';

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// Get current user role for use in the display
$user_role = $_SESSION['user_role'];

// --- START: Fetch Entity Name ---
require_once __DIR__ . '/../app/db_connect.php'; // Ensures we have a database connection

$entity_name = ''; // Initialize the variable

// Check if the user is associated with a specific entity (carrier or facility)
if (!empty($_SESSION['entity_id']) && !empty($_SESSION['entity_type'])) {
    $table_name = '';
    // Determine which table to query based on the entity type stored in the session
    switch ($_SESSION['entity_type']) {
        case 'carrier':
            $table_name = 'carriers';
            break;
        case 'facility':
            $table_name = 'facilities';
            break;
    }

    if ($table_name) {
        // Prepare and execute a query to get the entity's name securely
        $sql = "SELECT name FROM $table_name WHERE id = ?";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("i", $_SESSION['entity_id']);
            $stmt->execute();
            $stmt->bind_result($name);
            if ($stmt->fetch()) {
                $entity_name = $name;
            }
            $stmt->close();
        }
    }
}
// --- END: Fetch Entity Name ---
?>

<!-- New Dynamic Texan Greeting Block -->
<div class="p-5 mb-4 bg-white rounded-3 shadow-sm">
    <div class="container-fluid py-5">
        <!-- The Greeting will be dynamically inserted here -->
        <h1 id="dynamic-greeting" class="display-5 fw-bold">Howdy!</h1>
        <!-- The Joke/Saying will be dynamically inserted here -->
        <p id="dynamic-saying" class="col-md-8 fs-4">Hang tight while we fetch a good one for ya...</p>
        
        <?php // This block will only display if we successfully found a company name
        if (!empty($entity_name)): ?>
            <p class="lead mt-4">
                Representing: <strong><?php echo htmlspecialchars($entity_name); ?></strong>
            </p>
        <?php endif; ?>

    </div>
</div>


<div class="row" id="dashboard-content">
    <!-- This is where the user-specific content will be loaded by the API call -->
    <div class="col-12 text-center">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2">Loading your dashboard...</p>
    </div>
</div>

<?php
// This includes the footer and necessary closing tags
require_once 'footer.php';
?>

<!-- This script handles all the dynamic functionality on the dashboard -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    // --- START: Dynamic Greeting Logic ---

    const greetingElement = document.getElementById('dynamic-greeting');
    const sayingElement = document.getElementById('dynamic-saying');

    const texanGreetings = [
        "Howdy, y'all!", "Well, hey there!", "Mornin'!", "How's it hangin'?", "What's kickin', little chicken?",
        "Howdy, partner!", "Look what the cat dragged in!", "Good to see ya!", "Come on in!", "Well, hey there, stranger!",
        "How y'all doin'?", "What's the good word?", "How have you been?", "Pull up a chair!", "Long time no see!",
        "Howdy-doody!", "Hey, good lookin'!", "What's cookin'?", "How's tricks?", "How's life treatin' ya?",
        "Well, shut my mouth!", "Hey now!", "How's your mom an' 'em?", "What's the rumpus?", "How's every little thing?",
        "Greetings!", "Howdy folks!", "Pleased to meet ya!", "Top of the mornin' to ya!", "What's shakin'?"
    ];

    const texanSayings = [
        "This ain't my first rodeo.", "He's all hat and no cattle.", "Don't mess with Texas.",
        "Bless your heart.", "Hotter than a two-dollar pistol.", "We're in high cotton.",
        "He's so crooked he could swallow nails and spit out corkscrews.", "If you don't like the weather in Texas, just wait five minutes.",
        "This is more fun than a barrel of monkeys.", "She's madder than a wet hen.", "He could talk the legs off a chair.",
        "Don't get your britches in a wad.", "I'm finer than a frog's hair split four ways.", "That dog won't hunt.",
        "He's about as useful as a screen door on a submarine.", "It's blowin' up a gully washer out there.",
        "She's got more nerve than a toothache.", "You can't get blood from a turnip.", "He's got a burr in his saddle.",
        "Don't count your chickens before they hatch.", "The bigger the hat, the smaller the herd.", "Come hell or high water.",
        "He's as happy as a pig in mud.", "You can hang your hat on that.", "It's so dry the trees are bribin' the dogs.",
        "He's slicker than a greased pig.", "That's about as welcome as a skunk at a lawn party.", "I'm so hungry I could eat a horse.",
        "You're barkin' up the wrong tree.", "Don't let the screen door hit you on the way out.", "He's got ants in his pants.",
        "That's a whole 'nother can of worms.", "You can't unscramble an egg.", "He's busier than a one-armed wallpaper hanger.",
        "She fell out of the ugly tree and hit every branch on the way down.", "That's as clear as mud.", "Hold your horses.",
        "It's a real barn burner.", "Don't get your feathers ruffled.", "He's a few bricks shy of a full load.",
        "It's not the size of the dog in the fight, it's the size of the fight in the dog.", "You can put lipstick on a pig, but it's still a pig.",
        "He's as sharp as a marble.", "Don't let your alligator mouth overload your hummingbird tail.", "That's close enough for government work.",
        "I'm fixin' to do it.", "It's hotter than a stolen tamale.", "He's got more money than God.", "Let's light this candle."
    ];

    function updateGreeting() {
        const randomGreeting = texanGreetings[Math.floor(Math.random() * texanGreetings.length)];
        const randomSaying = texanSayings[Math.floor(Math.random() * texanSayings.length)];
        greetingElement.textContent = randomGreeting;
        sayingElement.textContent = randomSaying;
    }

    // Update the greeting every 15 seconds
    setInterval(updateGreeting, 15000);
    // Call it once on page load so it appears immediately
    updateGreeting();

    // --- END: Dynamic Greeting Logic ---


    // --- START: Dashboard Data Fetching Logic ---
    const dashboardContent = document.getElementById('dashboard-content');
    const userRole = '<?php echo $user_role; ?>';

    // ... The rest of your dashboard fetching and rendering functions (renderCarrierDashboard, etc.) remain here ...

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
                                    <strong>${userIdentifier}</strong>: ${activity.message}
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
            const response = await fetch('https://bedrockcadence.com/api/dashboard_data.php', {
                method: 'GET',
                headers: { 'Accept': 'application/json' },
                credentials: 'include' 
            });

            if (!response.ok) {
                const errorText = await response.text();
                console.error('Server responded with an error:', response.status, errorText);
                throw new Error(`The server responded with status: ${response.status}`);
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
            console.error('Failed to fetch or parse dashboard data:', error);
            dashboardContent.innerHTML = '<div class="alert alert-danger" role="alert">A network error occurred or the server returned an invalid response. Please check the browser console for more details.</div>';
        } finally {
            setTimeout(updateDashboard, 10000);
        }
    }

    (async () => {
        try {
            await updateDashboard();
        } catch (e) {
            // Error is already handled by the updateDashboard function
        }
    })();
});
</script>