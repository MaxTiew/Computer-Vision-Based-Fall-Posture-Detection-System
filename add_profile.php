<?php
// add_profile.php
require_once 'auth_session.php';
require_caregiver_auth();

include 'db_connect.php';
$caregiverID = $_SESSION['caregiverID'];
$caregiverName = $_SESSION['caregiverName'] ?? 'Caregiver';

$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Sanitize Elder Data
    $name = $conn->real_escape_string($_POST['elder_name']);
    $age = (int)$_POST['elder_age'];
    $medical = $conn->real_escape_string($_POST['medical_notes']);
    
    // Generate unique IDs
    $elderID = "ELD-" . uniqid();
    $contactID = "EC-" . uniqid();

    // 2. Sanitize Emergency Contact Data
    $contact_name = $conn->real_escape_string($_POST['contact_name']);
    $contact_rel = $conn->real_escape_string($_POST['contact_rel']);
    $contact_phone = $conn->real_escape_string($_POST['contact_phone']);

    // Begin Database Transaction to ensure both insert safely
    $conn->begin_transaction();

    try {
        // Insert into ElderProfile
        $sql_elder = "INSERT INTO ElderProfile (elderID, caregiverID, name, age, medicalNotes) 
                      VALUES ('$elderID', '$caregiverID', '$name', $age, '$medical')";
        $conn->query($sql_elder);

        // Insert into EmergencyContact
        $sql_contact = "INSERT INTO EmergencyContact (contactID, elderID, name, relationship, phone) 
                        VALUES ('$contactID', '$elderID', '$contact_name', '$contact_rel', '$contact_phone')";
        $conn->query($sql_contact);

        // Commit transaction
        $conn->commit();
        
        // Redirect back to profiles page where the new card will now be visible
        auth_redirect('profiles.php');
    } catch (Exception $e) {
        // Rollback if something went wrong
        $conn->rollback();
        $error_message = "Failed to save profile: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Elder Profile - GoodLife Vision</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Inherit Sidebar and Layout styling from above */
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background-color: #f8f9fc; display: flex; height: 100vh; color: #2c3e50; }
        .sidebar { width: 260px; background-color: #ffffff; border-right: 1px solid #eaeaea; display: flex; flex-direction: column; padding-top: 20px; }
        .logo-container { padding: 0 20px 30px 20px; }
        .logo-container img { width: 140px; height: auto; }
        .nav-links { list-style: none; padding: 0; margin: 0; flex-grow: 1; }
        .nav-links li { margin-bottom: 5px; }
        .nav-links a { display: flex; align-items: center; padding: 14px 24px; color: #7f8c8d; text-decoration: none; font-size: 15px; font-weight: 500; transition: all 0.3s; }
        .nav-links a i { margin-right: 15px; font-size: 18px; width: 20px; text-align: center; }
        .nav-links a.active { color: #4a90e2; border-left: 4px solid #4a90e2; background-color: #f0f7ff; }
        .main-content { flex-grow: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .top-bar { display: flex; justify-content: flex-end; align-items: center; padding: 20px 40px; background-color: #f8f9fc; }
        .profile-info { text-align: right; margin-right: 20px; }
        .profile-info h4 { margin: 0; font-size: 14px; color: #2c3e50; }
        .profile-info p { margin: 0; font-size: 12px; color: #7f8c8d; }
        
        .page-header { padding: 20px 60px 10px 60px; }
        .header-text h1 { margin: 0 0 5px 0; font-size: 24px; }
        .header-text p { margin: 0; color: #7f8c8d; font-size: 14px; }

        /* Form Styling */
        .form-container { background: white; margin: 20px 60px; padding: 40px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); max-width: 600px; }
        .form-section { margin-bottom: 30px; }
        .form-section h3 { font-size: 16px; color: #2c3e50; border-bottom: 1px solid #eaeaea; padding-bottom: 10px; margin-bottom: 20px; margin-top: 0;}
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 13px; color: #7f8c8d; margin-bottom: 8px; font-weight: bold; }
        .form-group input, .form-group textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; font-family: inherit; font-size: 14px; transition: border-color 0.3s; }
        .form-group input:focus, .form-group textarea:focus { border-color: #4a90e2; outline: none; }
        
        .btn-submit { background: linear-gradient(135deg, #4a90e2, #5c6bc0); color: white; padding: 14px 24px; border: none; border-radius: 8px; font-weight: bold; font-size: 15px; cursor: pointer; transition: opacity 0.3s; width: 100%; }
        .btn-submit:hover { opacity: 0.9; }
        .error { background: #fadbd8; color: #e74c3c; padding: 10px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
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
            <a href="profiles.php" style="color: #7f8c8d; text-decoration:none; font-size:14px;"><i class="fa-solid fa-arrow-left"></i> Back to Profiles</a>
        </div>

        <div class="page-header">
            <div class="header-text">
                <h1>Register New Profile</h1>
                <p>Add a new individual to your monitoring list</p>
            </div>
        </div>

        <div class="form-container">
            <?php if($error_message != "") echo "<div class='error'>$error_message</div>"; ?>

            <form action="add_profile.php" method="POST">
                
                <div class="form-section">
                    <h3>Elder/OKU Personal Details</h3>
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="elder_name" required placeholder="e.g. Margaret Smith">
                    </div>
                    <div class="form-group">
                        <label>Age</label>
                        <input type="number" name="elder_age" required placeholder="e.g. 78">
                    </div>
                    <div class="form-group">
                        <label>Medical Notes (Optional)</label>
                        <textarea name="medical_notes" rows="3" placeholder="e.g. Diabetes, takes insulin daily, history of falls"></textarea>
                    </div>
                </div>

                <div class="form-section">
                    <h3>Primary Emergency Contact</h3>
                    <div class="form-group">
                        <label>Contact Name</label>
                        <input type="text" name="contact_name" required placeholder="e.g. John Smith">
                    </div>
                    <div class="form-group">
                        <label>Relationship</label>
                        <input type="text" name="contact_rel" required placeholder="e.g. Son">
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="contact_phone" required placeholder="e.g. 012-345-6789">
                    </div>
                </div>

                <button type="submit" class="btn-submit">Save Profile & Contacts</button>
            </form>
        </div>
    </div>

<?php render_auth_client_script(); ?>
</body>
</html>
