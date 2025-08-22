<?php
// FILE: public_html/admin/billing_export.php

// Initialize the application and session
require_once __DIR__ . '/../../app/init.php';

// --- Security Check ---
// Ensure the user is logged in and is an admin.
// You might have a more robust role check in a central function.
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    // Redirect to login page or show an access denied message
    header('Location: /login.php');
    exit;
}

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
            $exportService = new BillingExportService();
            $records = $exportService->getBillingDataForExport($startDate, $endDate);

            if (empty($records)) {
                $message = "No un-exported billing records were found for the selected date range.";
                $messageType = 'warning';
            } else {
                // --- CSV Generation and Download ---
                $billingIdsToMark = array_column($records, 'billing_id_internal');

                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="billing_export_' . date('Y-m-d') . '.csv"');

                $output = fopen('php://output', 'w');

                // Add the header row from your sample file
                fputcsv($output, [
                    "Invoice Date", "Invoice Number", "Estimate Number", "Invoice Status", "Customer Name", 
                    "Due Date", "PurchaseOrder", "Template Name", "Currency Code", "Exchange Rate", 
                    "Item Name", "SKU", "Item Desc", "Quantity", "Item Price", "Discount(%)", 
                    "Item Tax", "Item Tax %", "Item Tax Authority", "Item Tax Exemption Reason", 
                    "Notes", "Terms & Conditions"
                ]);

                // Loop through the data and map it to the CSV format
                foreach ($records as $record) {
                    fputcsv($output, [
                        date('Y-m-d', strtotime($record['invoice_date'])), // Invoice Date
                        'INV-' . $record['invoice_number'], // Invoice Number
                        '', // Estimate Number
                        'Draft', // Invoice Status
                        $record['customer_name'], // Customer Name
                        date('Y-m-d', strtotime($record['invoice_date'])), // Due Date
                        $record['purchase_order'], // PurchaseOrder
                        'Classic', // Template Name
                        'USD', // Currency Code
                        '1.00', // Exchange Rate
                        $record['item_name'], // Item Name
                        $record['sku'], // SKU
                        'Trip Service - ID: ' . $record['purchase_order'], // Item Desc
                        '1', // Quantity
                        '0.00', // Item Price (NOTE: Price is not in the DB, placeholder used)
                        '0', // Discount(%)
                        '', // Item Tax
                        '', // Item Tax %
                        '', // Item Tax Authority
                        '', // Item Tax Exemption Reason
                        'Thank you for your business.', // Notes
                        'Please pay within 30 days.' // Terms & Conditions
                    ]);
                }

                fclose($output);

                // --- Mark records as exported AFTER successful download ---
                $exportService->markAsExported($billingIdsToMark);

                // Stop script execution to prevent rendering the HTML below
                exit;
            }
        } catch (Exception $e) {
            $message = "An error occurred: " . $e->getMessage();
            $messageType = 'danger';
            LoggingService::log($_SESSION['user_id'], null, 'billing_export_failed', $e->getMessage());
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
    <!-- Using Tailwind CSS for styling -->
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