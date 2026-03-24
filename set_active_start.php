<?php
// set_active_start.php
require_once 'auth_session.php';
require_caregiver_auth(true);
header('Content-Type: application/json');

if (!isset($_SESSION['activeStartTime'])) {
    // Store as UNIX timestamp in milliseconds
    $_SESSION['activeStartTime'] = time() * 1000;
}
echo json_encode(["status" => "success", "startTime" => $_SESSION['activeStartTime']]);
?>
