<?php
// profiles.php
require_once 'auth_session.php';
require_caregiver_auth();
include 'db_connect.php';

$caregiverID = $_SESSION['caregiverID'];
$caregiverName = $_SESSION['caregiverName'] ?? 'Caregiver';
$message = "";

// 1. Handle Form Submission for ADDING New Elder Profile
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_profile'])) {
    $elderID = "ELD-" . rand(10000, 99999);
    $contactID = "EC-" . rand(10000, 99999);
    
    $name = $conn->real_escape_string($_POST['name']);
    $age = (int)$_POST['age'];
    $medicalNotes = $conn->real_escape_string($_POST['medicalNotes']);
    
    // Emergency Contact Details
    $contactName = $conn->real_escape_string($_POST['contactName']);
    $relationship = $conn->real_escape_string($_POST['relationship']);
    $phone = $conn->real_escape_string($_POST['phone']);

    // Handle File Upload
    $profilePhoto = "images/default-avatar.png"; 
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $target_dir = "uploads/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $file_extension = pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION);
        $new_filename = $elderID . "_" . time() . "." . $file_extension;
        $target_file = $target_dir . $new_filename;

        if (move_uploaded_file($_FILES["photo"]["tmp_name"], $target_file)) {
            $profilePhoto = $target_file;
        }
    }

    $sql_elder = "INSERT INTO ElderProfile (elderID, caregiverID, name, age, medicalNotes, profilePhoto) 
                  VALUES ('$elderID', '$caregiverID', '$name', $age, '$medicalNotes', '$profilePhoto')";

    if ($conn->query($sql_elder) === TRUE) {
        $sql_contact = "INSERT INTO EmergencyContact (contactID, elderID, name, relationship, phone) 
                        VALUES ('$contactID', '$elderID', '$contactName', '$relationship', '$phone')";
        if ($conn->query($sql_contact) === TRUE) {
            $message = "<div class='success-msg'>Profile successfully registered!</div>";
        } else {
            $message = "<div class='error-msg'>Error adding contact: " . $conn->error . "</div>";
        }
    } else {
        $message = "<div class='error-msg'>Error adding profile: " . $conn->error . "</div>";
    }
}

// 2. Handle Form Submission for EDITING Elder Profile
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_profile'])) {
    $elderID = $conn->real_escape_string($_POST['elderID']);
    $name = $conn->real_escape_string($_POST['name']);
    $age = (int)$_POST['age'];
    $medicalNotes = $conn->real_escape_string($_POST['medicalNotes']);
    
    $contactName = $conn->real_escape_string($_POST['contactName']);
    $relationship = $conn->real_escape_string($_POST['relationship']);
    $phone = $conn->real_escape_string($_POST['phone']);

    // Check if a new photo was uploaded during edit
    $photoUpdateSql = "";
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $target_dir = "uploads/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $file_extension = pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION);
        $new_filename = $elderID . "_" . time() . "_edit." . $file_extension;
        $target_file = $target_dir . $new_filename;

        if (move_uploaded_file($_FILES["photo"]["tmp_name"], $target_file)) {
            $photoUpdateSql = ", profilePhoto='$target_file'";
        }
    }

    // Update both tables
    $updateElder = "UPDATE ElderProfile SET name='$name', age=$age, medicalNotes='$medicalNotes' $photoUpdateSql WHERE elderID='$elderID' AND caregiverID='$caregiverID'";
    $updateContact = "UPDATE EmergencyContact SET name='$contactName', relationship='$relationship', phone='$phone' WHERE elderID='$elderID'";

    if ($conn->query($updateElder) === TRUE && $conn->query($updateContact) === TRUE) {
        $message = "<div class='success-msg'>Profile successfully updated!</div>";
    } else {
        $message = "<div class='error-msg'>Error updating profile: " . $conn->error . "</div>";
    }
}

// 3. Handle Form Submission for DELETING Elder Profile
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_profile'])) {
    $elderID = $conn->real_escape_string($_POST['elderID']);
    
    // Because of 'ON DELETE CASCADE' in the database schema, deleting the ElderProfile 
    // will automatically delete their EmergencyContact record too.
    $deleteSql = "DELETE FROM ElderProfile WHERE elderID='$elderID' AND caregiverID='$caregiverID'";
    
    if ($conn->query($deleteSql) === TRUE) {
        $message = "<div class='success-msg'>Profile successfully deleted!</div>";
    } else {
        $message = "<div class='error-msg'>Error deleting profile: " . $conn->error . "</div>";
    }
}

// Fetch existing profiles for this caregiver
$profiles = [];
$fetch_sql = "SELECT e.elderID, e.caregiverID, e.name AS elderName, e.age, e.medicalNotes, e.profilePhoto, e.createdAt, 
                     c.name AS contactName, c.relationship, c.phone 
              FROM ElderProfile e 
              LEFT JOIN EmergencyContact c ON e.elderID = c.elderID 
              WHERE e.caregiverID = '$caregiverID' 
              ORDER BY e.createdAt DESC";
$result = $conn->query($fetch_sql);
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $profiles[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elder/OKU Profiles - GoodLife Vision</title>
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

        /* Profiles Page Specific Styling */
        .page-header { display: flex; justify-content: space-between; align-items: center; padding: 20px 60px 10px 60px; }
        .page-header h1 { margin: 0; font-size: 24px; }
        .page-header p { color: #7f8c8d; margin-top: 5px; font-size: 14px; }
        .btn-add { background: linear-gradient(135deg, #4a90e2, #5c6bc0); color: white; padding: 12px 20px; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; display: flex; align-items: center; font-size: 14px; transition: opacity 0.3s; }
        .btn-add i { margin-right: 8px; }
        .btn-add:hover { opacity: 0.9; }
        
        .content-body { padding: 20px 60px; }
        
        /* Cards Grid */
        .profiles-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 25px; }
        .profile-card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); position: relative; }
        .profile-header { display: flex; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
        .profile-img { width: 60px; height: 60px; border-radius: 50%; object-fit: cover; background-color: #f0f7ff; margin-right: 15px; }
        .profile-name h3 { margin: 0 0 5px 0; font-size: 18px; color: #2c3e50; }
        .profile-name p { margin: 0; font-size: 13px; color: #7f8c8d; }
        
        .profile-section { margin-bottom: 15px; }
        .profile-section h4 { margin: 0 0 5px 0; font-size: 13px; color: #95a5a6; text-transform: uppercase; letter-spacing: 0.5px; }
        .profile-section p { margin: 0; font-size: 14px; color: #34495e; line-height: 1.4; }
        
        .card-actions { display: flex; justify-content: flex-end; gap: 15px; margin-top: 20px; }
        .action-icon { color: #95a5a6; cursor: pointer; transition: color 0.3s; font-size: 18px; }
        .action-icon:hover { color: #4a90e2; }
        .action-icon.delete:hover { color: #e74c3c; }

        .empty-state { text-align: center; padding: 60px 20px; background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); }
        .empty-state i { font-size: 48px; color: #bdc3c7; margin-bottom: 15px; }
        .empty-state h3 { color: #2c3e50; margin-bottom: 10px; }
        .empty-state p { color: #7f8c8d; margin-bottom: 20px; }

        /* Modal Styling */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background-color: white; padding: 30px; border-radius: 12px; width: 100%; max-width: 500px; max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-header h2 { margin: 0; font-size: 20px; }
        .close-btn { cursor: pointer; font-size: 20px; color: #7f8c8d; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-size: 13px; font-weight: bold; color: #34495e; }
        .form-group input, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; font-family: inherit; }
        .form-group textarea { resize: vertical; min-height: 80px; }
        
        /* Image Preview Styling */
        .img-upload-container { display: flex; align-items: center; gap: 15px; }
        .preview-img { width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px dashed #4a90e2; display: none; }
        
        .btn-submit { width: 100%; padding: 12px; background: #4a90e2; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; margin-top: 10px; }
        .btn-danger { background: #e74c3c; }
        
        .success-msg { background: #d5f5e3; color: #27ae60; padding: 10px; border-radius: 6px; margin-bottom: 20px; text-align: center; }
        .error-msg { background: #fadbd8; color: #e74c3c; padding: 10px; border-radius: 6px; margin-bottom: 20px; text-align: center; }
        .section-divider { margin: 25px 0 15px 0; border-bottom: 1px solid #eee; padding-bottom: 5px; font-size: 16px; font-weight: bold; color: #2c3e50; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="logo-container"><img src="images/logo.jpg" alt="GoodLife Vision Logo"></div>
        <ul class="nav-links">
            <li><a href="dashboard.php"><i class="fa-solid fa-table-cells-large"></i> Dashboard</a></li>
            <li><a href="profiles.php" class="active"><i class="fa-solid fa-user-group"></i> Elder/OKU Profiles</a></li>
            <li><a href="monitoring.php"><i class="fa-solid fa-video"></i> Monitoring</a></li>
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

        <div class="page-header">
            <div>
                <h1>Elder/OKU Profiles</h1>
                <p>Manage elder/oku profiles and their information</p>
            </div>
            <button class="btn-add" onclick="openAddModal()"><i class="fa-solid fa-plus"></i> Add New Elder/OKU</button>
        </div>

        <div class="content-body">
            <?php echo $message; ?>

            <?php if (count($profiles) > 0): ?>
                <div class="profiles-grid">
                    <?php foreach ($profiles as $p): ?>
                    <div class="profile-card">
                        <div class="profile-header">
                            <img src="<?php echo htmlspecialchars($p['profilePhoto'] ?: 'images/default-avatar.png'); ?>" alt="Profile" class="profile-img">
                            <div class="profile-name">
                                <h3><?php echo htmlspecialchars($p['elderName']); ?></h3>
                                <p>Age: <?php echo htmlspecialchars($p['age']); ?></p>
                            </div>
                        </div>
                        
                        <div class="profile-section">
                            <h4>Medical Notes</h4>
                            <p><?php echo htmlspecialchars($p['medicalNotes'] ?: 'None provided.'); ?></p>
                        </div>

                        <div class="profile-section">
                            <h4>Emergency Contacts</h4>
                            <p><strong><?php echo htmlspecialchars($p['contactName']); ?></strong> (<?php echo htmlspecialchars($p['relationship']); ?>)</p>
                            <p style="color: #4a90e2; font-weight: 500;"><?php echo htmlspecialchars($p['phone']); ?></p>
                        </div>

                        <div class="card-actions">
                            <i class="fa-regular fa-pen-to-square action-icon" title="Edit" 
                               data-id="<?php echo htmlspecialchars($p['elderID']); ?>"
                               data-name="<?php echo htmlspecialchars($p['elderName'], ENT_QUOTES); ?>"
                               data-age="<?php echo htmlspecialchars($p['age']); ?>"
                               data-notes="<?php echo htmlspecialchars($p['medicalNotes'], ENT_QUOTES); ?>"
                               data-cname="<?php echo htmlspecialchars($p['contactName'], ENT_QUOTES); ?>"
                               data-rel="<?php echo htmlspecialchars($p['relationship'], ENT_QUOTES); ?>"
                               data-phone="<?php echo htmlspecialchars($p['phone']); ?>"
                               data-photo="<?php echo htmlspecialchars($p['profilePhoto']); ?>"
                               onclick="openEditModal(this)"></i>
                               
                            <i class="fa-regular fa-trash-can action-icon delete" title="Delete" 
                               onclick="openDeleteModal('<?php echo htmlspecialchars($p['elderID']); ?>')"></i>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fa-solid fa-user-plus"></i>
                    <h3>No Profiles Registered Yet</h3>
                    <p>Get started by adding the details of the individual you are caring for.</p>
                    <button class="btn-add" style="margin: 0 auto;" onclick="openAddModal()"><i class="fa-solid fa-plus"></i> Add New Profile</button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="addProfileModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Register New Elder/OKU</h2>
                <span class="close-btn" onclick="closeAddModal()"><i class="fa-solid fa-xmark"></i></span>
            </div>
            
            <form action="profiles.php" method="POST" enctype="multipart/form-data">
                <div class="section-divider">Personal Details</div>
                
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" required placeholder="Enter elder's name">
                </div>
                
                <div class="form-group" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <label>Age</label>
                        <input type="number" name="age" required min="1" max="120" placeholder="e.g. 75">
                    </div>
                    <div>
                        <label>Profile Photo</label>
                        <div class="img-upload-container">
                            <input type="file" name="photo" id="addPhotoInput" accept="image/png, image/jpeg, image/jpg">
                            <img id="addImagePreview" class="preview-img" src="" alt="Preview">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Medical Notes</label>
                    <textarea name="medicalNotes" placeholder="E.g., Diabetes, history of falls..."></textarea>
                </div>

                <div class="section-divider">Emergency Contact</div>

                <div class="form-group">
                    <label>Contact Name</label>
                    <input type="text" name="contactName" required placeholder="Enter contact name">
                </div>

                <div class="form-group" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <label>Relationship</label>
                        <input type="text" name="relationship" required placeholder="e.g. Son, Daughter">
                    </div>
                    <div>
                        <label>Phone Number (Malaysia)</label>
                        <input type="tel" name="phone" required placeholder="01X-XXXXXXX" pattern="^(\+?6?01)[0-46-9]-*[0-9]{7,8}$" title="Must be a valid Malaysian phone number starting with 01">
                    </div>
                </div>

                <button type="submit" name="add_profile" class="btn-submit">Save Profile</button>
            </form>
        </div>
    </div>

    <div id="editProfileModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Elder/OKU Profile</h2>
                <span class="close-btn" onclick="closeEditModal()"><i class="fa-solid fa-xmark"></i></span>
            </div>
            
            <form action="profiles.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="elderID" id="edit_elderID">
                
                <div class="section-divider">Personal Details</div>
                
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" id="edit_name" required>
                </div>
                
                <div class="form-group" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <label>Age</label>
                        <input type="number" name="age" id="edit_age" required min="1" max="120">
                    </div>
                    <div>
                        <label>Update Photo (Optional)</label>
                        <div class="img-upload-container">
                            <input type="file" name="photo" id="editPhotoInput" accept="image/png, image/jpeg, image/jpg">
                            <img id="editImagePreview" class="preview-img" src="" alt="Preview">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Medical Notes</label>
                    <textarea name="medicalNotes" id="edit_notes"></textarea>
                </div>

                <div class="section-divider">Emergency Contact</div>

                <div class="form-group">
                    <label>Contact Name</label>
                    <input type="text" name="contactName" id="edit_contactName" required>
                </div>

                <div class="form-group" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div>
                        <label>Relationship</label>
                        <input type="text" name="relationship" id="edit_relationship" required>
                    </div>
                    <div>
                        <label>Phone Number (Malaysia)</label>
                        <input type="tel" name="phone" id="edit_phone" required pattern="^(\+?6?01)[0-46-9]-*[0-9]{7,8}$" title="Must be a valid Malaysian phone number starting with 01">
                    </div>
                </div>

                <button type="submit" name="edit_profile" class="btn-submit">Save Changes</button>
            </form>
        </div>
    </div>

    <div id="deleteProfileModal" class="modal">
        <div class="modal-content" style="max-width: 400px; text-align: center;">
            <div class="modal-header" style="justify-content: center; border-bottom: none;">
                <h2 style="color: #e74c3c;"><i class="fa-solid fa-triangle-exclamation"></i> Delete Profile</h2>
            </div>
            <p>Are you sure you want to permanently delete this profile? This action cannot be undone.</p>
            
            <form action="profiles.php" method="POST" style="margin-top: 25px;">
                <input type="hidden" name="elderID" id="delete_elderID">
                <div style="display: flex; gap: 15px;">
                    <button type="button" class="btn-submit" style="background: #95a5a6;" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" name="delete_profile" class="btn-submit btn-danger">Yes, Delete</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // --- Add Modal Logic ---
        const addModal = document.getElementById("addProfileModal");
        function openAddModal() { addModal.style.display = "flex"; }
        function closeAddModal() { addModal.style.display = "none"; }
        
        // --- Edit Modal Logic ---
        const editModal = document.getElementById("editProfileModal");
        function openEditModal(button) {
            // Retrieve data from the clicked icon
            document.getElementById('edit_elderID').value = button.getAttribute('data-id');
            document.getElementById('edit_name').value = button.getAttribute('data-name');
            document.getElementById('edit_age').value = button.getAttribute('data-age');
            document.getElementById('edit_notes').value = button.getAttribute('data-notes');
            document.getElementById('edit_contactName').value = button.getAttribute('data-cname');
            document.getElementById('edit_relationship').value = button.getAttribute('data-rel');
            document.getElementById('edit_phone').value = button.getAttribute('data-phone');
            
            // Set the existing photo in the preview
            const photoSrc = button.getAttribute('data-photo') || 'images/default-avatar.png';
            const editPreview = document.getElementById('editImagePreview');
            editPreview.src = photoSrc;
            editPreview.style.display = 'block';

            editModal.style.display = "flex";
        }
        function closeEditModal() { editModal.style.display = "none"; }

        // --- Delete Modal Logic ---
        const deleteModal = document.getElementById("deleteProfileModal");
        function openDeleteModal(id) {
            document.getElementById('delete_elderID').value = id;
            deleteModal.style.display = "flex";
        }
        function closeDeleteModal() { deleteModal.style.display = "none"; }

        // --- Global Modal Close Logic ---
        window.onclick = function(event) {
            if (event.target == addModal) closeAddModal();
            if (event.target == editModal) closeEditModal();
            if (event.target == deleteModal) closeDeleteModal();
        }

        // --- Image Preview Handlers ---
        function setupImagePreview(inputId, previewId) {
            const input = document.getElementById(inputId);
            const preview = document.getElementById(previewId);
            input.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function() {
                        preview.setAttribute('src', this.result);
                        preview.style.display = 'block';
                    }
                    reader.readAsDataURL(file);
                } else {
                    preview.style.display = 'none';
                    preview.setAttribute('src', '');
                }
            });
        }
        
        setupImagePreview('addPhotoInput', 'addImagePreview');
        setupImagePreview('editPhotoInput', 'editImagePreview');
    </script>
    <?php render_auth_client_script(); ?>
    <script src="monitoring_global.js"></script>
</body>
</html>
