<?php
// FILE: licensure.php (REVISED)

$page_title = 'Licensure Management';
require_once 'header.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

$allowed_roles = ['superuser', 'admin'];
$user_role = $_SESSION['user_role'] ?? null;
if (!in_array($user_role, $allowed_roles) || ($_SESSION['entity_type'] ?? null) !== 'carrier' && $user_role !== 'admin') {
    header("location: dashboard.php");
    exit;
}

require_once __DIR__ . '/../../app/db_connect.php';

$page_message = '';
$carriers = [];
$selected_carrier = null; 
$is_editable = false;

// Check for a success message from the details page
if (isset($_GET['update']) && $_GET['update'] === 'success') {
    $page_message = "Carrier information has been updated successfully.";
}

// --- DATA RETRIEVAL ---
if ($user_role === 'admin') {
    // Admin sees a list of all carriers
    $stmt = $mysqli->prepare("SELECT id, name, verification_status FROM carriers WHERE is_active = 1 ORDER BY name ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $carriers[] = $row;
    }
    $stmt->close();

} elseif ($user_role === 'superuser' && $_SESSION['entity_type'] === 'carrier') {
    // Superuser sees their own details (This entire section is unchanged)
    $stmt = $mysqli->prepare("SELECT id, name, verification_status, license_state, license_number, license_expires_at FROM carriers WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $_SESSION['entity_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $selected_carrier = $result->fetch_assoc();
    $stmt->close();

    if ($selected_carrier) {
        $is_editable = ($selected_carrier['verification_status'] === 'waiting');
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
                            <th scope="col" class="relative px-6 py-3"><span class="sr-only">Edit</span></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($carriers as $carrier_item): ?>
                        <tr class="hover:bg-gray-50 transition-colors duration-150 ease-in-out">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($carrier_item['name']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                    <?= htmlspecialchars(ucwords($carrier_item['verification_status'])); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="licensure-details.php?carrier_id=<?= htmlspecialchars($carrier_item['id']); ?>" class="text-indigo-600 hover:text-indigo-900">
                                    View Details
                                </a>
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

    <?php if ($user_role === 'superuser'): ?>
    <div id="licensure-details" class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200 mt-6">
        <?php if ($selected_carrier): ?>
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                 <h2 class="text-xl font-semibold text-gray-800"><?= htmlspecialchars($selected_carrier['name']); ?></h2>
                 <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                     Status: <?= htmlspecialchars(ucwords(str_replace('_', ' ', $selected_carrier['verification_status']))); ?>
                 </span>
            </div>
            <div class="p-6">
                <form id="licensure-form-superuser" method="POST" action="licensure-details.php" class="space-y-6">
                    <input type="hidden" name="carrier_id" value="<?= htmlspecialchars($selected_carrier['id']); ?>">
                     <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="license_state" class="block text-sm font-medium text-gray-700">License State</label>
                            <input type="text" id="license_state" name="license_state" value="<?= htmlspecialchars($selected_carrier['license_state'] ?? ''); ?>" <?= !$is_editable ? 'readonly' : ''; ?> required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm <?= !$is_editable ? 'bg-gray-100' : ''; ?>">
                        </div>
                        <div>
                           <label for="license_number" class="block text-sm font-medium text-gray-700">License Number</label>
                           <input type="text" id="license_number" name="license_number" value="<?= htmlspecialchars($selected_carrier['license_number'] ?? ''); ?>" <?= !$is_editable ? 'readonly' : ''; ?> required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm <?= !$is_editable ? 'bg-gray-100' : ''; ?>">
                        </div>
                     </div>
                     <div>
                        <label for="license_expires_at" class="block text-sm font-medium text-gray-700">Expiration Date</label>
                        <input type="date" id="license_expires_at" name="license_expires_at" value="<?= htmlspecialchars($selected_carrier['license_expires_at'] ?? ''); ?>" <?= !$is_editable ? 'readonly' : ''; ?> required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm <?= !$is_editable ? 'bg-gray-100' : ''; ?>">
                     </div>
                    <?php if ($is_editable): ?>
                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">Save Changes</button>
                    </div>
                    <?php else: ?>
                    <div class="bg-yellow-100 text-yellow-800 p-3 rounded-md text-sm text-center font-semibold">
                        This information is in '<?= htmlspecialchars($selected_carrier['verification_status']); ?>' status and cannot be changed.
                    </div>
                    <?php endif; ?>
                 </form>
            </div>
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