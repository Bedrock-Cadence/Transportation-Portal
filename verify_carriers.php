<?php
$page_title = 'Verify Carriers';
require_once 'header.php';
require_once __DIR__ . '/../../app/db_connect.php';

// --- Permission Check for Bedrock Admin ---
if (!isset($_SESSION["loggedin"]) || $_SESSION['user_role'] !== 'bedrock_admin') {
    header("location: login.php");
    exit;
}

// --- Handle Approval/Denial ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $carrier_id_to_update = $_POST['carrier_id'];
    $decision = $_POST['decision'];
    $new_status = ($decision == 'approve') ? 'verified' : 'failed';

    $sql_update = "UPDATE carriers SET verification_status = ?, is_verified = ? WHERE id = ?";
    if ($stmt_update = $mysqli->prepare($sql_update)) {
        $is_verified_flag = ($decision == 'approve') ? 1 : 0;
        $stmt_update->bind_param("sii", $new_status, $is_verified_flag, $carrier_id_to_update);
        $stmt_update->execute();
        $stmt_update->close();
    }
}

// --- Fetch all pending carriers ---
$pending_carriers = [];
$sql = "SELECT id, name, license_number, license_state, license_expires_at FROM carriers WHERE verification_status = 'pending'";
$result = $mysqli->query($sql);
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()){
        $pending_carriers[] = $row;
    }
}
$mysqli->close();
?>

<h2 class="mb-4">Pending Carrier Verifications</h2>

<?php if (!empty($pending_carriers)): ?>
    <?php foreach ($pending_carriers as $carrier): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-1"><?php echo htmlspecialchars($carrier['name']); ?></h5>
                        <p class="card-text text-muted mb-0">
                            <b>License #:</b> <?php echo htmlspecialchars($carrier['license_number']); ?> | 
                            <b>State:</b> <?php echo htmlspecialchars($carrier['license_state']); ?> | 
                            <b>Expires:</b> <?php echo htmlspecialchars($carrier['license_expires_at']); ?>
                        </p>
                    </div>
                    <div>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline">
                            <input type="hidden" name="carrier_id" value="<?php echo $carrier['id']; ?>">
                            <button type="submit" name="decision" value="approve" class="btn btn-success">Approve</button>
                        </form>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="d-inline">
                            <input type="hidden" name="carrier_id" value="<?php echo $carrier['id']; ?>">
                            <button type="submit" name="decision" value="reject" class="btn btn-danger">Reject</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="alert alert-success">
        <h4 class="alert-heading">All Clear!</h4>
        <p>No carriers are currently pending verification. ✅</p>
    </div>
<?php endif; ?>

<?php
require_once 'footer.php';
?>