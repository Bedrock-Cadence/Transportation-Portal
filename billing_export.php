<?php
// FILE: public_html/admin/billing_export.php

// Initialize the application and session
require_once __DIR__ . '/../../app/init.php';
echo '<pre>DEBUG: Application and session initialized.</pre>';

// --- Security Check ---
// Ensure the user is logged in and is an admin.
// You might have a more robust role check in a central function.
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo '<pre>DEBUG: Security check failed. User not logged in or not admin.</pre>';
    // Redirect to login page or show an access denied message
    header('Location: /login.php');
    exit;
}
echo '<pre>DEBUG: Security check passed. User is an admin.</pre>';


$pageTitle = "Billing Export";
$message = '';
$messageType = '';

// Handle the form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $startDate = $_POST['start_date'] ?? null;
    $endDate = $_POST['end_date'] ?? null;

    if (empty($startDate) || empty($endDate)) {
        $message = "Please select both a start and end date.";
        $messageType = 'danger';
    } else {
try {
            echo 'Checkpoint 1: Inside the try block.';

            echo '<br>Checkpoint 2: About to create new BillingExportService() instance...';
            $exportService = new BillingExportService();
            echo '<br>Checkpoint 3: BillingExportService instance CREATED SUCCESSFULLY.';

            echo '<br>Checkpoint 4: About to call getBillingDataForExport() method...';
            $records = $exportService->getBillingDataForExport($startDate, $endDate);
            echo '<br>Checkpoint 5: getBillingDataForExport() method EXECUTED SUCCESSFULLY.';
            
            // For now, we stop here to see the debug output clearly.
            die('<br><br>--- DEBUGGING COMPLETE ---<br>All steps passed. The issue is further down.');

            // The rest of your script logic would go here...

        } catch (PDOException $e) {
            // This will catch database-specific errors (like connection failed)
            die("<br><br><strong>DATABASE ERROR CAUGHT:</strong> " . $e->getMessage());

        } catch (Exception $e) {
            // This will catch other general PHP errors
            die("<br><br><strong>GENERAL ERROR CAUGHT:</strong> " . $e->getMessage());
        }
    }
}

// Include your standard header file
// require_once __DIR__ . '/../_templates/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto mt-10 p-8 bg-white rounded-lg shadow-md max-w-2xl">
        <h1 class="text-2xl font-bold mb-6 text-gray-800">Billing Export</h1>
        
        <p class="text-gray-600 mb-6">
            Select a date range to download a CSV of all un-exported billing transactions. 
            Once downloaded, the records will be marked as exported.
        </p>

        <?php if ($message): ?>
            <div class="p-4 mb-4 text-sm rounded-lg <?php echo $messageType === 'danger' ? 'bg-red-100 text-red-700' : ($messageType === 'warning' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700'); ?>" role="alert">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form action="billing_export.php" method="POST">
            <div class="flex flex-col md:flex-row gap-4 mb-6">
                <div class="flex-1">
                    <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                    <input type="date" id="start_date" name="start_date" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div class="flex-1">
                    <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                    <input type="date" id="end_date" name="end_date" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>
            
            <div class="text-right">
                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Generate and Download CSV
                </button>
            </div>
        </form>
    </div>
</body>
</html>