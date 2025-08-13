<?php
header('Content-Type: application/json');

// Allow only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

// Read POST data
$data = json_decode(file_get_contents('php://input'), true);
$unit   = escapeshellcmd($data['unit'] ?? '');
$action = $data['action'] ?? '';

if (!$unit || !$action) {
    echo json_encode(["error" => "Missing unit or action"]);
    exit;
}

// Map actions to systemctl commands
$allowedActions = [
    'start'   => "systemctl start $unit",
    'stop'    => "systemctl stop $unit",
    'restart' => "systemctl restart $unit",
    'enable'  => "systemctl enable $unit",
    'disable' => "systemctl disable $unit",
    'status'  => "systemctl is-active $unit"
];

if (!isset($allowedActions[$action])) {
    echo json_encode(["error" => "Invalid action"]);
    exit;
}

// Execute the action
$output = shell_exec($allowedActions[$action] . " 2>&1");

// Always fetch current status
$status  = trim(shell_exec("systemctl is-active $unit"));
$enabled = trim(shell_exec("systemctl is-enabled $unit")) === "enabled";

// Send JSON response
echo json_encode([
    "unit"    => $unit,
    "status"  => $status,
    "enabled" => $enabled,
    "output"  => $output
]);
