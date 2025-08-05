<?php
session_start();
include 'db_connect.php';

// Check if user is logged in and is student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Get student's enrolled courses
$courses_query = "SELECT c.*, u.username as instructor_name 
                  FROM courses c 
                  JOIN users u ON c.instructor_id = u.id 
                  WHERE c.id IN (
                      SELECT course_id FROM enrollments WHERE user_id = $user_id
                  )
                  ORDER BY c.created_at DESC";
$courses_result = pg_query($conn, $courses_query);

// Get upcoming assignments
$assignments_query = "SELECT a.*, c.title as course_title, c.id as course_id
                      FROM assessments a 
                      JOIN courses c ON a.course_id = c.id 
                      WHERE a.type = 'assignment' 
                      AND a.due_date > NOW()
                      AND c.id IN (
                          SELECT course_id FROM enrollments WHERE user_id = $user_id
                      )
                      ORDER BY a.due_date ASC 
                      LIMIT 5";
$assignments_result = pg_query($conn, $assignments_query);

// Get recent submissions
$submissions_query = "SELECT s.*, a.title as assignment_title, c.title as course_title
                      FROM submissions s 
                      JOIN assessments a ON s.assessment_id = a.id 
                      JOIN courses c ON a.course_id = c.id 
                      WHERE s.student_id = $user_id 
                      ORDER BY s.submitted_at DESC 
                      LIMIT 5";
$submissions_result = pg_query($conn, $submissions_query);

// Get quiz results
$quiz_results_query = "SELECT qr.*, q.title as quiz_title, q.category
                       FROM quiz_results qr 
                       JOIN quizzes q ON qr.quiz_id = q.id 
                       WHERE qr.user_id = $user_id 
                       ORDER BY qr.submitted_at DESC 
                       LIMIT 5";
$quiz_results_result = pg_query($conn, $quiz_results_query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cesus - Student Dashboard</title>
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

        .course-instructor {
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

        .grade-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .grade-excellent {
            background: #d4edda;
            color: #155724;
        }

        .grade-good {
            background: #d1ecf1;
            color: #0c5460;
        }

        .grade-average {
            background: #fff3cd;
            color: #856404;
        }

        .grade-poor {
            background: #f8d7da;
            color: #721c24;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .courses-grid {
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
                Cesus Classroom
            </div>
            <nav class="nav-menu">
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
            <p class="welcome-subtitle">Your journey to academic excellence starts here with Cesus LMS.</p>

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
                            <i class="fas fa-book-open"></i> Interactive Learning
                        </h4>
                        <p style="color: #666; font-size: 0.9rem; margin: 0;">
                            Access course materials, submit assignments, and take quizzes in an intuitive digital
                            environment.
                        </p>
                    </div>
                    <div style="background: white; padding: 1rem; border-radius: 6px; border-left: 4px solid #96c93d;">
                        <h4 style="color: #333; margin-bottom: 0.5rem; font-size: 1rem;">
                            <i class="fas fa-edit"></i> Flexible Submissions
                        </h4>
                        <p style="color: #666; font-size: 0.9rem; margin: 0;">
                            Edit your assignments before grading and submit multiple file formats for better learning
                            outcomes.
                        </p>
                    </div>
                    <div style="background: white; padding: 1rem; border-radius: 6px; border-left: 4px solid #ffc107;">
                        <h4 style="color: #333; margin-bottom: 0.5rem; font-size: 1rem;">
                            <i class="fas fa-chart-line"></i> Progress Tracking
                        </h4>
                        <p style="color: #666; font-size: 0.9rem; margin: 0;">
                            Monitor your academic progress with real-time feedback and detailed performance analytics.
                        </p>
                    </div>
                </div>

                <!-- Learning Tips for Students -->
                <div style="background: white; padding: 1.5rem; border-radius: 8px; margin-top: 1rem;">
                    <h4 style="color: #00b09b; margin-bottom: 1rem; font-size: 1.1rem;">
                        <i class="fas fa-lightbulb"></i> Study Success Tips
                    </h4>
                    <ul style="color: #666; line-height: 1.6; margin: 0; padding-left: 1.5rem;">
                        <li>Check your <strong>Upcoming Assignments</strong> regularly to stay on top of deadlines</li>
                        <li>Use the <strong>Edit Submission</strong> feature to improve your work before grading</li>
                        <li>Review your quiz results to identify areas for improvement</li>
                        <li>Communicate with instructors through the messaging system for clarification</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Courses Section -->
            <div class="courses-section">
                <div class="section-header">
                    <h2 class="section-title">My Courses</h2>
                    <a href="student_courses.php" class="section-action">View All</a>
                </div>
                <div class="courses-grid">
                    <?php while ($course = pg_fetch_assoc($courses_result)): ?>
                        <div class="course-card">
                            <div class="course-header">
                                <h3 class="course-title"><?php echo htmlspecialchars($course['title']); ?></h3>
                                <p class="course-instructor">by <?php echo htmlspecialchars($course['instructor_name']); ?>
                                </p>
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
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="sidebar">
                <!-- Upcoming Assignments -->
                <div class="sidebar-card">
                    <div class="section-header">
                        <h3 class="section-title">Upcoming Assignments</h3>
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

                <!-- Recent Submissions -->
                <div class="sidebar-card">
                    <div class="section-header">
                        <h3 class="section-title">Recent Submissions</h3>
                    </div>
                    <div class="sidebar-content">
                        <ul class="item-list">
                            <?php while ($submission = pg_fetch_assoc($submissions_result)): ?>
                                <li>
                                    <div class="item-title">
                                        <?php echo htmlspecialchars($submission['assignment_title']); ?>
                                    </div>
                                    <div class="item-meta">
                                        <?php echo htmlspecialchars($submission['course_title']); ?> •
                                        Submitted: <?php echo date('M j, g:i A', strtotime($submission['submitted_at'])); ?>
                                        <?php if (isset($submission['score']) && $submission['score'] !== null): ?>
                                            <br>
                                            <span class="grade-badge grade-<?php
                                            $percentage = ($submission['score'] / $submission['max_points']) * 100;
                                            if ($percentage >= 90)
                                                echo 'excellent';
                                            elseif ($percentage >= 80)
                                                echo 'good';
                                            elseif ($percentage >= 70)
                                                echo 'average';
                                            else
                                                echo 'poor';
                                            ?>">
                                                Score:
                                                <?php echo $submission['score']; ?>/<?php echo $submission['max_points']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                </div>

                <!-- Quiz Results -->
                <div class="sidebar-card">
                    <div class="section-header">
                        <h3 class="section-title">Recent Quiz Results</h3>
                    </div>
                    <div class="sidebar-content">
                        <ul class="item-list">
                            <?php while ($quiz = pg_fetch_assoc($quiz_results_result)): ?>
                                <li>
                                    <div class="item-title">
                                        <?php echo htmlspecialchars($quiz['quiz_title']); ?>
                                    </div>
                                    <div class="item-meta">
                                        <?php echo htmlspecialchars($quiz['category']); ?> •
                                        <?php echo date('M j, g:i A', strtotime($quiz['submitted_at'])); ?>
                                        <br>
                                        <span class="grade-badge grade-<?php
                                        if ($quiz['percentage'] >= 90)
                                            echo 'excellent';
                                        elseif ($quiz['percentage'] >= 80)
                                            echo 'good';
                                        elseif ($quiz['percentage'] >= 70)
                                            echo 'average';
                                        else
                                            echo 'poor';
                                        ?>">
                                            <?php echo $quiz['percentage']; ?>%
                                        </span>
                                    </div>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                </div>

                <!-- Student Features -->
                <div class="sidebar-card">
                    <div class="section-header">
                        <h3 class="section-title">Student Features</h3>
                    </div>
                    <div class="sidebar-content">
                        <div style="background: #f8f9fa; padding: 1rem; border-radius: 6px; margin-bottom: 1rem;">
                            <h4 style="color: #00b09b; font-size: 1rem; margin-bottom: 0.5rem;">
                                <i class="fas fa-edit"></i> Edit Submissions
                            </h4>
                            <p style="color: #666; font-size: 0.9rem; margin: 0;">
                                Modify your assignments before they're graded to improve your work quality.
                            </p>
                        </div>
                        <div style="background: #f8f9fa; padding: 1rem; border-radius: 6px; margin-bottom: 1rem;">
                            <h4 style="color: #00b09b; font-size: 1rem; margin-bottom: 0.5rem;">
                                <i class="fas fa-file-upload"></i> File Uploads
                            </h4>
                            <p style="color: #666; font-size: 0.9rem; margin: 0;">
                                Submit multiple file formats including PDF, DOC, ZIP, and more for comprehensive
                                assignments.
                            </p>
                        </div>
                        <div style="background: #f8f9fa; padding: 1rem; border-radius: 6px;">
                            <h4 style="color: #00b09b; font-size: 1rem; margin-bottom: 0.5rem;">
                                <i class="fas fa-comments"></i> Direct Communication
                            </h4>
                            <p style="color: #666; font-size: 0.9rem; margin: 0;">
                                Message instructors directly for questions, clarifications, and academic support.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>