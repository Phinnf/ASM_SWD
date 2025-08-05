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

// Get submission ID from URL
$submission_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$submission_id) {
    header('Location: assignments.php');
    exit;
}

// Get submission details
$submission_query = "SELECT s.*, a.title as assignment_title, a.description as assignment_description, 
                           a.max_points, a.due_date, c.title as course_title, c.course_code, c.id as course_id,
                           u.username as student_name, u.email as student_email
                    FROM submissions s 
                    JOIN assessments a ON s.assessment_id = a.id 
                    JOIN courses c ON a.course_id = c.id 
                    JOIN users u ON s.student_id = u.id 
                    WHERE s.id = $submission_id";

$submission_result = pg_query($conn, $submission_query);

if (!$submission_result || pg_num_rows($submission_result) == 0) {
    header('Location: assignments.php');
    exit;
}

$submission = pg_fetch_assoc($submission_result);

// Check if user has permission to view this submission
$can_view = false;
if ($role === 'instructor') {
    // Check if instructor teaches this course
    $instructor_check = "SELECT 1 FROM courses WHERE id = {$submission['course_id']} AND instructor_id = $user_id";
    $instructor_result = pg_query($conn, $instructor_check);
    $can_view = pg_num_rows($instructor_result) > 0;
} else {
    // Check if student owns this submission
    $can_view = $submission['student_id'] == $user_id;
}

if (!$can_view) {
    header('Location: assignments.php');
    exit;
}

// Handle grading (instructors only)
$grade_msg = '';
if (isset($_POST['grade_submission']) && $role === 'instructor') {
    $grade = (float) $_POST['grade'];
    $feedback = pg_escape_string($conn, trim($_POST['feedback']));

    $query = "UPDATE submissions SET grade = $grade, feedback = '$feedback', graded_at = NOW() 
              WHERE id = $submission_id";
    $res = pg_query($conn, $query);

    if ($res) {
        $grade_msg = 'Grade submitted successfully!';
        // Refresh submission data
        $submission_result = pg_query($conn, $submission_query);
        $submission = pg_fetch_assoc($submission_result);
    } else {
        $grade_msg = 'Error submitting grade.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>View Submission - LMS</title>
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
            max-width: 1000px;
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

        .submission-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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

        .submission-content {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid #e0e0e0;
        }

        .submission-text {
            white-space: pre-wrap;
            line-height: 1.6;
            color: #333;
        }

        .grade-section {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .grade-display {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .grade-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 700;
            font-size: 1.1rem;
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

        .feedback-box {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
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

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: #00b09b;
            outline: none;
            box-shadow: 0 0 0 2px #00b09b22;
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

        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.8rem;
        }

        .status-graded {
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        @media (max-width: 768px) {
            .submission-info {
                grid-template-columns: 1fr;
            }

            .section-card {
                padding: 1.5rem;
            }
        }

        .link-status {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.8rem;
        }

        .link-status.valid {
            background: #d4edda;
            color: #155724;
        }

        .link-status.invalid {
            background: #f8d7da;
            color: #721c24;
        }

        .link-status.checking {
            background: #fff3cd;
            color: #856404;
        }
    </style>
</head>

<body>
    <div class="menu-bar">
        <div class="logo">
            <img src="assets/logo.png" alt="LMS Logo">
            <span>LMS</span>
        </div>
        <a href="main.php"><i class="fa fa-home"></i> Home</a>
        <a href="my_courses.php"><i class="fa fa-book"></i> My Courses</a>
        <a href="quizzes.php"><i class="fa fa-question-circle"></i> Quizzes</a>
        <a href="assignments.php"><i class="fa fa-file-alt"></i> Assignments</a>
        <a href="messages.php"><i class="fa fa-envelope"></i> Messages</a>
        <a href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="container">
        <?php if ($grade_msg): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($grade_msg); ?></div>
        <?php endif; ?>

        <!-- Assignment Details -->
        <div class="section-card">
            <div class="section-header">
                <h2><i class="fa fa-file-alt"></i> Assignment Submission</h2>
                <div style="display: flex; gap: 0.5rem;">
                    <?php if ($role === 'student' && $submission['grade'] === null): ?>
                        <a href="edit_submission.php?id=<?php echo $submission_id; ?>" class="btn-gradient"
                            style="text-decoration: none;">
                            <i class="fa fa-edit"></i> Edit Submission
                        </a>
                    <?php endif; ?>
                    <a href="assignments.php" class="btn-secondary">
                        <i class="fa fa-arrow-left"></i> Back to Assignments
                    </a>
                </div>
            </div>

            <div class="submission-info">
                <div class="info-card">
                    <div class="info-label">Assignment</div>
                    <div class="info-value"><?php echo htmlspecialchars($submission['assignment_title']); ?></div>
                </div>
                <div class="info-card">
                    <div class="info-label">Course</div>
                    <div class="info-value"><?php echo htmlspecialchars($submission['course_code']); ?> -
                        <?php echo htmlspecialchars($submission['course_title']); ?>
                    </div>
                </div>
                <div class="info-card">
                    <div class="info-label">Student</div>
                    <div class="info-value"><?php echo htmlspecialchars($submission['student_name']); ?></div>
                </div>
                <div class="info-card">
                    <div class="info-label">Submitted</div>
                    <div class="info-value"><?php echo date('M j, Y g:i A', strtotime($submission['submitted_at'])); ?>
                    </div>
                </div>
                <div class="info-card">
                    <div class="info-label">Due Date</div>
                    <div class="info-value"><?php echo date('M j, Y', strtotime($submission['due_date'])); ?></div>
                </div>
                <div class="info-card">
                    <div class="info-label">Max Points</div>
                    <div class="info-value"><?php echo $submission['max_points']; ?></div>
                </div>
            </div>

            <!-- Assignment Description -->
            <div class="section-card" style="margin-bottom: 2rem;">
                <h3 style="color: #00b09b; margin-bottom: 1rem;">Assignment Description</h3>
                <div class="submission-content">
                    <div class="submission-text"><?php echo htmlspecialchars($submission['assignment_description']); ?>
                    </div>
                </div>
            </div>

            <!-- Student Submission -->
            <div class="section-card" style="margin-bottom: 2rem;">
                <h3 style="color: #00b09b; margin-bottom: 1rem;">Student Submission</h3>
                <div class="submission-content">
                    <?php
                    $submission_text = $submission['submission_text'];
                    $text_parts = explode('--- UPLOADED FILES ---', $submission_text);
                    $main_text = trim($text_parts[0]);

                    // Extract links from submission text
                    $links = [];
                    preg_match_all('/https?:\/\/[^\s<>"]+/', $main_text, $matches);
                    if (!empty($matches[0])) {
                        $links = $matches[0];
                    }
                    ?>
                    <div class="submission-text"><?php echo htmlspecialchars($main_text); ?></div>

                    <?php if (!empty($links) && $role === 'instructor'): ?>
                        <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e0e0e0;">
                            <h4 style="color: #00b09b; margin-bottom: 1rem;">
                                <i class="fa fa-link"></i> Links Found in Submission
                            </h4>
                            <div id="links-container">
                                <?php foreach ($links as $index => $link): ?>
                                    <div class="link-item"
                                        style="display: flex; align-items: center; padding: 0.8rem; background: #f8f9fa; border-radius: 8px; margin-bottom: 0.8rem; border: 1px solid #e0e0e0;">
                                        <div style="flex-grow: 1;">
                                            <div style="font-weight: 600; color: #333; margin-bottom: 0.3rem;">
                                                Link <?php echo $index + 1; ?>
                                            </div>
                                            <div style="color: #00b09b; word-break: break-all; font-size: 0.9rem;">
                                                <a href="<?php echo htmlspecialchars($link); ?>" target="_blank"
                                                    style="color: #00b09b; text-decoration: none;">
                                                    <?php echo htmlspecialchars($link); ?>
                                                </a>
                                            </div>
                                        </div>
                                        <div style="margin-left: 1rem;">
                                            <button type="button" class="btn-secondary" style="margin-right: 0.5rem;"
                                                onclick="checkLink('<?php echo htmlspecialchars($link); ?>', <?php echo $index; ?>)">
                                                <i class="fa fa-external-link-alt"></i> Check Link
                                            </button>
                                            <span id="link-status-<?php echo $index; ?>" class="link-status"></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (count($text_parts) > 1): ?>
                        <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e0e0e0;">
                            <h4 style="color: #00b09b; margin-bottom: 1rem;">
                                <i class="fa fa-paperclip"></i> Uploaded Files
                            </h4>

                            <?php
                            $files_section = $text_parts[1];
                            $files = explode("\n", trim($files_section));



                            foreach ($files as $file_line):
                                if (strpos($file_line, 'File:') === 0):
                                    $file_info = trim(substr($file_line, 5));
                                    // Check if file info contains the pipe separator (original_name|stored_name)
                                    if (strpos($file_info, '|') !== false) {
                                        list($file_name, $stored_filename) = explode('|', $file_info, 2);
                                    } else {
                                        $file_name = $file_info;
                                        $stored_filename = '';
                                    }
                                    ?>
                                    <div
                                        style="display: flex; align-items: center; padding: 0.8rem; background: #f8f9fa; border-radius: 6px; margin-bottom: 0.5rem; border: 1px solid #e0e0e0;">
                                        <i class="fa fa-file" style="color: #00b09b; margin-right: 0.5rem;"></i>
                                        <span style="flex-grow: 1;"><?php echo htmlspecialchars($file_name); ?></span>
                                        <span style="color: #666; font-size: 0.9rem; margin-right: 1rem;">Uploaded</span>
                                        <?php if ($role === 'instructor'): ?>
                                            <?php if ($stored_filename): ?>
                                                <a href="download_submission.php?file=<?php echo urlencode($stored_filename); ?>&submission_id=<?php echo $submission_id; ?>"
                                                    class="btn-gradient"
                                                    style="text-decoration: none; padding: 0.3rem 0.8rem; font-size: 0.8rem;">
                                                    <i class="fa fa-download"></i> Download
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php
                                endif;
                            endforeach;
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Grade Section -->
            <?php if ($submission['grade'] !== null): ?>
                <div class="grade-section">
                    <h3 style="color: #856404; margin-bottom: 1rem;">Grade & Feedback</h3>
                    <div class="grade-display">
                        <span class="grade-badge <?php
                        echo $submission['grade'] >= 80 ? 'grade-excellent' :
                            ($submission['grade'] >= 60 ? 'grade-good' : 'grade-poor');
                        ?>">
                            <?php echo $submission['grade']; ?>/<?php echo $submission['max_points']; ?>
                        </span>
                        <span class="status-badge status-graded">Graded</span>
                        <span style="color: #666;">
                            <?php echo date('M j, Y g:i A', strtotime($submission['graded_at'])); ?>
                        </span>
                    </div>
                    <?php if ($submission['feedback']): ?>
                        <div class="feedback-box">
                            <strong>Feedback:</strong><br>
                            <?php echo htmlspecialchars($submission['feedback']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="grade-section">
                    <h3 style="color: #856404; margin-bottom: 1rem;">Grade & Feedback</h3>
                    <span class="status-badge status-pending">Not Graded</span>
                </div>
            <?php endif; ?>

            <!-- Grade Form (Instructors only) -->
            <?php if ($role === 'instructor' && $submission['grade'] === null): ?>
                <div class="section-card">
                    <h3 style="color: #00b09b; margin-bottom: 1rem;">Grade This Submission</h3>
                    <form method="post">
                        <div class="form-group">
                            <label>Grade (out of <?php echo $submission['max_points']; ?>)</label>
                            <input type="number" name="grade" min="0" max="<?php echo $submission['max_points']; ?>"
                                step="0.1" required>
                        </div>
                        <div class="form-group">
                            <label>Feedback</label>
                            <textarea name="feedback" rows="4" placeholder="Provide feedback for the student..."></textarea>
                        </div>
                        <button type="submit" name="grade_submission" class="btn-gradient">
                            <i class="fa fa-star"></i> Submit Grade
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function checkLink(url, index) {
            const statusElement = document.getElementById(`link-status-${index}`);
            statusElement.textContent = 'Checking...';
            statusElement.className = 'link-status checking';

            // Create a proxy endpoint to check the link
            fetch('check_link.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `url=${encodeURIComponent(url)}`
            })
                .then(response => response.json())
                .then(data => {
                    if (data.valid) {
                        statusElement.textContent = 'Valid';
                        statusElement.className = 'link-status valid';
                    } else {
                        statusElement.textContent = 'Invalid';
                        statusElement.className = 'link-status invalid';
                    }
                })
                .catch(error => {
                    statusElement.textContent = 'Error';
                    statusElement.className = 'link-status invalid';
                    console.error('Error checking link:', error);
                });
        }
    </script>
</body>

</html>