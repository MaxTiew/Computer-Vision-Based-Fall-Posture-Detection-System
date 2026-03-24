<?php
// register.php
session_start();
include 'db_connect.php';

$error_message = "";
$success_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $conn->real_escape_string($_POST['fullname']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $_POST['phone']; // We will escape this after sanitizing
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // --- INPUT VALIDATION ---

    // 1. Sanitize and Validate Phone Number (Malaysia format: starts with 01, 10-11 digits total)
    // Remove any spaces or dashes the user might have typed
    $clean_phone = preg_replace('/[^0-9]/', '', $phone);
    $phone_regex = '/^01\d{8,9}$/'; 

    // 2. Validate Password (Must contain at least 1 letter, 1 number, 1 symbol, and be at least 8 chars long)
    $password_regex = '/^(?=.*[A-Za-z])(?=.*\d)(?=.*[\W_]).{8,}$/';

    if (!preg_match($phone_regex, $clean_phone)) {
        $error_message = "Invalid phone number! Must be a valid Malaysian number (e.g., 0123456789).";
    } elseif (!preg_match($password_regex, $password)) {
        $error_message = "Password must be at least 8 characters long, containing at least one letter, one number, and one symbol.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match!";
    } else {
        // Validation passed. Escape the cleaned phone number for the database
        $safe_phone = $conn->real_escape_string($clean_phone);

        // Check if email already exists
        $check_email = "SELECT email FROM Caregiver WHERE email = '$email'";
        $result = $conn->query($check_email);

        if ($result && $result->num_rows > 0) {
            $error_message = "An account with this email already exists.";
        } else {
            // Check if phone number already exists
            $check_phone = "SELECT phoneNumber FROM Caregiver WHERE phoneNumber = '$safe_phone'";
            $phone_result = $conn->query($check_phone);

            if ($phone_result && $phone_result->num_rows > 0) {
                $error_message = "An account with this phone number already exists.";
            } else {
                // Generate a unique Caregiver ID (e.g., CG-12345)
                $caregiverID = "CG-" . rand(10000, 99999);

                // Insert into database
                // NOTE: Storing plain text passwords is a major security risk. 
                // Consider using password_hash($password, PASSWORD_DEFAULT) in a real application.
                $sql = "INSERT INTO Caregiver (caregiverID, name, email, password, phoneNumber) 
                        VALUES ('$caregiverID', '$name', '$email', '$password', '$safe_phone')";

                if ($conn->query($sql) === TRUE) {
                    $success_message = "Account created successfully! Redirecting to login...";
                    // Redirect to login page after 2 seconds
                    header("refresh:2;url=login.php");
                } else {
                    $error_message = "Error: " . $conn->error;
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - GoodLife Vision</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7f6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .register-container {
            background-color: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            width: 100%;
            max-width: 450px;
        }
        .logo-area {
            text-align: center;
            margin-bottom: 20px;
        }
        .main-logo {
            width: 180px;
            height: auto;
            margin-bottom: 10px;
        }
        .logo-area p {
            color: #7f8c8d;
            font-size: 14px;
            margin-top: 5px;
        }
        h2 {
            color: #2c3e50;
            font-size: 20px;
            margin-bottom: 5px;
        }
        .subtitle {
            color: #7f8c8d;
            font-size: 13px;
            margin-bottom: 25px;
            line-height: 1.5;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #34495e;
            font-size: 14px;
        }
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .form-group input:focus {
            border-color: #4a90e2;
            outline: none;
        }
        .btn-register {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #4a90e2, #6b52ae);
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: opacity 0.3s;
            margin-top: 10px;
        }
        .btn-register:hover {
            opacity: 0.9;
        }
        .footer-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
        }
        .footer-link a {
            color: #4a90e2;
            text-decoration: none;
            font-weight: bold;
        }
        .error {
            color: #e74c3c;
            background-color: #fadbd8;
            padding: 10px;
            border-radius: 5px;
            font-size: 13px;
            margin-bottom: 15px;
            text-align: center;
        }
        .success {
            color: #27ae60;
            background-color: #d5f5e3;
            padding: 10px;
            border-radius: 5px;
            font-size: 13px;
            margin-bottom: 15px;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="register-container">
    <div class="logo-area">
        <img src="images/logo.jpg" alt="GoodLife Vision Logo" class="main-logo">
        <p>Smart Fall Detection for Independent Living</p>
    </div>
    
    <h2>Register as a New User</h2>
    <p class="subtitle">Caregiver fills in required credentials. The system validates details and verifies your account.</p>

    <?php if($error_message != "") echo "<div class='error'>$error_message</div>"; ?>
    <?php if($success_message != "") echo "<div class='success'>$success_message</div>"; ?>

    <form action="register.php" method="POST">
        <div class="form-group">
            <label for="fullname">Full Name</label>
            <input type="text" id="fullname" name="fullname" placeholder="Enter your full name" required value="<?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : ''; ?>">
        </div>
        
        <div class="form-group">
            <label for="phone">Phone Number</label>
            <input type="tel" id="phone" name="phone" placeholder="e.g. 0123456789" required value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
        </div>

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" placeholder="caregiver@example.com" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
        </div>
        
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="Create a password" required>
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required>
        </div>

        <button type="submit" class="btn-register">Create Account</button>
    </form>

    <div class="footer-link">
        Already have an account? <a href="login.php">Login</a>
    </div>
</div>

</body>
</html>
