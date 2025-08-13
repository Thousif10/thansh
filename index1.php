<?php
// Developed by THOUSIF K

// List of services to manage
$services = [
    ['name' => 'httpd', 'display' => 'Apache Web Server', 'description' => 'Handles web requests'],
    ['name' => 'sshd',  'display' => 'SSH Server', 'description' => 'Allows secure shell access'],
    ['name' => 'firewalld', 'display' => 'Firewall Service', 'description' => 'Manages firewall rules']
];

// Handle Enable/Disable actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['service'], $_POST['action'])) {
    $service = escapeshellcmd($_POST['service']);
    $action = ($_POST['action'] === 'enable') ? 'start' : 'stop';
    
    // Run systemctl command via sudo
    exec("sudo systemctl $action $service 2>&1", $output, $status);

    if ($status !== 0) {
        $error = "Error: " . implode("\n", $output);
    } else {
        header("Location: " . $_SERVER['PHP_SELF']); // Refresh page after action
        exit;
    }
}

// Function to check service status
function getServiceStatus($service) {
    exec("systemctl is-active $service 2>&1", $output, $status);
    return ($status === 0 && trim($output[0]) === "active") ? "Running" : "Stopped";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Service Management</title>
    <style>
        body { font-family: Arial, sans-serif; }
        table { width: 80%; margin: 20px auto; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 10px; text-align: center; }
        th { background-color: #eee; }
        .running { color: green; font-weight: bold; }
        .stopped { color: red; font-weight: bold; }
        form { display: inline; }
    </style>
</head>
<body>
    <h2 style="text-align:center;">Service Management</h2>
    <?php if (!empty($error)) echo "<p style='color:red;text-align:center;'>$error</p>"; ?>
    <table>
        <tr>
            <th>Service Name</th>
            <th>Display Service</th>
            <th>Description</th>
            <th>Status</th>
            <th>Enable/Disable</th>
        </tr>
        <?php foreach ($services as $svc): 
            $status = getServiceStatus($svc['name']);
        ?>
        <tr>
            <td><?= htmlspecialchars($svc['name']) ?></td>
            <td><?= htmlspecialchars($svc['display']) ?></td>
            <td><?= htmlspecialchars($svc['description']) ?></td>
            <td class="<?= strtolower($status) ?>"><?= $status ?></td>
            <td>
                <?php if ($status === "Running"): ?>
                    <form method="POST">
                        <input type="hidden" name="service" value="<?= $svc['name'] ?>">
                        <input type="hidden" name="action" value="disable">
                        <button type="submit">Disable</button>
                    </form>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="service" value="<?= $svc['name'] ?>">
                        <input type="hidden" name="action" value="enable">
                        <button type="submit">Enable</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
