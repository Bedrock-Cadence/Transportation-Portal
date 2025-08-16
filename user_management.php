<?php
// FILE: index.php

// 1. Set the page title for the header.
$page_title = 'User Management';

// 2. Include the header, which also handles session startup.
require_once 'header.php';

// 3. Security Check: If the user isn't logged in, send them to the login page.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// Security Check: Only allow authorized roles to access this page.
$allowed_roles = ['facility_superuser', 'carrier_superuser', 'bedrock_admin'];
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], $allowed_roles)) {
    // Redirect to a dashboard or show an unauthorized message.
    header("location: dashboard.php");
    exit;
}

// 4. Include the database connection file. The $mysqli object is now available for use.
require_once __DIR__ . '/../../app/db_connect.php';

// Initialize arrays to hold active and inactive users.
$active_users = [];
$inactive_users = [];

// Prepare the SQL query based on the user's role.
try {
    $sql = "SELECT uuid, first_name, last_name, email, role, is_active FROM users";

    // If the user is a superuser (not a bedrock_admin), we need to filter by their entity ID.
    if ($_SESSION['user_role'] === 'carrier_superuser' || $_SESSION['user_role'] === 'facility_superuser') {
        // We're assuming the entity_id is stored in the session.
        if (!isset($_SESSION['entity_id'])) {
            // Log an error and handle gracefully if entity_id is missing.
            error_log("Missing entity_id for superuser in session.");
            // Maybe redirect or show an error message to the user.
            echo '<p class="text-red-500">Error: Your account is missing an associated entity ID. Please contact support.</p>';
        } else {
            $sql .= " WHERE entity_id = ?";
            // Use the established $mysqli connection object.
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param("i", $_SESSION['entity_id']);
        }
    } else {
        // If the user is a bedrock_admin, query all users.
        // Use the established $mysqli connection object.
        $stmt = $mysqli->prepare($sql);
    }
    
    // Check if the statement was successfully prepared before executing.
    if (isset($stmt)) {
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                // Separate users into active and inactive lists.
                if ($row['is_active']) {
                    $active_users[] = $row;
                } else {
                    $inactive_users[] = $row;
                }
            }
        }
    }

} catch (Exception $e) {
    // Log the error and display a friendly message.
    error_log("Database query error: " . $e->getMessage());
    echo '<p class="text-red-500">A problem occurred while retrieving user data. Please try again later.</p>';
}

// Function to translate internal roles to display names.
function getDisplayName($role) {
    switch ($role) {
        case 'carrier_superuser':
            return 'Carrier Admin';
        case 'carrier_user':
            return 'Carrier Staff';
        case 'facility_superuser':
            return 'Facility Admin';
        case 'facility_user':
            return 'Facility Staff';
        case 'bedrock_admin':
            return 'Bedrock Employee';
        default:
            return ucfirst(str_replace('_', ' ', $role));
    }
}
?>

<div id="dashboard-container" class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">User Management</h1>
    </div>

    <!-- Active Users Section -->
    <div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200 mb-8">
        <div class="px-6 py-4 border-b bg-gray-50">
            <h2 class="text-xl font-semibold text-gray-800">Active Users</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Access</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($active_users)): ?>
                        <tr><td colspan="4" class="px-6 py-4 text-center text-gray-500">No active users found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($active_users as $user): ?>
                            <tr class="hover:bg-gray-50 transition-colors duration-150 ease-in-out">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?php echo htmlspecialchars($user['email']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?php echo htmlspecialchars(getDisplayName($user['role'])); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">
                                    <a href="user_profile.php?uuid=<?php echo htmlspecialchars($user['uuid']); ?>" class="btn btn-info text-blue-600 hover:text-blue-800 font-semibold">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Inactive Users Section -->
    <div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200">
        <div class="px-6 py-4 border-b bg-gray-50">
            <h2 class="text-xl font-semibold text-gray-800">Inactive Users</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Access</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($inactive_users)): ?>
                        <tr><td colspan="4" class="px-6 py-4 text-center text-gray-500">No inactive users found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($inactive_users as $user): ?>
                            <tr class="hover:bg-gray-50 transition-colors duration-150 ease-in-out">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?php echo htmlspecialchars($user['email']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?php echo htmlspecialchars(getDisplayName($user['role'])); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">
                                    <a href="user_profile.php?uuid=<?php echo htmlspecialchars($user['uuid']); ?>" class="btn btn-info text-blue-600 hover:text-blue-800 font-semibold">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
// This includes the footer and necessary closing tags.
require_once 'footer.php';
?>