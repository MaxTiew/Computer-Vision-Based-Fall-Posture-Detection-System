<?php
// settings.php
require_once 'auth_session.php';
require_caregiver_auth();

include 'db_connect.php';

$settingsFile = 'python file/settings.json';

// Default Settings
$defaultSettings = [
    "RELATIVE_SLUMP_THRESHOLD" => 0.15,
    "TILT_THRESHOLD_DEG" => 45,
    "EYE_SQUINT_THRESHOLD" => 0.02,
    "BROW_FURROW_THRESHOLD" => 0.18,
    "HAND_HOLD_THRESHOLD" => 1.5,
    "SLUMP_HOLD_DURATION" => 2.0,
    "TILT_HOLD_DURATION" => 1.5,
    "PAIN_HOLD_DURATION" => 2.0,
    "FALL_HOLD_DURATION" => 5.0,
    "GRACE_PERIOD_DURATION" => 7.0,
    "KEYWORDS" => ["help", "ah", "ahh", "ouch", "ow", "pain", "emergency", "stop", "doctor", "hurt"],
    "GRACE_PROMPT_TEXT" => "Are you okay? Please show thumbs up if you are safe.",
    "GRACE_PROMPT_AUDIO" => ""
];

// Load Current Settings
if (file_exists($settingsFile)) {
    $currentSettings = json_decode(file_get_contents($settingsFile), true);
    // Merge with defaults to ensure all keys exist
    $currentSettings = array_merge($defaultSettings, $currentSettings);
} else {
    $currentSettings = $defaultSettings;
}

$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gracePromptAudio = $currentSettings['GRACE_PROMPT_AUDIO'];

    // Handle File Upload
    if (isset($_FILES['grace_prompt_audio']) && $_FILES['grace_prompt_audio']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'python file/uploads/grace_prompts/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileTmpPath = $_FILES['grace_prompt_audio']['tmp_name'];
        $fileName = $_FILES['grace_prompt_audio']['name'];
        $fileSize = $_FILES['grace_prompt_audio']['size'];
        $fileType = $_FILES['grace_prompt_audio']['type'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        $allowedfileExtensions = array('mp3', 'wav', 'ogg');
        if (in_array($fileExtension, $allowedfileExtensions)) {
            $newFileName = 'grace_prompt_' . time() . '.' . $fileExtension;
            $dest_path = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $gracePromptAudio = 'uploads/grace_prompts/' . $newFileName;
            } else {
                $message = "Error: There was some error moving the file to upload directory.";
            }
        } else {
            $message = "Error: Upload failed. Allowed file types: " . implode(',', $allowedfileExtensions);
        }
    }

    $newSettings = [
        "RELATIVE_SLUMP_THRESHOLD" => (float)$_POST['slump_threshold'],
        "TILT_THRESHOLD_DEG" => (int)$_POST['tilt_threshold'],
        "EYE_SQUINT_THRESHOLD" => (float)$_POST['squint_threshold'],
        "BROW_FURROW_THRESHOLD" => (float)$_POST['brow_threshold'],
        "HAND_HOLD_THRESHOLD" => (float)$_POST['hand_threshold'],
        "SLUMP_HOLD_DURATION" => (float)$_POST['slump_duration'],
        "TILT_HOLD_DURATION" => (float)$_POST['tilt_duration'],
        "PAIN_HOLD_DURATION" => (float)$_POST['pain_duration'],
        "FALL_HOLD_DURATION" => (float)$_POST['fall_duration'],
        "GRACE_PERIOD_DURATION" => (float)$_POST['grace_duration'],
        "KEYWORDS" => array_map('trim', explode(',', $_POST['keywords'])),
        "GRACE_PROMPT_TEXT" => $currentSettings['GRACE_PROMPT_TEXT'], // Preserve existing text
        "GRACE_PROMPT_AUDIO" => $gracePromptAudio
    ];

    if (file_put_contents($settingsFile, json_encode($newSettings, JSON_PRETTY_PRINT))) {
        $currentSettings = $newSettings;
        $message = "Settings saved successfully!";
    } else {
        $message = "Error: Could not save settings. Check permissions.";
    }
}

$caregiverName = $_SESSION['caregiverName'] ?? 'Caregiver';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - GoodLife Vision</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            background-color: #f8f9fc;
            display: flex;
            height: 100vh;
            color: #2c3e50;
        }
        
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
        }
        .nav-links {
            list-style: none;
            padding: 0;
            margin: 0;
            flex-grow: 1;
        }
        .nav-links a {
            display: flex;
            align-items: center;
            padding: 14px 24px;
            color: #7f8c8d;
            text-decoration: none;
            font-size: 15px;
            font-weight: 500;
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

        .main-content {
            flex-grow: 1;
            overflow-y: auto;
            padding: 40px;
        }

        .top-bar {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            margin-bottom: 30px;
        }
        .profile-info {
            text-align: right;
            margin-right: 20px;
        }
        .profile-info h4 { margin: 0; font-size: 14px; }
        .profile-info p { margin: 0; font-size: 12px; color: #7f8c8d; }
        
        .settings-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            max-width: 800px;
            margin: 0 auto;
        }
        
        .settings-header {
            margin-bottom: 30px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        .form-section {
            margin-bottom: 30px;
        }
        
        .form-section h3 {
            font-size: 18px;
            color: #4a90e2;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .form-section h3 i {
            margin-right: 10px;
        }
        
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 5px;
            color: #34495e;
        }
        
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
        }
        
        .form-group p {
            font-size: 11px;
            color: #95a5a6;
            margin: 5px 0 0 0;
        }
        
        .save-btn {
            background-color: #4a90e2;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
            width: 100%;
        }
        
        .save-btn:hover {
            background-color: #357abd;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        /* Tag Input Styling */
        .tags-input-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: white;
            min-height: 45px;
            align-items: center;
        }
        .tag {
            background: #4a90e2;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 500;
        }
        .tag i {
            cursor: pointer;
            font-size: 12px;
            opacity: 0.8;
            transition: 0.2s;
        }
        .tag i:hover {
            opacity: 1;
            transform: scale(1.2);
        }
        .tag-input {
            border: none !important;
            outline: none !important;
            padding: 5px !important;
            flex-grow: 1;
            min-width: 120px;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo-container">
            <img src="images/logo.jpg" alt="Logo">
        </div>
        <ul class="nav-links">
            <li><a href="dashboard.php"><i class="fa-solid fa-table-cells-large"></i> Dashboard</a></li>
            <li><a href="profiles.php"><i class="fa-solid fa-user-group"></i> Elder/OKU Profiles</a></li>
            <li><a href="monitoring.php"><i class="fa-solid fa-video"></i> Monitoring</a></li>
            <li><a href="alerts.php"><i class="fa-regular fa-bell"></i> Alerts</a></li>
            <li><a href="settings.php" class="active"><i class="fa-solid fa-gear"></i> Settings</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <div class="profile-info">
                <h4><?php echo htmlspecialchars($caregiverName); ?></h4>
                <p>Caregiver</p>
            </div>
            <a href="logout.php" style="color: #7f8c8d; text-decoration: none; font-size: 14px;">Logout</a>
        </div>

        <div class="settings-card">
            <div class="settings-header">
                <h2><i class="fa-solid fa-sliders"></i> System AI Settings</h2>
                <p>Fine-tune detection sensitivity and alert timings</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>

            <form method="POST" id="settingsForm" enctype="multipart/form-data">
                <div class="form-section">
                    <h3><i class="fa-solid fa-volume-high"></i> Grace Period Voice Prompt</h3>
                    <div class="form-group">
                        <label>Custom Voice Recording (Highly Recommended)</label>
                        <input type="file" name="grace_prompt_audio" accept=".mp3,.wav,.ogg">
                        <?php if (!empty($currentSettings['GRACE_PROMPT_AUDIO'])): ?>
                            <div id="voicePromptContainer" style="display: flex; align-items: center; gap: 10px; margin-top: 5px;">
                                <p style="color: #4a90e2; margin: 0;">Current file: <?php echo htmlspecialchars(basename($currentSettings['GRACE_PROMPT_AUDIO'])); ?></p>
                                <button type="button" onclick="deleteVoicePrompt()" style="background: #e74c3c; color: white; border: none; padding: 4px 10px; border-radius: 4px; font-size: 11px; cursor: pointer;">
                                    <i class="fa-solid fa-trash"></i> Delete
                                </button>
                            </div>
                        <?php endif; ?>
                        <p>Upload an .mp3, .wav, or .ogg file. If provided, this recording will play during the grace period.</p>
                    </div>
                </div>

                <div class="form-section">
                    <h3><i class="fa-solid fa-brain"></i> Detection Sensitivity</h3>
                    <div class="grid">
                        <div class="form-group">
                            <label>Slump Threshold (0.0 - 1.0)</label>
                            <input type="number" step="0.01" name="slump_threshold" value="<?php echo $currentSettings['RELATIVE_SLUMP_THRESHOLD']; ?>">
                            <p>How far the head drops relative to shoulders. Lower = more sensitive.</p>
                        </div>
                        <div class="form-group">
                            <label>Head Tilt Angle (Degrees)</label>
                            <input type="number" name="tilt_threshold" value="<?php echo $currentSettings['TILT_THRESHOLD_DEG']; ?>">
                            <p>Degrees of tilt before alert. Default: 45.</p>
                        </div>
                        <div class="form-group">
                            <label>Eye Squint Threshold (0.0 - 1.0)</label>
                            <input type="number" step="0.001" name="squint_threshold" value="<?php echo $currentSettings['EYE_SQUINT_THRESHOLD']; ?>">
                            <p>Sensitivity for squinted eyes in pain. Lower = harder to trigger.</p>
                        </div>
                        <div class="form-group">
                            <label>Brow Furrow Threshold (0.0 - 1.0)</label>
                            <input type="number" step="0.01" name="brow_threshold" value="<?php echo $currentSettings['BROW_FURROW_THRESHOLD']; ?>">
                            <p>Normalized distance between inner brows. Default: 0.18.</p>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3><i class="fa-solid fa-clock"></i> Alert Grace Periods (Seconds)</h3>
                    <div class="grid">
                        <div class="form-group">
                            <label>Pain Alert Duration</label>
                            <input type="number" step="0.1" name="pain_duration" value="<?php echo $currentSettings['PAIN_HOLD_DURATION']; ?>">
                        </div>
                        <div class="form-group">
                            <label>Fall/Lying Duration</label>
                            <input type="number" step="0.1" name="fall_duration" value="<?php echo $currentSettings['FALL_HOLD_DURATION']; ?>">
                        </div>
                        <div class="form-group">
                            <label>Slump Alert Duration</label>
                            <input type="number" step="0.1" name="slump_duration" value="<?php echo $currentSettings['SLUMP_HOLD_DURATION']; ?>">
                        </div>
                        <div class="form-group">
                            <label>Tilt Alert Duration</label>
                            <input type="number" step="0.1" name="tilt_duration" value="<?php echo $currentSettings['TILT_HOLD_DURATION']; ?>">
                        </div>
                        <div class="form-group">
                            <label>Chest Clutching Duration</label>
                            <input type="number" step="0.1" name="hand_threshold" value="<?php echo $currentSettings['HAND_HOLD_THRESHOLD']; ?>">
                        </div>
                        <div class="form-group">
                            <label>Grace Period (Thumbs Up) Duration</label>
                            <input type="number" step="0.1" name="grace_duration" value="<?php echo $currentSettings['GRACE_PERIOD_DURATION']; ?>">
                            <p>Seconds to wait for a Thumbs Up before recording.</p>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3><i class="fa-solid fa-microphone"></i> Voice Keywords</h3>
                    <div class="form-group">
                        <label>Distress Keywords</label>
                        <div class="tags-input-container" id="tagsContainer">
                            <?php foreach ($currentSettings['KEYWORDS'] as $keyword): ?>
                                <span class="tag" data-val="<?php echo htmlspecialchars($keyword); ?>">
                                    <?php echo htmlspecialchars($keyword); ?>
                                    <i class="fa-solid fa-xmark" onclick="removeTag(this)"></i>
                                </span>
                            <?php endforeach; ?>
                            <input type="text" class="tag-input" id="newTagInput" placeholder="Type a word and press Enter...">
                        </div>
                        <!-- Hidden input to store comma separated value for POST -->
                        <input type="hidden" name="keywords" id="hiddenKeywords" value="<?php echo htmlspecialchars(implode(',', $currentSettings['KEYWORDS'])); ?>">
                        <p>Words the system listens for. Type a word and press **Enter** to add it.</p>
                    </div>
                </div>

                <button type="submit" class="save-btn">Save All Settings</button>
            </form>
        </div>
    </div>

    <script>
        const tagsContainer = document.getElementById('tagsContainer');
        const newTagInput = document.getElementById('newTagInput');
        const hiddenKeywords = document.getElementById('hiddenKeywords');
        const form = document.getElementById('settingsForm');

        function updateHiddenInput() {
            const tags = Array.from(document.querySelectorAll('.tag')).map(t => t.getAttribute('data-val'));
            hiddenKeywords.value = tags.join(',');
        }

        function removeTag(element) {
            element.parentElement.remove();
            updateHiddenInput();
        }

        function addTag(val) {
            val = val.trim().toLowerCase();
            if (!val) return;
            
            // Avoid duplicates
            const existingTags = Array.from(document.querySelectorAll('.tag')).map(t => t.getAttribute('data-val'));
            if (existingTags.includes(val)) {
                newTagInput.value = '';
                return;
            }

            const tag = document.createElement('span');
            tag.className = 'tag';
            tag.setAttribute('data-val', val);
            tag.innerHTML = `${val} <i class="fa-solid fa-xmark" onclick="removeTag(this)"></i>`;
            
            tagsContainer.insertBefore(tag, newTagInput);
            newTagInput.value = '';
            updateHiddenInput();
        }

        newTagInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                addTag(newTagInput.value);
            }
        });

        // Optional: Add on comma too
        newTagInput.addEventListener('input', (e) => {
            if (newTagInput.value.includes(',')) {
                const parts = newTagInput.value.split(',');
                addTag(parts[0]);
                newTagInput.value = parts[1] || '';
            }
        });

        async function deleteVoicePrompt() {
            if (!confirm('Are you sure you want to delete the custom voice prompt? The system will revert to the text prompt.')) {
                return;
            }

            try {
                const response = await fetch('delete_grace_prompt.php', {
                    method: 'POST'
                });
                const data = await response.json();
                
                if (data.status === 'success') {
                    const container = document.getElementById('voicePromptContainer');
                    if (container) {
                        container.remove();
                    }
                    alert(data.message);
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An unexpected error occurred.');
            }
        }
    </script>
    <?php render_auth_client_script(); ?>
    <script src="monitoring_global.js"></script>
</body>
</html>
