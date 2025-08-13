<?php
// service-management.php
// Developed by THOUSIF K

// Function to get service status
function getServiceStatus($service) {
    $output = shell_exec("systemctl is-active " . escapeshellarg($service) . " 2>&1");
    return trim($output);
}

// Function to enable/start a service
function enableService($service) {
    shell_exec("sudo systemctl enable " . escapeshellarg($service) . " 2>&1");
    shell_exec("sudo systemctl start " . escapeshellarg($service) . " 2>&1");
}

// Function to disable/stop a service
function disableService($service) {
    shell_exec("sudo systemctl stop " . escapeshellarg($service) . " 2>&1");
    shell_exec("sudo systemctl disable " . escapeshellarg($service) . " 2>&1");
}

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['service']) && isset($_POST['action'])) {
    $service = $_POST['service'];
    if ($_POST['action'] === 'enable') {
        enableService($service);
    } elseif ($_POST['action'] === 'disable') {
        disableService($service);
    }
}

// Example services list (you can add more)
$services = ['httpd', 'sshd', 'firewalld'];

?>
<!DOCTYPE html>
<html>
<head>
    <title>Service Management</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f9f9f9;
        }
        h1 {
            background: #4CAF50;
            color: white;
            padding: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        table, th, td {
            border: 1px solid #ccc;
        }
        th {
            background: #f2f2f2;
            padding: 10px;
        }
        td {
            padding: 10px;
            text-align: center;
        }
        button {
            padding: 5px 10px;
            border: none;
            color: white;
            cursor: pointer;
        }
        .enable-btn {
            background: #4CAF50;
        }
        .disable-btn {
            background: #f44336;
        }
        .status-active {
            color: green;
            font-weight: bold;
        }
        .status-inactive {
            color: red;
            font-weight: bold;
        }
    </style>
</head>
<body>

<h1>Service Management</h1>

<table>
    <tr>
        <th>Service Name</th>
        <th>Description</th>
        <th>Status</th>
        <th>Enable/Disable</th>
    </tr>
    <?php foreach ($services as $service): 
        $status = getServiceStatus($service);
        $desc = shell_exec("systemctl show -p Description " . escapeshellarg($service) . " 2>/dev/null | cut -d= -f2");
    ?>
    <tr>
        <td><?php echo htmlspecialchars($service); ?></td>
        <td><?php echo htmlspecialchars(trim($desc)); ?></td>
        <td class="<?php echo $status === 'active' ? 'status-active' : 'status-inactive'; ?>">
            <?php echo ucfirst($status); ?>
        </td>
        <td>
            <form method="POST" style="display:inline;">
                <input type="hidden" name="service" value="<?php echo htmlspecialchars($service); ?>">
                <input type="hidden" name="action" value="enable">
                <button class="enable-btn" type="submit">Enable</button>
            </form>
            <form method="POST" style="display:inline;">
                <input type="hidden" name="service" value="<?php echo htmlspecialchars($service); ?>">
                <input type="hidden" name="action" value="disable">
                <button class="disable-btn" type="submit">Disable</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

</body>
</html>
