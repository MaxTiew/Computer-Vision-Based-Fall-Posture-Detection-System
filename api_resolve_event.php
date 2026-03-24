<?php
// api_resolve_event.php
header("Content-Type: application/json");
include 'db_connect.php';

// This API handles resolution calls from the Python backend
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $eventID = $conn->real_escape_string($_GET['id']);
    
    $status = "";
    if ($action === 'ack') {
        $status = 'Acknowledged';
    } elseif ($action === 'dismiss') {
        $status = 'Dismissed';
    }

    if ($status !== "") {
        $update_sql = "UPDATE eventlog SET STATUS = '$status' WHERE eventID = '$eventID'";
        if ($conn->query($update_sql) === TRUE) {
            echo json_encode(["status" => "success", "message" => "Alert $status"]);
        } else {
            echo json_encode(["status" => "error", "message" => $conn->error]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid action"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Missing parameters"]);
}
?>