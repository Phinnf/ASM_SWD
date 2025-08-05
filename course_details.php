<?php
session_start();
include 'db_connect.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$username = $_SESSION['username'];

// Get course ID from URL
$course_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$course_id) {
    header('Location: student_dashboard.php');
    exit;
}

// Get course details
$course_query = "SELECT c.*, u.username as instructor_name, u.email as instructor_email
                 FROM courses c 
                 JOIN users u ON c.instructor_id = u.id 
                 WHERE c.id = $course_id";
$course_result = pg_query($conn, $course_query);

if (!$course_result || pg_num_rows($course_result) == 0) {
    header('Location: student_dashboard.php');
    exit;
}

$course = pg_fetch_assoc($course_result);

// Check if student is enrolled in this course
$enrollment_check = "SELECT 1 FROM enrollments WHERE course_id = $course_id AND user_id = $user_id";
$enrollment_result = pg_query($conn, $enrollment_check);

if (pg_num_rows($enrollment_result) == 0) {
    header('Location: student_dashboard.php');
    exit;
}

// Get course materials
$materials_query = "SELECT * FROM materials WHERE course_id = $course_id ORDER BY uploaded_at DESC";
$materials_result = pg_query($conn, $materials_query);

// Get course assignments
$assignments_query = "SELECT * FROM assessments WHERE course_id = $course_id AND type = 'assignment' ORDER BY due_date ASC";
$assignments_result = pg_query($conn, $assignments_query);

// Get course quizzes
$quizzes_query = "SELECT * FROM assessments WHERE course_id = $course_id AND type = 'quiz' ORDER BY due_date ASC";
$quizzes_result = pg_query($conn, $quizzes_query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($course['title']); ?> - Course Details</title>
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

        .menu-bar .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-right: 2rem;
        }

        .menu-bar .logo img {
            height: 35px;
            width: auto;
        }

        .menu-bar .logo span {
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
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

        .section-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 4px 24px rgba(0, 176, 155, 0.10);
            padding: 2.5rem;
            margin-bottom: 2rem;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .section-header h2 {
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
            color: #00b09b;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-gradient {
            background: linear-gradient(135deg, #00b09b, #96c93d);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0.6rem 1.5rem;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-gradient:hover {
            background: linear-gradient(135deg, #96c93d, #00b09b);
        }

        .btn-secondary {
            background: #f8f9fa;
            color: #00b09b;
            border: 2px solid #00b09b;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-secondary:hover {
            background: #00b09b;
            color: white;
        }

        .course-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .info-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1.5rem;
            border-left: 4px solid #00b09b;
        }

        .info-label {
            font-weight: 600;
            color: #666;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .info-value {
            font-size: 1.1rem;
            font-weight: 700;
            color: #333;
        }

        .course-description {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid #e0e0e0;
            line-height: 1.6;
            color: #333;
        }

        .materials-grid,
        .assignments-grid,
        .quizzes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .item-card {
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            padding: 1.5rem;
            transition: transform 0.2s, box-shadow 0.2s;
            border: 1px solid #e0e0e0;
        }

        .item-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0, 176, 155, 0.15);
        }

        .item-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #00b09b;
            margin-bottom: 0.5rem;
        }

        .item-meta {
            display: flex;
            gap: 1rem;
            margin: 0.5rem 0;
            font-size: 0.9rem;
            color: #666;
        }

        .item-description {
            color: #666;
            margin: 0.5rem 0;
            line-height: 1.4;
        }

        .item-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.8rem;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-submitted {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-overdue {
            background: #f8d7da;
            color: #721c24;
        }

        .empty-state {
            text-align: center;
            color: #666;
            padding: 3rem;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #ccc;
        }

        @media (max-width: 768px) {

            .materials-grid,
            .assignments-grid,
            .quizzes-grid {
                grid-template-columns: 1fr;
            }

            .section-card {
                padding: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <div class="menu-bar">
        <div class="logo">
            <img src="assets/logo.png" alt="Logo">
            <span>EduLearn</span>
        </div>
        <a href="main.php"><i class="fa fa-home"></i> Home</a>
        <a href="my_courses.php"><i class="fa fa-book"></i> My Courses</a>
        <a href="quizzes.php"><i class="fa fa-question-circle"></i> Quizzes</a>
        <a href="assignments.php"><i class="fa fa-file-alt"></i> Assignments</a>
        <a href="messages.php"><i class="fa fa-envelope"></i> Messages</a>
        <a href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="container">
        <!-- Course Header -->
        <div class="section-card">
            <div class="section-header">
                <h2><i class="fa fa-book"></i> <?php echo htmlspecialchars($course['title']); ?></h2>
                <a href="student_dashboard.php" class="btn-secondary">
                    <i class="fa fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>

            <div class="course-info">
                <div class="info-card">
                    <div class="info-label">Course Code</div>
                    <div class="info-value"><?php echo htmlspecialchars($course['course_code']); ?></div>
                </div>
                <div class="info-card">
                    <div class="info-label">Instructor</div>
                    <div class="info-value"><?php echo htmlspecialchars($course['instructor_name']); ?></div>
                </div>
                <div class="info-card">
                    <div class="info-label">Email</div>
                    <div class="info-value"><?php echo htmlspecialchars($course['instructor_email']); ?></div>
                </div>
                <div class="info-card">
                    <div class="info-label">Created</div>
                    <div class="info-value"><?php echo date('M j, Y', strtotime($course['created_at'])); ?></div>
                </div>
            </div>

            <div class="course-description">
                <h3 style="color: #00b09b; margin-bottom: 1rem;">Course Description</h3>
                <?php echo htmlspecialchars($course['description']); ?>
            </div>
        </div>

        <!-- Course Materials -->
        <div class="section-card">
            <div class="section-header">
                <h2><i class="fa fa-file-alt"></i> Course Materials</h2>
            </div>

            <?php if ($materials_result && pg_num_rows($materials_result) > 0): ?>
                <div class="materials-grid">
                    <?php while ($material = pg_fetch_assoc($materials_result)): ?>
                        <div class="item-card">
                            <div class="item-title"><?php echo htmlspecialchars($material['title']); ?></div>
                            <div class="item-meta">
                                <span><i class="fa fa-calendar"></i>
                                    <?php echo date('M j, Y', strtotime($material['uploaded_at'])); ?></span>
                            </div>
                            <div class="item-description"><?php echo htmlspecialchars($material['description']); ?></div>
                            <div class="item-actions">
                                <?php if ($material['file_path']): ?>
                                    <a href="<?php echo htmlspecialchars($material['file_path']); ?>" class="btn-gradient"
                                        target="_blank">
                                        <i class="fa fa-download"></i> Download
                                    </a>
                                <?php endif; ?>
                                <?php if ($material['url']): ?>
                                    <a href="<?php echo htmlspecialchars($material['url']); ?>" class="btn-secondary"
                                        target="_blank">
                                        <i class="fa fa-external-link-alt"></i> View Link
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fa fa-file-alt"></i>
                    <p>No course materials available yet.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Course Assignments -->
        <div class="section-card">
            <div class="section-header">
                <h2><i class="fa fa-tasks"></i> Course Assignments</h2>
                <a href="assignments.php?course=<?php echo $course_id; ?>" class="btn-gradient">
                    <i class="fa fa-eye"></i> View All Assignments
                </a>
            </div>

            <?php if ($assignments_result && pg_num_rows($assignments_result) > 0): ?>
                <div class="assignments-grid">
                    <?php while ($assignment = pg_fetch_assoc($assignments_result)): ?>
                        <?php
                        $due_date = new DateTime($assignment['due_date']);
                        $now = new DateTime();
                        $is_overdue = $due_date < $now;
                        ?>
                        <div class="item-card">
                            <div class="item-title"><?php echo htmlspecialchars($assignment['title']); ?></div>
                            <div class="item-meta">
                                <span><i class="fa fa-calendar"></i> Due:
                                    <?php echo date('M j, Y', strtotime($assignment['due_date'])); ?></span>
                                <span><i class="fa fa-star"></i> <?php echo $assignment['max_points']; ?> points</span>
                            </div>
                            <div class="item-description"><?php echo htmlspecialchars($assignment['description']); ?></div>
                            <div class="item-actions">
                                <a href="assignments.php?id=<?php echo $assignment['id']; ?>" class="btn-gradient">
                                    <i class="fa fa-eye"></i> View Assignment
                                </a>
                                <?php if ($is_overdue): ?>
                                    <span class="status-badge status-overdue">Overdue</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fa fa-tasks"></i>
                    <p>No assignments available for this course.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Course Quizzes -->
        <div class="section-card">
            <div class="section-header">
                <h2><i class="fa fa-question-circle"></i> Course Quizzes</h2>
                <a href="quizzes.php?course=<?php echo $course_id; ?>" class="btn-gradient">
                    <i class="fa fa-eye"></i> View All Quizzes
                </a>
            </div>

            <?php if ($quizzes_result && pg_num_rows($quizzes_result) > 0): ?>
                <div class="quizzes-grid">
                    <?php while ($quiz = pg_fetch_assoc($quizzes_result)): ?>
                        <div class="item-card">
                            <div class="item-title"><?php echo htmlspecialchars($quiz['title']); ?></div>
                            <div class="item-meta">
                                <span><i class="fa fa-calendar"></i>
                                    <?php echo date('M j, Y', strtotime($quiz['due_date'])); ?></span>
                                <span><i class="fa fa-star"></i> <?php echo $quiz['max_points']; ?> points</span>
                            </div>
                            <div class="item-description"><?php echo htmlspecialchars($quiz['description']); ?></div>
                            <div class="item-actions">
                                <a href="quizzes.php?id=<?php echo $quiz['id']; ?>" class="btn-gradient">
                                    <i class="fa fa-play"></i> Take Quiz
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fa fa-question-circle"></i>
                    <p>No quizzes available for this course.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>