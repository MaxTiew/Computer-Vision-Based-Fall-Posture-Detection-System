<?php
// alerts.php
require_once 'auth_session.php';
require_caregiver_auth();
include 'db_connect.php';

$caregiverID = $_SESSION['caregiverID'];
$caregiverName = $_SESSION['caregiverName'] ?? 'Caregiver';

function table_has_column($conn, $table, $column) {
    $column = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

// Handle Actions: Acknowledge or Dismiss Alerts
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $eventID = $conn->real_escape_string($_GET['id']);
    
    if ($action === 'ack') {
        $update_sql = "UPDATE eventlog SET STATUS = 'Acknowledged' WHERE eventID = '$eventID'";
        $conn->query($update_sql);
    } elseif ($action === 'dismiss') {
        $update_sql = "UPDATE eventlog SET STATUS = 'Dismissed' WHERE eventID = '$eventID'";
        $conn->query($update_sql);
    }
    
    auth_redirect('alerts.php');
}

// 1. Fetch all ALERTS (eventlog)
$alerts = [];
$eventVideoPath2Select = table_has_column($conn, 'eventlog', 'videoPath2') ? ', el.videoPath2' : ', NULL AS videoPath2';
$fetch_alerts_sql = "SELECT el.eventID, el.eventType, el.TIMESTAMP, el.STATUS, el.videoPath{$eventVideoPath2Select}, ep.name AS elderName 
              FROM eventlog el 
              JOIN ElderProfile ep ON el.elderID = ep.elderID 
              WHERE ep.caregiverID = '$caregiverID' 
              ORDER BY el.TIMESTAMP DESC";
$result_alerts = $conn->query($fetch_alerts_sql);
if ($result_alerts && $result_alerts->num_rows > 0) {
    while($row = $result_alerts->fetch_assoc()) {
        $alerts[] = $row;
    }
}

// 2. Fetch all SESSIONS (monitoringsession)
$sessions = [];
$sessionVideoPath2Select = table_has_column($conn, 'monitoringsession', 'videoPath2') ? ', ms.videoPath2' : ', NULL AS videoPath2';
$fetch_sessions_sql = "SELECT ms.sessionID, ms.startTime, ms.endTime, ms.STATUS, ms.videoPath{$sessionVideoPath2Select}, ep.name AS elderName 
              FROM monitoringsession ms 
              JOIN ElderProfile ep ON ms.elderID = ep.elderID 
              WHERE ep.caregiverID = '$caregiverID' 
              ORDER BY ms.startTime DESC";
$result_sessions = $conn->query($fetch_sessions_sql);
if ($result_sessions && $result_sessions->num_rows > 0) {
    while($row = $result_sessions->fetch_assoc()) {
        $sessions[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alert History - GoodLife Vision</title>
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

        /* Alerts Page Specific Styling */
        .page-header { padding: 20px 60px 10px 60px; }
        .page-header h1 { margin: 0; font-size: 24px; }
        .page-header p { color: #7f8c8d; margin-top: 5px; font-size: 14px; }
        
        .content-body { padding: 20px 60px; }

        /* Tabs Styling */
        .tabs-container { margin-bottom: 15px; display: flex; gap: 10px; }
        .tab-btn { padding: 10px 24px; border: none; background: #e5e7eb; color: #4b5563; border-radius: 8px; font-size: 14px; font-weight: bold; cursor: pointer; transition: all 0.3s; }
        .tab-btn:hover { background: #d1d5db; }
        .tab-btn.active { background: #4a90e2; color: white; box-shadow: 0 4px 10px rgba(74, 144, 226, 0.3); }
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.4s; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

        /* Table Styling */
        .table-container { background-color: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        thead { background-color: #f4f7f6; }
        th { padding: 16px 20px; font-size: 13px; color: #7f8c8d; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #eaeaea; }
        td { padding: 16px 20px; font-size: 14px; color: #2c3e50; border-bottom: 1px solid #eaeaea; vertical-align: middle; }
        tbody tr:hover { background-color: #fdfdfe; }
        tbody tr:last-child td { border-bottom: none; }

        /* Status Badges */
        .badge { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; display: inline-block; }
        .badge.pending { background-color: #fee2e2; color: #e74c3c; }
        .badge.acknowledged { background-color: #d1fae5; color: #059669; }
        .badge.dismissed { background-color: #f3f4f6; color: #6b7280; }
        .badge.completed { background-color: #e0f2fe; color: #0284c7; }

        /* Action Buttons */
        .action-btns { display: flex; gap: 8px; }
        .btn-action { padding: 6px 12px; border: none; border-radius: 6px; font-size: 12px; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; transition: opacity 0.3s; }
        .btn-action i { margin-right: 5px; }
        .btn-action:hover { opacity: 0.8; }
        .btn-ack { background-color: #4a90e2; color: white; }
        .btn-dismiss { background-color: #e5e7eb; color: #4b5563; }
        .btn-watch { background-color: #27ae60; color: white; }
        .btn-delete { background-color: #fcebeb; color: #e74c3c; border: 1px solid #f9d5d5; }
        .btn-delete:hover { background-color: #fee2e2; }
        .btn-clear-all { background-color: #f3f4f6; color: #7f8c8d; border: 1px solid #ddd; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: bold; cursor: pointer; transition: all 0.3s; margin-left: auto; }
        .btn-clear-all:hover { background-color: #e74c3c; color: white; border-color: #e74c3c; }

        /* Filter Bar Styling */
        .filters-bar { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; gap: 5px; }
        .filter-group label { font-size: 12px; font-weight: 700; color: #7f8c8d; text-transform: uppercase; }
        .filter-group input, .filter-group select { padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; color: #2c3e50; outline: none; min-width: 150px; }
        .filter-group input:focus, .filter-group select:focus { border-color: #4a90e2; }
        .btn-reset { padding: 8px 16px; background: #f3f4f6; color: #4b5563; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: 0.3s; height: 36px; display: flex; align-items: center; gap: 5px; }
        .btn-reset:hover { background: #e5e7eb; }

        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state i { font-size: 48px; color: #bdc3c7; margin-bottom: 15px; }
        .empty-state h3 { color: #2c3e50; margin-bottom: 10px; }
        .empty-state p { color: #7f8c8d; }
        .type-icon { margin-right: 8px; color: #e67e22; }

        /* Video Modal Styling */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.7); backdrop-filter: blur(5px); }
        .modal-content { background-color: white; margin: 5% auto; padding: 25px; border-radius: 12px; width: 80%; max-width: 920px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); position: relative; }
        .close-modal { position: absolute; top: 15px; right: 20px; font-size: 28px; font-weight: bold; color: #95a5a6; cursor: pointer; transition: 0.3s; }
        .close-modal:hover { color: #e74c3c; }
        .modal-content h3 { margin-top: 0; margin-bottom: 15px; color: #2c3e50; }
        .video-wrapper { width: 100%; border-radius: 8px; overflow: hidden; background: #000; display: flex; justify-content: center; }
        video { width: 100%; max-height: 500px; outline: none; }
        .dual-video-wrapper { display: none; grid-template-columns: 1fr 1fr; gap: 15px; }
        .dual-video-slot { background: #000; border-radius: 8px; overflow: hidden; position: relative; }
        .dual-video-label { position: absolute; top: 10px; left: 10px; background: rgba(0,0,0,0.6); color: white; padding: 2px 8px; border-radius: 4px; font-size: 10px; z-index: 5; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="logo-container"><img src="images/logo.jpg" alt="GoodLife Vision Logo"></div>
        <ul class="nav-links">
            <li><a href="dashboard.php"><i class="fa-solid fa-table-cells-large"></i> Dashboard</a></li>
            <li><a href="profiles.php"><i class="fa-solid fa-user-group"></i> Elder/OKU Profiles</a></li>
            <li><a href="monitoring.php"><i class="fa-solid fa-video"></i> Monitoring</a></li>
            <li><a href="alerts.php" class="active"><i class="fa-regular fa-bell"></i> Alerts</a></li>
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

        <div class="page-header">
            <h1>Alert & Session History</h1>
            <p>Review specific 10-second alerts and full monitoring session recordings.</p>
        </div>

        <div class="content-body">
            
            <!-- Filters Section -->
            <div class="filters-bar">
                <div class="filter-group">
                    <label><i class="fa-solid fa-magnifying-glass"></i> Keyword Search</label>
                    <input type="text" id="keywordSearch" placeholder="Search elder or event..." onkeyup="applyFilters()">
                </div>
                <div class="filter-group">
                    <label><i class="fa-solid fa-user"></i> Elder</label>
                    <select id="elderFilter" onchange="applyFilters()">
                        <option value="all">All Elders</option>
                        <?php 
                        $uniqueElders = [];
                        foreach(array_merge($alerts, $sessions) as $item) {
                            if(!in_array($item['elderName'], $uniqueElders)) $uniqueElders[] = $item['elderName'];
                        }
                        sort($uniqueElders);
                        foreach($uniqueElders as $name): ?>
                            <option value="<?php echo htmlspecialchars($name); ?>"><?php echo htmlspecialchars($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label><i class="fa-solid fa-calendar"></i> Date</label>
                    <input type="date" id="dateFilter" onchange="applyFilters()">
                </div>
                <div class="filter-group" id="typeFilterGroup">
                    <label><i class="fa-solid fa-triangle-exclamation"></i> Event Type</label>
                    <select id="typeFilter" onchange="applyFilters()">
                        <option value="all">All Types</option>
                        <option value="POSTURE">Posture</option>
                        <option value="BODY">Body</option>
                        <option value="FACE">Face</option>
                        <option value="FALL">Fall</option>
                        <option value="VOICE">Voice</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label><i class="fa-solid fa-circle-info"></i> Status</label>
                    <select id="statusFilter" onchange="applyFilters()">
                        <option value="all">All Status</option>
                        <option value="Pending">Pending</option>
                        <option value="Acknowledged">Acknowledged</option>
                        <option value="Dismissed">Dismissed</option>
                        <option value="Completed">Completed</option>
                    </select>
                </div>
                <button class="btn-reset" onclick="resetFilters()">
                    <i class="fa-solid fa-rotate-right"></i> Reset
                </button>
            </div>

            <div class="tabs-container">
                <button class="tab-btn active" onclick="switchTab('alertsTab', this)">
                    <i class="fa-solid fa-triangle-exclamation"></i> Alert Events
                </button>
                <button class="tab-btn" onclick="switchTab('sessionsTab', this)">
                    <i class="fa-solid fa-video"></i> Full Sessions
                </button>
                
                <button id="clearAlertsBtn" class="btn-clear-all" onclick="deleteAll('alert')" style="display: <?php echo (count($alerts) > 0) ? 'block' : 'none'; ?>;">
                    <i class="fa-solid fa-trash-sweep"></i> Clear All Alerts
                </button>
                <button id="clearSessionsBtn" class="btn-clear-all" onclick="deleteAll('session')" style="display: none;">
                    <i class="fa-solid fa-trash-sweep"></i> Clear All Sessions
                </button>
            </div>

            <div id="alertsTab" class="tab-content active">
                <div class="table-container">
                    <?php if (count($alerts) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Elder Target</th>
                                    <th>Event Type</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($alerts as $alert): 
                                    $dateObj = new DateTime($alert['TIMESTAMP']);
                                    $formattedDate = $dateObj->format('M d, Y \a\t h:i A');
                                    
                                    $status = $alert['STATUS'] ?? 'Pending';
                                    $badgeClass = 'pending';
                                    if ($status == 'Acknowledged') $badgeClass = 'acknowledged';
                                    if ($status == 'Dismissed') $badgeClass = 'dismissed';
                                ?>
                                <tr>
                                    <td><strong><?php echo $formattedDate; ?></strong></td>
                                    <td><?php echo htmlspecialchars($alert['elderName']); ?></td>
                                    <td>
                                        <i class="fa-solid fa-triangle-exclamation type-icon"></i> 
                                        <?php echo htmlspecialchars($alert['eventType']); ?>
                                    </td>
                                    <td><span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($status); ?></span></td>
                                    <td>
                                        <div class="action-btns">
                                            <?php if (!empty($alert['videoPath'])): 
                                                $vPath = str_replace(' ', '%20', $alert['videoPath']);
                                                $vPath2 = !empty($alert['videoPath2']) ? str_replace(' ', '%20', $alert['videoPath2']) : '';
                                            ?>
                                                <button class="btn-action btn-watch" onclick="openVideo('<?php echo $vPath; ?>', 'Alert: <?php echo htmlspecialchars($alert['eventType']); ?>', '<?php echo $vPath2; ?>')">
                                                    <i class="fa-solid fa-play"></i> Watch
                                                </button>
                                            <?php endif; ?>
                                            <button class="btn-action btn-delete" onclick="confirmDelete('alert', '<?php echo $alert['eventID']; ?>')">
                                                <i class="fa-solid fa-trash-can"></i> Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fa-regular fa-folder-open"></i>
                            <h3>No Alerts Found</h3>
                            <p>There are no emergency alerts logged in the system.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div id="sessionsTab" class="tab-content">
                <div class="table-container">
                    <?php if (count($sessions) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Session Started</th>
                                    <th>Session Ended</th>
                                    <th>Elder Target</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sessions as $session): 
                                    $startObj = new DateTime($session['startTime']);
                                    $formattedStart = $startObj->format('M d, Y \a\t h:i A');
                                    
                                    $endObj = new DateTime($session['endTime']);
                                    $formattedEnd = $endObj->format('h:i A'); // Just show time for end
                                    
                                    $badgeClass = ($session['STATUS'] == 'Completed') ? 'completed' : 'pending';
                                ?>
                                <tr>
                                    <td><strong><?php echo $formattedStart; ?></strong></td>
                                    <td><?php echo $formattedEnd; ?></td>
                                    <td><?php echo htmlspecialchars($session['elderName']); ?></td>
                                    <td><span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($session['STATUS']); ?></span></td>
                                    <td>
                                        <div class="action-btns">
                                            <?php if (!empty($session['videoPath'])): 
                                                $sVPath = str_replace(' ', '%20', $session['videoPath']);
                                                $sVPath2 = !empty($session['videoPath2']) ? str_replace(' ', '%20', $session['videoPath2']) : '';
                                            ?>
                                                <button class="btn-action btn-watch" onclick="openVideo('<?php echo $sVPath; ?>', 'Full Session: <?php echo htmlspecialchars($session['elderName']); ?>', '<?php echo $sVPath2; ?>')">
                                                    <i class="fa-solid fa-play"></i> Watch Full
                                                </button>
                                            <?php else: ?>
                                                <span style="color: #95a5a6; font-size: 12px; font-style: italic;">No video file</span>
                                            <?php endif; ?>
                                            <button class="btn-action btn-delete" onclick="confirmDelete('session', '<?php echo $session['sessionID']; ?>')">
                                                <i class="fa-solid fa-trash-can"></i> Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fa-regular fa-folder-open"></i>
                            <h3>No Sessions Found</h3>
                            <p>No complete monitoring sessions have been recorded yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <div id="videoModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeVideo()">&times;</span>
            <h3 id="modalVideoTitle">Video Playback</h3>
            <div id="singleVideoWrapper" class="video-wrapper">
                <video id="webPlayer" controls>
                    <source id="videoSource" src="" type="video/webm">
                    Your browser does not support the video tag.
                </video>
            </div>
            <div id="dualVideoWrapper" class="dual-video-wrapper">
                <div class="dual-video-slot">
                    <span class="dual-video-label">PRIMARY</span>
                    <video id="webPlayer1" controls muted>
                        <source id="videoSource1" src="" type="video/webm">
                        Your browser does not support the video tag.
                    </video>
                </div>
                <div class="dual-video-slot">
                    <span class="dual-video-label">SECONDARY</span>
                    <video id="webPlayer2" controls muted>
                        <source id="videoSource2" src="" type="video/webm">
                        Your browser does not support the video tag.
                    </video>
                </div>
            </div>
            <!-- Playback Speed Selector -->
            <div style="margin-top: 15px; display: flex; align-items: center; justify-content: center; gap: 20px; border-top: 1px solid #eee; padding-top: 15px;">
                <label for="playbackSpeed" style="font-size: 14px; color: #7f8c8d; font-weight: 600;">
                    <i class="fa-solid fa-gauge-high"></i> Playback Speed:
                </label>
                <select id="playbackSpeed" onchange="setPlaybackSpeed(this.value)" style="padding: 6px 12px; border-radius: 6px; border: 1px solid #ddd; background: white; cursor: pointer; font-size: 14px; outline: none;">
                    <option value="0.25">0.25x</option>
                    <option value="0.5">0.5x</option>
                    <option value="0.75">0.75x</option>
                    <option value="1.0" selected>1.0x (Normal)</option>
                    <option value="1.25">1.25x</option>
                    <option value="1.5">1.5x</option>
                    <option value="2.0">2.0x</option>
                </select>
                <button id="syncPlaybackBtn" type="button" onclick="syncVideos()" style="display: none; padding: 6px 15px; background: #4a90e2; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: bold;">
                    <i class="fa-solid fa-rotate"></i> Sync Playback
                </button>
            </div>
        </div>
    </div>

    <script>
        // Tab Switcher Logic
        function switchTab(tabId, btnElement) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            // Remove active class from all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab and highlight button
            document.getElementById(tabId).classList.add('active');
            btnElement.classList.add('active');

            // Manage Clear All button visibility and Filter visibility
            const clearAlertsBtn = document.getElementById('clearAlertsBtn');
            const clearSessionsBtn = document.getElementById('clearSessionsBtn');
            const typeFilterGroup = document.getElementById('typeFilterGroup');
            
            if (tabId === 'alertsTab') {
                clearAlertsBtn.style.display = (<?php echo count($alerts); ?> > 0) ? 'block' : 'none';
                clearSessionsBtn.style.display = 'none';
                typeFilterGroup.style.display = 'flex';
            } else {
                clearAlertsBtn.style.display = 'none';
                clearSessionsBtn.style.display = (<?php echo count($sessions); ?> > 0) ? 'block' : 'none';
                typeFilterGroup.style.display = 'none';
            }

            // Save the active tab to localStorage
            localStorage.setItem('activeAlertsTab', tabId);
        }

        // Restore active tab on page load
        window.addEventListener('DOMContentLoaded', (event) => {
            const savedTabId = localStorage.getItem('activeAlertsTab');
            if (savedTabId) {
                const tabBtn = document.querySelector(`.tab-btn[onclick*="${savedTabId}"]`);
                if (tabBtn) {
                    switchTab(savedTabId, tabBtn);
                }
            }
        });

        // Video Player Modal Logic
        function openVideo(videoPath, title, videoPath2 = '') {
            const modal = document.getElementById('videoModal');
            const titleElement = document.getElementById('modalVideoTitle');
            const speedSelect = document.getElementById('playbackSpeed');
            const singleWrapper = document.getElementById('singleVideoWrapper');
            const dualWrapper = document.getElementById('dualVideoWrapper');
            const syncButton = document.getElementById('syncPlaybackBtn');
            
            titleElement.innerText = title;
            modal.style.display = 'block';

            if (videoPath2 && videoPath2 !== '') {
                const player1 = document.getElementById('webPlayer1');
                const player2 = document.getElementById('webPlayer2');
                const source1 = document.getElementById('videoSource1');
                const source2 = document.getElementById('videoSource2');

                singleWrapper.style.display = 'none';
                dualWrapper.style.display = 'grid';
                syncButton.style.display = 'inline-flex';

                source1.src = videoPath;
                source2.src = videoPath2;
                player1.load();
                player2.load();
                player1.oncanplay = function() {
                    player1.playbackRate = parseFloat(speedSelect.value);
                };
                player2.oncanplay = function() {
                    player2.playbackRate = parseFloat(speedSelect.value);
                };
                player1.onplay = () => player2.play();
                player1.onpause = () => player2.pause();
                player1.onseeking = () => { player2.currentTime = player1.currentTime; };
                player1.play();
                player2.play();
            } else {
                const player = document.getElementById('webPlayer');
                const source = document.getElementById('videoSource');

                dualWrapper.style.display = 'none';
                singleWrapper.style.display = 'flex';
                syncButton.style.display = 'none';
                source.src = videoPath;
                player.load();
                player.oncanplay = function() {
                    player.playbackRate = parseFloat(speedSelect.value);
                };
                player.play();
            }
        }

        function setPlaybackSpeed(speed) {
            [document.getElementById('webPlayer'), document.getElementById('webPlayer1'), document.getElementById('webPlayer2')].forEach((player) => {
                if (player) {
                    player.playbackRate = parseFloat(speed);
                }
            });
        }

        function syncVideos() {
            const player1 = document.getElementById('webPlayer1');
            const player2 = document.getElementById('webPlayer2');
            player2.currentTime = player1.currentTime;
            if (player1.paused) {
                player2.pause();
            } else {
                player2.play();
            }
        }

        async function deleteAll(type) {
            const msg = (type === 'alert') 
                ? "WARNING: This will permanently delete ALL alert records and their videos. Continue?" 
                : "WARNING: This will permanently delete ALL session records and their videos. Continue?";
            
            if (confirm(msg)) {
                if (confirm("Are you absolutely sure? This action cannot be undone.")) {
                    try {
                        const response = await fetch('delete_recording.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ type: type, id: 'all' })
                        });
                        
                        const result = await response.json();
                        if (result.status === 'success') {
                            location.reload();
                        } else {
                            alert("Error: " + result.message);
                        }
                    } catch (error) {
                        console.error("Delete all failed:", error);
                        alert("An error occurred while trying to clear the records.");
                    }
                }
            }
        }

        async function confirmDelete(type, id) {
            const msg = (type === 'alert') 
                ? "Are you sure you want to delete this alert record and its video?" 
                : "Are you sure you want to delete this session record and its video?";
            
            if (confirm(msg)) {
                try {
                    const response = await fetch('delete_recording.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ type: type, id: id })
                    });
                    
                    const result = await response.json();
                    if (result.status === 'success') {
                        location.reload(); // Refresh to show updated list
                    } else {
                        alert("Error: " + result.message);
                    }
                } catch (error) {
                    console.error("Delete failed:", error);
                    alert("An error occurred while trying to delete the recording.");
                }
            }
        }

        // Filtering Logic
        function applyFilters() {
            const keyword = document.getElementById('keywordSearch').value.toLowerCase();
            const elder = document.getElementById('elderFilter').value;
            const date = document.getElementById('dateFilter').value;
            const type = document.getElementById('typeFilter').value;
            const status = document.getElementById('statusFilter').value;

            // Determine active tab
            const activeTabId = localStorage.getItem('activeAlertsTab') || 'alertsTab';
            const table = document.querySelector(`#${activeTabId} table`);
            if (!table) return;

            const rows = table.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length < 4) return;

                let show = true;

                // 1. Keyword Search (Across all visible text)
                const rowText = row.innerText.toLowerCase();
                if (keyword && !rowText.includes(keyword)) show = false;

                // 2. Elder Filter
                const elderName = cells[1].innerText.trim();
                if (elder !== 'all' && elderName !== elder) show = false;

                // 3. Date Filter
                const dateTimeText = cells[0].innerText.trim(); // "Mar 16, 2026 at 09:12 PM"
                if (date) {
                    // Convert row date to YYYY-MM-DD for comparison
                    const rowDate = new Date(dateTimeText);
                    const filterDate = new Date(date);
                    if (rowDate.toDateString() !== filterDate.toDateString()) show = false;
                }

                // 4. Type Filter (Only for Alerts Tab)
                if (activeTabId === 'alertsTab') {
                    const typeText = cells[2].innerText.trim().toUpperCase();
                    if (type !== 'all' && !typeText.includes(type)) show = false;
                }

                // 5. Status Filter
                const statusText = cells[3].innerText.trim();
                if (status !== 'all' && statusText !== status) show = false;

                row.style.display = show ? '' : 'none';
            });
        }

        function resetFilters() {
            document.getElementById('keywordSearch').value = '';
            document.getElementById('elderFilter').value = 'all';
            document.getElementById('dateFilter').value = '';
            document.getElementById('typeFilter').value = 'all';
            document.getElementById('statusFilter').value = 'all';
            applyFilters();
        }

        function closeVideo() {
            const modal = document.getElementById('videoModal');
            const singlePlayer = document.getElementById('webPlayer');
            const dualPlayer1 = document.getElementById('webPlayer1');
            const dualPlayer2 = document.getElementById('webPlayer2');

            [singlePlayer, dualPlayer1, dualPlayer2].forEach((player) => {
                if (player) {
                    player.pause();
                }
            });
            modal.style.display = 'none';
            document.getElementById('videoSource').src = "";
            document.getElementById('videoSource1').src = "";
            document.getElementById('videoSource2').src = "";
            singlePlayer.load();
            dualPlayer1.load();
            dualPlayer2.load();
        }

        // Close modal if user clicks outside of the video box
        window.onclick = function(event) {
            const modal = document.getElementById('videoModal');
            if (event.target == modal) {
                closeVideo();
            }
        }
    </script>
    <?php render_auth_client_script(); ?>
    <script src="monitoring_global.js"></script>
</body>
</html>
