<?php
header('Content-Type: application/json');

function runCmd($cmd){
    return trim(shell_exec("$cmd 2>&1"));
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $_GET['fn'] === 'list') {
    // You can adjust service list here
    $units = ['httpd','sshd','firewalld','mariadb','snmpd'];
    $data = [];
    foreach ($units as $u){
        $status = runCmd("systemctl is-active $u");
        $enabled = (runCmd("systemctl is-enabled $u") === 'enabled');
        $data[] = [
            'unit' => $u,
            'display' => ucfirst($u),
            'desc' => "$u service",
            'status' => $status ?: 'inactive',
            'enabled' => $enabled
        ];
    }
    echo json_encode($data);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_REQUEST;
$fn = $input['fn'] ?? '';
$unit = escapeshellarg($input['unit'] ?? '');

switch($fn){
    case 'enable':  $out = runCmd("sudo systemctl enable $unit"); break;
    case 'disable': $out = runCmd("sudo systemctl disable $unit"); break;
    case 'play':    $out = runCmd("sudo systemctl start $unit"); break;
    case 'stop':    $out = runCmd("sudo systemctl stop $unit"); break;
    case 'reload':  $out = runCmd("sudo systemctl restart $unit"); break;
    case 'logs':    
        $unit = escapeshellarg($_GET['unit'] ?? '');
        $out = runCmd("sudo journalctl -u $unit -n 30 --no-pager");
        echo json_encode(['logs'=>$out]);
        exit;
    default: $out = 'Unknown action';
}

echo json_encode(['output'=>$out]);
