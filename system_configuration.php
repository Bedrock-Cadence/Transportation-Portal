<?php
// FILE: public_html/portal/system_configuration.php

require_once __DIR__ . '/../../app/init.php';

if (!Auth::can('view_admin_tools')) {
    Utils::redirect('index.php');
}

$page_title = 'Entity Configuration';
$page_message = '';
$page_error = '';
$configService = new ConfigService();
$db = Database::getInstance();

$entityId = null;
$entityType = null;
$selectedEntity = null;
$configData = [];
$showForm = false;

// Determine which entity is being configured
if (Auth::hasRole('admin')) {
    if (!empty($_GET['facility_id'])) {
        $entityId = (int)$_GET['facility_id'];
        $entityType = 'facility';
    } elseif (!empty($_GET['carrier_id'])) {
        $entityId = (int)$_GET['carrier_id'];
        $entityType = 'carrier';
    }
} elseif (Auth::hasRole('superuser')) {
    $entityId = (int)Auth::user('entity_id');
    $entityType = Auth::user('entity_type');
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Re-assign entity from POST to ensure we're saving the correct one
    $entityId = (int)($_POST['entity_id'] ?? $entityId);
    $entityType = $_POST['entity_type'] ?? $entityType;

    if (empty($entityId) || empty($entityType)) {
        $page_error = "Invalid entity selected for configuration.";
    } else {
        try {
            $configService->updateEntityConfig($entityId, $entityType, $_POST);
            $page_message = "Configuration updated successfully.";
        } catch (Exception $e) {
            $page_error = "Error updating configuration: " . $e->getMessage();
        }
    }
}

// Fetch data for the selected entity to display in the form
if ($entityId && $entityType) {
    $selectedEntity = $configService->getEntityConfig($entityId, $entityType);
    if ($selectedEntity) {
        $configData = $selectedEntity['config'];
        $showForm = true;
    } else {
        $page_error = "The selected entity record could not be found.";
    }
}

// Fetch lists for dropdowns
$allFacilities = $db->fetchAll("SELECT id, name FROM facilities WHERE is_active = 1 ORDER BY name ASC");
$allCarriers = $db->fetchAll("SELECT id, name FROM carriers WHERE is_active = 1 ORDER BY name ASC");

require_once 'header.php';
?>

<div class="max-w-4xl mx-auto">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Entity Configuration</h1>
    <?php if ($page_message): ?><div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded mb-4" role="alert"><p><?= Utils::e($page_message) ?></p></div><?php endif; ?>
    <?php if ($page_error): ?><div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded mb-4" role="alert"><p><?= Utils::e($page_error) ?></p></div><?php endif; ?>

    <?php if (Auth::hasRole('admin')): ?>
    <div class="bg-white shadow-md rounded-lg p-6 border border-gray-200 mb-8">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Select an Entity to Configure</h2>
        <form method="GET" action="system_configuration.php" id="entity-selector-form" class="space-y-4">
            <div>
                <label for="entity-select" class="block text-sm font-medium text-gray-700">Select Entity</label>
                <select id="entity-select" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Choose an entity...</option>
                    <optgroup label="Facilities">
                        <?php foreach ($allFacilities as $f): ?>
                        <option value="facility-<?= $f['id'] ?>" <?= ($entityType === 'facility' && $entityId === $f['id']) ? 'selected' : '' ?>>
                            <?= Utils::e($f['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="Carriers">
                        <?php foreach ($allCarriers as $c): ?>
                        <option value="carrier-<?= $c['id'] ?>" <?= ($entityType === 'carrier' && $entityId === $c['id']) ? 'selected' : '' ?>>
                            <?= Utils::e($c['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
            </div>
            <button type="submit" id="load-entity-btn" class="inline-flex justify-center py-2 px-4 border rounded-md shadow-sm text-sm font-medium text-white bg-gray-600 hover:bg-gray-700">Load Configuration</button>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($showForm): ?>
    <div id="config-form-section" class="bg-white shadow-md rounded-lg border border-gray-200">
        <div class="px-6 py-4 bg-gray-50 border-b">
            <h2 class="text-xl font-semibold text-gray-800">Configuration for: <span class="text-blue-600"><?= Utils::e($selectedEntity['name'] ?? '') ?></span></h2>
        </div>
        <div class="p-6">
            <form id="config-form" method="POST" action="system_configuration.php">
                <input type="hidden" name="entity_id" value="<?= Utils::e($selectedEntity['id'] ?? '') ?>">
                <input type="hidden" name="entity_type" value="<?= Utils::e($selectedEntity['type'] ?? '') ?>">

                <div id="facility-settings-form" class="space-y-8" style="display: <?= $entityType === 'facility' ? 'block' : 'none'; ?>">
                    <div>
                        <label for="facility-secure-email" class="block text-sm font-medium text-gray-700">Secure PHI Email Address</label>
                        <input type="email" name="secure_email_address" id="facility-secure-email" value="<?= Utils::e($selectedEntity['secure_email_address'] ?? '') ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="secure.reports@facility.com">
                        <p class="mt-2 text-sm text-gray-500">Provide an email address you affirm is secure for receiving trip reports containing PHI.</p>
                    </div>

                    <fieldset class="border-t pt-6">
                        <legend class="text-base font-medium text-gray-900">Bidding Duration</legend>
                        <div class="mt-4 grid grid-cols-1 gap-y-6 sm:grid-cols-2 sm:gap-x-4">
                            <div>
                                <label for="short-bid-duration" class="block text-sm font-medium text-gray-700">Standard Trips (&lt; 150 miles)</label>
                                <input type="number" name="short_bid_duration" id="short-bid-duration" value="<?= Utils::e($configData['short_bid_duration'] ?? '15') ?>" min="5" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <p class="mt-2 text-sm text-gray-500">Duration in minutes for bidding on standard trips.</p>
                            </div>
                            <div>
                                <label for="long-bid-duration" class="block text-sm font-medium text-gray-700">Long-Distance Trips (&ge; 150 miles)</label>
                                <input type="number" name="long_bid_duration" id="long-bid-duration" value="<?= Utils::e($configData['long_bid_duration'] ?? '30') ?>" min="5" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <p class="mt-2 text-sm text-gray-500">Duration in minutes for bidding on long-distance trips.</p>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="border-t pt-6">
                        <legend class="text-base font-medium text-gray-900">Carrier Preferences</legend>
                        <div class="mt-4 grid grid-cols-1 gap-y-6 sm:grid-cols-2 sm:gap-x-4">
                            <div>
                                <label for="preferred-carriers" class="block text-sm font-medium text-gray-700">Preferred Carriers</label>
                                <select multiple name="preferred_carriers[]" id="preferred-carriers" class="mt-1 block w-full h-48 rounded-md border-gray-300 shadow-sm">
                                    <?php foreach ($allCarriers as $carrier): ?>
                                    <option value="<?= $carrier['id'] ?>" <?= in_array($carrier['id'], $configData['preferred_carriers'] ?? []) ? 'selected' : '' ?>><?= Utils::e($carrier['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="mt-2 text-sm text-gray-500">Hold Ctrl/Cmd to select multiple. Preferred carriers may be prioritized by the system.</p>
                            </div>
                            <div>
                                <label for="blacklisted-carriers" class="block text-sm font-medium text-gray-700">Blacklisted Carriers</label>
                                <select multiple name="blacklisted_carriers[]" id="blacklisted-carriers" class="mt-1 block w-full h-48 rounded-md border-gray-300 shadow-sm">
                                    <?php foreach ($allCarriers as $carrier): ?>
                                    <option value="<?= $carrier['id'] ?>" <?= in_array($carrier['id'], $configData['blacklisted_carriers'] ?? []) ? 'selected' : '' ?>><?= Utils::e($carrier['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="mt-2 text-sm text-gray-500">Blacklisted carriers will NOT be able to see or bid on your trip requests.</p>
                            </div>
                        </div>
                    </fieldset>
                </div>

                <div id="carrier-settings-form" class="space-y-8" style="display: <?= $entityType === 'carrier' ? 'block' : 'none'; ?>">
                    <div>
                        <label for="carrier-secure-email" class="block text-sm font-medium text-gray-700">Secure PHI Email Address</label>
                        <input type="email" name="secure_email_address" id="carrier-secure-email" value="<?= Utils::e($selectedEntity['secure_email_address'] ?? '') ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="secure.reports@carrier.com">
                        <p class="mt-2 text-sm text-gray-500">Provide an email address you affirm is secure for receiving trip reports containing PHI.</p>
                    </div>

                    <fieldset class="border-t pt-6">
                        <legend class="text-base font-medium text-gray-900">Service Area</legend>
                        <div class="mt-4">
                            <label for="ltd-miles" class="block text-sm font-medium text-gray-700">Max Trip Distance (One-Way)</label>
                            <input type="number" name="ltd_miles" id="ltd-miles" value="<?= Utils::e($configData['ltd_miles'] ?? '150') ?>" min="1" class="mt-1 block w-full max-w-xs rounded-md border-gray-300 shadow-sm">
                            <p class="mt-2 text-sm text-gray-500">Set the maximum one-way distance in miles you will accept for trips. This will filter the trips you see.</p>
                        </div>
                    </fieldset>

                    <?php
                        $equipmentOptions = ['Oxygen (O2)', 'IV Pump', 'Cardiac Monitor', 'ECMO', 'Ventilator', 'Stair Chair', 'Bariatric Stretcher'];
                        $currentEquipment = $configData['special_equipment'] ?? [];
                    ?>
                    <fieldset class="border-t pt-6">
                        <legend class="text-base font-medium text-gray-900">Special Equipment Capabilities</legend>
                        <div class="mt-4 grid grid-cols-2 gap-4">
                            <?php foreach ($equipmentOptions as $equip): ?>
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input id="equip-<?= str_replace(' ', '-', strtolower($equip)) ?>" name="special_equipment[]" type="checkbox" value="<?= $equip ?>" <?= in_array($equip, $currentEquipment) ? 'checked' : '' ?> class="h-4 w-4 text-indigo-600 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm"><label for="equip-<?= str_replace(' ', '-', strtolower($equip)) ?>" class="font-medium text-gray-700"><?= Utils::e($equip) ?></label></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <p class="mt-4 text-sm text-gray-500">Select all specialized equipment your service can provide to help match you with appropriate trips.</p>
                    </fieldset>

                    <fieldset class="border-t pt-6">
                        <legend class="text-base font-medium text-gray-900">Facility Preferences</legend>
                        <div class="mt-4 grid grid-cols-1 gap-y-6 sm:grid-cols-2 sm:gap-x-4">
                            <div>
                                <label for="preferred-facilities" class="block text-sm font-medium text-gray-700">Preferred Facilities</label>
                                <select multiple name="preferred_facilities[]" id="preferred-facilities" class="mt-1 block w-full h-48 rounded-md border-gray-300 shadow-sm">
                                    <?php foreach ($allFacilities as $facility): ?>
                                    <option value="<?= $facility['id'] ?>" <?= in_array($facility['id'], $configData['preferred_facilities'] ?? []) ? 'selected' : '' ?>><?= Utils::e($facility['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="mt-2 text-sm text-gray-500">Hold Ctrl/Cmd to select multiple.</p>
                            </div>
                            <div>
                                <label for="blacklisted-facilities" class="block text-sm font-medium text-gray-700">Blacklisted Facilities</label>
                                <select multiple name="blacklisted_facilities[]" id="blacklisted-facilities" class="mt-1 block w-full h-48 rounded-md border-gray-300 shadow-sm">
                                     <?php foreach ($allFacilities as $facility): ?>
                                    <option value="<?= $facility['id'] ?>" <?= in_array($facility['id'], $configData['blacklisted_facilities'] ?? []) ? 'selected' : '' ?>><?= Utils::e($facility['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="mt-2 text-sm text-gray-500">You may not see trips from these facilities.</p>
                            </div>
                        </div>
                    </fieldset>
                </div>
                
                <div class="flex justify-end pt-8 border-t mt-8">
                    <button type="submit" class="inline-flex justify-center py-2 px-4 border rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if (Auth::hasRole('admin')): ?>
<script nonce="<?= $cspNonce ?>">
document.addEventListener('DOMContentLoaded', function() {
    const selector = document.getElementById('entity-select');
    const form = document.getElementById('entity-selector-form');
    const button = document.getElementById('load-entity-btn');

    button.addEventListener('click', function(e) {
        e.preventDefault();
        const selectedValue = selector.value;
        if (selectedValue) {
            const [type, id] = selectedValue.split('-');
            if (type === 'facility') {
                form.action = 'system_configuration.php?facility_id=' + id;
            } else if (type === 'carrier') {
                form.action = 'system_configuration.php?carrier_id=' + id;
            }
            form.submit();
        }
    });
});
</script>
<?php endif; ?>

<?php require_once 'footer.php'; ?>