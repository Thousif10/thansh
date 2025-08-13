<?php
// Developed by THOUSIF K

// List of services to manage
$services = ["httpd", "sshd", "firewalld"]; // You can add more

// Function to get service status
function getServiceStatus($service) {
    $output = shell_exec("systemctl is-active " . escapeshellarg($service) . " 2>&1");
    return trim($output);
}

// Function to start/stop service
if (isset($_GET['action']) && isset($_GET['service'])) {
    $service = escapeshellarg($_GET['service']);
    $action = $_GET['action'] === 'enable' ? 'start' : 'stop';
    shell_exec("sudo systemctl $action $service 2>&1");
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
        table { border-collapse: collapse; width: 70%; margin: auto; }
        th, td { padding: 10px; border: 1px solid #ccc; text-align: center; }
        th { background-color: #f2f2f2; }
        .running { color: green; font-weight: bold; }
        .stopped { color: red; font-weight: bold; }
        a.button { padding: 6px 12px; text-decoration: none; border-radius: 4px; color: white; }
        .enable { background-color: green; }
        .disable { background-color: red; }
    </style>
</head>
<body>

<h2 style="text-align:center;">Service Management</h2>
<table>
    <tr>
        <th>Service Name</th>
        <th>Status</th>
        <th>Action</th>
    </tr>
    <?php foreach ($services as $srv): 
        $status = getServiceStatus($srv);
    ?>
    <tr>
        <td><?php echo htmlspecialchars($srv); ?></td>
        <td class="<?php echo $status === 'active' ? 'running' : 'stopped'; ?>">
            <?php echo ucfirst($status); ?>
        </td>
        <td>
            <?php if ($status === 'active'): ?>
                <a class="button disable" href="?action=disable&service=<?php echo urlencode($srv); ?>">Disable</a>
            <?php else: ?>
                <a class="button enable" href="?action=enable&service=<?php echo urlencode($srv); ?>">Enable</a>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

</body>
</html>
