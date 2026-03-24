<?php
// api_send_alert_email.php
// This API is called by Python after an alert is logged to send an email notification to the caregiver.

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';
include 'db_connect.php';

// Ensure consistent timezone (Malaysia)
date_default_timezone_set("Asia/Kuala_Lumpur");

header("Content-Type: application/json");

// Get POST data
$data = json_decode(file_get_contents("php://input"), true);
$eventID = $data['eventID'] ?? null;

if (!$eventID) {
    echo json_encode(["status" => "error", "message" => "Missing eventID"]);
    exit;
}

// Fetch alert details, elder name, and caregiver email
$sql = "SELECT el.*, ep.name AS elderName, c.name AS caregiverName, c.email AS caregiverEmail 
        FROM eventlog el 
        JOIN ElderProfile ep ON el.elderID = ep.elderID 
        JOIN Caregiver c ON ep.caregiverID = c.caregiverID 
        WHERE el.eventID = '$eventID'";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    
    $elderName = $row['elderName'];
    $elderID = $row['elderID'];
    $eventType = $row['eventType'];
    $timestamp = $row['TIMESTAMP'];
    $videoPath = $row['videoPath'];
    $caregiverEmail = $row['caregiverEmail'];
    $caregiverName = $row['caregiverName'];

    // Format timestamp for Malaysia
    $dateObj = new DateTime($timestamp);
    $formattedDate = $dateObj->format('l, d M Y, h:i A');

    $mail = new PHPMailer(true);

    try {
        // ======================================================================
        // SMTP CONFIGURATION (Change these for your actual SMTP server)
        // ======================================================================
        // For local testing with Gmail: 
        // 1. Enable 2-Factor Authentication on your Google Account
        // 2. Generate an 'App Password'
        // 3. Use that password here
        
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; 
        $mail->SMTPAuth   = true;
        $mail->Username   = 'xxx@gmail.com'; // YOUR EMAIL HERE
        $mail->Password   = 'xxxx xxxx xxxx xxxx';      // YOUR APP PASSWORD HERE
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        // ======================================================================

        $mail->setFrom('xxx@gmail.com', 'GoodLife Vision Alert');
        $mail->addAddress($caregiverEmail, $caregiverName);

        // Attachment: Recorded Alert Video
        $fullVideoPath = __DIR__ . DIRECTORY_SEPARATOR . $videoPath;
        if (file_exists($fullVideoPath)) {
            $mail->addAttachment($fullVideoPath, "Alert_Video.webm");
        }

        // Content
        $mail->isHTML(true);
        $mail->Subject = "EMERGENCY ALERT: $eventType for $elderName";
        
        // Base URL for the system - change to your public IP/domain if hosted
        $systemBaseUrl = "http://localhost/goodlife/"; 
        $alertUrl = $systemBaseUrl . "alerts.php";
        $videoUrl = $systemBaseUrl . $videoPath;

        $mail->Body = "
            <div style='font-family: Arial, sans-serif; color: #333;'>
                <h2 style='color: #e74c3c;'>Emergency Alert Detected</h2>
                <p>Hello <strong>$caregiverName</strong>,</p>
                <p>The GoodLife Vision system has detected an emergency alert for <strong>$elderName</strong>.</p>
                <div style='background-color: #f9f9f9; padding: 15px; border-left: 5px solid #e74c3c; margin: 20px 0;'>
                    <p><strong>Elder Name:</strong> $elderName</p>
                    <p><strong>Elder ID:</strong> $elderID</p>
                    <p><strong>Alert Type:</strong> $eventType</p>
                    <p><strong>Date & Time:</strong> $formattedDate (Malaysia Time)</p>
                    <p><strong>Current Status:</strong> Awaiting Caregiver Review</p>
                </div>
                <p>Please log in to the system immediately to review and respond to this alert:</p>
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='$alertUrl' style='background-color: #4a90e2; color: white; padding: 12px 25px; text-decoration: none; border-radius: 8px; font-weight: bold;'>Open Alert Dashboard</a>
                </p>
                <p>You can also watch the recorded alert video directly here:</p>
                <p><a href='$videoUrl'>Watch Alert Video Link (Localhost)</a></p>
                <p style='color: #7f8c8d; font-size: 12px;'>Note: Since the system is currently on localhost, video links and system access will only work if you are on the same machine/network. The alert video is also attached to this email for your convenience.</p>
                <hr>
                <p style='font-size: 13px; color: #7f8c8d;'>This is an automated message from your GoodLife Vision monitoring system.</p>
            </div>
        ";

        $mail->AltBody = "Emergency Alert Detected for $elderName. Type: $eventType. Time: $formattedDate. Please check your GoodLife Vision dashboard at $alertUrl";

        $mail->send();
        echo json_encode(["status" => "success", "message" => "Email sent to $caregiverEmail"]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => "Email failed to send. Mailer Error: {$mail->ErrorInfo}"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Alert or Caregiver not found for eventID: $eventID"]);
}
?>