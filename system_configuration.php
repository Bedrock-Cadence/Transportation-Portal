<?php
// FILE: system_configuration.php

// 1. Set the page title for the header.
$page_title = 'System Configuration';

// 2. Include the header, which also handles session startup.
require_once 'header.php';

// 3. Security Check: Redirect if the user isn't logged in.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// 4. Security Check: Only allow 'superuser' and 'admin' roles to access this page.
$allowed_roles = ['superuser', 'admin'];
$user_role = $_SESSION['user_role'] ?? null;
if (!in_array($user_role, $allowed_roles)) {
    header("location: dashboard.php");
    exit;
}

// Check if this is an AJAX request from the front-end.
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] === 'true';

// 5. Include the database connection file.
require_once __DIR__ . '/../../app/db_connect.php';

// Initialize variables for messages and errors.
$page_message = '';
$page_error = '';
$all_facilities = [];
$all_carriers = [];
$selected_entity = null;
$config_data = [];

// --- Start of Utility Functions ---

/**
 * Logs an action to the user_activity_logs table.
 * @param mysqli $conn The database connection object.
 * @param int $user_id The ID of the user performing the action.
 * @param string $action The type of action (e.g., 'config_updated').
 * @param string $message A detailed message about the action.
 */
function log_user_activity($conn, $user_id, $action, $message) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $log_stmt = $conn->prepare("INSERT INTO user_activity_logs (user_id, action, message, ip_address) VALUES (?, ?, ?, ?)");
    if ($log_stmt) {
        $log_stmt->bind_param("isss", $user_id, $action, $message, $ip_address);
        $log_stmt->execute();
        $log_stmt->close();
    }
}

/**
 * Compares old and new config data and generates a detailed log message.
 * @param array $old_data The original configuration data.
 * @param array $new_data The new configuration data.
 * @return string The detailed log message.
 */
function generate_config_log_message($old_data, $new_data) {
    $changes = [];
    foreach ($new_data as $key => $value) {
        if (!isset($old_data[$key]) || json_encode($old_data[$key]) !== json_encode($value)) {
            $old_value = isset($old_data[$key]) ? (is_array($old_data[$key]) ? json_encode($old_data[$key]) : $old_data[$key]) : 'N/A';
            $new_value = is_array($value) ? json_encode($value) : $value;
            $changes[] = "{$key}: '{$old_value}' -> '{$new_value}'";
        }
    }
    return implode(', ', $changes);
}

// --- End of Utility Functions ---

// --- Start of Form Submission Handling ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $entity_id = $_POST['entity_id'] ?? null;
    $entity_type = $_POST['entity_type'] ?? null;
    $table_name = ($entity_type === 'facility') ? 'facilities' : 'carriers';

    $target_entity_id = ($user_role === 'superuser') ? $_SESSION['entity_id'] : $entity_id;
    $target_entity_type = ($user_role === 'superuser') ? $_SESSION['entity_type'] : $entity_type;

    if ($target_entity_id === null || $target_entity_type === null) {
        $page_error = "Invalid entity selected.";
    } else {
        try {
            // Fetch the old config data for logging.
            $stmt = $mysqli->prepare("SELECT config_settings FROM {$table_name} WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $target_entity_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $old_config_json = $result->fetch_assoc()['config_settings'] ?? '{}';
            $old_config_data = json_decode($old_config_json, true);
            $stmt->close();
            
            // Build the new config data based on the form submission.
            if ($target_entity_type === 'facility') {
                $new_config_data = [
                    'short_bid_duration' => $_POST['short_bid_duration'] ?? 15,
                    'long_bid_duration' => $_POST['long_bid_duration'] ?? 30,
                    'secure_email' => trim($_POST['secure_email'] ?? ''),
                    'preferred_carriers' => $_POST['preferred_carriers'] ?? [],
                    'blacklisted_carriers' => $_POST['blacklisted_carriers'] ?? [],
                    'app_access' => isset($_POST['app_access']) ? 1 : 0
                ];
            } else { // carrier
                $new_config_data = [
                    'secure_email' => trim($_POST['secure_email'] ?? ''),
                    'preferred_facilities' => $_POST['preferred_facilities'] ?? [],
                    'blacklisted_facilities' => $_POST['blacklisted_facilities'] ?? [],
                    'trip_types' => $_POST['trip_types'] ?? [],
                    'ltd_miles' => $_POST['ltd_miles'] ?? 150
                ];
            }
            
            // Serialize the new config data to JSON.
            $new_config_json = json_encode($new_config_data);
            
            // Update the database.
            $stmt = $mysqli->prepare("UPDATE {$table_name} SET config_settings = ? WHERE id = ?");
            $stmt->bind_param("si", $new_config_json, $target_entity_id);
            if ($stmt->execute()) {
                $page_message = "Configuration updated successfully.";
                $log_message = "Configuration for {$target_entity_type} ID: {$target_entity_id} updated. " . generate_config_log_message($old_config_data, $new_config_data);
                log_user_activity($mysqli, $_SESSION['user_id'], 'config_updated', $log_message);
            } else {
                $page_error = "Error updating configuration: " . $stmt->error;
            }
            $stmt->close();

        } catch (Exception $e) {
            $page_error = "An error occurred: " . $e->getMessage();
        }
    }
}
// --- End of Form Submission Handling ---

// --- Start of Page Data Retrieval ---
// Fetch all active facilities and carriers for the admin lists and select options.
$facilities_stmt = $mysqli->prepare("SELECT id, name FROM facilities WHERE is_active = 1 ORDER BY name ASC");
$facilities_stmt->execute();
$facilities_result = $facilities_stmt->get_result();
while ($row = $facilities_result->fetch_assoc()) {
    $all_facilities[] = $row;
}
$facilities_stmt->close();

$carriers_stmt = $mysqli->prepare("SELECT id, name FROM carriers WHERE is_active = 1 ORDER BY name ASC");
$carriers_stmt->execute();
$carriers_result = $carriers_stmt->get_result();
while ($row = $carriers_result->fetch_assoc()) {
    $all_carriers[] = $row;
}
$carriers_stmt->close();

if ($user_role === 'admin') {
    $entity_type = $_GET['entity_type'] ?? null;
    $entity_id = $_GET['entity_id'] ?? null;
    
    if ($is_ajax && $entity_type && $entity_id) {
        $table_name = ($entity_type === 'facility') ? 'facilities' : 'carriers';
        $stmt = $mysqli->prepare("SELECT id, name, config_settings FROM {$table_name} WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $entity_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $selected_entity = $result->fetch_assoc();
        $stmt->close();
        
        if ($selected_entity) {
            $config_data = json_decode($selected_entity['config_settings'], true) ?? [];
            header('Content-Type: application/json');
            echo json_encode([
                'id' => $selected_entity['id'],
                'name' => $selected_entity['name'],
                'type' => $entity_type,
                'config' => $config_data
            ]);
            exit;
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Entity not found.']);
            exit;
        }
    }
} elseif ($user_role === 'superuser') {
    $entity_type = $_SESSION['entity_type'];
    $entity_id = $_SESSION['entity_id'];
    $table_name = ($entity_type === 'facility') ? 'facilities' : 'carriers';

    $stmt = $mysqli->prepare("SELECT id, name, config_settings FROM {$table_name} WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $entity_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $selected_entity = $result->fetch_assoc();
    $stmt->close();

    if ($selected_entity) {
        $config_data = json_decode($selected_entity['config_settings'], true) ?? [];
    } else {
        $page_error = "Your entity record was not found.";
    }
}
$mysqli->close();
?>

<div id="config-container" class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">System Configuration</h1>
    </div>

    <?php if (!empty($page_message)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p class="font-bold">Success</p>
            <p><?= htmlspecialchars($page_message); ?></p>
        </div>
    <?php endif; ?>
    <?php if (!empty($page_error)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p class="font-bold">Error</p>
            <p><?= htmlspecialchars($page_error); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($user_role === 'admin'): ?>
    <!-- Admin View: List of Entities -->
    <div id="admin-entity-list" class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200 mb-6">
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-800">Select an Entity to Configure</h2>
        </div>
        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h3 class="font-bold text-lg mb-2">Facilities</h3>
                <div class="overflow-y-auto max-h-64 border rounded-md">
                    <ul class="divide-y divide-gray-200">
                        <?php if (empty($all_facilities)): ?>
                            <li class="px-4 py-2 text-sm text-gray-500">No active facilities found.</li>
                        <?php else: ?>
                            <?php foreach ($all_facilities as $facility): ?>
                                <li class="px-4 py-2 hover:bg-gray-100 cursor-pointer text-sm" onclick="fetchEntityConfig('facility', <?= htmlspecialchars($facility['id']); ?>)">
                                    <?= htmlspecialchars($facility['name']); ?>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <div>
                <h3 class="font-bold text-lg mb-2">Carriers</h3>
                <div class="overflow-y-auto max-h-64 border rounded-md">
                    <ul class="divide-y divide-gray-200">
                        <?php if (empty($all_carriers)): ?>
                            <li class="px-4 py-2 text-sm text-gray-500">No active carriers found.</li>
                        <?php else: ?>
                            <?php foreach ($all_carriers as $carrier): ?>
                                <li class="px-4 py-2 hover:bg-gray-100 cursor-pointer text-sm" onclick="fetchEntityConfig('carrier', <?= htmlspecialchars($carrier['id']); ?>)">
                                    <?= htmlspecialchars($carrier['name']); ?>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Configuration Form Section -->
    <div id="config-form-section" class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200" style="display: <?= ($user_role === 'admin' && !$selected_entity) ? 'none' : 'block'; ?>;">
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
            <h2 class="text-xl font-semibold text-gray-800">Configuration for: <span id="entity-name-header" class="text-blue-600 font-bold"><?= htmlspecialchars($selected_entity['name'] ?? 'N/A'); ?></span></h2>
            <p class="text-sm text-gray-500" id="entity-type-header"><?= htmlspecialchars(ucfirst($_SESSION['entity_type'] ?? '')); ?></p>
        </div>
        <div class="p-6">
            <form id="config-form" method="POST" action="system_configuration.php" class="space-y-6">
                <input type="hidden" name="entity_id" id="entity-id-input" value="<?= htmlspecialchars($selected_entity['id'] ?? ''); ?>">
                <input type="hidden" name="entity_type" id="entity-type-input" value="<?= htmlspecialchars($_SESSION['entity_type'] ?? ''); ?>">

                <div id="facility-settings-form" style="display: <?= (isset($_SESSION['entity_type']) && $_SESSION['entity_type'] === 'facility') || ($user_role === 'admin' && (($_GET['entity_type'] ?? '') === 'facility' || ($selected_entity && $selected_entity['type'] === 'facility'))) ? 'block' : 'none'; ?>">
                    <!-- Facility Settings -->
                    <div>
                        <label for="short_bid_duration" class="block text-sm font-medium text-gray-700">Bid Duration (<150 miles)</label>
                        <input type="number" id="short_bid_duration" name="short_bid_duration" value="<?= htmlspecialchars($config_data['short_bid_duration'] ?? 15); ?>" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="long_bid_duration" class="block text-sm font-medium text-gray-700">Bid Duration (150+ miles) 
                            <span class="tooltip-container">
                                <span class="text-gray-400">i</span>
                                <span class="tooltip-text">For long-distance transfers, a longer bid duration is recommended.</span>
                            </span>
                        </label>
                        <input type="number" id="long_bid_duration" name="long_bid_duration" value="<?= htmlspecialchars($config_data['long_bid_duration'] ?? 30); ?>" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="secure_email_facility" class="block text-sm font-medium text-gray-700">Secure PHI Email</label>
                        <input type="email" id="secure_email_facility" name="secure_email" value="<?= htmlspecialchars($config_data['secure_email'] ?? ''); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="preferred_carriers" class="block text-sm font-medium text-gray-700">Preferred Carriers</label>
                        <select id="preferred_carriers" name="preferred_carriers[]" multiple class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                             <?php
                                foreach ($all_carriers as $c) {
                                    $selected = in_array($c['id'], $config_data['preferred_carriers'] ?? []) ? 'selected' : '';
                                    echo "<option value=\"{$c['id']}\" {$selected}>" . htmlspecialchars($c['name']) . "</option>";
                                }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label for="blacklisted_carriers" class="block text-sm font-medium text-gray-700">Blacklisted Carriers</label>
                        <select id="blacklisted_carriers" name="blacklisted_carriers[]" multiple class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                             <?php
                                foreach ($all_carriers as $c) {
                                    $selected = in_array($c['id'], $config_data['blacklisted_carriers'] ?? []) ? 'selected' : '';
                                    echo "<option value=\"{$c['id']}\" {$selected}>" . htmlspecialchars($c['name']) . "</option>";
                                }
                            ?>
                        </select>
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" id="app_access" name="app_access" value="1" <?= ($config_data['app_access'] ?? 0) ? 'checked' : ''; ?> class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="app_access" class="ml-2 block text-sm font-medium text-gray-700">Enable Android/iOPS App Access</label>
                    </div>
                </div>

                <div id="carrier-settings-form" style="display: <?= (isset($_SESSION['entity_type']) && $_SESSION['entity_type'] === 'carrier') || ($user_role === 'admin' && (($_GET['entity_type'] ?? '') === 'carrier' || ($selected_entity && $selected_entity['type'] === 'carrier'))) ? 'block' : 'none'; ?>">
                    <!-- Carrier Settings -->
                    <div>
                        <label for="secure_email_carrier" class="block text-sm font-medium text-gray-700">Secure PHI Email</label>
                        <input type="email" id="secure_email_carrier" name="secure_email" value="<?= htmlspecialchars($config_data['secure_email'] ?? ''); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="preferred_facilities" class="block text-sm font-medium text-gray-700">Preferred Facilities</label>
                        <select id="preferred_facilities" name="preferred_facilities[]" multiple class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                             <?php
                                foreach ($all_facilities as $f) {
                                    $selected = in_array($f['id'], $config_data['preferred_facilities'] ?? []) ? 'selected' : '';
                                    echo "<option value=\"{$f['id']}\" {$selected}>" . htmlspecialchars($f['name']) . "</option>";
                                }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label for="blacklisted_facilities" class="block text-sm font-medium text-gray-700">Blacklisted Facilities</label>
                        <select id="blacklisted_facilities" name="blacklisted_facilities[]" multiple class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                             <?php
                                foreach ($all_facilities as $f) {
                                    $selected = in_array($f['id'], $config_data['blacklisted_facilities'] ?? []) ? 'selected' : '';
                                    echo "<option value=\"{$f['id']}\" {$selected}>" . htmlspecialchars($f['name']) . "</option>";
                                }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Trip Types</label>
                        <div class="mt-1 space-y-2">
                            <div class="flex items-center">
                                <input type="checkbox" id="trip_type_stretcher" name="trip_types[]" value="Stretcher" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" <?= in_array('Stretcher', $config_data['trip_types'] ?? []) ? 'checked' : ''; ?>>
                                <label for="trip_type_stretcher" class="ml-2 block text-sm font-medium text-gray-700">Stretcher</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="trip_type_wheelchair" name="trip_types[]" value="Wheelchair" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" <?= in_array('Wheelchair', $config_data['trip_types'] ?? []) ? 'checked' : ''; ?>>
                                <label for="trip_type_wheelchair" class="ml-2 block text-sm font-medium text-gray-700">Wheelchair</label>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label for="ltd_miles" class="block text-sm font-medium text-gray-700">Long-Distance Threshold (miles)
                            <span class="tooltip-container">
                                <span class="text-gray-400">i</span>
                                <span class="tooltip-text">Trips over this distance will show a red "LDT" indicator.</span>
                            </span>
                        </label>
                        <input type="number" id="ltd_miles" name="ltd_miles" value="<?= htmlspecialchars($config_data['ltd_miles'] ?? 150); ?>" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.tooltip-container {
    position: relative;
    display: inline-block;
}
.tooltip-container .tooltip-text {
    visibility: hidden;
    background-color: #333;
    color: #fff;
    text-align: center;
    border-radius: 6px;
    padding: 5px;
    position: absolute;
    z-index: 1;
    width: 200px;
    bottom: 125%;
    left: 50%;
    margin-left: -100px;
    opacity: 0;
    transition: opacity 0.3s;
}
.tooltip-container:hover .tooltip-text {
    visibility: visible;
    opacity: 1;
}
</style>

<?php
// This includes the footer and necessary closing tags.
require_once 'footer.php';
?>

<script>
function fetchEntityConfig(entityType, entityId) {
    const configFormSection = document.getElementById('config-form-section');
    const entityNameHeader = document.getElementById('entity-name-header');
    const entityTypeHeader = document.getElementById('entity-type-header');
    const entityIdInput = document.getElementById('entity-id-input');
    const entityTypeInput = document.getElementById('entity-type-input');

    const facilitySettingsForm = document.getElementById('facility-settings-form');
    const carrierSettingsForm = document.getElementById('carrier-settings-form');

    configFormSection.style.display = 'block';

    fetch(`system_configuration.php?entity_type=${entityType}&entity_id=${entityId}&ajax=true`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok.');
            }
            return response.json();
        })
        .then(data => {
            entityNameHeader.textContent = data.name;
            entityTypeHeader.textContent = `(${data.type.charAt(0).toUpperCase() + data.type.slice(1)})`;
            entityIdInput.value = data.id;
            entityTypeInput.value = data.type;

            // Show/hide the correct form sections
            if (data.type === 'facility') {
                facilitySettingsForm.style.display = 'block';
                carrierSettingsForm.style.display = 'none';

                // Populate facility form fields
                document.getElementById('short_bid_duration').value = data.config.short_bid_duration ?? 15;
                document.getElementById('long_bid_duration').value = data.config.long_bid_duration ?? 30;
                document.getElementById('secure_email_facility').value = data.config.secure_email ?? '';
                document.getElementById('app_access').checked = data.config.app_access === 1;

                // Handle multi-select lists
                Array.from(document.getElementById('preferred_carriers').options).forEach(option => {
                    option.selected = data.config.preferred_carriers?.includes(option.value) ?? false;
                });
                Array.from(document.getElementById('blacklisted_carriers').options).forEach(option => {
                    option.selected = data.config.blacklisted_carriers?.includes(option.value) ?? false;
                });

            } else if (data.type === 'carrier') {
                carrierSettingsForm.style.display = 'block';
                facilitySettingsForm.style.display = 'none';

                // Populate carrier form fields
                document.getElementById('secure_email_carrier').value = data.config.secure_email ?? '';
                document.getElementById('ltd_miles').value = data.config.ltd_miles ?? 150;
                
                // Handle multi-select lists
                Array.from(document.getElementById('preferred_facilities').options).forEach(option => {
                    option.selected = data.config.preferred_facilities?.includes(option.value) ?? false;
                });
                Array.from(document.getElementById('blacklisted_facilities').options).forEach(option => {
                    option.selected = data.config.blacklisted_facilities?.includes(option.value) ?? false;
                });
                
                // Handle checkboxes
                document.getElementById('trip_type_stretcher').checked = data.config.trip_types?.includes('Stretcher') ?? false;
                document.getElementById('trip_type_wheelchair').checked = data.config.trip_types?.includes('Wheelchair') ?? false;
            }
        })
        .catch(error => {
            console.error('Error fetching entity data:', error);
            alert('An error occurred while loading entity data. Please try again.');
        });
}
</script>