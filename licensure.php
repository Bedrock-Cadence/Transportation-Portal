<?php
// FILE: public_html/portal/licensure.php

require_once __DIR__ . '/../../app/init.php';

// Security Check: Only admins can see this list view.
if (!Auth::hasRole('admin')) {
    // If a carrier superuser lands here, send them to their specific details page.
    if (Auth::hasRole('superuser') && Auth::user('entity_type') === 'carrier') {
        Utils::redirect('licensure_details.php?carrier_id=' . Auth::user('entity_id'));
    }
    Utils::redirect('index.php');
}

$page_title = 'Licensure Management';
$carrierService = new CarrierService();
$carriers = $carrierService->getAllCarriers();

require_once 'header.php';
?>

<div class="max-w-4xl mx-auto">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Carrier Licensure Management</h1>
    <div class="bg-white shadow-md rounded-lg border border-gray-200">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Carrier Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Verification Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($carriers as $carrier): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= Utils::e($carrier['name']) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= Utils::e(ucfirst($carrier['verification_status'])) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <a href="licensure_details.php?carrier_id=<?= Utils::e($carrier['id']) ?>" class="text-indigo-600 hover:text-indigo-900">View/Edit Details</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'footer.php'; ?>