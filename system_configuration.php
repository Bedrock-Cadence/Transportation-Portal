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
$db = Database::getInstance(); // For fetching lists
$allFacilities = $db->fetchAll("SELECT id, name FROM facilities WHERE is_active = 1 ORDER BY name ASC");
$allCarriers = $db->fetchAll("SELECT id, name FROM carriers WHERE is_active = 1 ORDER BY name ASC");
$selectedEntity = null;
$configData = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $entityId = (int)($_POST['entity_id'] ?? (Auth::user('entity_type') ? Auth::user('entity_id') : null));
    $entityType = $_POST['entity_type'] ?? Auth::user('entity_type');

    if (empty($entityId) || empty($entityType)) {
        $page_error = "Invalid entity selected for configuration.";
    } else {
        try {
            $configService->updateEntityConfig($entityId, $entityType, $_POST);
            $page_message = "Configuration updated successfully.";
        } catch (Exception $e) {
            $page_error = "Error: " . $e->getMessage();
        }
    }
}

if (Auth::hasRole('superuser')) {
    $entityType = Auth::user('entity_type');
    $entityId = (int)Auth::user('entity_id');
    $selectedEntity = $configService->getEntityConfig($entityId, $entityType);
    if ($selectedEntity) {
        $configData = $selectedEntity['config'];
    } else {
        $page_error = "Your entity record could not be found.";
    }
}

require_once 'header.php';
?>

<div class="max-w-4xl mx-auto">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Entity Configuration</h1>
    <?php if ($page_message): ?><div class="bg-green-100 text-green-700 p-4 rounded mb-4"><?= Utils::e($page_message) ?></div><?php endif; ?>
    <?php if ($page_error): ?><div class="bg-red-100 text-red-700 p-4 rounded mb-4"><?= Utils::e($page_error) ?></div><?php endif; ?>

    <?php if (Auth::hasRole('admin')): ?>
        <div class="bg-white shadow-md rounded-lg p-6 border border-gray-200 mb-8">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Select an Entity to Configure</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="facility-select" class="block text-sm font-medium text-gray-700">Facilities</label>
                    <select id="facility-select" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">Choose a facility...</option>
                        <?php foreach ($allFacilities as $f): ?><option value="<?= $f['id'] ?>"><?= Utils::e($f['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="carrier-select" class="block text-sm font-medium text-gray-700">Carriers</label>
                    <select id="carrier-select" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">Choose a carrier...</option>
                         <?php foreach ($allCarriers as $c): ?><option value="<?= $c['id'] ?>"><?= Utils::e($c['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div id="config-form-section" class="bg-white shadow-md rounded-lg border border-gray-200" style="display: <?= Auth::hasRole('superuser') ? 'block' : 'none'; ?>;">
        <div class="px-6 py-4 bg-gray-50 border-b">
            <h2 class="text-xl font-semibold text-gray-800">Configuration for: <span id="entity-name-header" class="text-blue-600"><?= Utils::e($selectedEntity['name'] ?? '') ?></span></h2>
        </div>
        <div class="p-6">
            <form id="config-form" method="POST" action="system_configuration.php" class="space-y-6">
                <input type="hidden" name="entity_id" id="entity-id-input" value="<?= Utils::e($selectedEntity['id'] ?? '') ?>">
                <input type="hidden" name="entity_type" id="entity-type-input" value="<?= Utils::e(Auth::user('entity_type') ?? '') ?>">

                <div id="facility-settings-form" class="space-y-6" style="display: <?= Auth::user('entity_type') === 'facility' ? 'block' : 'none'; ?>">
                    </div>

                <div id="carrier-settings-form" class="space-y-6" style="display: <?= Auth::user('entity_type') === 'carrier' ? 'block' : 'none'; ?>">
                    </div>
                
                <div class="flex justify-end pt-4">
                    <button type="submit" class="inline-flex justify-center py-2 px-4 border rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script nonce="<?= $cspNonce ?>" src="/assets/js/config.js" defer></script>
<?php require_once 'footer.php'; ?>