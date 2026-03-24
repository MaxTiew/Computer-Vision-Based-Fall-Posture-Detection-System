<?php
// api_log_event.php
header("Content-Type: application/json");
include 'db_connect.php';

$data = json_decode(file_get_contents("php://input"), true);

function table_has_column($conn, $table, $column) {
    $column = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

if (isset($data['elderID'], $data['eventType'], $data['videoPath'])) {
    $elderID = $conn->real_escape_string($data['elderID']);
    $eventType = $conn->real_escape_string($data['eventType']);
    $videoPath = $conn->real_escape_string($data['videoPath']);
    $videoPath2 = $conn->real_escape_string($data['videoPath2'] ?? '');
    $status = "Pending"; 

    if (table_has_column($conn, 'eventlog', 'videoPath2')) {
        $sql = "INSERT INTO `eventlog` (`elderID`, `eventType`, `TIMESTAMP`, `STATUS`, `videoPath`, `videoPath2`, `notes`, `caregiverMessage`) 
                VALUES ('$elderID', '$eventType', NOW(), '$status', '$videoPath', '$videoPath2', '', '')";
    } else {
        $sql = "INSERT INTO `eventlog` (`elderID`, `eventType`, `TIMESTAMP`, `STATUS`, `videoPath`, `notes`, `caregiverMessage`) 
                VALUES ('$elderID', '$eventType', NOW(), '$status', '$videoPath', '', '')";
    }
    
    if ($conn->query($sql) === TRUE) {
        echo json_encode([
            "status" => "success",
            "eventID" => $conn->insert_id
        ]);
    } else {
        // This will send the exact SQL error back to Python
        echo json_encode(["status" => "error", "message" => $conn->error]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Missing data"]);
}
?>
