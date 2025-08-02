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

// Get statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM users WHERE role = 'student') as total_students,
    (SELECT COUNT(*) FROM users WHERE role = 'instructor') as total_instructors,
    (SELECT COUNT(*) FROM users WHERE role = 'admin') as total_admins,
    (SELECT COUNT(*) FROM courses) as total_courses,
    (SELECT COUNT(*) FROM assessments) as total_assessments,
    (SELECT COUNT(*) FROM enrollments) as total_enrollments,
    (SELECT COUNT(*) FROM submissions) as total_submissions";
$stats_result = pg_query($conn, $stats_query);
$stats = pg_fetch_assoc($stats_result);

// Get recent activities
$activities_query = "SELECT 
    u.username, 
    u.role, 
    u.created_at,
    'New user registered' as activity
    FROM users u 
    ORDER BY u.created_at DESC 
    LIMIT 10";
$activities_result = pg_query($conn, $activities_query);

// Handle user management actions
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    $target_user_id = (int) $_POST['user_id'];

    if ($action === 'delete_user' && $target_user_id !== $user_id) {
        // Delete user's data first
        pg_query($conn, "DELETE FROM submissions WHERE student_id = $target_user_id");
        pg_query($conn, "DELETE FROM enrollments WHERE user_id = $target_user_id");
        pg_query($conn, "DELETE FROM quiz_results WHERE user_id = $target_user_id");
        pg_query($conn, "DELETE FROM messages WHERE sender_id = $target_user_id OR receiver_id = $target_user_id");
        
        // Delete courses if user is instructor
        $instructor_courses = pg_query($conn, "SELECT id FROM courses WHERE instructor_id = $target_user_id");
        while ($course = pg_fetch_assoc($instructor_courses)) {
            $course_id = $course['id'];
            pg_query($conn, "DELETE FROM materials WHERE course_id = $course_id");
            pg_query($conn, "DELETE FROM assessments WHERE course_id = $course_id");
            pg_query($conn, "DELETE FROM enrollments WHERE course_id = $course_id");
        }
        pg_query($conn, "DELETE FROM courses WHERE instructor_id = $target_user_id");
        
        // Finally delete the user
        $delete_query = "DELETE FROM users WHERE id = $target_user_id";
        pg_query($conn, $delete_query);
    } elseif ($action === 'change_role') {
        $new_role = pg_escape_string($conn, $_POST['new_role']);
        $update_query = "UPDATE users SET role = '$new_role' WHERE id = $target_user_id";
        pg_query($conn, $update_query);
    }
}

// Get all users for management
$users_query = "SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC";
$users_result = pg_query($conn, $users_query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cesus - Admin Dashboard</title>
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
            font-size: 1.8rem;
            font-weight: 700;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-menu a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: background 0.3s;
        }

        .user-menu a:hover {
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

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 2.5rem;
            color: #00b09b;
            margin-bottom: 1rem;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #666;
            font-weight: 500;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        /* User Management */
        .user-management {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .section-header {
            background: #f8f9fa;
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
        }

        .user-table {
            width: 100%;
            border-collapse: collapse;
        }

        .user-table th,
        .user-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .user-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
        }

        .user-table tr:hover {
            background: #f8f9fa;
        }

        .role-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .role-student {
            background: #e3f2fd;
            color: #1976d2;
        }

        .role-instructor {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        .role-admin {
            background: #ffebee;
            color: #d32f2f;
        }



        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-warning:hover {
            background: #e0a800;
        }

        /* Recent Activities */
        .recent-activities {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .activity-item {
            padding: 1rem 1.5rem;
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

        /* Responsive */
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>

<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="logo">Cesus Admin</div>
            <div class="user-menu">
                <span>Welcome, <?php echo htmlspecialchars($username); ?></span>
                <a href="admin_management.php"><i class="fas fa-cogs"></i> Management</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="main-content">
        <h1 class="page-title">Admin Dashboard</h1>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="stat-number"><?php echo $stats['total_students']; ?></div>
                <div class="stat-label">Total Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <div class="stat-number"><?php echo $stats['total_instructors']; ?></div>
                <div class="stat-label">Total Instructors</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="stat-number"><?php echo $stats['total_admins']; ?></div>
                <div class="stat-label">Total Admins</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-number"><?php echo $stats['total_courses']; ?></div>
                <div class="stat-label">Total Courses</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="stat-number"><?php echo $stats['total_assessments']; ?></div>
                <div class="stat-label">Total Assessments</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number"><?php echo $stats['total_enrollments']; ?></div>
                <div class="stat-label">Total Enrollments</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-number"><?php echo $stats['total_submissions']; ?></div>
                <div class="stat-label">Total Submissions</div>
            </div>

        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- User Management -->
            <div class="user-management">
                <div class="section-header">
                    <h2 class="section-title">User Management</h2>
                </div>
                <div style="overflow-x: auto;">
                    <table class="user-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($user = pg_fetch_assoc($users_result)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="role-badge role-<?php echo $user['role']; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($user['id'] != $user_id): ?>
                                                <!-- Role Change -->
                                                <form method="post" style="display: inline;">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <input type="hidden" name="action" value="change_role">
                                                    <select name="new_role" onchange="this.form.submit()"
                                                        style="padding: 0.25rem; border-radius: 3px; border: 1px solid #ddd;">
                                                        <option value="student" <?php echo $user['role'] === 'student' ? 'selected' : ''; ?>>Student</option>
                                                        <option value="instructor" <?php echo $user['role'] === 'instructor' ? 'selected' : ''; ?>>Instructor</option>
                                                        <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                    </select>
                                                </form>
                                                

                                                <!-- Delete User -->
                                                <form method="post" style="display: inline;">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <button type="submit" class="btn btn-danger"
                                                        onclick="return confirm('Are you sure you want to delete this user? This will remove all their data permanently.')"
                                                        title="Delete User">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span style="color: #666; font-size: 0.9rem;">Current User</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="recent-activities">
                <div class="section-header">
                    <h2 class="section-title">Recent Activities</h2>
                </div>
                <?php while ($activity = pg_fetch_assoc($activities_result)): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="activity-content">
                            <h4><?php echo htmlspecialchars($activity['username']); ?></h4>
                            <p><?php echo $activity['activity']; ?> -
                                <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?></p>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</body>

</html>