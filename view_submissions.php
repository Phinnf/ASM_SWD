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

// Get assignment ID from URL
$assignment_id = isset($_GET['assignment_id']) ? (int) $_GET['assignment_id'] : 0;

if (!$assignment_id) {
    header('Location: assignments.php');
    exit;
}

// Get assignment details
$assignment_query = "SELECT a.*, c.title as course_title, c.course_code, c.instructor_id
                    FROM assessments a 
                    JOIN courses c ON a.course_id = c.id 
                    WHERE a.id = $assignment_id AND a.type = 'assignment'";
$assignment_result = pg_query($conn, $assignment_query);

if (!$assignment_result || pg_num_rows($assignment_result) == 0) {
    header('Location: assignments.php');
    exit;
}

$assignment = pg_fetch_assoc($assignment_result);

// Check if user has permission to view this assignment's submissions
$can_view = false;
if ($role === 'instructor') {
    $can_view = $assignment['instructor_id'] == $user_id;
} else {
    // Students can only view their own submissions
    $student_check = "SELECT 1 FROM enrollments WHERE user_id = $user_id AND course_id = {$assignment['course_id']}";
    $student_result = pg_query($conn, $student_check);
    $can_view = pg_num_rows($student_result) > 0;
}

if (!$can_view) {
    header('Location: assignments.php');
    exit;
}

// Get submissions for this assignment
if ($role === 'instructor') {
    $submissions_query = "SELECT s.*, u.username as student_name, u.email as student_email
                         FROM submissions s 
                         JOIN users u ON s.student_id = u.id 
                         WHERE s.assessment_id = $assignment_id 
                         ORDER BY s.submitted_at DESC";
} else {
    $submissions_query = "SELECT s.*, u.username as student_name, u.email as student_email
                         FROM submissions s 
                         JOIN users u ON s.student_id = u.id 
                         WHERE s.assessment_id = $assignment_id AND s.student_id = $user_id 
                         ORDER BY s.submitted_at DESC";
}

$submissions = pg_query($conn, $submissions_query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Assignment Submissions - LMS</title>
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

        .logo {
            display: flex;
            align-items: center;
        }

        .logo img {
            height: 40px;
            margin-right: 20px;
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

        .assignment-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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

        .submissions-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .submissions-table th,
        .submissions-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        .submissions-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #00b09b;
        }

        .submissions-table tr:hover {
            background: #f8f9fa;
        }

        .grade-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .grade-excellent {
            background: #d4edda;
            color: #155724;
        }

        .grade-good {
            background: #fff3cd;
            color: #856404;
        }

        .grade-poor {
            background: #f8d7da;
            color: #721c24;
        }

        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.8rem;
        }

        .status-submitted {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-graded {
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .btn-gradient {
            background: linear-gradient(135deg, #00b09b, #96c93d);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            font-weight: 600;
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

        .submission-text {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .no-submissions {
            text-align: center;
            padding: 3rem;
            color: #666;
        }

        .no-submissions i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #ccc;
        }

        @media (max-width: 768px) {
            .assignment-info {
                grid-template-columns: 1fr;
            }

            .section-card {
                padding: 1.5rem;
            }

            .submissions-table {
                font-size: 0.9rem;
            }

            .submissions-table th,
            .submissions-table td {
                padding: 0.5rem;
            }
        }
    </style>
</head>

<body>
    <div class="menu-bar">
        <div class="logo">
            <img src="assets/logo.png" alt="Logo" style="height: 40px; margin-right: 20px;">
        </div>
        <a href="main.php"><i class="fa fa-home"></i> Home</a>
        <a href="my_courses.php"><i class="fa fa-book"></i> My Courses</a>
        <a href="quizzes.php"><i class="fa fa-question-circle"></i> Quizzes</a>
        <a href="assignments.php"><i class="fa fa-file-alt"></i> Assignments</a>
        <a href="messages.php"><i class="fa fa-envelope"></i> Messages</a>
        <a href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="container">
        <!-- Assignment Details -->
        <div class="section-card">
            <div class="section-header">
                <h2><i class="fa fa-file-alt"></i> Assignment Submissions</h2>
                <a href="assignments.php" class="btn-secondary">
                    <i class="fa fa-arrow-left"></i> Back to Assignments
                </a>
            </div>

            <div class="assignment-info">
                <div class="info-card">
                    <div class="info-label">Assignment</div>
                    <div class="info-value"><?php echo htmlspecialchars($assignment['title']); ?></div>
                </div>
                <div class="info-card">
                    <div class="info-label">Course</div>
                    <div class="info-value"><?php echo htmlspecialchars($assignment['course_code']); ?> -
                        <?php echo htmlspecialchars($assignment['course_title']); ?></div>
                </div>
                <div class="info-card">
                    <div class="info-label">Due Date</div>
                    <div class="info-value"><?php echo date('M j, Y', strtotime($assignment['due_date'])); ?></div>
                </div>
                <div class="info-card">
                    <div class="info-label">Max Points</div>
                    <div class="info-value"><?php echo $assignment['max_points']; ?></div>
                </div>
            </div>

            <!-- Submissions Table -->
            <?php if ($submissions && pg_num_rows($submissions) > 0): ?>
                <table class="submissions-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Submitted</th>
                            <th>Submission</th>
                            <th>Grade</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($submission = pg_fetch_assoc($submissions)): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($submission['student_name']); ?></strong><br>
                                    <small
                                        style="color: #666;"><?php echo htmlspecialchars($submission['student_email']); ?></small>
                                </td>
                                <td><?php echo date('M j, Y g:i A', strtotime($submission['submitted_at'])); ?></td>
                                <td>
                                    <div class="submission-text"
                                        title="<?php echo htmlspecialchars($submission['submission_text']); ?>">
                                        <?php echo htmlspecialchars(substr($submission['submission_text'], 0, 100)); ?>
                                        <?php if (strlen($submission['submission_text']) > 100): ?>...<?php endif; ?>
                                    </div>
                                    
                                    <?php if ($role === 'instructor'): ?>
                                        <?php
                                        // Check for uploaded files in submission text
                                        $text_parts = explode('--- UPLOADED FILES ---', $submission['submission_text']);
                                        if (count($text_parts) > 1) {
                                            $files_section = $text_parts[1];
                                            $files = explode("\n", trim($files_section));
                                            $uploaded_files = [];
                                            
                                            foreach ($files as $file_line) {
                                                if (strpos($file_line, 'File:') === 0) {
                                                    $file_info = trim(substr($file_line, 5));
                                                    if (strpos($file_info, '|') !== false) {
                                                        list($file_name, $stored_filename) = explode('|', $file_info, 2);
                                                        if ($stored_filename) {
                                                            $uploaded_files[] = [
                                                                'name' => $file_name,
                                                                'stored' => $stored_filename
                                                            ];
                                                        }
                                                    }
                                                }
                                            }
                                            
                                            if (!empty($uploaded_files)): ?>
                                                <div style="margin-top: 0.5rem;">
                                                    <small style="color: #666; display: block; margin-bottom: 0.3rem;">
                                                        <i class="fa fa-paperclip"></i> Uploaded Files:
                                                    </small>
                                                    <?php foreach ($uploaded_files as $file): ?>
                                                        <a href="download_submission.php?file=<?php echo urlencode($file['stored']); ?>&submission_id=<?php echo $submission['id']; ?>"
                                                           class="btn-secondary"
                                                           style="text-decoration: none; padding: 0.2rem 0.5rem; font-size: 0.75rem; margin-right: 0.3rem; margin-bottom: 0.2rem; display: inline-block;">
                                                            <i class="fa fa-download"></i> <?php echo htmlspecialchars(substr($file['name'], 0, 20)); ?>
                                                            <?php if (strlen($file['name']) > 20): ?>...<?php endif; ?>
                                                        </a>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php } ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($submission['grade'] !== null): ?>
                                        <span class="grade-badge <?php
                                        echo $submission['grade'] >= 80 ? 'grade-excellent' :
                                            ($submission['grade'] >= 60 ? 'grade-good' : 'grade-poor');
                                        ?>">
                                            <?php echo $submission['grade']; ?>/<?php echo $assignment['max_points']; ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #666;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($submission['grade'] !== null): ?>
                                        <span class="status-badge status-graded">Graded</span>
                                    <?php else: ?>
                                        <span class="status-badge status-pending">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="view_submission.php?id=<?php echo $submission['id']; ?>" class="btn-gradient">
                                        <i class="fa fa-eye"></i> View
                                    </a>
                                    <?php if ($role === 'instructor' && $submission['grade'] === null): ?>
                                        <a href="view_submission.php?id=<?php echo $submission['id']; ?>" class="btn-secondary"
                                            style="margin-left: 0.5rem;">
                                            <i class="fa fa-star"></i> Grade
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-submissions">
                    <i class="fa fa-file-alt"></i>
                    <h3>No submissions found</h3>
                    <p>No students have submitted this assignment yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>