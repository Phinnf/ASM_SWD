<?php
session_start();
include 'db_connect.php';

// Check if user is logged in and is instructor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Get instructor's courses
$courses_query = "SELECT c.*, 
                  (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as student_count,
                  (SELECT COUNT(*) FROM assessments WHERE course_id = c.id AND type = 'assignment') as assignment_count
                  FROM courses c 
                  WHERE c.instructor_id = $user_id 
                  ORDER BY c.created_at DESC";
$courses_result = pg_query($conn, $courses_query);

// Get recent assignments
$assignments_query = "SELECT a.*, c.title as course_title
                      FROM assessments a 
                      JOIN courses c ON a.course_id = c.id 
                      WHERE a.type = 'assignment' 
                      AND c.instructor_id = $user_id 
                      ORDER BY a.due_date DESC 
                      LIMIT 5";
$assignments_result = pg_query($conn, $assignments_query);

// Get pending submissions to grade
$pending_submissions_query = "SELECT s.*, a.title as assignment_title, c.title as course_title, u.username as student_name
                              FROM submissions s 
                              JOIN assessments a ON s.assessment_id = a.id 
                              JOIN courses c ON a.course_id = c.id 
                              JOIN users u ON s.student_id = u.id 
                              WHERE c.instructor_id = $user_id 
                              AND s.grade IS NULL
                              ORDER BY s.submitted_at ASC 
                              LIMIT 10";
$pending_submissions_result = pg_query($conn, $pending_submissions_query);

// Get course statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM courses WHERE instructor_id = $user_id) as total_courses,
    (SELECT COUNT(*) FROM assessments WHERE type = 'assignment' AND course_id IN (SELECT id FROM courses WHERE instructor_id = $user_id)) as total_assignments,
    (SELECT COUNT(*) FROM submissions WHERE grade IS NULL AND assessment_id IN (SELECT id FROM assessments WHERE course_id IN (SELECT id FROM courses WHERE instructor_id = $user_id))) as pending_grades,
    (SELECT COUNT(*) FROM enrollments WHERE course_id IN (SELECT id FROM courses WHERE instructor_id = $user_id)) as total_students";
$stats_result = pg_query($conn, $stats_query);
$stats = pg_fetch_assoc($stats_result);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cesus - Instructor Dashboard</title>
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

        .welcome-section {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .welcome-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .welcome-subtitle {
            color: #666;
            font-size: 1.1rem;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 2rem;
            color: #00b09b;
            margin-bottom: 1rem;
        }

        .stat-number {
            font-size: 1.8rem;
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

        /* Course Cards */
        .courses-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .section-header {
            background: #f8f9fa;
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
        }

        .section-action {
            color: #00b09b;
            text-decoration: none;
            font-weight: 500;
        }

        .courses-grid {
            padding: 1.5rem;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .course-card {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .course-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .course-header {
            background: linear-gradient(135deg, #00b09b 0%, #96c93d 100%);
            color: white;
            padding: 1.5rem;
        }

        .course-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .course-stats {
            display: flex;
            gap: 1rem;
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .course-content {
            padding: 1.5rem;
        }

        .course-description {
            color: #666;
            margin-bottom: 1rem;
            line-height: 1.5;
        }

        .course-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s;
            display: inline-block;
        }

        .btn-primary {
            background: #00b09b;
            color: white;
        }

        .btn-primary:hover {
            background: #089e8a;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-warning:hover {
            background: #e0a800;
        }

        /* Sidebar */
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .sidebar-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .sidebar-content {
            padding: 1.5rem;
        }

        .item-list {
            list-style: none;
        }

        .item-list li {
            padding: 0.75rem 0;
            border-bottom: 1px solid #e9ecef;
        }

        .item-list li:last-child {
            border-bottom: none;
        }

        .item-title {
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .item-meta {
            font-size: 0.8rem;
            color: #666;
        }

        .item-link {
            color: #00b09b;
            text-decoration: none;
            font-weight: 500;
        }

        .item-link:hover {
            text-decoration: underline;
        }

        .urgent-badge {
            background: #dc3545;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .courses-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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
                Cesus Classroom
            </div>
            <nav class="nav-menu">
                <a href="instructor_courses.php"><i class="fas fa-book"></i> My Courses</a>
                <a href="assignments.php"><i class="fas fa-tasks"></i> Assignments</a>
                <a href="quizzes.php"><i class="fas fa-question-circle"></i> Quizzes</a>
                <a href="messages.php"><i class="fas fa-comments"></i> Messages</a>
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h1 class="welcome-title">Welcome back, <?php echo htmlspecialchars($username); ?>!</h1>
            <p class="welcome-subtitle">Empowering education through innovative digital learning management.</p>

            <!-- System Information -->
            <div
                style="margin-top: 1.5rem; padding: 1.5rem; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 8px;">
                <h3 style="color: #00b09b; margin-bottom: 1rem; font-size: 1.3rem;">
                    <i class="fas fa-graduation-cap"></i> Cesus Learning Management System
                </h3>
                <div
                    style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                    <div style="background: white; padding: 1rem; border-radius: 6px; border-left: 4px solid #00b09b;">
                        <h4 style="color: #333; margin-bottom: 0.5rem; font-size: 1rem;">
                            <i class="fas fa-users"></i> Student Engagement
                        </h4>
                        <p style="color: #666; font-size: 0.9rem; margin: 0;">
                            Track student progress, monitor participation, and provide personalized feedback to enhance
                            learning outcomes.
                        </p>
                    </div>
                    <div style="background: white; padding: 1rem; border-radius: 6px; border-left: 4px solid #96c93d;">
                        <h4 style="color: #333; margin-bottom: 0.5rem; font-size: 1rem;">
                            <i class="fas fa-tasks"></i> Assignment Management
                        </h4>
                        <p style="color: #666; font-size: 0.9rem; margin: 0;">
                            Create, distribute, and grade assignments efficiently with our comprehensive assessment
                            tools.
                        </p>
                    </div>
                    <div style="background: white; padding: 1rem; border-radius: 6px; border-left: 4px solid #ffc107;">
                        <h4 style="color: #333; margin-bottom: 0.5rem; font-size: 1rem;">
                            <i class="fas fa-chart-line"></i> Analytics & Insights
                        </h4>
                        <p style="color: #666; font-size: 0.9rem; margin: 0;">
                            Gain valuable insights into student performance and course effectiveness through detailed
                            analytics.
                        </p>
                    </div>
                </div>

                <!-- Quick Tips for Instructors -->
                <div style="background: white; padding: 1.5rem; border-radius: 8px; margin-top: 1rem;">
                    <h4 style="color: #00b09b; margin-bottom: 1rem; font-size: 1.1rem;">
                        <i class="fas fa-lightbulb"></i> Teaching Tips
                    </h4>
                    <ul style="color: #666; line-height: 1.6; margin: 0; padding-left: 1.5rem;">
                        <li>Use the <strong>Link Checking</strong> feature to validate external resources submitted by
                            students</li>
                        <li>Provide timely feedback to keep students engaged and motivated</li>
                        <li>Create diverse assessment types to accommodate different learning styles</li>
                        <li>Monitor student progress regularly to identify those who need additional support</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-number"><?php echo $stats['total_courses']; ?></div>
                <div class="stat-label">Total Courses</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="stat-number"><?php echo $stats['total_students']; ?></div>
                <div class="stat-label">Total Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="stat-number"><?php echo $stats['total_assignments']; ?></div>
                <div class="stat-label">Total Assignments</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number"><?php echo $stats['pending_grades']; ?></div>
                <div class="stat-label">Pending Grades</div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Courses Section -->
            <div class="courses-section">
                <div class="section-header">
                    <h2 class="section-title">My Courses</h2>
                    <a href="instructor_courses.php" class="section-action">Manage All</a>
                </div>
                <div class="courses-grid">
                    <?php while ($course = pg_fetch_assoc($courses_result)): ?>
                        <div class="course-card">
                            <div class="course-header">
                                <h3 class="course-title"><?php echo htmlspecialchars($course['title']); ?></h3>
                                <div class="course-stats">
                                    <span><i class="fas fa-users"></i> <?php echo $course['student_count']; ?>
                                        students</span>
                                    <span><i class="fas fa-tasks"></i> <?php echo $course['assignment_count']; ?>
                                        assignments</span>
                                </div>
                            </div>
                            <div class="course-content">
                                <p class="course-description">
                                    <?php echo htmlspecialchars(substr($course['description'], 0, 100)) . '...'; ?>
                                </p>
                                <div class="course-actions">
                                    <a href="course_details.php?id=<?php echo $course['id']; ?>"
                                        class="btn btn-primary">View Course</a>
                                    <a href="assignments.php?course=<?php echo $course['id']; ?>"
                                        class="btn btn-secondary">Assignments</a>
                                    <a href="view_submissions.php?course=<?php echo $course['id']; ?>"
                                        class="btn btn-warning">Grade Work</a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="sidebar">
                <!-- Recent Assignments -->
                <div class="sidebar-card">
                    <div class="section-header">
                        <h3 class="section-title">Recent Assignments</h3>
                    </div>
                    <div class="sidebar-content">
                        <ul class="item-list">
                            <?php while ($assignment = pg_fetch_assoc($assignments_result)): ?>
                                <li>
                                    <div class="item-title">
                                        <a href="assignments.php?id=<?php echo $assignment['id']; ?>" class="item-link">
                                            <?php echo htmlspecialchars($assignment['title']); ?>
                                        </a>
                                    </div>
                                    <div class="item-meta">
                                        <?php echo htmlspecialchars($assignment['course_title']); ?> •
                                        Due: <?php echo date('M j, g:i A', strtotime($assignment['due_date'])); ?>
                                    </div>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                </div>

                <!-- Pending Grades -->
                <div class="sidebar-card">
                    <div class="section-header">
                        <h3 class="section-title">Pending Grades</h3>
                        <?php if ($stats['pending_grades'] > 0): ?>
                            <span class="urgent-badge"><?php echo $stats['pending_grades']; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="sidebar-content">
                        <ul class="item-list">
                            <?php while ($submission = pg_fetch_assoc($pending_submissions_result)): ?>
                                <li>
                                    <div class="item-title">
                                        <a href="view_submission.php?id=<?php echo $submission['id']; ?>" class="item-link">
                                            <?php echo htmlspecialchars($submission['assignment_title']); ?>
                                        </a>
                                    </div>
                                    <div class="item-meta">
                                        <?php echo htmlspecialchars($submission['student_name']); ?> •
                                        <?php echo htmlspecialchars($submission['course_title']); ?> •
                                        Submitted: <?php echo date('M j, g:i A', strtotime($submission['submitted_at'])); ?>
                                    </div>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                        <?php if ($stats['pending_grades'] > 0): ?>
                            <div style="margin-top: 1rem; text-align: center;">
                                <a href="view_submissions.php" class="btn btn-warning">View All Pending</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- System Features -->
                <div class="sidebar-card">
                    <div class="section-header">
                        <h3 class="section-title">System Features</h3>
                    </div>
                    <div class="sidebar-content">
                        <div style="background: #f8f9fa; padding: 1rem; border-radius: 6px; margin-bottom: 1rem;">
                            <h4 style="color: #00b09b; font-size: 1rem; margin-bottom: 0.5rem;">
                                <i class="fas fa-link"></i> Link Validation
                            </h4>
                            <p style="color: #666; font-size: 0.9rem; margin: 0;">
                                Check external links submitted by students for accessibility and validity.
                            </p>
                        </div>
                        <div style="background: #f8f9fa; padding: 1rem; border-radius: 6px; margin-bottom: 1rem;">
                            <h4 style="color: #00b09b; font-size: 1rem; margin-bottom: 0.5rem;">
                                <i class="fas fa-edit"></i> Student Editing
                            </h4>
                            <p style="color: #666; font-size: 0.9rem; margin: 0;">
                                Students can edit their submissions before grading for better learning outcomes.
                            </p>
                        </div>
                        <div style="background: #f8f9fa; padding: 1rem; border-radius: 6px;">
                            <h4 style="color: #00b09b; font-size: 1rem; margin-bottom: 0.5rem;">
                                <i class="fas fa-chart-bar"></i> Progress Tracking
                            </h4>
                            <p style="color: #666; font-size: 0.9rem; margin: 0;">
                                Monitor student progress and performance with detailed analytics and reports.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>