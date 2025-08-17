<?php
// FILE: public/licensure.php

require_once 'init.php';

// --- Security & Permission Check ---
$user_role = $_SESSION['user_role'] ?? null;
if (!isset($_SESSION["loggedin"]) || !in_array($user_role, ['superuser', 'admin'])) {
    redirect('dashboard.php');
}
// Further check for superuser to ensure they are part of a carrier entity
if ($user_role === 'superuser' && $_SESSION['entity_type'] !== 'carrier') {
    redirect('dashboard.php');
}

$page_title = 'Licensure Management';
$db = Database::getInstance();
$page_message = '';
$page_error = '';
$carriers = [];
$selected_carrier = null; 
$is_editable = false;

if (isset($_GET['update']) && $_GET['update'] === 'success') {
    $page_message = "Carrier information has been updated successfully.";
}

// --- Data Retrieval ---
try {
    if ($user_role === 'admin') {
        $carriers = $db->query("SELECT id, name, verification_status FROM carriers WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
    } elseif ($user_role === 'superuser') {
        $selected_carrier = $db->query("SELECT id, name, verification_status, license_state, license_number, license_expires_at FROM carriers WHERE id = ? LIMIT 1", [$_SESSION['entity_id']])->fetch();
        if ($selected_carrier) {
            $is_editable = ($selected_carrier['verification_status'] === 'waiting');
        } else {
            $page_error = "Your carrier record could not be found.";
        }
    }
} catch (Exception $e) {
    $page_error = "An error occurred while fetching licensure data.";
    error_log("Licensure Page Error: " . $e->getMessage());
}

require_once 'header.php';
?>

<div id="licensure-container" class="p-6">
    <?php if ($user_role === 'admin'): ?>
        <a href="licensure_details.php?carrier_id=<?= e($carrier_item['id']); ?>" class="text-indigo-600 hover:text-indigo-900">View Details</a>
    <?php endif; ?>

    <?php if ($user_role === 'superuser' && $selected_carrier): ?>
        <input type="text" id="license_state" name="license_state" value="<?= e($selected_carrier['license_state'] ?? ''); ?>" <?= !$is_editable ? 'readonly' : ''; ?> >
    <?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>