<?php
// active_monitoring.php
require_once 'auth_session.php';
require_caregiver_auth();
include 'db_connect.php';

$caregiverName = $_SESSION['caregiverName'] ?? 'Caregiver';

// 1. If coming from the setup page (POST), initialize the session
if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($_POST['elderID'])) {
    $_SESSION['activeElderID'] = $conn->real_escape_string($_POST['elderID']);
    $_SESSION['cameraMode'] = (($_POST['cameraMode'] ?? 'single') === 'dual') ? 'dual' : 'single';
    $_SESSION['camera1'] = trim($_POST['camera1'] ?? '');
    $_SESSION['camera2'] = trim($_POST['camera2'] ?? '');
}

// 2. If no active elder is set (either in POST or SESSION), go back to setup
if (!isset($_SESSION['activeElderID'])) {
    auth_redirect('monitoring.php');
}

$elderID = $_SESSION['activeElderID'];
$cameraMode = (($_SESSION['cameraMode'] ?? 'single') === 'dual') ? 'dual' : 'single';
$camera1 = $_SESSION['camera1'] ?? '';
$camera2 = $_SESSION['camera2'] ?? '';
$activeStartTimeStr = $_SESSION['activeStartTime'] ?? null;
$elderName = "Unknown Profile";
$elderPhoto = "images/default-avatar.png";
$emergencyPhone = "";

// Fetch Elder Profile
$sql = "SELECT name, profilePhoto FROM ElderProfile WHERE elderID = '$elderID'";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $elderName = $row['name'];
    $elderPhoto = $row['profilePhoto'] ? $row['profilePhoto'] : "images/default-avatar.png";
}

// Fetch Emergency Contact Info
$sql_emergency = "SELECT relationship, phone FROM emergencycontact WHERE elderID = '$elderID' LIMIT 1";
$res_emergency = $conn->query($sql_emergency);
$emergencyPhone = "";
$emergencyRelationship = "";
if ($res_emergency && $res_emergency->num_rows > 0) {
    $row_e = $res_emergency->fetch_assoc();
    $emergencyPhone = $row_e['phone'];
    $emergencyRelationship = $row_e['relationship'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Monitoring - GoodLife Vision</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Shared Dashboard Styling */
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background-color: #f8f9fc; display: flex; height: 100vh; color: #2c3e50; }
        .sidebar { width: 260px; background-color: #ffffff; border-right: 1px solid #eaeaea; display: flex; flex-direction: column; padding-top: 20px; }
        .logo-container { padding: 0 20px 30px 20px; text-align: center; }
        .logo-container img { width: 140px; height: auto; }
        .nav-links { list-style: none; padding: 0; margin: 0; flex-grow: 1; }
        .nav-links li { margin-bottom: 5px; }
        .nav-links a { display: flex; align-items: center; padding: 14px 24px; color: #7f8c8d; text-decoration: none; font-size: 15px; font-weight: 500; transition: all 0.3s; }
        .nav-links a i { margin-right: 15px; font-size: 18px; width: 20px; text-align: center; }
        .nav-links a:hover { background-color: #f4f7f6; color: #2c3e50; }
        .nav-links a.active { color: #4a90e2; border-left: 4px solid #4a90e2; background-color: #f0f7ff; }
        .main-content { flex-grow: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .top-bar { display: flex; justify-content: flex-end; align-items: center; padding: 20px 40px; background-color: #f8f9fc; }
        .profile-info { text-align: right; margin-right: 20px; }
        .profile-info h4 { margin: 0; font-size: 14px; color: #2c3e50; }
        .profile-info p { margin: 0; font-size: 12px; color: #7f8c8d; }
        .logout-btn { color: #7f8c8d; text-decoration: none; font-size: 14px; display: flex; align-items: center; transition: color 0.3s; }
        .logout-btn i { margin-left: 8px; }
        .logout-btn:hover { color: #e74c3c; }

        /* Two-Column Monitoring Layout */
        .monitoring-workspace {
            padding: 20px 40px;
            display: flex;
            gap: 25px;
            align-items: flex-start;
        }

        /* LEFT COLUMN: Video & Profile */
        .video-column {
            flex: 3;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .workspace-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            padding: 20px 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        }
        
        .target-profile {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .target-profile img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #4a90e2;
        }
        .target-profile h2 { margin: 0; font-size: 20px; }
        .target-profile p { margin: 0; color: #7f8c8d; font-size: 13px; }

        .video-container {
            background-color: #111;
            border-radius: 12px;
            width: 100%;
            aspect-ratio: 16 / 9;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .dual-video-grid {
            display: none;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            width: 100%;
            height: 100%;
            padding: 10px;
            box-sizing: border-box;
        }

        .video-slot {
            position: relative;
            background: #000;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #333;
        }

        .video-label {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(0,0,0,0.6);
            color: white;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            z-index: 10;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .camera-off-msg { text-align: center; color: #7f8c8d; }
        .camera-off-msg i { font-size: 48px; margin-bottom: 15px; color: #34495e; }
        .camera-off-msg h3 { margin: 0 0 5px 0; color: #ecf0f1; }
        
        #liveStream { width: 100%; height: 100%; object-fit: cover; display: none; }
        #dualStream1, #dualStream2 { width: 100%; height: 100%; object-fit: cover; display: block; }

        /* RIGHT COLUMN: Session Details & Controls */
        .side-column {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 25px;
            min-width: 280px;
        }

        .side-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        }

        .side-card h3 {
            margin: 0 0 20px 0;
            font-size: 16px;
            color: #2c3e50;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }

        /* Status & Timer Info */
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .info-row span { color: #7f8c8d; font-size: 14px; font-weight: 500; }
        .info-row strong { color: #2c3e50; font-size: 15px; }
        
        .timer-display {
            font-size: 32px !important;
            color: #4a90e2 !important;
            font-weight: bold;
            font-variant-numeric: tabular-nums;
            display: block;
            text-align: right;
        }

        /* Dynamic Status Indicator */
        .live-status {
            display: inline-flex;
            align-items: center;
            padding: 8px 14px;
            border-radius: 30px;
            font-weight: bold;
            font-size: 13px;
            transition: all 0.3s;
        }
        /* States */
        .live-status.standby { background-color: #f3f4f6; color: #7f8c8d; }
        .live-status.active { background-color: #e0f2fe; color: #0284c7; }
        .live-status.safe { background-color: #dcfce7; color: #166534; }
        .live-status.critical { 
            background-color: #fef2f2; 
            color: #dc2626; 
            border: 2px solid #fca5a5;
            animation: warning-flash 1s infinite alternate;
        }
        
        .status-dot { width: 8px; height: 8px; border-radius: 50%; margin-right: 8px; }
        .standby .status-dot { background-color: #95a5a6; }
        .active .status-dot { background-color: #0284c7; animation: pulse-blue 1.5s infinite; }
        .safe .status-dot { background-color: #16a34a; }
        .critical .status-dot { background-color: #dc2626; }

        @keyframes pulse-blue {
            0% { box-shadow: 0 0 0 0 rgba(2, 132, 199, 0.7); }
            70% { box-shadow: 0 0 0 8px rgba(2, 132, 199, 0); }
            100% { box-shadow: 0 0 0 0 rgba(2, 132, 199, 0); }
        }

        @keyframes warning-flash {
            0% { background-color: #fef2f2; }
            100% { background-color: #fee2e2; }
        }

        /* Controls Panel */
        .controls-wrapper {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .btn-control {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s;
            color: white;
        }
        
        .btn-start { background-color: #27ae60; }
        .btn-start:hover { background-color: #2ecc71; transform: translateY(-2px); }
        
        .btn-stop { background-color: #e74c3c; display: none; }
        .btn-stop:hover { background-color: #c0392b; transform: translateY(-2px); }
        
        /* Alert Resolution Buttons Area below Video */
        #resolutionControls {
            display: none; /* Controlled by JS */
            margin-top: 15px;
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 2px solid #fee2e2;
        }

        .btn-resolve {
            flex: 1;
            padding: 22px 15px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            transition: all 0.3s;
            color: white;
            text-decoration: none;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            text-align: left;
        }
        .btn-resolve i { font-size: 24px; }
        .btn-content { display: flex; flex-direction: column; }
        .btn-content strong { font-size: 18px; display: block; }
        .btn-content span { font-size: 12px; opacity: 0.9; font-weight: normal; }

        .btn-ack-live { background-color: #4a90e2; }
        .btn-dismiss-live { background-color: #95a5a6; }
        .btn-call-emergency { background-color: #e67e22; }
        
        .btn-resolve:hover { transform: translateY(-3px); filter: brightness(1.1); box-shadow: 0 8px 20px rgba(0,0,0,0.15); }
        .btn-resolve:disabled { opacity: 0.6; cursor: not-allowed; }

        .btn-control:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            pointer-events: none;
        }

        .btn-back { background-color: #f3f4f6; color: #2c3e50; }
        .btn-back:hover { background-color: #e5e7eb; }

        /* Sidebar Emergency Card */
        .emergency-card { border-left: 4px solid #e67e22; }
        .contact-info { margin-bottom: 15px; }
        .contact-info label { color: #7f8c8d; font-weight: 500; display: block; font-size: 11px; text-transform: uppercase; margin-bottom: 2px; }
        .contact-info p { margin: 0 0 12px 0; font-size: 15px; color: #2c3e50; }

    </style>
</head>
<body>

    <div class="sidebar">
        <div class="logo-container"><img src="images/logo.jpg" alt="GoodLife Vision Logo"></div>
        <ul class="nav-links">
            <li><a href="dashboard.php"><i class="fa-solid fa-table-cells-large"></i> Dashboard</a></li>
            <li><a href="profiles.php"><i class="fa-solid fa-user-group"></i> Elder/OKU Profiles</a></li>
            <li><a href="monitoring.php" class="active"><i class="fa-solid fa-video"></i> Monitoring</a></li>
            <li><a href="alerts.php"><i class="fa-regular fa-bell"></i> Alerts</a></li>
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

        <div class="monitoring-workspace">
            
            <div class="video-column">
                <div class="workspace-header">
                    <div class="target-profile">
                        <img src="<?php echo htmlspecialchars($elderPhoto); ?>" alt="Elder Photo">
                        <div>
                            <h2><?php echo htmlspecialchars($elderName); ?></h2>
                            <p>Posture Module target selected</p>
                        </div>
                    </div>
                </div>

                <div class="video-container" id="videoContainer">
                    <div class="camera-off-msg" id="cameraOffMsg">
                        <i class="fa-solid fa-video-slash"></i>
                        <h3>Monitoring Paused</h3>
                        <p>Click "Start Monitoring" to activate the video stream.</p>
                    </div>
                    <img id="liveStream" src="" alt="Live Video Stream">
                    <div id="dualVideoGrid" class="dual-video-grid">
                        <div class="video-slot">
                            <span class="video-label">Primary Camera</span>
                            <img id="dualStream1" src="" alt="Primary Camera Stream">
                        </div>
                        <div class="video-slot">
                            <span class="video-label">Secondary Camera</span>
                            <img id="dualStream2" src="" alt="Secondary Camera Stream">
                        </div>
                    </div>
                </div>

                <!-- Alert Resolution Area (Redesigned with Subtitles) -->
                <div id="resolutionControls" style="display: none; gap: 15px; width: 100%;">
                    <button type="button" class="btn-resolve btn-ack-live" onclick="resolveAlert('ack')">
                        <i class="fa-solid fa-check"></i>
                        <div class="btn-content">
                            <strong>Acknowledge Alert</strong>
                            <span>Confirm you've seen this</span>
                        </div>
                    </button>
                    <button type="button" class="btn-resolve btn-dismiss-live" onclick="resolveAlert('dismiss')">
                        <i class="fa-solid fa-xmark"></i>
                        <div class="btn-content">
                            <strong>Dismiss Alert</strong>
                            <span>Mark as false alarm</span>
                        </div>
                    </button>
                </div>
            </div>

            <div class="side-column">
                
                <div class="side-card">
                    <h3><i class="fa-solid fa-circle-info" style="color:#4a90e2;"></i> Session Details</h3>
                    
                    <div class="info-row">
                        <span>System Status</span>
                        <div id="statusBadge" class="live-status standby">
                            <span class="status-dot"></span>
                            <span id="statusText">Standby</span>
                        </div>
                    </div>

                    <div class="info-row" style="margin-bottom: 25px;">
                        <span>Live View Status</span>
                        <div id="detectionBadge" class="live-status standby">
                            <span class="status-dot"></span>
                            <span id="detectionText">Waiting...</span>
                        </div>
                    </div>

                    <div class="info-row">
                        <span>Started At</span>
                        <strong id="startTimeDisplay">--:-- --</strong>
                    </div>

                    <div class="info-row">
                        <span>Ended At</span>
                        <strong id="endTimeDisplay">--:-- --</strong>
                    </div>

                    <div class="info-row" style="flex-direction: column; align-items: flex-start; gap: 5px; margin-top: 15px;">
                        <span>Time Duration</span>
                        <strong id="durationTime" class="timer-display">00:00:00</strong>
                    </div>
                </div>

                <div class="side-card">
                    <h3><i class="fa-solid fa-sliders" style="color:#4a90e2;"></i> Controls</h3>
                    <div class="controls-wrapper">
                        <button type="button" id="btnStart" class="btn-control btn-start" onclick="toggleMonitoring(true)">
                            <i class="fa-solid fa-play"></i> Start Monitoring
                        </button>
                        
                        <button type="button" id="btnStop" class="btn-control btn-stop" onclick="toggleMonitoring(false)">
                            <i class="fa-solid fa-stop"></i> Stop Monitoring
                        </button>

                        <!-- Alert Resolution Panel (Appears only during RECORDING/LOCKED) -->
                        <div id="resolutionControls">
                            <button type="button" class="btn-resolve btn-ack-live" onclick="resolveAlert('ack')">
                                <i class="fa-solid fa-check"></i>
                                <div class="btn-content">
                                    <strong>Acknowledge Alert</strong>
                                    <span>Confirm you've seen this</span>
                                </div>
                            </button>
                            <button type="button" class="btn-resolve btn-dismiss-live" onclick="resolveAlert('dismiss')">
                                <i class="fa-solid fa-xmark"></i>
                                <div class="btn-content">
                                    <strong>Dismiss Alert</strong>
                                    <span>Mark as false alarm</span>
                                </div>
                            </button>
                        </div>

                        <button type="button" id="btnChangeProfile" class="btn-control btn-back" onclick="changeProfile()">
                            <i class="fa-solid fa-arrow-left"></i> Change Profile
                        </button>
                    </div>
                </div>

                <!-- NEW: Emergency Contact Panel -->
                <div class="side-card emergency-card">
                    <h3><i class="fa-solid fa-phone" style="color:#e67e22;"></i> Emergency Contact</h3>
                    <div class="contact-info">
                        <label>Relationship</label>
                        <p><strong><?php echo htmlspecialchars($emergencyRelationship ?: 'Not Specified'); ?></strong></p>
                        
                        <label>Phone Number</label>
                        <p><strong><?php echo htmlspecialchars($emergencyPhone ?: 'Not Provided'); ?></strong></p>
                    </div>
                    <?php if ($emergencyPhone): ?>
                        <a href="tel:<?php echo $emergencyPhone; ?>" class="btn-control btn-call-emergency" style="text-decoration: none;">
                            <i class="fa-solid fa-phone-flip"></i> Call Contact
                        </a>
                    <?php endif; ?>
                </div>

            </div>

        </div>
    </div>

    <script>
        let timerInterval;
        let statusInterval; 
        let sessionStartTime = null;
        const selectedCameraMode = <?php echo json_encode($cameraMode); ?>;
        const selectedCamera1 = <?php echo json_encode($camera1); ?>;
        const selectedCamera2 = <?php echo json_encode($camera2); ?>;

        // PHP-Provided Start Time
        const phpStartTime = "<?php echo $activeStartTimeStr; ?>";

        async function changeProfile() {
            // If monitoring is active (Stop button is visible), shut down the python server
            const btnStop = document.getElementById('btnStop');
            if (btnStop && btnStop.style.display === 'flex') {
                try {
                    await fetch('http://localhost:5000/shutdown', { mode: 'no-cors' });
                } catch (e) {
                    console.log("Shutdown signal sent.");
                }
            }
            
            // Always clear the PHP session so monitoring.php doesn't redirect us back here
            await fetch('clear_active_session.php');
            window.location.href = window.appendGoodLifeAuthUrl ? window.appendGoodLifeAuthUrl('monitoring.php') : 'monitoring.php';
        }

        function updateStartTimeDisplay(dateObj) {
            let hours = dateObj.getHours();
            let mins = String(dateObj.getMinutes()).padStart(2, '0');
            let ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12; 
            document.getElementById('startTimeDisplay').innerText = `${hours}:${mins} ${ampm}`;
        }

        // Auto-detect if monitoring is already running
        window.onload = async function() {
            try {
                let response = await fetch('http://localhost:5000/status');
                if (response.ok) {
                    const data = await response.json();
                    if (!data.active) {
                        return;
                    }
                    console.log("Monitoring is already active. Synchronizing UI...");
                    // Switch UI to Active state immediately
                    setUIActive(true);
                    
                    // Start polling
                    statusInterval = setInterval(fetchSystemStatus, 500);
                    timerInterval = setInterval(updateTimer, 1000);

                    if (phpStartTime && phpStartTime !== "") {
                        // phpStartTime is now a numeric UNIX timestamp in ms
                        sessionStartTime = new Date(parseInt(phpStartTime));
                        updateStartTimeDisplay(sessionStartTime);
                    } else {
                        sessionStartTime = new Date(); 
                        document.getElementById('startTimeDisplay').innerText = "Running...";
                    }
                    updateTimer(); 

                }
            } catch (e) {
                console.log("System is in Standby.");
            }
        };

        function setUIActive(isActive) {
            const btnStart = document.getElementById('btnStart');
            const btnStop = document.getElementById('btnStop');
            const btnChangeProfile = document.getElementById('btnChangeProfile');
            const cameraOffMsg = document.getElementById('cameraOffMsg');
            const liveStream = document.getElementById('liveStream');
            const dualVideoGrid = document.getElementById('dualVideoGrid');
            const dualStream1 = document.getElementById('dualStream1');
            const dualStream2 = document.getElementById('dualStream2');
            const statusBadge = document.getElementById('statusBadge');
            const statusText = document.getElementById('statusText');
            const detectionBadge = document.getElementById('detectionBadge');
            const detectionText = document.getElementById('detectionText');
            const isDualMode = selectedCameraMode === 'dual';

            if (isActive) {
                btnStart.style.display = 'none';
                btnStop.style.display = 'flex';
                if (btnChangeProfile) btnChangeProfile.disabled = true;
                cameraOffMsg.style.display = 'none';

                if (isDualMode) {
                    liveStream.style.display = 'none';
                    liveStream.src = '';
                    dualVideoGrid.style.display = 'grid';
                    dualStream1.src = "http://localhost:5000/video_feed1";
                    dualStream2.src = "http://localhost:5000/video_feed2";
                } else {
                    dualVideoGrid.style.display = 'none';
                    dualStream1.src = '';
                    dualStream2.src = '';
                    liveStream.style.display = 'block';
                    liveStream.src = "http://localhost:5000/video_feed";
                }

                statusBadge.className = 'live-status active';
                statusText.innerText = "Active";
                detectionBadge.className = 'live-status safe';
                detectionText.innerText = "Analyzing...";
            } else {
                btnStart.style.display = 'flex';
                btnStop.style.display = 'none';
                if (btnChangeProfile) btnChangeProfile.disabled = false;
                cameraOffMsg.style.display = 'block';
                liveStream.style.display = 'none';
                liveStream.src = ""; 
                dualVideoGrid.style.display = 'none';
                dualStream1.src = '';
                dualStream2.src = '';
                statusBadge.className = 'live-status standby';
                statusText.innerText = "Standby";
                detectionBadge.className = 'live-status standby';
                detectionText.innerText = "Waiting...";
            }
        }

        // Function to format the stopwatch
        function updateTimer() {
            if (!sessionStartTime) return;
            const now = new Date();
            const diffInSeconds = Math.floor((now - sessionStartTime) / 1000);
            
            const hours = String(Math.floor(diffInSeconds / 3600)).padStart(2, '0');
            const minutes = String(Math.floor((diffInSeconds % 3600) / 60)).padStart(2, '0');
            const seconds = String(diffInSeconds % 60).padStart(2, '0');
            
            document.getElementById('durationTime').innerText = `${hours}:${minutes}:${seconds}`;
        }

        // Fetch Alert Data from Python specifically for the Detection Status
        async function fetchSystemStatus() {
            try {
                let response = await fetch('http://localhost:5000/status');
                let data = await response.json();
                
                const detectionBadge = document.getElementById('detectionBadge');
                const detectionText = document.getElementById('detectionText');
                const resolutionPanel = document.getElementById('resolutionControls');
                const statusBadge = document.getElementById('statusBadge');
                const statusText = document.getElementById('statusText');

                if (!data.active) {
                    setUIActive(false);
                    resolutionPanel.style.display = 'none';
                    window.currentActiveEventID = null;
                    if (data.error) {
                        statusBadge.className = 'live-status standby';
                        statusText.innerText = 'Error';
                        detectionBadge.className = 'live-status critical';
                        detectionText.innerText = data.error;
                    }
                    return;
                }

                if (data.machine_state === "RECORDING" || data.machine_state === "LOCKED") {
                    // Confirmed Alert
                    detectionBadge.className = 'live-status critical';
                    const alertMsg = (data.alert_types && data.alert_types.length > 0) 
                        ? data.alert_types.join(' + ') 
                        : (data.message || 'ALERT');
                    detectionText.innerText = 'ALERT: ' + alertMsg;

                    // Show resolution controls if an event ID exists
                    if (data.current_event_id) {
                        resolutionPanel.style.display = 'flex';
                        window.currentActiveEventID = data.current_event_id;
                    }

                } else if (data.machine_state === "GRACE") {
                    // Grace Period (In-between state)
                    detectionBadge.className = 'live-status standby';
                    detectionText.innerText = 'Analyzing Alert...';
                    resolutionPanel.style.display = 'none';
                } else {
                    // Normal monitoring
                    detectionBadge.className = 'live-status safe';
                    detectionText.innerText = 'Normal (' + data.mode + ')';
                    resolutionPanel.style.display = 'none';
                    window.currentActiveEventID = null;
                }
            } catch (error) {
                // Fails silently if camera drops so it doesn't interrupt UI
            }
        }

        async function resolveAlert(action) {
            if (!window.currentActiveEventID) return;

            const btns = document.querySelectorAll('.btn-resolve');
            btns.forEach(b => b.disabled = true);

            try {
                let response = await fetch('http://localhost:5000/resolve_alert', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        action: action,
                        eventID: window.currentActiveEventID 
                    })
                });

                if (response.ok) {
                    console.log("Alert resolved successfully.");
                    // The polling (fetchSystemStatus) will naturally hide the panel 
                    // once the Python state machine resets to NORMAL.
                }
            } catch (e) {
                console.error("Resolution failed:", e);
                alert("Failed to resolve alert. Please try again.");
            } finally {
                btns.forEach(b => b.disabled = false);
            }
        }

        // Helper to ping Server until it's ready
        async function pingServer(retries = 10) {
            for (let i = 0; i < retries; i++) {
                try {
                    let response = await fetch('http://localhost:5000/status');
                    if (response.ok) return true;
                } catch (e) {
                    console.log("Waiting for Python server... attempt " + (i + 1));
                    await new Promise(resolve => setTimeout(resolve, 1500)); // Wait 1.5s between pings
                }
            }
            return false;
        }

        // Main Start/Stop Logic
        async function toggleMonitoring(isStarting) {
            const btnStart = document.getElementById('btnStart');
            const btnStop = document.getElementById('btnStop');
            const btnChangeProfile = document.getElementById('btnChangeProfile');
            const cameraOffMsg = document.getElementById('cameraOffMsg');
            const liveStream = document.getElementById('liveStream');
            
            // Core System Badges
            const statusBadge = document.getElementById('statusBadge');
            const statusText = document.getElementById('statusText');
            
            // Detection Badges
            const detectionBadge = document.getElementById('detectionBadge');
            const detectionText = document.getElementById('detectionText');
            
            const startTimeDisplay = document.getElementById('startTimeDisplay');
            const endTimeDisplay = document.getElementById('endTimeDisplay');

            if (isStarting) {
                // Booting UI State
                btnStart.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Booting Camera...';
                btnStart.disabled = true;
                if (btnChangeProfile) btnChangeProfile.disabled = true;
                statusBadge.className = 'live-status standby';
                statusText.innerText = "Booting...";
                detectionBadge.className = 'live-status standby';
                detectionText.innerText = "Waiting for stream...";
                endTimeDisplay.innerText = '--:-- --';
                
                try {
                    // Start the Python script 
                    await fetch('start_python.php');
                    
                    // Wait for server to be ready
                    const isReady = await pingServer();
                    
                    if (!isReady) {
                        throw new Error("Python server timed out or failed to start.");
                    }

                    // --- TELL PYTHON WHICH ELDER IS BEING MONITORED ---
                    try {
                        const setElderResponse = await fetch('http://localhost:5000/set_elder', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                elderID: "<?php echo $elderID; ?>",
                                cameraMode: selectedCameraMode,
                                camera1: selectedCamera1,
                                camera2: selectedCamera2
                            })
                        });
                        const setElderData = await setElderResponse.json();
                        if (!setElderResponse.ok || !['success', 'already_running'].includes(setElderData.status)) {
                            throw new Error(setElderData.message || 'Failed to initialize monitoring.');
                        }
                    } catch (e) {
                        throw e;
                    }

                    // Switch UI to Active
                    btnStart.innerHTML = '<i class="fa-solid fa-play"></i> Start Monitoring';
                    btnStart.disabled = false;
                    setUIActive(true);

                    // Start Timers
                    sessionStartTime = new Date();
                    updateStartTimeDisplay(sessionStartTime);
                    
                    // NEW: Save start time to PHP session via AJAX
                    fetch('set_active_start.php');

                    timerInterval = setInterval(updateTimer, 1000);
                    
                    // Start polling Python backend every 500ms
                    statusInterval = setInterval(fetchSystemStatus, 500);
                    
                } catch (error) {
                    console.error("Failed to start Python script:", error);
                    try {
                        fetch('http://localhost:5000/shutdown', { mode: 'no-cors' });
                    } catch (shutdownError) {
                        console.log("Shutdown signal sent after failed startup.");
                    }
                    btnStart.innerHTML = '<i class="fa-solid fa-play"></i> Start Monitoring';
                    btnStart.disabled = false;
                    if (btnChangeProfile) btnChangeProfile.disabled = false;
                    alert(error.message || "Error starting the camera system. Please check if your camera is connected.");
                    
                    statusBadge.className = 'live-status standby';
                    statusText.innerText = "Error";
                }

                
            } else {
                // UI Stop State
                btnStop.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Stopping Camera...';
                btnStop.disabled = true;
                if (btnChangeProfile) btnChangeProfile.disabled = true;
                
                statusBadge.className = 'live-status standby';
                statusText.innerText = "Stopping...";

                // Stop intervals immediately to stop UI updates
                clearInterval(timerInterval);
                clearInterval(statusInterval);

                // Capture and display the exact End Time
                const sessionEndTime = new Date();
                let endHours = sessionEndTime.getHours();
                let endMins = String(sessionEndTime.getMinutes()).padStart(2, '0');
                let endAmpm = endHours >= 12 ? 'PM' : 'AM';
                endHours = endHours % 12;
                endHours = endHours ? endHours : 12; 
                endTimeDisplay.innerText = `${endHours}:${endMins} ${endAmpm}`;

                // Shutdown Python server
                try {
                    fetch('http://localhost:5000/shutdown', { mode: 'no-cors' });
                    // Clear the PHP session flag so monitoring.php shows the setup form again
                    fetch('clear_active_session.php');
                } catch(e) {
                    console.log("Shutdown signal sent.");
                }

                // Wait 3 seconds for cleanup before showing Start button again
                setTimeout(() => {
                    btnStart.style.display = 'flex';
                    btnStart.innerHTML = '<i class="fa-solid fa-play"></i> Start Monitoring';
                    btnStart.disabled = false;
                    
                    btnStop.style.display = 'none';
                    btnStop.innerHTML = '<i class="fa-solid fa-stop"></i> Stop Monitoring';
                    btnStop.disabled = false;

                    if (btnChangeProfile) btnChangeProfile.disabled = false;
                    setUIActive(false);
                }, 3000);
            }
        }
    </script>
    <?php render_auth_client_script(); ?>
    <script src="monitoring_global.js"></script>
</body>
</html>
