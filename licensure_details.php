<?php
// FILE: licensure-details.php (NEW FILE)

$page_title = 'Edit Carrier Licensure';
require_once 'header.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// Security: This page is for Admins ONLY.
if (($_SESSION['user_role'] ?? null) !== 'admin') {
    header("location: dashboard.php");
    exit;
}

require_once __DIR__ . '/../../app/db_connect.php';

function log_user_activity($mysqli, $user_id, $action, $message) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $log_stmt = $mysqli->prepare("INSERT INTO user_activity_logs (user_id, action, message, ip_address) VALUES (?, ?, ?, ?)");
    if ($log_stmt) {
        $log_stmt->bind_param("isss", $user_id, $action, $message, $ip_address);
        $log_stmt->execute();
        $log_stmt->close();
    }
}

$page_error = '';
$selected_carrier = null;
$carrier_id = $_GET['carrier_id'] ?? $_POST['carrier_id'] ?? null;

if ($carrier_id === null) {
    header("location: licensure.php");
    exit;
}

// --- FORM SUBMISSION HANDLING ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $verification_status_input = $_POST['verification_status'] ?? null;
    $license_state = trim($_POST['license_state'] ?? '');
    $license_number = trim($_POST['license_number'] ?? '');
    $license_expires_at = trim($_POST['license_expires_at'] ?? '');

    // ADDED: Fetch the carrier's current data BEFORE the update for logging purposes.
    $stmt_old_data = $mysqli->prepare("SELECT verification_status, license_state, license_number, license_expires_at FROM carriers WHERE id = ? LIMIT 1");
    $stmt_old_data->bind_param("i", $carrier_id);
    $stmt_old_data->execute();
    $result_old_data = $stmt_old_data->get_result();
    $old_data = $result_old_data->fetch_assoc();
    $stmt_old_data->close();

    if (!$old_data) {
        $page_error = "Cannot update. The carrier record does not exist.";
        log_user_activity($mysqli, $_SESSION['user_id'], 'licensure_update_failed', "Attempted to update non-existent carrier ID: {$carrier_id}");
    } else {
        try {
            $mysqli->begin_transaction();
            
            $update_query = "UPDATE carriers SET license_state = ?, license_number = ?, license_expires_at = ?, verification_status = ? WHERE id = ?";
            $types = "ssssi";
            $params = [$license_state, $license_number, empty($license_expires_at) ? null : $license_expires_at, $verification_status_input, $carrier_id];

            $stmt = $mysqli->prepare($update_query);
            $stmt->bind_param($types, ...$params);
            
            if ($stmt->execute()) {
                $mysqli->commit();

                // CHANGED: Build a detailed log message with before and after values.
                $log_message = "Admin updated licensure for carrier ID {$carrier_id}. ";
                $changes = [];

                if ($old_data['verification_status'] !== $verification_status_input) {
                    $changes[] = "Status: '{$old_data['verification_status']}' -> '{$verification_status_input}'";
                }
                if ($old_data['license_state'] !== $license_state) {
                    $changes[] = "State: '{$old_data['license_state']}' -> '{$license_state}'";
                }
                if ($old_data['license_number'] !== $license_number) {
                    $changes[] = "Number: '{$old_data['license_number']}' -> '{$license_number}'";
                }
                // Handle date comparison carefully
                $old_date = $old_data['license_expires_at'] ?? '';
                if ($old_date !== $license_expires_at) {
                    $changes[] = "Expires: '{$old_date}' -> '{$license_expires_at}'";
                }

                if (!empty($changes)) {
                    $log_message .= "Changes: " . implode(', ', $changes) . ".";
                } else {
                    $log_message .= "No changes were made to the data.";
                }
                
                log_user_activity($mysqli, $_SESSION['user_id'], 'licensure_updated', $log_message);
                
                header("location: licensure.php?update=success");
                exit;
            } else {
                $mysqli->rollback();
                $page_error = "Error updating licensure: " . $stmt->error;
                log_user_activity($mysqli, $_SESSION['user_id'], 'licensure_update_failed', "Failed to update carrier licensure for ID: {$carrier_id}");
            }
            $stmt->close();
        } catch (Exception $e) {
            $mysqli->rollback();
            $page_error = "An error occurred: " . $e->getMessage();
        }
    }
}

// --- DATA RETRIEVAL FOR DISPLAY ---
$stmt = $mysqli->prepare("SELECT id, name, verification_status, license_state, license_number, license_expires_at FROM carriers WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $carrier_id);
$stmt->execute();
$result = $stmt->get_result();
$selected_carrier = $result->fetch_assoc();
$stmt->close();

if (!$selected_carrier) {
    header("location: licensure.php");
    exit;
}
?>

<div class="p-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Edit Licensure for <?= htmlspecialchars($selected_carrier['name']); ?></h1>

    <?php if (!empty($page_error)): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
        <p class="font-bold">Error</p>
        <p><?= htmlspecialchars($page_error); ?></p>
    </div>
    <?php endif; ?>

    <div class="bg-white shadow-md rounded-lg p-6 border border-gray-200">
        <form id="licensure-form-details" method="POST" action="licensure_details.php" class="space-y-6">
            <input type="hidden" name="carrier_id" value="<?= htmlspecialchars($selected_carrier['id']); ?>">
            <div>
                <label for="verification_status" class="block text-sm font-medium text-gray-700">Verification Status</label>
                <select id="verification_status" name="verification_status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="waiting" <?= $selected_carrier['verification_status'] === 'waiting' ? 'selected' : ''; ?>>Waiting</option>
                    <option value="pending" <?= $selected_carrier['verification_status'] === 'pending' ? 'selected' : ''; ?>>Pending Review</option>
                    <option value="verified" <?= $selected_carrier['verification_status'] === 'verified' ? 'selected' : ''; ?>>Verified</option>
                    <option value="failed" <?= $selected_carrier['verification_status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
                    <option value="expired" <?= $selected_carrier['verification_status'] === 'expired' ? 'selected' : ''; ?>>Expired</option>
                    <option value="suspended" <?= $selected_carrier['verification_status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                </select>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="license_state" class="block text-sm font-medium text-gray-700">License State</label>
                    <input type="text" id="license_state" name="license_state" value="<?= htmlspecialchars($selected_carrier['license_state'] ?? ''); ?>" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label for="license_number" class="block text-sm font-medium text-gray-700">License Number</label>
                    <input type="text" id="license_number" name="license_number" value="<?= htmlspecialchars($selected_carrier['license_number'] ?? ''); ?>" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
            </div>
            <div>
                <label for="license_expires_at" class="block text-sm font-medium text-gray-700">Expiration Date</label>
                <input type="date" id="license_expires_at" name="license_expires_at" value="<?= htmlspecialchars($selected_carrier['license_expires_at'] ?? ''); ?>" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div class="flex justify-end items-center gap-x-4 border-t pt-6">
                <a href="licensure.php" class="py-2 px-4 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Cancel</a>
                <button type="submit" class="py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<?php
require_once 'footer.php';
?>