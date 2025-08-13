<?php
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
$unit = escapeshellcmd($data['unit'] ?? '');
$action = $data['action'] ?? '';

if(!$unit || !$action){
    echo json_encode(['error'=>'Missing params']); exit;
}

$systemctl = '/bin/systemctl';

switch($action){
    case 'start':   shell_exec("sudo $systemctl start $unit 2>&1"); break;
    case 'stop':    shell_exec("sudo $systemctl stop $unit 2>&1"); break;
    case 'restart': shell_exec("sudo $systemctl restart $unit 2>&1"); break;
    case 'enable':  shell_exec("sudo $systemctl enable $unit 2>&1"); break;
    case 'disable': shell_exec("sudo $systemctl disable $unit 2>&1"); break;
    case 'logs':
        $output = shell_exec("sudo journalctl -u $unit --no-pager -n 20 2>&1");
        echo json_encode(['unit'=>$unit,'output'=>$output]); exit;
    case 'status': break;
}

$status = trim(shell_exec("sudo $systemctl is-active $unit"));
$enabled = trim(shell_exec("sudo $systemctl is-enabled $unit")) === 'enabled';

echo json_encode([
    'unit'=>$unit,
    'status'=>$status,
    'enabled'=>$enabled
]);
