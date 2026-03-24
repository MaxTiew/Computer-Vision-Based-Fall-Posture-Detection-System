<?php
// dashboard.php
require_once 'auth_session.php';
require_caregiver_auth();

include 'db_connect.php';

// Fetch the actual logged-in user's name
$caregiverName = $_SESSION['caregiverName'] ?? 'Caregiver';

// Query the database to check if there are any real "Pending" alerts
$pending_count = 0;
$alert_sql = "SELECT COUNT(*) as count FROM EventLog WHERE status = 'Pending'";
$result = $conn->query($alert_sql);
if ($result && $row = $result->fetch_assoc()) {
    $pending_count = $row['count'];
}

// System Status 
$monitoringStatus = "System is monitoring and ready. Node: OK"; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Main Dashboard - GoodLife Vision</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            background-color: #f8f9fc; /* Light grey background from PDF */
            display: flex;
            height: 100vh;
            color: #2c3e50;
        }
        
        /* Sidebar Styling (Light Theme) */
        .sidebar {
            width: 260px;
            background-color: #ffffff;
            border-right: 1px solid #eaeaea;
            display: flex;
            flex-direction: column;
            padding-top: 20px;
        }
        .logo-container {
            padding: 0 20px 30px 20px;
        }
        .logo-container img {
            width: 140px;
            height: auto;
        }
        .nav-links {
            list-style: none;
            padding: 0;
            margin: 0;
            flex-grow: 1;
        }
        .nav-links li {
            margin-bottom: 5px;
        }
        .nav-links a {
            display: flex;
            align-items: center;
            padding: 14px 24px;
            color: #7f8c8d;
            text-decoration: none;
            font-size: 15px;
            font-weight: 500;
            transition: all 0.3s;
        }
        .nav-links a i {
            margin-right: 15px;
            font-size: 18px;
            width: 20px;
            text-align: center;
        }
        .nav-links a:hover {
            background-color: #f4f7f6;
            color: #2c3e50;
        }
        .nav-links a.active {
            color: #4a90e2;
            border-left: 4px solid #4a90e2;
            background-color: #f0f7ff;
        }
        
        /* Red Notification Badge for Sidebar/Cards */
        .badge {
            background-color: #e74c3c;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 11px;
            margin-left: auto;
            font-weight: bold;
        }

        /* Main Content Area */
        .main-content {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        /* Top Bar */
        .top-bar {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            padding: 20px 40px;
            background-color: #f8f9fc;
        }
        .profile-info {
            text-align: right;
            margin-right: 20px;
        }
        .profile-info h4 {
            margin: 0;
            font-size: 14px;
            color: #2c3e50;
        }
        .profile-info p {
            margin: 0;
            font-size: 12px;
            color: #7f8c8d;
        }
        .logout-btn {
            color: #7f8c8d;
            text-decoration: none;
            font-size: 14px;
            display: flex;
            align-items: center;
            transition: color 0.3s;
        }
        .logout-btn i {
            margin-left: 8px;
        }
        .logout-btn:hover {
            color: #e74c3c;
        }

        /* Dashboard Body */
        .dashboard-body {
            padding: 20px 60px;
            text-align: center;
            flex-grow: 1;
        }
        .welcome-text h1 {
            font-size: 28px;
            margin-bottom: 5px;
            font-weight: 600;
        }
        .welcome-text p {
            color: #7f8c8d;
            margin-top: 0;
            margin-bottom: 40px;
        }

        /* Dynamic Alert Banner */
        .alert-banner {
            background: linear-gradient(to right, #e74c3c, #c0392b);
            color: white;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 16px;
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
        }
        .alert-banner i {
            margin-right: 10px;
            font-size: 20px;
        }

        /* 3-Card Layout */
        .cards-container {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-bottom: 40px;
        }
        .card {
            width: 260px;
            padding: 30px 20px;
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.04);
            text-decoration: none;
            color: #2c3e50;
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        }
        .card i {
            font-size: 32px;
            margin-bottom: 15px;
            color: #4a90e2;
        }
        .card h3 {
            margin: 0 0 10px 0;
            font-size: 16px;
        }
        .card p {
            margin: 0;
            font-size: 13px;
            color: #7f8c8d;
            line-height: 1.5;
        }

        /* Highlighted Blue Center Card */
        .card.primary-card {
            background: linear-gradient(135deg, #4a90e2, #5c6bc0);
            color: white;
        }
        .card.primary-card i, .card.primary-card p {
            color: white;
        }

        /* Card Notification Badge */
        .card-badge {
            position: absolute;
            top: -10px;
            right: -10px;
            background-color: #e74c3c;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .footer-status {
            color: #95a5a6;
            font-size: 13px;
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="logo-container">
            <img src="images/logo.jpg" alt="GoodLife Vision Logo">
        </div>
        
        <ul class="nav-links">
            <li><a href="dashboard.php" class="active"><i class="fa-solid fa-table-cells-large"></i> Dashboard</a></li>
            <li><a href="profiles.php"><i class="fa-solid fa-user-group"></i> Elder/OKU Profiles</a></li>
            <li><a href="monitoring.php"><i class="fa-solid fa-video"></i> Monitoring</a></li>
            <li>
                <a href="alerts.php">
                    <i class="fa-regular fa-bell"></i> Alerts 
                    <?php if($pending_count > 0): ?>
                        <span class="badge"><?php echo $pending_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li><a href="settings.php"><i class="fa-solid fa-gear"></i> Settings</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <div class="profile-info">
                <h4><?php echo htmlspecialchars($caregiverName); ?></h4>
                <p>Caregiver</p>
            </div>
            <a href="logout.php" class="logout-btn"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
        </div>

        <div class="dashboard-body">
            <div class="welcome-text">
                <h1>Welcome, <?php echo htmlspecialchars($caregiverName); ?></h1>
                <p>What would you like to do today?</p>
            </div>

            <?php if($pending_count > 0): ?>
            <div class="alert-banner">
                <i class="fa-solid fa-bell"></i>
                <?php echo $pending_count; ?> Pending Alert<?php echo $pending_count > 1 ? 's' : ''; ?>! Immediate attention needed!
            </div>
            <?php endif; ?>

            <div class="cards-container">
                <a href="profiles.php" class="card">
                    <i class="fa-solid fa-user-group" style="color: #a29bfe;"></i>
                    <h3>Elder/Oku Profiles</h3>
                    <p>Manage elder/oku information and contacts</p>
                </a>
                
                <a href="monitoring.php" class="card primary-card">
                    <i class="fa-solid fa-video"></i>
                    <h3>Start Monitoring</h3>
                    <p>Begin real-time fall detection</p>
                </a>

                <a href="alerts.php" class="card">
                    <?php if($pending_count > 0): ?>
                        <div class="card-badge"><?php echo $pending_count; ?></div>
                    <?php endif; ?>
                    <i class="fa-regular fa-bell" style="color: #f1c40f;"></i>
                    <h3>Event Log</h3>
                    <p>View alerts and event history</p>
                </a>
            </div>

            <div class="footer-status">
                <?php echo $monitoringStatus; ?>
            </div>
        </div>
    </div>

    <?php render_auth_client_script(); ?>
    <script src="monitoring_global.js"></script>
</body>
</html>
