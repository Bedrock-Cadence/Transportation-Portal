<?php
// FILE: public/licensure_details.php

require_once 'init.php';

// --- Security & Permission Check ---
if (!isset($_SESSION["loggedin"]) || $_SESSION['user_role'] !== 'admin') {
    redirect('dashboard.php');
}

$page_title = 'Edit Carrier Licensure';
$db = Database::getInstance();
$page_error = '';
$selected_carrier = null;
$carrier_id = $_GET['carrier_id'] ?? $_POST['carrier_id'] ?? null;

if ($carrier_id === null) {
    redirect("licensure.php");
}

// --- Form Submission Handling ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $verification_status = $_POST['verification_status'] ?? '';
    $license_state = trim($_POST['license_state'] ?? '');
    $license_number = trim($_POST['license_number'] ?? '');
    $license_expires_at = empty($_POST['license_expires_at']) ? null : trim($_POST['license_expires_at']);

    try {
        $db->pdo()->beginTransaction();

        $sql = "UPDATE carriers SET license_state = ?, license_number = ?, license_expires_at = ?, verification_status = ? WHERE id = ?";
        $params = [$license_state, $license_number, $license_expires_at, $verification_status, $carrier_id];
        $db->query($sql, $params);
        
        log_user_action('licensure_updated', "Admin updated licensure details for carrier ID: {$carrier_id}. New status: {$verification_status}.");

        $db->pdo()->commit();
        redirect("licensure.php?update=success");

    } catch (Exception $e) {
        if ($db->pdo()->inTransaction()) $db->pdo()->rollBack();
        $page_error = "Error updating licensure: " . $e->getMessage();
        error_log("Licensure Details Update Error: " . $e->getMessage());
    }
}

// --- Data Retrieval for Display ---
try {
    $selected_carrier = $db->query("SELECT id, name, verification_status, license_state, license_number, license_expires_at FROM carriers WHERE id = ? LIMIT 1", [$carrier_id])->fetch();
    if (!$selected_carrier) {
        redirect("licensure.php");
    }
} catch (Exception $e) {
    $page_error = "Could not retrieve carrier details.";
    error_log("Licensure Details Fetch Error: " . $e->getMessage());
}

require_once 'header.php';
?>

<div class="p-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Edit Licensure for <?= e($selected_carrier['name'] ?? 'Carrier'); ?></h1>

    <?php if (!empty($page_error)): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
        <p class="font-bold">Error</p>
        <p><?= e($page_error); ?></p>
    </div>
    <?php endif; ?>

    <?php if ($selected_carrier): ?>
    <div class="bg-white shadow-md rounded-lg p-6 border border-gray-200">
        <form id="licensure-form-details" method="POST" action="licensure_details.php" class="space-y-6">
            <input type="hidden" name="carrier_id" value="<?= e($selected_carrier['id']); ?>">
            <div>
                <label for="verification_status" class="block text-sm font-medium text-gray-700">Verification Status</label>
                <select id="verification_status" name="verification_status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="waiting" <?= ($selected_carrier['verification_status'] ?? '') === 'waiting' ? 'selected' : ''; ?>>Waiting</option>
                    <option value="pending" <?= ($selected_carrier['verification_status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending Review</option>
                    <option value="verified" <?= ($selected_carrier['verification_status'] ?? '') === 'verified' ? 'selected' : ''; ?>>Verified</option>
                    <option value="failed" <?= ($selected_carrier['verification_status'] ?? '') === 'failed' ? 'selected' : ''; ?>>Failed</option>
                    <option value="expired" <?= ($selected_carrier['verification_status'] ?? '') === 'expired' ? 'selected' : ''; ?>>Expired</option>
                    <option value="suspended" <?= ($selected_carrier['verification_status'] ?? '') === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                </select>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="license_state" class="block text-sm font-medium text-gray-700">License State</label>
                    <input type="text" id="license_state" name="license_state" value="<?= e($selected_carrier['license_state'] ?? ''); ?>" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label for="license_number" class="block text-sm font-medium text-gray-700">License Number</label>
                    <input type="text" id="license_number" name="license_number" value="<?= e($selected_carrier['license_number'] ?? ''); ?>" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
            </div>
            <div>
                <label for="license_expires_at" class="block text-sm font-medium text-gray-700">Expiration Date</label>
                <input type="date" id="license_expires_at" name="license_expires_at" value="<?= e($selected_carrier['license_expires_at'] ?? ''); ?>" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div class="flex justify-end items-center gap-x-4 border-t pt-6">
                <a href="licensure.php" class="py-2 px-4 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancel</a>
                <button type="submit" class="py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">Save Changes</button>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>