<?php
$page_title = 'Case Management';
require_once 'header.php';
require_once __DIR__ . '/../../app/db_connect.php';

// --- Permission Check for Bedrock Admin ---
if (!isset($_SESSION["loggedin"]) || $_SESSION['user_role'] !== 'bedrock_admin') {
    header("location: login.php");
    exit;
}

// --- Fetch all open cases ---
$open_cases = [];
$sql = "SELECT c.id, c.case_type, c.created_at, t.uuid AS trip_uuid
        FROM cases c
        JOIN trips t ON c.trip_id = t.id
        WHERE c.status = 'open'
        ORDER BY c.created_at ASC";

$result = $mysqli->query($sql);
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()){
        $open_cases[] = $row;
    }
}
$mysqli->close();
?>

<h2 class="mb-4">Open Case Queue</h2>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Case ID</th>
                        <th>Trip ID</th>
                        <th>Case Type</th>
                        <th>Date Opened</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($open_cases)): ?>
                        <?php foreach ($open_cases as $case): ?>
                            <tr>
                                <td><?php echo $case['id']; ?></td>
                                <td><code><?php echo substr($case['trip_uuid'], 0, 8); ?>...</code></td>
                                <td><?php echo htmlspecialchars($case['case_type']); ?></td>
                                <td><?php echo date('M j, Y g:i A', strtotime($case['created_at'])); ?></td>
                                <td>
                                    <a href="view_case.php?id=<?php echo $case['id']; ?>" class="btn btn-primary btn-sm">Review Case</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center p-4">No open cases at this time. Great job! ✅</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
require_once 'footer.php';
?>