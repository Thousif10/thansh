<?php
// Service list
$services = [
    ['name' => 'httpd', 'display' => 'Apache Web Server', 'description' => 'Handles web requests'],
    ['name' => 'sshd', 'display' => 'SSH Server', 'description' => 'Allows secure shell access'],
    ['name' => 'firewalld', 'display' => 'Firewall Service', 'description' => 'Manages firewall rules'],
];

// Function to get status
function getStatus($service) {
    $output = shell_exec("sudo systemctl is-active " . escapeshellarg($service) . " 2>&1");
    return trim($output) === "active" ? "Running" : "Stopped";
}

// Handle enable/disable action
if (isset($_GET['action']) && isset($_GET['service'])) {
    $service = escapeshellarg($_GET['service']);
    if ($_GET['action'] === 'start') {
        shell_exec("sudo systemctl start $service 2>&1");
    } elseif ($_GET['action'] === 'stop') {
        shell_exec("sudo systemctl stop $service 2>&1");
    }
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Service Management</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        h2 { color: #333; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background: #f4f4f4; }
        a.button { padding: 6px 12px; background: #007BFF; color: #fff; text-decoration: none; border-radius: 4px; }
        a.button.stop { background: #dc3545; }
    </style>
</head>
<body>
    <h2>Service Management</h2>
    <table>
        <tr>
            <th>Service Name</th>
            <th>Display Service</th>
            <th>Description</th>
            <th>Status</th>
            <th>Enable/Disable</th>
        </tr>
        <?php foreach ($services as $svc): 
            $status = getStatus($svc['name']); ?>
            <tr>
                <td><?= htmlspecialchars($svc['name']) ?></td>
                <td><?= htmlspecialchars($svc['display']) ?></td>
                <td><?= htmlspecialchars($svc['description']) ?></td>
                <td><?= $status ?></td>
                <td>
                    <?php if ($status === "Running"): ?>
                        <a class="button stop" href="?action=stop&service=<?= urlencode($svc['name']) ?>">Disable</a>
                    <?php else: ?>
                        <a class="button" href="?action=start&service=<?= urlencode($svc['name']) ?>">Enable</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
    <p>new code</p>
</body>
</html>


