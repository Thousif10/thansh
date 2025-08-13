<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Service Management</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        h1 {
            text-align: center;
            color: #333;
        }
        .top-menu {
            margin-bottom: 20px;
            text-align: center;
        }
        .top-menu button {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            margin: 5px;
            border-radius: 5px;
            cursor: pointer;
        }
        .top-menu button:hover {
            background: #0056b3;
        }
        table {
            width: 100%;
            background: white;
            border-collapse: collapse;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: center;
        }
        th {
            background: #007bff;
            color: white;
        }
        tr:nth-child(even) {
            background: #f9f9f9;
        }
        .btn {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .enable {
            background: green;
            color: white;
        }
        .disable {
            background: red;
            color: white;
        }
        .status-running {
            color: green;
            font-weight: bold;
        }
        .status-stopped {
            color: red;
            font-weight: bold;
        }
        .error {
            color: red;
            margin: 10px 0;
            text-align: center;
        }
    </style>
</head>
<body>

<h1>Service Management</h1>

<div class="top-menu">
    <button>Add</button>
    <button>Manage</button>
    <button>Service Details</button>
    <button>Dashboard</button>
</div>

<div class="error" id="error-message">API error: HTTP 500</div>

<table>
    <thead>
        <tr>
            <th>Service Name</th>
            <th>Display Service</th>
            <th>Description</th>
            <th>Status</th>
            <th>Enable/Disable</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>httpd</td>
            <td>Apache Web Server</td>
            <td>Handles web requests</td>
            <td class="status-stopped">Stopped</td>
            <td><button class="btn enable">Enable</button></td>
            <td><button class="btn">Start</button></td>
        </tr>
        <tr>
            <td>sshd</td>
            <td>SSH Server</td>
            <td>Allows secure shell access</td>
            <td class="status-stopped">Stopped</td>
            <td><button class="btn enable">Enable</button></td>
            <td><button class="btn">Start</button></td>
        </tr>
        <tr>
            <td>firewalld</td>
            <td>Firewall Service</td>
            <td>Manages firewall rules</td>
            <td class="status-stopped">Stopped</td>
            <td><button class="btn enable">Enable</button></td>
            <td><button class="btn">Start</button></td>
        </tr>
    </tbody>
</table>

</body>
</html>
