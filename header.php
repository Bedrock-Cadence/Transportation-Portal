<?php
// We start the session on every page that includes the header.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Bedrock Cadence Portal'; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    </head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="index.php">Bedrock Cadence</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNavbar">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <?php if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
            <li class="nav-item">
                <a class="nav-link" href="index.php">Dashboard</a>
            </li>
            <?php // Carrier Links
            if (in_array($_SESSION['user_role'], ['carrier_user', 'carrier_superuser'])): ?>
                <li class="nav-item"><a class="nav-link" href="trip_board.php">Trip Board</a></li>
                <li class="nav-item"><a class="nav-link" href="carrier_profile.php">My Profile</a></li>
            <?php endif; ?>

            <?php
            if(in_array($_SESSION['user_role'], ['carrier_superuser', 'facility_superuser', 'bedrock_admin'])) : ?>
            <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Admin Tools
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                        <li><a class="dropdown-item" href="users.php">User Management</a></li>

                        <?php if (in_array($_SESSION['user_role'], ['bedrock_admin'])): ?>
                            <li><a class="dropdown-item" href="verify_carriers.php">Verify Carriers</a></li>
                            <li class="list-group-item"><a href="billing.php">Generate Billing Export</a></li>
                        <?php endif; ?>

                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="billing.php">Billing Export</a></li>
                    </ul>
            </li>
            <?php endif; ?>

            <?php // Facility Links
            if (in_array($_SESSION['user_role'], ['facility_user', 'facility_superuser'])): ?>
                 <li class="nav-item"><a class="nav-link" href="create_trip.php">Create Trip</a></li>
                 <li class="nav-item"><a class="nav-link" href="#">View Trips</a></li>
            <?php endif; ?>

            <?php // Admin Links
            if ($_SESSION['user_role'] === 'bedrock_admin'): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Admin Tools
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                        <li><a class="dropdown-item" href="invite_user.php">Invite User</a></li>
                        <li><a class="dropdown-item" href="verify_carriers.php">Verify Carriers</a></li>
                        <li><a class="dropdown-item" href="case_management.php">Manage Cases</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="billing.php">Billing Export</a></li>
                    </ul>
                </li>
            <?php endif; ?>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav">
        <?php if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
            <li class="nav-item">
                <a class="nav-link" href="logout.php">Log Out</a>
            </li>
        <?php else: ?>
             <li class="nav-item">
                <a class="nav-link" href="login.php">Login</a>
            </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<main class="container mt-4">