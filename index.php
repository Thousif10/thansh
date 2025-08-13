<?php
// Developed by THOUSIF K
// Simple Service Management Panel for RHEL using PHP + shell_exec

// Function to get service status
function getServiceStatus($service) {
    $status = shell_exec("systemctl is-active " . escapeshellarg($service) . " 2>&1");
    return trim($status);
}

// Function to start service
function startService($service) {
    shell_exec("sudo systemctl start " . escapeshellarg($service) . " 2>&1");
}

// Function to stop service
function stopService($service) {
    shell_exec("sudo systemctl stop " . escapeshellarg($service) . " 2>&1");
}

// Handle service action
if (isset($_POST['action']) && isset($_POST['service'])) {
    $service = $_POST['service'];
    if ($_POST['action'] == 'start') {
        startService($service);
    } elseif ($_POST['action'] == 'stop') {
        stopService($service);
    }
}

// List of services to manage (You can add more here)
$services = [
    ['name' => 'httpd', 'display' => 'Apache Web Server', 'description' => 'Handles HTTP requests.'],
    ['name' => 'sshd', 'display' => 'SSH Server', 'description' => 'Secure shell remote login.'],
    ['name' => 'firewalld', 'display' => 'Firewall', 'description' => 'Manages firewall rules.']
];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Service Management Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
        h1 { background: #333; color: #fff; padding: 15px; text-align: center; }
        nav { background: #555; padding: 10px; text-align: center; }
        nav a { color: white; margin: 0 15px; text-decoration: none; font-weight: bold; }
        nav a:hover { text-decoration: underline; }
        table { width: 80%; margin: 20px auto; border-collapse: collapse; background: white; box-shadow: 0 0 5px rgba(0,0,0,0.1); }
        table, th, td { border: 1px solid #ccc; }
        th, td { padding: 12px; text-align: center; }
        th { background: #f2f2f2; }
        .active { color: green; font-weight: bold; }
        .inactive { color: red; font-weight: bold; }
        button { padding: 6px 12px; border: none; background: #333; color: white; cursor: pointer; border-radius: 4px; }
        button:hover { background: #555; }
    </style>
</head>
<body>

<h1>Service Management Dashboard</h1>

<nav>
    <a href="index.php">Dashboard</a>
    <a href="#">Add</a>
    <a href="#">Manage</a>
    <a href="#">Service Details</a>
</nav>

<table>
    <tr>
        <th>Service Name</th>
        <th>Display Service</th>
        <th>Description</th>
        <th>Status</th>
        <th>Enable</th>
        <th>Action</th>
    </tr>
    <?php foreach ($services as $service): ?>
        <tr>
            <td><?= htmlspecialchars($service['name']) ?></td>
            <td><?= htmlspecialchars($service['display']) ?></td>
            <td><?= htmlspecialchars($service['description']) ?></td>
            <td class="<?= getServiceStatus($service['name']) == 'active' ? 'active' : 'inactive' ?>">
                <?= ucfirst(getServiceStatus($service['name'])) ?>
            </td>
            <td>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="service" value="<?= htmlspecialchars($service['name']) ?>">
                    <input type="hidden" name="action" value="start">
                    <button type="submit">Enable</button>
                </form>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="service" value="<?= htmlspecialchars($service['name']) ?>">
                    <input type="hidden" name="action" value="stop">
                    <button type="submit">Disable</button>
                </form>
            </td>
            <td>
                <?= (getServiceStatus($service['name']) == 'active') ? 'Running' : 'Stopped' ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

</body>
</html>
