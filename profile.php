<?php
session_start();
include 'db_connect.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Get current user data
$user_query = "SELECT * FROM users WHERE id = $user_id";
$user_result = pg_query($conn, $user_query);
$user_data = pg_fetch_assoc($user_result);

// Handle profile updates
$profile_msg = '';
$profile_msg_class = '';

if (isset($_POST['update_profile'])) {
    $new_username = pg_escape_string($conn, trim($_POST['username']));
    $new_email = pg_escape_string($conn, trim($_POST['email']));

    // Check if username or email already exists (excluding current user)
    $check_query = "SELECT id FROM users WHERE (username = '$new_username' OR email = '$new_email') AND id != $user_id";
    $check_result = pg_query($conn, $check_query);

    if (pg_num_rows($check_result) > 0) {
        $profile_msg = 'Username or email already exists.';
        $profile_msg_class = 'error';
    } else {
        $update_query = "UPDATE users SET username = '$new_username', email = '$new_email' WHERE id = $user_id";
        $update_result = pg_query($conn, $update_query);

        if ($update_result) {
            $_SESSION['username'] = $new_username;
            $profile_msg = 'Profile updated successfully!';
            $profile_msg_class = 'success';

            // Refresh user data
            $user_result = pg_query($conn, $user_query);
            $user_data = pg_fetch_assoc($user_result);
        } else {
            $profile_msg = 'Error updating profile.';
            $profile_msg_class = 'error';
        }
    }
}

// Handle password change
$password_msg = '';
$password_msg_class = '';

if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Verify current password
    if (password_verify($current_password, $user_data['password'])) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 6) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $password_query = "UPDATE users SET password = '$hashed_password' WHERE id = $user_id";
                $password_result = pg_query($conn, $password_query);

                if ($password_result) {
                    $password_msg = 'Password changed successfully!';
                    $password_msg_class = 'success';
                } else {
                    $password_msg = 'Error changing password.';
                    $password_msg_class = 'error';
                }
            } else {
                $password_msg = 'New password must be at least 6 characters long.';
                $password_msg_class = 'error';
            }
        } else {
            $password_msg = 'New passwords do not match.';
            $password_msg_class = 'error';
        }
    } else {
        $password_msg = 'Current password is incorrect.';
        $password_msg_class = 'error';
    }
}

// Get user statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM quiz_results WHERE user_id = $user_id) as total_quizzes_taken,
    (SELECT COUNT(*) FROM enrollments WHERE user_id = $user_id) as courses_enrolled,
    (SELECT COUNT(*) FROM messages WHERE sender_id = $user_id OR receiver_id = $user_id) as total_messages,
    (SELECT AVG(percentage) FROM quiz_results WHERE user_id = $user_id) as avg_quiz_score
";
$stats_result = pg_query($conn, $stats_query);
$stats = pg_fetch_assoc($stats_result);

// Get recent activity
$activity_query = "SELECT 
    'quiz' as type,
    q.title as title,
    qr.percentage as score,
    qr.submitted_at as date
    FROM quiz_results qr 
    JOIN quizzes q ON qr.quiz_id = q.id 
    WHERE qr.user_id = $user_id
    ORDER BY date DESC 
    LIMIT 10";
$activity_result = pg_query($conn, $activity_query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>My Profile - LMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            background: #f4f8fb;
            font-family: 'Roboto', Arial, sans-serif;
        }

        .menu-bar {
            background: linear-gradient(90deg, #00b09b 0%, #96c93d 100%);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            padding: 0 30px;
            display: flex;
            align-items: center;
            height: 60px;
        }

        .menu-bar a {
            color: white;
            padding: 0 18px;
            text-decoration: none;
            font-size: 17px;
            line-height: 60px;
            font-weight: 500;
            transition: background 0.2s;
        }

        .menu-bar a:hover {
            background: rgba(0, 0, 0, 0.08);
            border-radius: 6px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .profile-header {
            background: linear-gradient(135deg, #00b09b, #96c93d);
            color: white;
            border-radius: 20px;
            padding: 3rem 2rem;
            text-align: center;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0, 176, 155, 0.2);
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 3rem;
        }

        .profile-name {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .profile-role {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.5rem 1.5rem;
            border-radius: 25px;
            font-weight: 600;
            display: inline-block;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: #fff;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-4px);
        }

        .stat-icon {
            font-size: 2rem;
            color: #00b09b;
            margin-bottom: 0.5rem;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #666;
            font-weight: 600;
        }

        .section-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 4px 24px rgba(0, 176, 155, 0.10);
            padding: 2.5rem;
            margin-bottom: 2rem;
        }

        .section-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .section-header h2 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 700;
            color: #00b09b;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }

        .form-group input {
            width: 100%;
            padding: 0.8rem;
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        .form-group input:focus {
            border-color: #00b09b;
            outline: none;
            box-shadow: 0 0 0 2px #00b09b22;
        }

        .btn-gradient {
            background: linear-gradient(135deg, #00b09b, #96c93d);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0.8rem 2rem;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-gradient:hover {
            background: linear-gradient(135deg, #96c93d, #00b09b);
        }

        .btn-secondary {
            background: #f8f9fa;
            color: #00b09b;
            border: 2px solid #00b09b;
            border-radius: 8px;
            padding: 0.8rem 2rem;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-secondary:hover {
            background: #00b09b;
            color: white;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .activity-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #e0e0e0;
            transition: background 0.2s;
        }

        .activity-item:hover {
            background: #f8f9fa;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.2rem;
            color: white;
        }

        .activity-icon.quiz {
            background: linear-gradient(135deg, #00b09b, #96c93d);
        }



        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.2rem;
        }

        .activity-details {
            color: #666;
            font-size: 0.9rem;
        }

        .activity-score {
            font-weight: 700;
            color: #00b09b;
        }

        .two-column {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        @media (max-width: 768px) {
            .two-column {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .profile-header {
                padding: 2rem 1rem;
            }

            .profile-name {
                font-size: 2rem;
            }
        }
    </style>
</head>

<body>
    <div class="menu-bar">
        <a href="main.php"><i class="fa fa-home"></i> Home</a>
        <a href="quizzes.php"><i class="fa fa-question-circle"></i> Quizzes</a>
        <a href="my_courses.php"><i class="fa fa-book"></i> My Courses</a>
        <a href="profile.php"><i class="fa fa-user"></i> Profile</a>
        <a href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="container">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-avatar">
                <i class="fa fa-user"></i>
            </div>
            <div class="profile-name"><?php echo htmlspecialchars($user_data['username']); ?></div>
            <div class="profile-role"><?php echo ucfirst($user_data['role']); ?></div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fa fa-question-circle"></i></div>
                <div class="stat-number"><?php echo $stats['total_quizzes_taken']; ?></div>
                <div class="stat-label">Quizzes Taken</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon"><i class="fa fa-book"></i></div>
                <div class="stat-number"><?php echo $stats['courses_enrolled']; ?></div>
                <div class="stat-label">Courses Enrolled</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa fa-chart-line"></i></div>
                <div class="stat-number"><?php echo round($stats['avg_quiz_score'], 1); ?>%</div>
                <div class="stat-label">Avg Quiz Score</div>
            </div>
        </div>

        <div class="two-column">
            <!-- Profile Settings -->
            <div class="section-card">
                <div class="section-header">
                    <h2><i class="fa fa-user-edit"></i> Profile Settings</h2>
                </div>

                <?php if ($profile_msg): ?>
                    <div class="alert alert-<?php echo $profile_msg_class; ?>">
                        <?php echo htmlspecialchars($profile_msg); ?>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username"
                            value="<?php echo htmlspecialchars($user_data['username']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user_data['email']); ?>"
                            required>
                    </div>
                    <div class="form-group">
                        <label>Role</label>
                        <input type="text" value="<?php echo ucfirst($user_data['role']); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Member Since</label>
                        <input type="text" value="<?php echo date('F j, Y', strtotime($user_data['created_at'])); ?>"
                            disabled>
                    </div>
                    <button type="submit" name="update_profile" class="btn-gradient">
                        <i class="fa fa-save"></i> Update Profile
                    </button>
                </form>
            </div>

            <!-- Change Password -->
            <div class="section-card">
                <div class="section-header">
                    <h2><i class="fa fa-lock"></i> Change Password</h2>
                </div>

                <?php if ($password_msg): ?>
                    <div class="alert alert-<?php echo $password_msg_class; ?>">
                        <?php echo htmlspecialchars($password_msg); ?>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" required>
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" required>
                    </div>
                    <button type="submit" name="change_password" class="btn-gradient">
                        <i class="fa fa-key"></i> Change Password
                    </button>
                </form>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="section-card">
            <div class="section-header">
                <h2><i class="fa fa-history"></i> Recent Activity</h2>
            </div>

            <?php if ($activity_result && pg_num_rows($activity_result) > 0): ?>
                <?php while ($activity = pg_fetch_assoc($activity_result)): ?>
                    <div class="activity-item">
                        <div class="activity-icon quiz">
                            <i class="fa fa-question-circle"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title"><?php echo htmlspecialchars($activity['title']); ?></div>
                            <div class="activity-details">
                                <?php echo date('M j, Y g:i A', strtotime($activity['date'])); ?>
                            </div>
                        </div>
                        <div class="activity-score"><?php echo $activity['score']; ?>%</div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="text-align: center; color: #666; padding: 2rem;">
                    <i class="fa fa-info-circle"
                        style="font-size: 2rem; margin-bottom: 1rem; display: block; color: #ccc;"></i>
                    No recent activity. Start taking quizzes to see your activity here!
                </p>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="section-card">
            <div class="section-header">
                <h2><i class="fa fa-rocket"></i> Quick Actions</h2>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <a href="quizzes.php" class="btn-gradient" style="text-decoration: none; text-align: center;">
                    <i class="fa fa-question-circle"></i> Take Quizzes
                </a>

                <a href="my_courses.php" class="btn-gradient" style="text-decoration: none; text-align: center;">
                    <i class="fa fa-book"></i> My Courses
                </a>
                <a href="main.php" class="btn-secondary" style="text-decoration: none; text-align: center;">
                    <i class="fa fa-home"></i> Dashboard
                </a>
            </div>
        </div>
    </div>
</body>

</html>