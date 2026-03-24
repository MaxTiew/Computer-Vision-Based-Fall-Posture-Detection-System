<?php
// delete_recording.php
require_once 'auth_session.php';
require_caregiver_auth(true);
include 'db_connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $tableHasColumn = function($table, $column) use ($conn) {
        $column = $conn->real_escape_string($column);
        $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $result && $result->num_rows > 0;
    };

    $deleteVideoFiles = function($row) {
        foreach (['videoPath', 'videoPath2'] as $field) {
            if (!empty($row[$field])) {
                $fullPath = __DIR__ . '/' . $row[$field];
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
            }
        }
    };
    
    if (!isset($data['type']) || !isset($data['id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Missing parameters']);
        exit();
    }

    $type = $data['type']; // 'alert' or 'session'
    $id = $conn->real_escape_string($data['id']);
    
    $table = ($type === 'alert') ? 'eventlog' : 'monitoringsession';
    $pk = ($type === 'alert') ? 'eventID' : 'sessionID';
    $hasVideoPath2 = $tableHasColumn($table, 'videoPath2');
    $videoPath2Select = $hasVideoPath2 ? ', videoPath2' : ", NULL AS videoPath2";

    if ($id === 'all') {
        // 1. Fetch all video paths for this caregiver's elders
        $fetch_all_sql = "SELECT videoPath{$videoPath2Select} FROM $table t 
                         JOIN ElderProfile ep ON t.elderID = ep.elderID 
                         WHERE ep.caregiverID = '" . $_SESSION['caregiverID'] . "'";
        $result = $conn->query($fetch_all_sql);
        
        while ($row = $result->fetch_assoc()) {
            $deleteVideoFiles($row);
        }

        // 2. Delete all records for this caregiver's elders
        $delete_all_sql = "DELETE t FROM $table t 
                          JOIN ElderProfile ep ON t.elderID = ep.elderID 
                          WHERE ep.caregiverID = '" . $_SESSION['caregiverID'] . "'";
        
        if ($conn->query($delete_all_sql)) {
            echo json_encode(['status' => 'success', 'message' => 'All recordings deleted successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete database records: ' . $conn->error]);
        }
        exit();
    }

    // 1. Fetch the video paths first to delete the file(s)
    $fetch_sql = "SELECT videoPath{$videoPath2Select} FROM $table WHERE $pk = '$id'";
    $result = $conn->query($fetch_sql);
    
    if ($result && $row = $result->fetch_assoc()) {
        $deleteVideoFiles($row);

        // 3. Delete the record from the database
        $delete_sql = "DELETE FROM $table WHERE $pk = '$id'";
        if ($conn->query($delete_sql)) {
            echo json_encode(['status' => 'success', 'message' => 'Recording deleted successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete database record: ' . $conn->error]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Recording not found']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>
