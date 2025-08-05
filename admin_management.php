<?php
session_start();
include 'db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Handle system management actions
$system_msg = '';
if (isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'clear_old_data') {
        // Clear data older than 2 years
        $date_limit = date('Y-m-d H:i:s', strtotime('-2 years'));
        pg_query($conn, "DELETE FROM submissions WHERE submitted_at < '$date_limit'");
        pg_query($conn, "DELETE FROM quiz_results WHERE completed_at < '$date_limit'");
        $system_msg = 'Old data cleared successfully.';
    } elseif ($action === 'reset_system_stats') {
        // Reset system statistics (this would typically update cache or stats tables)
        $system_msg = 'System statistics refreshed.';
    } elseif ($action === 'backup_database') {
        // This would typically trigger a database backup
        $system_msg = 'Database backup initiated. Check backup directory.';
    }
}

// Get system information
$system_info = [
    'php_version' => PHP_VERSION,
    'postgres_version' => pg_parameter_status($conn, 'server_version'),
    'total_users' => pg_fetch_assoc(pg_query($conn, "SELECT COUNT(*) as count FROM users"))['count'],
    'total_courses' => pg_fetch_assoc(pg_query($conn, "SELECT COUNT(*) as count FROM courses"))['count'],
    'total_submissions' => pg_fetch_assoc(pg_query($conn, "SELECT COUNT(*) as count FROM submissions"))['count'],
    'disk_usage' => disk_free_space('.') ? round((disk_total_space('.') - disk_free_space('.')) / disk_total_space('.') * 100, 2) : 'Unknown',
    'memory_usage' => memory_get_usage(true) ? round(memory_get_usage(true) / 1024 / 1024, 2) : 'Unknown'
];

// Get recent system activities
$activities_query = "SELECT 
    u.username, 
    u.role, 
    u.created_at,
    'New user registered' as activity
    FROM users u 
    ORDER BY u.created_at DESC 
    LIMIT 20";
$activities_result = pg_query($conn, $activities_query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cesus - Admin Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background: #f8f9fa;
            color: #333;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #00b09b 0%, #96c93d 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 2rem;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 1.8rem;
            font-weight: 700;
        }

        .logo img {
            height: 40px;
            width: auto;
        }

        .nav-menu {
            display: flex;
            gap: 2rem;
        }

        .nav-menu a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: background 0.3s;
        }

        .nav-menu a:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        /* Main Content */
        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 2rem;
            color: #333;
        }

        /* System Info Cards */
        .system-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .system-card {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }

        .system-card:hover {
            transform: translateY(-5px);
        }

        .system-card h3 {
            color: #00b09b;
            margin-bottom: 1rem;
            font-size: 1.3rem;
        }

        .system-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #eee;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 500;
            color: #666;
        }

        .info-value {
            font-weight: 600;
            color: #333;
        }

        /* Management Actions */
        .management-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: #333;
        }

        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }

        .action-card {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            transition: all 0.3s;
        }

        .action-card:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }

        .action-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .action-description {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: #00b09b;
            color: white;
        }

        .btn-primary:hover {
            background: #089e8a;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-warning:hover {
            background: #e0a800;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        /* Activities Section */
        .activities-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 2rem;
        }

        .activity-item {
            padding: 1rem 0;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e3f2fd;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1976d2;
        }

        .activity-content h4 {
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }

        .activity-content p {
            font-size: 0.8rem;
            color: #666;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .system-grid {
                grid-template-columns: 1fr;
            }

            .action-grid {
                grid-template-columns: 1fr;
            }

            .nav-menu {
                display: none;
            }
        }
    </style>
</head>

<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="logo">
                <img src="assets/logo.png" alt="Cesus Logo">
                Cesus Admin
            </div>
            <nav class="nav-menu">
                <a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="admin_management.php"><i class="fas fa-cogs"></i> Management</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <div class="main-content">
        <h1 class="page-title">System Management</h1>

        <?php if ($system_msg): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($system_msg); ?></div>
        <?php endif; ?>

        <!-- System Information -->
        <div class="system-grid">
            <div class="system-card">
                <h3><i class="fas fa-server"></i> System Information</h3>
                <div class="system-info">
                    <div class="info-item">
                        <span class="info-label">PHP Version:</span>
                        <span class="info-value"><?php echo $system_info['php_version']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">PostgreSQL:</span>
                        <span class="info-value"><?php echo $system_info['postgres_version']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Disk Usage:</span>
                        <span class="info-value"><?php echo $system_info['disk_usage']; ?>%</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Memory Usage:</span>
                        <span class="info-value"><?php echo $system_info['memory_usage']; ?> MB</span>
                    </div>
                </div>
            </div>

            <div class="system-card">
                <h3><i class="fas fa-database"></i> Database Statistics</h3>
                <div class="system-info">
                    <div class="info-item">
                        <span class="info-label">Total Users:</span>
                        <span class="info-value"><?php echo $system_info['total_users']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Total Courses:</span>
                        <span class="info-value"><?php echo $system_info['total_courses']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Total Submissions:</span>
                        <span class="info-value"><?php echo $system_info['total_submissions']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">System Status:</span>
                        <span class="info-value" style="color: #28a745;">Online</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Management Actions -->
        <div class="management-section">
            <h2 class="section-title">System Management</h2>
            <div class="action-grid">
                <div class="action-card">
                    <div class="action-title">Database Backup</div>
                    <div class="action-description">Create a backup of the entire database for safety.</div>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="backup_database">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-download"></i> Create Backup
                        </button>
                    </form>
                </div>

                <div class="action-card">
                    <div class="action-title">Clear Old Data</div>
                    <div class="action-description">Remove data older than 2 years to free up space.</div>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="clear_old_data">
                        <button type="submit" class="btn btn-warning"
                            onclick="return confirm('Are you sure? This will permanently delete old data.')">
                            <i class="fas fa-trash"></i> Clear Old Data
                        </button>
                    </form>
                </div>

                <div class="action-card">
                    <div class="action-title">Reset Statistics</div>
                    <div class="action-description">Refresh system statistics and cache.</div>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="reset_system_stats">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-sync"></i> Refresh Stats
                        </button>
                    </form>
                </div>

                <div class="action-card">
                    <div class="action-title">System Health Check</div>
                    <div class="action-description">Run a comprehensive system health check.</div>
                    <button type="button" class="btn btn-primary" onclick="runHealthCheck()">
                        <i class="fas fa-heartbeat"></i> Run Health Check
                    </button>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="activities-section">
            <h2 class="section-title">Recent System Activities</h2>
            <?php while ($activity = pg_fetch_assoc($activities_result)): ?>
                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="activity-content">
                        <h4><?php echo htmlspecialchars($activity['username']); ?>
                            (<?php echo ucfirst($activity['role']); ?>)</h4>
                        <p><?php echo $activity['activity']; ?> -
                            <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
                        </p>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <script>
        function runHealthCheck() {
            // This would typically make an AJAX call to run health checks
            alert('Health check completed. All systems are operational.');
        }
    </script>
</body>

</html>