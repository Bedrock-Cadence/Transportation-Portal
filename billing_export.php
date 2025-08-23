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
// Handle the form submission
// Handle the form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $startDate = $_POST['start_date'] ?? null;
    $endDate = $_POST['end_date'] ?? null;

    if (empty($startDate) || empty($endDate)) {
        $message = "Please select both a start and end date.";
        $messageType = 'danger';
    } else {
        try {
            $exportService = new BillingExportService();
            $records = $exportService->getBillingDataForExport($startDate, $endDate);

            // --- CHECKPOINT 1 ---
            // Let's confirm we are still getting records correctly.
            echo 'Checkpoint 1: Records fetched successfully. Dumping records:';
            echo '<pre>';
            var_dump($records);
            echo '</pre>';
            // Move the die() statement below to the next checkpoint to continue debugging.
            die('Execution stopped at Checkpoint 1.');


            if (empty($records)) {
                $message = "No un-exported billing records were found for the selected date range.";
                $messageType = 'warning';
            } else {
                // --- CHECKPOINT 2 ---
                // We're inside the CSV generation block, right before we try to send headers.
                // If you see CP1 but not this, the 'if (empty($records))' logic has a problem.
                // die('Execution stopped at Checkpoint 2.');

                // Get the IDs before generating the download
                $billingIdsToMark = array_column($records, 'billing_id_internal');
                
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="billing_export_' . date('Y-m-d') . '.csv"');

                // --- CHECKPOINT 3 ---
                // If you saw CP2 but don't get a file download, the header() calls may be failing.
                // This is less likely to cause a WSOD, but good to check.
                // A failure here would show up in the error log (if enabled).
                // die('Execution stopped at Checkpoint 3.');

                $output = fopen('php://output', 'w');

                fputcsv($output, [
                    "Invoice Date", "Invoice Number", "Estimate Number", "Invoice Status", "Customer Name", 
                    "Due Date", "PurchaseOrder", "Template Name", "Currency Code", "Exchange Rate", 
                    "Item Name", "SKU", "Item Desc", "Quantity", "Item Price", "Discount(%)", 
                    "Item Tax", "Item Tax %", "Item Tax Authority", "Item Tax Exemption Reason", 
                    "Notes", "Terms & Conditions"
                ]);

                // --- CHECKPOINT 4 ---
                // The most likely place for an error is in this loop if the data is bad.
                // For example, a null value for 'invoice_date' could cause strtotime() to fail.
                // die('Execution stopped at Checkpoint 4.');

                foreach ($records as $index => $record) {
                    // echo 'Processing record ' . $index . '<br>'; // Uncomment for deep debugging
                    fputcsv($output, [
                        date('Y-m-d', strtotime($record['invoice_date'])),
                        'INV-' . $record['invoice_number'],
                        '', 'Draft', $record['customer_name'], date('Y-m-d', strtotime($record['invoice_date'])),
                        $record['purchase_order'], 'Classic', 'USD', '1.00', $record['item_name'], $record['sku'],
                        'Trip Service - ID: ' . $record['purchase_order'], '1', '0.00', '0', '', '', '', '',
                        'Thank you for your business.', 'Please pay within 30 days.'
                    ]);
                }

                fclose($output);
                
                $exportService->markAsExported($billingIdsToMark);
                
                exit;
            }
        } catch (Exception $e) {
            // --- CHECKPOINT 5 ---
            // If the script fails and jumps to the catch block, this will tell us.
            // The error would likely be in the LoggingService class itself.
            die('Execution stopped at Checkpoint 5. Exception caught: ' . $e->getMessage());
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