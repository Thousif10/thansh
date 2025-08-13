<?php
// Define services
$services = [
    ['name' => 'httpd', 'display' => 'Apache Web Server', 'desc' => 'Handles web requests'],
    ['name' => 'sshd', 'display' => 'SSH Server', 'desc' => 'Allows secure shell access'],
    ['name' => 'firewalld', 'display' => 'Firewall Service', 'desc' => 'Manages firewall rules']
];

// Handle enable/disable actions
if (isset($_POST['service']) && isset($_POST['action'])) {
    $service = escapeshellcmd($_POST['service']);
    $action = $_POST['action'] === 'enable' ? 'start' : 'stop';
    exec("sudo systemctl $action $service 2>&1", $output, $return_var);
    echo json_encode(['status' => $return_var === 0 ? 'success' : 'error', 'output' => implode("\n", $output)]);
    exit;
}

// Function to get service status
function getServiceStatus($service) {
    exec("systemctl is-active $service 2>&1", $output, $return_var);
    return trim($output[0]) === 'active' ? 'Running' : 'Stopped';
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Service Management</title>
    <style>
        body { font-family: Arial; background: #f5f5f5; margin: 0; padding: 20px; }
        h1 { background: #333; color: #fff; padding: 10px; }
        table { border-collapse: collapse; width: 100%; background: #fff; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }
        th { background: #444; color: #fff; }
        button { padding: 5px 10px; }
    </style>
    <script>
        function toggleService(service, action) {
            fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `service=${service}&action=${action}`
            }).then(res => res.json()).then(data => {
                if (data.status === 'success') location.reload();
                else alert('Error: ' + data.output);
            });
        }
    </script>
</head>
<body>
<h1>Service Management</h1>
<table>
    <tr>
        <th>Service Name</th>
        <th>Display Service</th>
        <th>Description</th>
        <th>Status</th>
        <th>Enable/Disable</th>
    </tr>
    <?php foreach ($services as $s): ?>
        <?php $status = getServiceStatus($s['name']); ?>
        <tr>
            <td><?= htmlspecialchars($s['name']) ?></td>
            <td><?= htmlspecialchars($s['display']) ?></td>
            <td><?= htmlspecialchars($s['desc']) ?></td>
            <td><?= $status ?></td>
            <td>
                <?php if ($status === 'Running'): ?>
                    <button onclick="toggleService('<?= $s['name'] ?>', 'disable')">Disable</button>
                <?php else: ?>
                    <button onclick="toggleService('<?= $s['name'] ?>', 'enable')">Enable</button>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
</body>
</html>
