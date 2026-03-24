<?php
// clear_active_session.php
require_once 'auth_session.php';
require_caregiver_auth(true);
header('Content-Type: application/json');

unset($_SESSION['activeElderID']);
unset($_SESSION['activeStartTime']);
unset($_SESSION['cameraMode']);
unset($_SESSION['camera1']);
unset($_SESSION['camera2']);
echo json_encode(["status" => "success"]);
?>
