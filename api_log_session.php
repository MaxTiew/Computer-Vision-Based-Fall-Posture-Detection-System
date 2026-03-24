<?php
// api_log_session.php
header("Content-Type: application/json");
include 'db_connect.php';

$data = json_decode(file_get_contents("php://input"), true);

function table_has_column($conn, $table, $column) {
    $column = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

if (isset($data['elderID'], $data['startTime'], $data['endTime'], $data['videoPath'])) {
    $elderID = $conn->real_escape_string($data['elderID']);
    $startTime = $conn->real_escape_string($data['startTime']);
    $endTime = $conn->real_escape_string($data['endTime']);
    $status = $conn->real_escape_string($data['status'] ?? 'Completed');
    $videoPath = $conn->real_escape_string($data['videoPath']);
    $videoPath2 = $conn->real_escape_string($data['videoPath2'] ?? '');

    if (table_has_column($conn, 'monitoringsession', 'videoPath2')) {
        $sql = "INSERT INTO `monitoringsession` (`elderID`, `startTime`, `endTime`, `STATUS`, `videoPath`, `videoPath2`) 
                VALUES ('$elderID', '$startTime', '$endTime', '$status', '$videoPath', '$videoPath2')";
    } else {
        $sql = "INSERT INTO `monitoringsession` (`elderID`, `startTime`, `endTime`, `STATUS`, `videoPath`) 
                VALUES ('$elderID', '$startTime', '$endTime', '$status', '$videoPath')";
    }
    
    if ($conn->query($sql) === TRUE) {
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => $conn->error]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Missing data"]);
}
?>
