<?php
$page_title = 'Review Case';
require_once 'header.php';
require_once __DIR__ . '/../../app/db_connect.php';

// --- Permission Check & Input Validation ---
if (!isset($_SESSION["loggedin"]) || $_SESSION['user_role'] !== 'bedrock_admin') { header("location: login.php"); exit; }
if (!isset($_GET['id'])) { header("location: case_management.php"); exit; }

$case_id = $_GET['id'];
$case_details = null;

// --- Handle Case Resolution ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['resolve_case'])) {
    $resolution_notes = htmlspecialchars(trim($_POST['resolution_notes']));
    
    $sql_update = "UPDATE cases SET status = 'resolved', resolution_notes = ?, resolved_at = NOW(), resolved_by_user_id = ? WHERE id = ?";
    if($stmt_update = $mysqli->prepare($sql_update)) {
        $stmt_update->bind_param("sii", $resolution_notes, $_SESSION['user_id'], $case_id);
        if($stmt_update->execute()) {
            header("location: case_management.php?status=resolved");
            exit;
        }
    }
}

// --- Fetch Case Details ---
$sql = "SELECT c.*, t.uuid AS trip_uuid, u.email AS opener_email
        FROM cases c
        JOIN trips t ON c.trip_id = t.id
        JOIN users u ON c.opened_by_user_id = u.id
        WHERE c.id = ?";

if ($stmt = $mysqli->prepare($sql)) {
    $stmt->bind_param("i", $case_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 1) {
        $case_details = $result->fetch_assoc();
    } else {
        header("location: case_management.php?error=notfound");
        exit;
    }
    $stmt->close();
}
$mysqli->close();
?>

<h2 class="mb-4">Reviewing Case #<?php echo $case_details['id']; ?></h2>
        
<div class="card shadow-sm mb-4">
    <div class="card-header"><h5 class="mb-0">Case Details</h5></div>
    <div class="card-body">
        <dl class="row">
            <dt class="col-sm-3">Trip ID:</dt>
            <dd class="col-sm-9"><code><?php echo htmlspecialchars($case_details['trip_uuid']); ?></code></dd>
            
            <dt class="col-sm-3">Case Type:</dt>
            <dd class="col-sm-9"><?php echo htmlspecialchars($case_details['case_type']); ?></dd>
            
            <dt class="col-sm-3">Opened By:</dt>
            <dd class="col-sm-9"><?php echo htmlspecialchars($case_details['opener_email']); ?></dd>
            
            <dt class="col-sm-3">Date Opened:</dt>
            <dd class="col-sm-9"><?php echo date('M j, Y g:i A', strtotime($case_details['created_at'])); ?></dd>
            
            <dt class="col-sm-3">Initial Notes:</dt>
            <dd class="col-sm-9"><?php echo nl2br(htmlspecialchars($case_details['initial_notes'])); ?></dd>
        </dl>
    </div>
</div>

<?php if ($case_details['status'] == 'open'): ?>
    <div class="card shadow-sm">
        <div class="card-header"><h5 class="mb-0">Resolve Case</h5></div>
        <div class="card-body">
            <form action="<?php echo htmlspecialchars($_SERVER["REQUEST_URI"]); ?>" method="post">
                <div class="mb-3">
                    <label for="resolution_notes" class="form-label">Resolution Notes</label>
                    <textarea name="resolution_notes" id="resolution_notes" class="form-control" rows="4" required></textarea>
                </div>
                <div class="d-grid">
                    <button type="submit" name="resolve_case" value="1" class="btn btn-primary btn-lg">Mark as Resolved</button>
                </div>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="card shadow-sm">
         <div class="card-header"><h5 class="mb-0">Case Resolved</h5></div>
        <div class="card-body">
            <dl class="row">
                <dt class="col-sm-3">Status:</dt>
                <dd class="col-sm-9"><span class="badge bg-success fs-6"><?php echo ucfirst($case_details['status']); ?></span></dd>
                <dt class="col-sm-3">Resolution Notes:</dt>
                <dd class="col-sm-9"><?php echo nl2br(htmlspecialchars($case_details['resolution_notes'])); ?></dd>
            </dl>
        </div>
    </div>
<?php endif; ?>

<?php
require_once 'footer.php';
?>