<?php
$page_title = 'Post-Rejection Options';
require_once 'header.php';
require_once __DIR__ . '/../../app/db_connect.php';

// --- Permission Check ---
if (!isset($_SESSION["loggedin"]) || !in_array($_SESSION['user_role'], ['facility_user', 'facility_superuser', 'bedrock_admin'])) {
    header("location: login.php");
    exit;
}
if (!isset($_GET['uuid'])) {
    header("location: dashboard.php");
    exit;
}

$uuid = $_GET['uuid'];
$facility_id = $_SESSION['entity_id'] ?? null;
$trip = null;

// --- Verify this facility owns the trip OR user is admin ---
$sql_verify = "SELECT id, facility_id FROM trips WHERE uuid = ?";
if ($stmt_verify = $mysqli->prepare($sql_verify)) {
    $stmt_verify->bind_param("s", $uuid);
    $stmt_verify->execute();
    $result = $stmt_verify->get_result();
    if($result->num_rows == 1) {
        $trip = $result->fetch_assoc();
    }
    $stmt_verify->close();
}

if (!$trip || ($_SESSION['user_role'] !== 'bedrock_admin' && $trip['facility_id'] != $facility_id)) {
    header("location: dashboard.php?error=unauthorized");
    exit;
}

// --- Handle the form submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && $_SESSION['user_role'] !== 'bedrock_admin') {
    $decision = $_POST['decision'];
    $sql_update = '';

    if ($decision == 'rebroadcast') {
        $new_bidding_closes_at = date('Y-m-d H:i:s', strtotime('+2 hours'));
        $sql_update = "UPDATE trips SET status = 'bidding', carrier_id = NULL, awarded_eta = NULL, bidding_closes_at = ? WHERE uuid = ?";
        if ($stmt_update = $mysqli->prepare($sql_update)) {
            $stmt_update->bind_param("ss", $new_bidding_closes_at, $uuid);
        }
    } elseif ($decision == 'cancel') {
        $sql_update = "UPDATE trips SET status = 'cancelled' WHERE uuid = ?";
        if ($stmt_update = $mysqli->prepare($sql_update)) {
            $stmt_update->bind_param("s", $uuid);
        }
    }

    if (isset($stmt_update)) {
        if ($stmt_update->execute()) {
            header("location: dashboard.php?status=trip_updated");
        } else {
            echo "Error updating trip.";
        }
        $stmt_update->close();
        exit;
    }
}
$mysqli->close();
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm text-center">
            <div class="card-header"><h2 class="h3">ETA Change Rejected</h2></div>
            <div class="card-body p-4">
                

[Image of a crossroads sign]

                <p class="lead">You have rejected the carrier's new ETA for Trip <code><?php echo substr($uuid, 0, 8); ?>...</code></p>
                <p>What would you like to do now?</p>

                <?php if ($_SESSION['user_role'] !== 'bedrock_admin'): ?>
                    <form action="<?php echo htmlspecialchars($_SERVER["REQUEST_URI"]); ?>" method="post" class="mt-4">
                        <div class="d-grid gap-3 d-sm-flex justify-content-sm-center">
                            <button type="submit" name="decision" value="rebroadcast" class="btn btn-warning btn-lg px-4 gap-3">
                                Re-Broadcast Trip
                            </button>
                            <button type="submit" name="decision" value="cancel" class="btn btn-danger btn-lg px-4">
                                Cancel Trip
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert alert-warning mt-4"><b>Admin Read-Only View:</b> Actions are disabled.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'footer.php';
?>