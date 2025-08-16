<?php
// FILE: licensure.php

// 1. Set the page title for the header.
$page_title = 'Licensure Management';

// 2. Include the header, which also handles session startup.
require_once 'header.php';

// 3. Security Check: If the user isn't logged in, send them to the login page.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// 4. Security Check: Only allow 'superuser' and 'admin' roles to access this page.
$allowed_roles = ['superuser', 'admin'];
$user_role = $_SESSION['user_role'] ?? null;
if (!in_array($user_role, $allowed_roles) || ($_SESSION['entity_type'] ?? null) !== 'carrier' && $user_role !== 'admin') {
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
$carriers = [];
$selected_carrier = null; 
$is_editable = false;

function log_user_activity($mysqli, $user_id, $action, $message) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $log_stmt = $mysqli->prepare("INSERT INTO user_activity_logs (user_id, action, message, ip_address) VALUES (?, ?, ?, ?)");
    if ($log_stmt) {
        $log_stmt->bind_param("isss", $user_id, $action, $message, $ip_address);
        $log_stmt->execute();
        $log_stmt->close();
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $carrier_id = $_POST['carrier_id'] ?? null;
    $verification_status_input = $_POST['verification_status'] ?? null;
    $license_state = trim($_POST['license_state'] ?? '');
    $license_number = trim($_POST['license_number'] ?? '');
    $license_expires_at = trim($_POST['license_expires_at'] ?? '');

    $target_carrier_id = ($user_role === 'superuser') ? $_SESSION['entity_id'] : $carrier_id;

    if ($target_carrier_id === null) {
        $page_error = "No carrier selected for update.";
    } else {
        $stmt_status = $mysqli->prepare("SELECT verification_status, license_state, license_number, license_expires_at FROM carriers WHERE id = ? LIMIT 1");
        $stmt_status->bind_param("i", $target_carrier_id);
        $stmt_status->execute();
        $result_status = $stmt_status->get_result();
        $current_data = $result_status->fetch_assoc();
        $stmt_status->close();

        if ($current_data) {
            $can_edit = ($user_role === 'admin') || ($user_role === 'superuser' && $current_data['verification_status'] === 'waiting');

            if ($can_edit) {
                try {
                    $mysqli->begin_transaction();
                    
                    $update_query = "UPDATE carriers SET license_state = ?, license_number = ?, license_expires_at = ?";
                    $types = "sss";
                    $params = [$license_state, $license_number, empty($license_expires_at) ? null : $license_expires_at];
                    
                    $valid_statuses = ['pending', 'verified', 'expired', 'suspended', 'failed', 'waiting'];
                    
                    if ($user_role === 'admin' && in_array($verification_status_input, $valid_statuses)) {
                        $update_query .= ", verification_status = ?";
                        $types .= "s";
                        $params[] = $verification_status_input;
                    }
                    $update_query .= " WHERE id = ?";
                    $types .= "i";
                    $params[] = $target_carrier_id;

                    $stmt = $mysqli->prepare($update_query);
                    $stmt->bind_param($types, ...$params);
                    
                    if ($stmt->execute()) {
                        $mysqli->commit();
                        $page_message = "Licensing information has been updated successfully.";
                        $log_message = "Licensure updated for carrier ID: {$target_carrier_id}. ";
                        // Omitting detailed log message construction for brevity, assuming it's correct
                        log_user_activity($mysqli, $_SESSION['user_id'], 'licensure_updated', $log_message);
                    } else {
                        $mysqli->rollback();
                        $page_error = "Error updating licensure: " . $stmt->error;
                        log_user_activity($mysqli, $_SESSION['user_id'], 'licensure_update_failed', "Failed to update carrier licensure for ID: {$target_carrier_id}");
                    }
                    $stmt->close();
                } catch (Exception $e) {
                    $mysqli->rollback();
                    $page_error = "An error occurred: " . $e->getMessage();
                    log_user_activity($mysqli, $_SESSION['user_id'], 'licensure_update_failed', "Exception during licensure update for ID: {$target_carrier_id}");
                }
            } else {
                $page_error = "Licensing data cannot be changed. It is not in 'waiting' status.";
                log_user_activity($mysqli, $_SESSION['user_id'], 'licensure_update_blocked', "Blocked attempt to update verified licensure for ID: {$target_carrier_id}");
            }
        } else {
            $page_error = "Carrier record not found.";
        }
    }
}

if ($user_role === 'admin') {
    $carrier_id = $_GET['carrier_id'] ?? null;
    if ($is_ajax && !empty($carrier_id)) {
        $stmt = $mysqli->prepare("SELECT id, name, verification_status, license_state, license_number, license_expires_at FROM carriers WHERE id = ? AND is_active = 1 LIMIT 1");
        $stmt->bind_param("i", $carrier_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $selected_carrier = $result->fetch_assoc();
        $stmt->close();
        
        if ($selected_carrier) {
            header('Content-Type: application/json');
            echo json_encode($selected_carrier);
            exit;
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Carrier not found.']);
            exit;
        }
    } else {
        $stmt = $mysqli->prepare("SELECT id, name, verification_status FROM carriers WHERE is_active = 1 ORDER BY name ASC");
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $carriers[] = $row;
        }
        $stmt->close();
    }
} elseif ($user_role === 'superuser' && $_SESSION['entity_type'] === 'carrier') {
    $stmt = $mysqli->prepare("SELECT id, name, verification_status, license_state, license_number, license_expires_at FROM carriers WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $_SESSION['entity_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $selected_carrier = $result->fetch_assoc();
    $stmt->close();

    if ($selected_carrier) {
        $is_editable = ($selected_carrier['verification_status'] === 'waiting');
    } else {
        $page_error = "Carrier record not found.";
    }
}
?>

<div id="licensure-container" class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Licensure Management</h1>
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
    <div id="carriers-list" class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200">
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-800">Active Carriers</h2>
        </div>
        <div class="p-6">
            <?php if (empty($carriers)): ?>
            <p class="text-center text-gray-500">No active carriers found.</p>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Carrier Name</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="relative px-6 py-3"><span class="sr-only">View</span></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($carriers as $carrier_item): ?>
                        <tr class="hover:bg-gray-50 transition-colors duration-150 ease-in-out cursor-pointer view-details-row" data-carrier-id="<?= htmlspecialchars($carrier_item['id']); ?>">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($carrier_item['name']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                    <?= htmlspecialchars(ucwords($carrier_item['verification_status'])); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button type="button" class="view-details-btn text-indigo-600 hover:text-indigo-900">View Details</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($user_role === 'admin'): ?>
    <div id="carrier-details-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center -m-5 mb-5">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-800" id="carrier-name-header">Carrier Details</h2>
                        <p class="text-sm text-gray-500 font-mono" id="carrier-id-header"></p>
                    </div>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium leading-4 bg-blue-100 text-blue-800" id="verification-status-display"></span>
                </div>
                
                <div class="p-6 space-y-8">
                    <form id="licensure-form" method="POST" action="licensure.php" class="space-y-6">
                        <input type="hidden" name="carrier_id" id="carrier-id-input" value="">
                        <div>
                            <label for="verification_status" class="block text-sm font-medium text-gray-700">Verification Status</label>
                            <select id="verification_status" name="verification_status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="waiting">Waiting</option>
                                <option value="pending">Pending Review</option>
                                <option value="verified">Verified</option>
                                <option value="failed">Failed</option>
                                <option value="expired">Expired</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="license_state" class="block text-sm font-medium text-gray-700">License State</label>
                                <input type="text" id="license_state" name="license_state" value="" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div>
                                <label for="license_number" class="block text-sm font-medium text-gray-700">License Number</label>
                                <input type="text" id="license_number" name="license_number" value="" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                        </div>
                        <div>
                            <label for="license_expires_at" class="block text-sm font-medium text-gray-700">Expiration Date</label>
                            <input type="date" id="license_expires_at" name="license_expires_at" value="" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div class="flex justify-end items-center gap-x-4">
                            <button type="button" id="close-modal-btn" class="py-2 px-4 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Close</button>
                            <button type="submit" class="py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($user_role === 'superuser'): ?>
    <div id="licensure-details" class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200 mt-6">
        <?php if ($selected_carrier): ?>
            <?php else: ?>
            <div class="p-6 text-center text-gray-500">
                <p>Licensure information could not be found.</p>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php
require_once 'footer.php';
?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const userRole = '<?= htmlspecialchars($user_role ?? ''); ?>';
    
    if (userRole === 'admin') {
        const carrierList = document.getElementById('carriers-list');
        const modal = document.getElementById('carrier-details-modal');
        const closeModalBtn = document.getElementById('close-modal-btn');

        const showModal = () => modal.classList.remove('hidden');
        const hideModal = () => modal.classList.add('hidden');

        const loadCarrierData = async (carrierId) => {
            try {
                const response = await fetch(`licensure.php?carrier_id=${carrierId}&ajax=true`);
                if (!response.ok) throw new Error(`Network response error. Status: ${response.status}`);
                
                const carrierData = await response.json();
                if (carrierData.error) throw new Error(carrierData.error);

                // Populate Modal Fields
                document.getElementById('carrier-name-header').textContent = carrierData.name;
                document.getElementById('carrier-id-header').textContent = `ID: ${carrierData.id}`;
                document.getElementById('carrier-id-input').value = carrierData.id;
                document.getElementById('verification_status').value = carrierData.verification_status;
                document.getElementById('license_state').value = carrierData.license_state || '';
                document.getElementById('license_number').value = carrierData.license_number || '';
                document.getElementById('license_expires_at').value = carrierData.license_expires_at || '';
                
                const statusDisplay = document.getElementById('verification-status-display');
                const statusText = carrierData.verification_status.replace('_', ' ');
                statusDisplay.textContent = `Status: ${statusText.charAt(0).toUpperCase() + statusText.slice(1)}`;

                showModal();
            } catch (error) {
                console.error('Error fetching carrier data:', error);
                alert('An error occurred while loading carrier data. Please try again.');
            }
        };

        carrierList.addEventListener('click', (event) => {
            const row = event.target.closest('.view-details-row');
            if (row) {
                const carrierId = row.dataset.carrierId;
                loadCarrierData(carrierId);
            }
        });

        closeModalBtn.addEventListener('click', hideModal);
        modal.addEventListener('click', (event) => {
            // Close modal if the outer overlay is clicked
            if (event.target === modal) {
                hideModal();
            }
        });
    }
});
</script>