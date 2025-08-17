<?php
// FILE: public/system_configuration.php

require_once 'init.php';

// --- Security & Permission Check ---
if (!isset($_SESSION["loggedin"]) || !in_array($_SESSION['user_role'], ['superuser', 'admin'])) {
    redirect('index.php');
}

$page_title = 'System Configuration';
$db = Database::getInstance();
$page_message = '';
$page_error = '';
$all_facilities = [];
$all_carriers = [];
$selected_entity = null;
$config_data = [];
$user_role = $_SESSION['user_role'];

// --- Form Submission Handling ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $entity_id = $_POST['entity_id'] ?? null;
    $entity_type = $_POST['entity_type'] ?? null;
    
    // Superusers can only edit their own entity
    if ($user_role === 'superuser') {
        $entity_id = $_SESSION['entity_id'];
        $entity_type = $_SESSION['entity_type'];
    }

    if (empty($entity_id) || empty($entity_type)) {
        $page_error = "Invalid entity selected for configuration.";
    } else {
        $table_name = ($entity_type === 'facility') ? 'facilities' : 'carriers';
        try {
            $db->pdo()->beginTransaction();

            $stmt_old = $db->query("SELECT config_settings FROM `{$table_name}` WHERE id = ? LIMIT 1", [$entity_id]);
            $old_config_json = $stmt_old->fetchColumn();
            $old_config_data = json_decode($old_config_json, true) ?: [];

            $new_config_data = [];
            if ($entity_type === 'facility') {
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
                    'ltd_miles' => $_POST['ltd_miles'] ?? 150,
                    'special_equipment' => $_POST['special_equipment'] ?? []
                ];
            }
            $new_config_json = json_encode($new_config_data);

            $db->query("UPDATE `{$table_name}` SET config_settings = ? WHERE id = ?", [$new_config_json, $entity_id]);
            
            log_user_action('config_updated', "Configuration for {$entity_type} ID: {$entity_id} was modified.");
            
            $db->pdo()->commit();
            $page_message = "Configuration updated successfully.";
        } catch (Exception $e) {
            if ($db->pdo()->inTransaction()) $db->pdo()->rollBack();
            $page_error = "An error occurred while saving the configuration.";
            error_log("System Config Error: " . $e->getMessage());
        }
    }
}

// --- Data Retrieval for Page Display ---
try {
    $all_facilities = $db->query("SELECT id, name FROM facilities WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
    $all_carriers = $db->query("SELECT id, name FROM carriers WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

    if ($user_role === 'superuser') {
        $table_name = $_SESSION['entity_type'] . 's';
        $selected_entity = $db->query("SELECT id, name, config_settings FROM `{$table_name}` WHERE id = ? LIMIT 1", [$_SESSION['entity_id']])->fetch();
        if ($selected_entity) {
            $config_data = json_decode($selected_entity['config_settings'], true) ?: [];
        }
    }
} catch (Exception $e) {
    $page_error = "Could not load configuration data.";
    error_log("System Config Page Load Error: " . $e->getMessage());
}

require_once 'header.php';
?>

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
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Special Equipment</label>
                        <div class="mt-1 space-y-2">
                            <div class="flex items-center">
                                <input type="checkbox" id="equipment_o2" name="special_equipment[]" value="O2" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" <?= in_array('O2', $config_data['special_equipment'] ?? []) ? 'checked' : ''; ?>>
                                <label for="equipment_o2" class="ml-2 block text-sm font-medium text-gray-700">O2</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="equipment_iv" name="special_equipment[]" value="IV" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" <?= in_array('IV', $config_data['special_equipment'] ?? []) ? 'checked' : ''; ?>>
                                <label for="equipment_iv" class="ml-2 block text-sm font-medium text-gray-700">IV</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="equipment_vent" name="special_equipment[]" value="Vent" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" <?= in_array('Vent', $config_data['special_equipment'] ?? []) ? 'checked' : ''; ?>>
                                <label for="equipment_vent" class="ml-2 block text-sm font-medium text-gray-700">Vent</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="equipment_ecmo" name="special_equipment[]" value="ECMO" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" <?= in_array('ECMO', $config_data['special_equipment'] ?? []) ? 'checked' : ''; ?>>
                                <label for="equipment_ecmo" class="ml-2 block text-sm font-medium text-gray-700">ECMO</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="equipment_cardiac" name="special_equipment[]" value="Cardiac Monitor" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" <?= in_array('Cardiac Monitor', $config_data['special_equipment'] ?? []) ? 'checked' : ''; ?>>
                                <label for="equipment_cardiac" class="ml-2 block text-sm font-medium text-gray-700">Cardiac Monitor</label>
                            </div>
                        </div>
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
                
                // Handle special equipment checkboxes
                const specialEquipment = data.config.special_equipment ?? [];
                document.getElementById('equipment_o2').checked = specialEquipment.includes('O2');
                document.getElementById('equipment_iv').checked = specialEquipment.includes('IV');
                document.getElementById('equipment_vent').checked = specialEquipment.includes('Vent');
                document.getElementById('equipment_ecmo').checked = specialEquipment.includes('ECMO');
                document.getElementById('equipment_cardiac').checked = specialEquipment.includes('Cardiac Monitor');
            }
        })
        .catch(error => {
            console.error('Error fetching entity data:', error);
            alert('An error occurred while loading entity data. Please try again.');
        });
}
</script>