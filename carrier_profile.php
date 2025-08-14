<?php
$page_title = 'Carrier Profile';
require_once 'header.php';
require_once __DIR__ . '/../../app/db_connect.php';

// --- Permission Check for Carrier Super User ---
if (!isset($_SESSION["loggedin"]) || $_SESSION['user_role'] !== 'carrier_superuser') {
    header("location: dashboard.php");
    exit;
}

// Re-using our placeholder encryption function
function encrypt_data_placeholder($data) { return base64_encode($data); }

$carrier_id = $_SESSION['entity_id'];
$page_message = '';

// --- Handle Form Submissions ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update_license'])) {
        $license_number = htmlspecialchars(trim($_POST['license_number']));
        $license_state = htmlspecialchars(trim($_POST['license_state']));
        $license_expires_at = $_POST['license_expires_at'];

        $sql = "UPDATE carriers SET license_number = ?, license_state = ?, license_expires_at = ? WHERE id = ?";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("sssi", $license_number, $license_state, $license_expires_at, $carrier_id);
            if($stmt->execute()) {
                $page_message = '<div class="alert alert-success">Licensing info updated successfully!</div>';
            } else {
                $page_message = '<div class="alert alert-danger">Error updating licensing info.</div>';
            }
            $stmt->close();
        }
    } elseif (isset($_POST['update_cad'])) {
        $cad_config = [
            'type' => $_POST['cad_type'],
            'endpoint' => trim($_POST['cad_endpoint']),
            'api_key' => trim($_POST['cad_api_key']) // The key is handled in memory and encrypted immediately
        ];

        $encrypted_config = encrypt_data_placeholder(json_encode($cad_config));

        $sql = "UPDATE carriers SET cad_import_config = ? WHERE id = ?";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("si", $encrypted_config, $carrier_id);
            if($stmt->execute()) {
                $page_message = '<div class="alert alert-success">CAD configuration saved successfully!</div>';
            } else {
                $page_message = '<div class="alert alert-danger">Error saving CAD configuration.</div>';
            }
            $stmt->close();
        }
    }
}

// --- Fetch current carrier data ---
$carrier = null;
$sql_fetch = "SELECT * FROM carriers WHERE id = ?";
if ($stmt_fetch = $mysqli->prepare($sql_fetch)) {
    $stmt_fetch->bind_param("i", $carrier_id);
    $stmt_fetch->execute();
    $result = $stmt_fetch->get_result();
    $carrier = $result->fetch_assoc();
    $stmt_fetch->close();
}
$mysqli->close();
?>

<h2 class="mb-4">Carrier Company Profile</h2>
<?php echo $page_message; ?>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <fieldset>
                <legend class="fs-5 border-bottom mb-3 pb-2">Licensing Information</legend>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="license_number" class="form-label">State License Number</label>
                        <input type="text" name="license_number" class="form-control" value="<?php echo htmlspecialchars($carrier['license_number']); ?>" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="license_state" class="form-label">License State</label>
                        <input type="text" name="license_state" class="form-control" value="<?php echo htmlspecialchars($carrier['license_state']); ?>" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="license_expires_at" class="form-label">Expiration Date</label>
                        <input type="date" name="license_expires_at" class="form-control" value="<?php echo htmlspecialchars($carrier['license_expires_at']); ?>" required>
                    </div>
                </div>
                <button type="submit" name="update_license" class="btn btn-primary">Save Licensing Info</button>
            </fieldset>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <fieldset>
                <legend class="fs-5 border-bottom mb-3 pb-2">CAD Import Configuration</legend>
                <p>Enter the API details for your dispatch software to enable one-click trip imports.</p>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="cad_type" class="form-label">CAD System</label>
                        <select name="cad_type" id="cad_type" class="form-select">
                            <option value="angeltrack">AngelTrack</option>
                            <option value="logis">LOGIS</option>
                            <option value="traumasoft">TraumaSoft</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-8 mb-3">
                        <label for="cad_endpoint" class="form-label">API Endpoint URL</label>
                        <input type="url" name="cad_endpoint" class="form-control" placeholder="https://your-company.traumasoft.com/api/v1/trips">
                    </div>
                </div>
                <div class="mb-3">
                    <label for="cad_api_key" class="form-label">API Key or Token</label>
                    <input type="password" name="cad_api_key" class="form-control">
                </div>
                <button type="submit" name="update_cad" class="btn btn-primary">Save CAD Configuration</button>
            </fieldset>
        </form>
    </div>
</div>

<?php
require_once 'footer.php';
?>