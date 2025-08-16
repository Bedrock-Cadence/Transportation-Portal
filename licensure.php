<?php
// FILE: licensure.php

// 1. Set the page title for the header.
$page_title = 'Licensure';

// 2. Include the header, which also handles session startup.
require_once 'header.php';

// 3. Security Check: If the user isn't logged in, send them to the login page.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// 4. Security Check: Only allow authorized roles to access this page.
$allowed_roles = ['carrier_superuser', 'bedrock_admin'];
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], $allowed_roles)) {
    // Redirect to a dashboard or show an unauthorized message.
    header("location: dashboard.php");
    exit;
}

// 5. Include the database connection file. The $mysqli object is now available for use.
require_once __DIR__ . '/../../app/db_connect.php';

// Initialize variables for messages and errors.
$page_message = '';
$page_error = '';
$carrier = null;
$is_editable = false;

// --- Start of Form Submission Handling ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Fetch the carrier's current verification status before attempting an update.
    $stmt_status = $mysqli->prepare("SELECT verification_status FROM carriers WHERE id = ? LIMIT 1");
    $stmt_status->bind_param("i", $_SESSION['entity_id']);
    $stmt_status->execute();
    $result_status = $stmt_status->get_result();
    $current_status = $result_status->fetch_assoc();
    $stmt_status->close();

    // Check if the record can be updated.
    if ($current_status && $current_status['verification_status'] !== 'verified') {
        try {
            $license_state = trim($_POST['license_state']);
            $license_number = trim($_POST['license_number']);
            $license_expires_at = trim($_POST['license_expires_at']);

            // Prepare the UPDATE query.
            $stmt = $mysqli->prepare("UPDATE carriers SET license_state = ?, license_number = ?, license_expires_at = ? WHERE id = ?");
            $stmt->bind_param("sssi", $license_state, $license_number, $license_expires_at, $_SESSION['entity_id']);
            
            if ($stmt->execute()) {
                $page_message = "Licensing information has been updated successfully.";
            } else {
                $page_error = "Error updating licensure: " . $stmt->error;
            }
            $stmt->close();

        } catch (Exception $e) {
            $page_error = "An error occurred: " . $e->getMessage();
        }
    } else {
        $page_error = "Licensing data cannot be changed. It is in a 'verified' status.";
    }
}
// --- End of Form Submission Handling ---


// --- Fetch the carrier's licensure data for display ---
try {
    $stmt = $mysqli->prepare("SELECT name, verification_status, license_state, license_number, license_expires_at FROM carriers WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $_SESSION['entity_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $carrier = $result->fetch_assoc();
    $stmt->close();

    if ($carrier) {
        $is_editable = ($carrier['verification_status'] !== 'verified');
    } else {
        $page_error = "Carrier record not found.";
    }

} catch (Exception $e) {
    $page_error = "Could not load carrier data. " . $e->getMessage();
}

$mysqli->close();
?>

<div id="licensure-container" class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Licensure Management</h1>
        <p class="text-sm text-gray-500">
            Last updated: <span class="font-semibold"><?= htmlspecialchars(date("F d, Y h:i:s A")); ?></span>
        </p>
    </div>

    <div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200">
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Carrier Licensure</h2>
                <p class="text-sm text-gray-500 font-mono"><?= htmlspecialchars($carrier['name'] ?? 'N/A'); ?></p>
            </div>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium leading-4 bg-blue-100 text-blue-800">
                Status: <?= htmlspecialchars(ucwords(str_replace('_', ' ', $carrier['verification_status']))); ?>
            </span>
        </div>

        <div class="p-6 space-y-8">
            <?php if (!empty($page_message)): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4" role="alert">
                    <p class="font-bold">Success</p>
                    <p><?= htmlspecialchars($page_message); ?></p>
                </div>
            <?php endif; ?>
            <?php if (!empty($page_error)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert">
                    <p class="font-bold">Error</p>
                    <p><?= htmlspecialchars($page_error); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!$is_editable): ?>
                <div class="bg-yellow-100 text-yellow-800 p-3 rounded-md text-sm text-center font-semibold">
                    This information is in 'verified' status and cannot be changed. Please contact Bedrock Cadence to request an update.
                </div>
            <?php endif; ?>

            <?php if ($carrier): ?>
                <form method="POST" action="licensure.php" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="license_state" class="block text-sm font-medium text-gray-700">License State</label>
                            <input type="text" id="license_state" name="license_state" value="<?= htmlspecialchars($carrier['license_state'] ?? ''); ?>" <?= $is_editable ? '' : 'readonly'; ?> required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 <?= $is_editable ? '' : 'bg-gray-100'; ?>">
                        </div>
                        <div>
                            <label for="license_number" class="block text-sm font-medium text-gray-700">License Number</label>
                            <input type="text" id="license_number" name="license_number" value="<?= htmlspecialchars($carrier['license_number'] ?? ''); ?>" <?= $is_editable ? '' : 'readonly'; ?> required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 <?= $is_editable ? '' : 'bg-gray-100'; ?>">
                        </div>
                    </div>
                    <div>
                        <label for="license_expires_at" class="block text-sm font-medium text-gray-700">Expiration Date</label>
                        <input type="date" id="license_expires_at" name="license_expires_at" value="<?= htmlspecialchars($carrier['license_expires_at'] ?? ''); ?>" <?= $is_editable ? '' : 'readonly'; ?> required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 <?= $is_editable ? '' : 'bg-gray-100'; ?>">
                    </div>

                    <?php if ($is_editable): ?>
                        <div class="flex justify-end">
                            <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Save Changes
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
            <?php else: ?>
                <p class="text-center text-gray-500">Carrier information not found.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// This includes the footer and necessary closing tags.
require_once 'footer.php';
?>