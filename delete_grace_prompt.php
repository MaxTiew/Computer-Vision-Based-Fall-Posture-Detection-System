<?php
// delete_grace_prompt.php
require_once 'auth_session.php';
require_caregiver_auth(true);
header('Content-Type: application/json');

$settingsFile = 'python file/settings.json';

if (file_exists($settingsFile)) {
    $currentSettings = json_decode(file_get_contents($settingsFile), true);
    
    if (!empty($currentSettings['GRACE_PROMPT_AUDIO'])) {
        $filePath = 'python file/' . $currentSettings['GRACE_PROMPT_AUDIO'];
        
        // Delete the physical file if it exists
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        // Update settings.json
        $currentSettings['GRACE_PROMPT_AUDIO'] = "";
        
        if (file_put_contents($settingsFile, json_encode($currentSettings, JSON_PRETTY_PRINT))) {
            echo json_encode(['status' => 'success', 'message' => 'Voice prompt deleted successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update settings file']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No voice prompt to delete']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Settings file not found']);
}
?>
