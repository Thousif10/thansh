<?php
// Database configuration
$servername = "localhost";
$username = "root";
$password = "user_123";
$dbname = "service_manager";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to check service status
function getServiceStatus($service) {
    $output = shell_exec("systemctl is-active {$service} 2>&1");
    $status = trim($output);
    
    if ($status === 'active') {
        return ['text' => 'Active', 'class' => 'status-active'];
    } else {
        return ['text' => 'Inactive', 'class' => 'status-inactive'];
    }
}

// Fetch services from database
$sql = "SELECT * FROM services";
$result = $conn->query($sql);

$services = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $status = getServiceStatus($row['service']);
        $services[] = [
            'name' => $row['service_name'],
            'display' => $row['display_name'],
            'description' => $row['description'],
            'service' => $row['service'],
            'status' => $status['text'],
            'statusClass' => $status['class']
        ];
    }
} else {
    // Default services if database is empty
    $services = [
       
    ];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Management Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --header-bg: #2c3e50;
            --sidebar-bg: #34495e;
            --active-color: #3498db;
            --inactive-color: #e74c3c;
            --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            overflow-x: hidden;
        }
        
        .dashboard-header {
            background-color: var(--header-bg);
            color: white;
            padding: 15px 0;
            box-shadow: var(--card-shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .main-content {
            padding: 25px;
        }
        
        .dashboard-title {
            color: var(--header-bg);
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eaeaea;
        }
        
        .service-card {
            background: white;
            border-radius: 8px;
            box-shadow: var(--card-shadow);
            margin-bottom: 20px;
            overflow: hidden;
            transition: transform 0.3s;
        }
        
        .service-card:hover {
            transform: translateY(-5px);
        }
        
        .service-header {
            background-color: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .service-status {
            display: flex;
            align-items: center;
        }
        
        .status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
            display: inline-block;
        }
        
        .status-active {
            background-color: #2ecc71;
        }
        
        .status-inactive {
            background-color: var(--inactive-color);
        }
        
        .service-body {
            padding: 20px;
        }
        
        .service-name {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--header-bg);
            margin-bottom: 5px;
        }
        
        .service-display-name {
            color: #7f8c8d;
            margin-bottom: 10px;
            font-size: 0.95rem;
        }
        
        .service-description {
            color: #555;
            margin-bottom: 15px;
            font-size: 0.9rem;
            line-height: 1.5;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn-action {
            flex: 1;
            min-width: 100px;
            font-size: 0.85rem;
            padding: 6px 10px;
        }
        
        .enable-toggle {
            display: flex;
            align-items: center;
            margin-top: 15px;
        }
        
        .form-check-input {
            margin-right: 8px;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            box-shadow: var(--card-shadow);
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--header-bg);
            margin: 10px 0;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.8rem;
        }
        
        .status-active-badge {
            background-color: rgba(46, 204, 113, 0.2);
            color: #27ae60;
        }
        
        .status-inactive-badge {
            background-color: rgba(231, 76, 60, 0.2);
            color: #c0392b;
        }
        
        @media (max-width: 768px) {
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-action {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="dashboard-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h3 class="mb-0"><i class="bi bi-gear-fill me-2"></i> Service Management</h3>
                </div>
                <div class="col-md-6 text-end">
                    <span class="badge bg-light text-dark me-3"><i class="bi bi-grid"></i> Dashboard</span>
                    <span class="badge bg-primary"><i class="bi bi-list"></i> Service Details</span>
                </div>
            </div>
        </div>
    </header>

    <div class="container-fluid">
        <div class="row">
            <!-- Main Content -->
            <main class="col-12 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="dashboard-title"><i class="bi bi-hdd-stack me-2"></i> Service Details</h1>
                    <div>
                        <button class="btn btn-sm btn-outline-primary me-2" onclick="refreshPage()">
                            <i class="bi bi-arrow-repeat"></i> Refresh
                        </button>
                        <button class="btn btn-sm btn-primary">
                            <i class="bi bi-plus-circle"></i> Add Service
                        </button>
                    </div>
                </div>

                <!-- Stats Row -->
                <div class="row mb-4">
                    <?php
                    $activeCount = 0;
                    foreach ($services as $service) {
                        if ($service['status'] === 'Active') $activeCount++;
                    }
                    $inactiveCount = count($services) - $activeCount;
                    ?>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <i class="bi bi-gear text-primary" style="font-size: 2rem;"></i>
                            <div class="stat-number"><?php echo count($services); ?></div>
                            <div class="stat-label">Total Services</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <i class="bi bi-check-circle text-success" style="font-size: 2rem;"></i>
                            <div class="stat-number"><?php echo $activeCount; ?></div>
                            <div class="stat-label">Active Services</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <i class="bi bi-exclamation-circle text-danger" style="font-size: 2rem;"></i>
                            <div class="stat-number"><?php echo $inactiveCount; ?></div>
                            <div class="stat-label">Inactive Services</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <i class="bi bi-shield-check text-warning" style="font-size: 2rem;"></i>
                            <div class="stat-number">5</div>
                            <div class="stat-label">System Services</div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Service Name</th>
                                        <th>Display Service</th>
                                        <th>Description</th>
                                        <th>Status</th>
                                        <th>Enable</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($services as $service): ?>
                                    <tr>
                                        <td><strong><?php echo $service['name']; ?></strong></td>
                                        <td><?php echo $service['display']; ?></td>
                                        <td><?php echo $service['description']; ?></td>
                                        <td>
                                            <div class="service-status">
                                                <span class="status-dot <?php echo $service['statusClass']; ?>"></span>
                                                <span class="status-badge <?php echo $service['status'] === 'Active' ? 'status-active-badge' : 'status-inactive-badge'; ?>">
                                                    <?php echo $service['status']; ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox">
                                            </div>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn btn-sm btn-outline-secondary" onclick="showStatus('<?php echo $service['name']; ?>')">
                                                    <i class="bi bi-info-circle"></i> Status
                                                </button>
                                                <button class="btn btn-sm btn-success" onclick="serviceAction('<?php echo $service['service']; ?>', 'start')">
                                                    <i class="bi bi-play-circle"></i> Start
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="serviceAction('<?php echo $service['service']; ?>', 'stop')">
                                                    <i class="bi bi-stop-circle"></i> Stop
                                                </button>
                                                <button class="btn btn-sm btn-warning" onclick="serviceAction('<?php echo $service['service']; ?>', 'restart')">
                                                    <i class="bi bi-arrow-repeat"></i> Restart
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Refresh the page to get updated status
        function refreshPage() {
            location.reload();
        }
        
        // Handle service actions
        function serviceAction(serviceName, action) {
            const actionMap = {
                'start': 'Starting',
                'stop': 'Stopping',
                'restart': 'Restarting'
            };
            
            const statusDiv = document.getElementById('action-status');
            if (!statusDiv) {
                const statusDiv = document.createElement('div');
                statusDiv.id = 'action-status';
                statusDiv.className = 'alert alert-info position-fixed top-0 start-50 translate-middle-x mt-3';
                statusDiv.style.zIndex = '2000';
                statusDiv.innerHTML = `${actionMap[action]} service: ${serviceName}...`;
                document.body.appendChild(statusDiv);
            } else {
                statusDiv.innerHTML = `${actionMap[action]} service: ${serviceName}...`;
                statusDiv.style.display = 'block';
            }
            
            // Simulate API call to backend
            setTimeout(() => {
                // In a real implementation, this would call a PHP script to execute the command
                // For demo purposes, we'll just reload after a short delay
                document.getElementById('action-status').innerHTML = 
                    `<i class="bi bi-check-circle"></i> Successfully ${action}ed ${serviceName}`;
                
                setTimeout(() => {
                    document.getElementById('action-status').style.display = 'none';
                    location.reload();
                }, 1500);
            }, 2000);
        }
        
        // Show service status details
        function showStatus(serviceName) {
            alert(`Detailed status for ${serviceName} would be shown here.\nIn a real implementation, this would display systemctl status output.`);
        }
        
        // Simulate loading
        document.addEventListener('DOMContentLoaded', function() {
            const loadingIndicator = document.createElement('div');
            loadingIndicator.id = 'loading-indicator';
            loadingIndicator.className = 'spinner-border text-primary position-fixed top-50 start-50';
            loadingIndicator.style.zIndex = '2000';
            document.body.appendChild(loadingIndicator);
            
            setTimeout(() => {
                document.getElementById('loading-indicator').remove();
            }, 800);
        });
    </script>
</body>
</html>
