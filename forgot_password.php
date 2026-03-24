<?php
// forgot_password.php
session_start();
include 'db_connect.php';

use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';
require_once 'mailer_helper.php';

$message = "";
$msg_class = "";
$selected_email = "";

function mask_email_address(string $email): string
{
    $parts = explode('@', $email, 2);
    if (count($parts) !== 2) {
        return htmlspecialchars($email);
    }

    $local = $parts[0];
    $domain = $parts[1];

    if (strlen($local) <= 2) {
        $masked_local = substr($local, 0, 1) . str_repeat('*', max(strlen($local) - 1, 1));
    } else {
        $masked_local = substr($local, 0, 1) . str_repeat('*', strlen($local) - 2) . substr($local, -1);
    }

    return htmlspecialchars($masked_local . '@' . $domain);
}

if (isset($_GET['email'])) {
    $incoming_email = trim($_GET['email']);

    if ($incoming_email === '') {
        unset($_SESSION['password_reset_email']);
    } else {
        $_SESSION['password_reset_email'] = $incoming_email;
    }
}

if (isset($_SESSION['password_reset_email'])) {
    $selected_email = trim($_SESSION['password_reset_email']);
}

if ($selected_email !== "" && !filter_var($selected_email, FILTER_VALIDATE_EMAIL)) {
    $message = "The selected email address is not valid. Please return to login and enter your registered email again.";
    $msg_class = "error";
    $selected_email = "";
    unset($_SESSION['password_reset_email']);
}

if ($selected_email === "" && $message === "") {
    $message = "Return to the login page, enter your registered email, then click Forgot Password.";
    $msg_class = "error";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if ($selected_email === "") {
        $message = "No account email was selected. Please return to the login page and click Forgot Password again.";
        $msg_class = "error";
    } else {
        $safe_email = $conn->real_escape_string($selected_email);
        $sql = "SELECT caregiverID, name, email FROM Caregiver WHERE email = '$safe_email'";
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $name = $row['name'];
            $recipient_email = $row['email'];
            $caregiver_id = $row['caregiverID'];

            $token = bin2hex(random_bytes(50));
            $expires = date("Y-m-d H:i:s", strtotime('+1 hour'));
            $update_sql = "UPDATE Caregiver SET reset_token = '$token', reset_expires = '$expires' WHERE caregiverID = '$caregiver_id'";

            if ($conn->query($update_sql) === TRUE) {
                $reset_link = get_application_base_url() . "reset_password.php?token=" . urlencode($token);

                $mail = null;
                try {
                    $mail = create_system_mailer();
                    $mail->addAddress($recipient_email, $name);
                    $mail->isHTML(true);
                    $mail->Subject = "GoodLife Vision - Password Reset Request";
                    $mail->Body = "
                        <div style='font-family: Arial, sans-serif; color: #333; line-height: 1.6;'>
                            <h2 style='color: #4a90e2;'>Password Reset Request</h2>
                            <p>Hello <strong>" . htmlspecialchars($name, ENT_QUOTES) . "</strong>,</p>
                            <p>We received a request to reset your GoodLife Vision password.</p>
                            <p style='margin: 24px 0;'>
                                <a href='" . htmlspecialchars($reset_link, ENT_QUOTES) . "' style='background-color: #4a90e2; color: #fff; text-decoration: none; padding: 12px 20px; border-radius: 8px; display: inline-block; font-weight: bold;'>Reset Password</a>
                            </p>
                            <p>If the button does not work, copy and open this link:</p>
                            <p><a href='" . htmlspecialchars($reset_link, ENT_QUOTES) . "'>" . htmlspecialchars($reset_link) . "</a></p>
                            <p>This link will expire in 1 hour.</p>
                            <p>If you did not request this, you can ignore this email safely.</p>
                            <hr>
                            <p style='font-size: 12px; color: #7f8c8d;'>This is an automated email from GoodLife Vision.</p>
                        </div>
                    ";
                    $mail->AltBody = "Hello $name,\n\nWe received a request to reset your GoodLife Vision password.\n\nOpen this link to reset your password:\n$reset_link\n\nThis link will expire in 1 hour.\n\nIf you did not request this, you can ignore this email.";
                    $mail->send();

                    $message = "A password reset link has been sent to " . mask_email_address($recipient_email) . ".";
                    $msg_class = "success";
                } catch (Exception $e) {
                    $mailer_error = $mail ? $mail->ErrorInfo : $e->getMessage();
                    $message = "The reset link was generated, but the email could not be sent. Mailer error: " . htmlspecialchars($mailer_error);
                    $msg_class = "error";
                }
            } else {
                $message = "Error generating reset link. Please try again.";
                $msg_class = "error";
            }
        } else {
            $message = "No account was found for the selected email. Please go back to login and check the email address.";
            $msg_class = "error";
        }
    }
}

$masked_selected_email = $selected_email !== "" ? mask_email_address($selected_email) : "";
$can_send = $selected_email !== "";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - GoodLife Vision</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f6; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; }
        .recovery-container { background-color: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); width: 100%; max-width: 400px; }
        .logo-area { text-align: center; margin-bottom: 20px; }
        .main-logo { width: 180px; height: auto; margin-bottom: 10px; }
        h2 { color: #2c3e50; font-size: 20px; margin-bottom: 10px; text-align: center; }
        .subtitle { color: #7f8c8d; font-size: 14px; margin-bottom: 25px; text-align: center; line-height: 1.5; }
        .email-card { background: #f8f9fc; border: 1px solid #e3eaf3; border-radius: 10px; padding: 14px 16px; margin-bottom: 20px; text-align: center; }
        .email-card .label { display: block; font-size: 12px; color: #7f8c8d; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.08em; }
        .email-card .value { color: #2c3e50; font-size: 15px; font-weight: 600; word-break: break-word; }
        .btn-recover { width: 100%; padding: 14px; background: linear-gradient(135deg, #4a90e2, #6b52ae); border: none; border-radius: 8px; color: white; font-size: 16px; font-weight: bold; cursor: pointer; transition: opacity 0.3s; }
        .btn-recover:hover { opacity: 0.9; }
        .btn-recover:disabled { background: #bdc3c7; cursor: not-allowed; opacity: 1; }
        .footer-link { text-align: center; margin-top: 25px; font-size: 14px; }
        .footer-link a { color: #4a90e2; text-decoration: none; font-weight: bold; }
        .message { padding: 10px; border-radius: 5px; font-size: 13px; margin-bottom: 15px; text-align: center; }
        .error { color: #e74c3c; background-color: #fadbd8; }
        .success { color: #27ae60; background-color: #d5f5e3; }
    </style>
</head>
<body>
<div class="recovery-container">
    <div class="logo-area">
        <img src="images/logo.jpg" alt="GoodLife Vision Logo" class="main-logo">
    </div>
    <h2>Password Recovery</h2>
    <p class="subtitle">We will send a secure password reset link to the registered email for the account you selected on the login page.</p>

    <?php if ($message != "") echo "<div class='message $msg_class'>" . $message . "</div>"; ?>

    <?php if ($can_send): ?>
        <div class="email-card">
            <span class="label">Reset Link Destination</span>
            <span class="value"><?php echo $masked_selected_email; ?></span>
        </div>
    <?php endif; ?>

    <form action="forgot_password.php" method="POST">
        <button type="submit" class="btn-recover" <?php echo $can_send ? '' : 'disabled'; ?>>Send Reset Link</button>
    </form>

    <div class="footer-link">
        <a href="login.php">Back to Login</a>
    </div>
</div>
</body>
</html>
