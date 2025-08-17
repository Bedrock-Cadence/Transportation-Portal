<?php
// FILE: public_html/portal/licensure_details.php

require_once __DIR__ . '/../../app/init.php';

$page_title = 'Licensure Details';
$page_error = '';
$page_message = '';
$carrierService = new CarrierService();
$carrierId = $_GET['carrier_id'] ?? (Auth::user('entity_type') === 'carrier' ? Auth::user('entity_id') : null);

if (!$carrierId) {
    Utils::redirect('index.php?error=no_carrier');
}

$carrier = $carrierService->getCarrierById((int)$carrierId);

// Security Check: Can the current user manage this specific carrier?
if (!$carrier || !Auth::can('manage_licensure', $carrier)) {
    Utils::redirect('index.php?error=unauthorized');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $carrierService->updateLicensure((int)$carrierId, $_POST);
        $page_message = "Licensure information updated successfully.";
        // Refresh carrier data after update
        $carrier = $carrierService->getCarrierById((int)$carrierId);
    } catch (Exception $e) {
        $page_error = $e->getMessage();
    }
}

$isEditable = Auth::hasRole('admin') || ($carrier['verification_status'] === 'waiting');

require_once 'header.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="flex justify-between items-center mb-4">
        <h1 class="text-2xl font-bold text-gray-800">Licensure Details for <?= Utils::e($carrier['name']) ?></h1>
        <?php if (Auth::hasRole('admin')): ?>
            <a href="licensure.php" class="text-blue-600 hover:text-blue-800">&larr; Back to Carrier List</a>
        <?php endif; ?>
    </div>
    
    <?php if ($page_message): ?><div class="bg-green-100 text-green-700 p-4 rounded mb-4"><?= Utils::e($page_message) ?></div><?php endif; ?>
    <?php if ($page_error): ?><div class="bg-red-100 text-red-700 p-4 rounded mb-4"><?= Utils::e($page_error) ?></div><?php endif; ?>

    <form method="POST" action="licensure_details.php?carrier_id=<?= Utils::e($carrierId) ?>" class="bg-white shadow-md rounded-lg p-6 border border-gray-200 space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="license_state" class="block text-sm font-medium text-gray-700">License State</label>
                <input type="text" id="license_state" name="license_state" value="<?= Utils::e($carrier['license_state'] ?? '') ?>" <?= !$isEditable ? 'readonly' : '' ?> class="mt-1 block w-full rounded-md border-gray-300 shadow-sm read-only:bg-gray-100">
            </div>
            <div>
                <label for="license_number" class="block text-sm font-medium text-gray-700">License Number</label>
                <input type="text" id="license_number" name="license_number" value="<?= Utils::e($carrier['license_number'] ?? '') ?>" <?= !$isEditable ? 'readonly' : '' ?> class="mt-1 block w-full rounded-md border-gray-300 shadow-sm read-only:bg-gray-100">
            </div>
        </div>
        <div>
            <label for="license_expires_at" class="block text-sm font-medium text-gray-700">Expiration Date</label>
            <input type="date" id="license_expires_at" name="license_expires_at" value="<?= Utils::e($carrier['license_expires_at'] ?? '') ?>" <?= !$isEditable ? 'readonly' : '' ?> class="mt-1 block w-full rounded-md border-gray-300 shadow-sm read-only:bg-gray-100">
        </div>

        <?php if (Auth::hasRole('admin')): ?>
            <div>
                <label for="verification_status" class="block text-sm font-medium text-gray-700">Verification Status</label>
                <select id="verification_status" name="verification_status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    <?php $statuses = ['pending', 'verified', 'expired', 'suspended', 'failed', 'waiting']; ?>
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?= $status ?>" <?= ($carrier['verification_status'] == $status) ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php else: ?>
             <div>
                <label class="block text-sm font-medium text-gray-700">Verification Status</label>
                <p class="mt-1 text-base p-2 bg-gray-100 rounded-md"><?= Utils::e(ucfirst($carrier['verification_status'])) ?></p>
            </div>
        <?php endif; ?>

        <?php if (!$isEditable && Auth::user('entity_type') === 'carrier'): ?>
            <div class="bg-blue-100 text-blue-700 p-4 rounded text-sm">
                Your licensure information has been submitted and is currently under review. To make changes, please contact Bedrock Cadence support.
            </div>
        <?php endif; ?>

        <?php if ($isEditable): ?>
            <div class="flex justify-end pt-4">
                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                    Save Changes
                </button>
            </div>
        <?php endif; ?>
    </form>
</div>

<?php require_once 'footer.php'; ?>