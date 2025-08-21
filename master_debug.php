<?php
// FILE: public_html/portal/master_debugger.php
//
// HOW TO USE:
// 1. Place this file in the same directory as your `view_trip.php`.
// 2. Configure the TRIP_UUID_TO_TEST with the trip you want to debug.
// 3. Configure the MOCK_USER_PROFILE to emulate the user role you want to test.
// 4. Open this file in your browser (e.g., yoursite.com/portal/master_debugger.php)

// --- STEP 1: CONFIGURATION ---

// Paste the UUID of the trip you are testing.
const TRIP_UUID_TO_TEST = '3c66605c-7e2a-11f0-9e50-04e3655061f8'; // <--- IMPORTANT

// Define the user you want to emulate.
const MOCK_USER_PROFILE = [
    'is_logged_in'    => true,
    'user_id'         => 1, // A fake user ID for logging
    'entity_type'     => 'carrier', // 'carrier', 'facility', or 'bedrock'
    'entity_id'       => 1,     // The ID of the carrier/facility to test
];

// --- END OF CONFIGURATION ---


// --- BOOTSTRAP & MOCKING ---

// Error reporting to ensure we see everything
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// A simple trace function to see the execution flow
function trace($message, $data = null) {
    echo '<pre style="background-color: #1F2937; color: #F9FAFB; border-left: 4px solid #3B82F6; padding: 12px; margin: 8px; font-family: monospace; font-size: 14px; white-space: pre-wrap; word-wrap: break-word;">';
    echo '<strong style="color: #60A5FA;">TRACE:</strong> <span style="color: #D1D5DB;">' . htmlspecialchars($message) . '</span>';
    if ($data !== null) {
        echo "<br><span style='color: #9CA3AF;'>--- DATA ---</span><br>";
        echo htmlspecialchars(print_r($data, true));
    }
    echo '</pre>';
    // Force output to the browser immediately
    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();
}

/**
 * Mock Authentication Class
 * This class overrides the real Auth class to let us emulate any user.
 * It will be loaded before init.php, so PHP uses this version.
 */
class Auth {
    private static $user = MOCK_USER_PROFILE;

    public static function isLoggedIn(): bool {
        return self::$user['is_logged_in'] ?? false;
    }

    public static function user(string $key) {
        return self::$user[$key] ?? null;
    }

    public static function hasRole(string $role): bool {
        // Simple role check for this debugger
        if ($role === 'admin' && self::$user['entity_type'] === 'bedrock') {
            return true;
        }
        return false;
    }
}

// Load the application environment AFTER our mock Auth class is defined.
require_once __DIR__ . '/../../app/init.php';


// --- DEBUGGER EXECUTION ---

echo '<div style="font-family: sans-serif; background-color: #111827; padding: 20px;">';
echo '<h1 style="color: #F9FAFB; border-bottom: 2px solid #374151; padding-bottom: 10px;">Master Debugger Trace</h1>';

try {
    trace('Debugger initiated.');
    trace('Emulating User Profile:', MOCK_USER_PROFILE);
    trace('Testing Trip UUID:', TRIP_UUID_TO_TEST);

    if (TRIP_UUID_TO_TEST === 'ENTER_TRIP_UUID_HERE' || empty(TRIP_UUID_TO_TEST)) {
        trace('ERROR: Please set the TRIP_UUID_TO_TEST constant on line 13.');
        die();
    }

    // --- REPLICATE THE LOGIC FROM view_trip.php ---

    trace('Instantiating TripService.');
    $tripService = new TripService();

    trace('Calling getTripByUuid()...');
    $trip = $tripService->getTripByUuid(TRIP_UUID_TO_TEST);

    if (!$trip) {
        trace('FATAL: Trip not found in the database for the given UUID.');
        die();
    }

    trace('Trip data fetched successfully.', [
        'trip_id' => $trip['id'],
        'status' => $trip['status'],
        'facility_id' => $trip['facility_id'],
        'carrier_id' => $trip['carrier_id'],
        'bidding_closes_at' => $trip['bidding_closes_at']
    ]);

    trace('Now calling determineViewMode() to check authorization...');

    // --- THE CORE TEST ---
    // We will now call the function that is failing.
    // To get a detailed trace, we'll temporarily modify the TripService class.
    // For this to work, you MUST add the trace() calls inside your actual tripService.php file.

    echo '<div style="background-color: #374151; border: 1px solid #4B5563; padding: 15px; margin: 10px 0; border-radius: 5px;">';
    echo '<h3 style="color: #F9FAFB; margin-top: 0;">ACTION REQUIRED: Add Traces to `tripService.php`</h3>';
    echo '<p style="color: #D1D5DB;">To see the step-by-step logic inside `determineViewMode`, please temporarily add the `trace()` function (copy from line 30 of this file) to the top of your `app/tripService.php` file. Then, add trace calls inside its `determineViewMode` method like the example below:</p>';
    echo '<pre style="background-color: #1F2937; color: #D1D5DB; padding: 10px; border-radius: 4px; font-size: 12px;">';
    echo htmlspecialchars(
'public function determineViewMode(array $trip): string {
    trace("--- Entering determineViewMode() ---");
    $entityId = Auth::user(\'entity_id\');
    $entityType = Auth::user(\'entity_type\');
    trace("User Info:", ["entity_id" => $entityId, "entity_type" => $entityType]);

    if ($entityType === \'carrier\') {
        trace("User is a carrier. Checking bidding window...");
        $biddingIsOpen = new DateTime() < new DateTime($trip[\'bidding_closes_at\'], new DateTimeZone(\'UTC\'));
        trace("Bidding is open:", $biddingIsOpen ? "Yes" : "No");
        // ...add more traces...
    }
    // ...
}'
    );
    echo '</pre>';
    echo '</div>';


    $viewMode = $tripService->determineViewMode($trip);

    trace('determineViewMode() finished.');
    trace('FINAL RESULT - View Mode:', $viewMode);

    echo '<hr style="border-color: #374151; margin: 20px 0;">';

    if ($viewMode === 'unauthorized') {
        echo '<h2 style="color: #F87171;">Conclusion: Access DENIED.</h2>';
        echo '<p style="color: #D1D5DB;">The script determined the user is unauthorized. In the live application, this would have triggered a redirect, causing the white screen. The trace log above shows the exact step where the "unauthorized" decision was made.</p>';
    } else {
        echo '<h2 style="color: #34D399;">Conclusion: Access GRANTED.</h2>';
        echo '<p style="color: #D1D5DB;">The script determined the user has access with the role: <strong>' . $viewMode . '</strong>. If you still got a white screen with this role, the error is happening later in `view_trip.php` during the HTML rendering phase.</p>';
    }


} catch (Throwable $e) {
    trace('A FATAL UNCAUGHT ERROR OCCURRED:', [
        'error_message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    echo '<h2 style="color: #F87171;">Conclusion: Script Halted by Fatal Error.</h2>';
    echo '<p style="color: #D1D5DB;">The trace above shows the exact point where the application crashed.</p>';
}

echo '</div>';