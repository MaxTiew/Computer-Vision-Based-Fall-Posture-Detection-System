<?php
// start_python.php
require_once 'auth_session.php';
require_caregiver_auth(true);
header('Content-Type: application/json');

// The exact path to your working Python file
$script_path = "C:\\xampp\\htdocs\\goodlife\\python file\\smart_detection.py";

// The Windows command to run Python invisibly in the background
// We use quotes around the path because your folder "python file" has a space in it
$command = "start /B py -3.11 \"$script_path\" > debug_log.txt 2>&1";

// Execute the command without waiting for it to finish (prevents the web page from freezing)
pclose(popen($command, "r"));

// Tell the Javascript frontend that the command was successfully sent
echo json_encode(["status" => "success", "message" => "Python command executed."]);
?>
