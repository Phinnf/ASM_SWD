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

// Handle assignment creation (instructors only)
$assignment_msg = '';
if (isset($_POST['create_assignment']) && $role === 'instructor') {
    $title = pg_escape_string($conn, trim($_POST['assignment_title']));
    $description = pg_escape_string($conn, trim($_POST['assignment_description']));
    $course_id = (int) $_POST['course_id'];
    $due_date = pg_escape_string($conn, trim($_POST['due_date']));
    $max_points = (int) $_POST['max_points'];

    $query = "INSERT INTO assessments (course_id, title, description, due_date, type, max_points) 
              VALUES ($course_id, '$title', '$description', '$due_date', 'assignment', $max_points)";
    $res = pg_query($conn, $query);

    if ($res) {
        $assignment_msg = 'Assignment created successfully!';
    } else {
        $assignment_msg = 'Error creating assignment.';
    }
}

// Handle assignment submission
$submission_msg = '';
if (isset($_POST['submit_assignment'])) {
    $assessment_id = (int) $_POST['assessment_id'];
    $submission_text = pg_escape_string($conn, trim($_POST['submission_text']));

    // Check if already submitted
    $check_query = "SELECT id FROM submissions WHERE assessment_id = $assessment_id AND student_id = $user_id";
    $check_result = pg_query($conn, $check_query);

    if (pg_num_rows($check_result) > 0) {
        $submission_msg = 'You have already submitted this assignment.';
    } else {
        // Handle file uploads
        $uploaded_files = [];
        if (isset($_FILES['submission_files']) && !empty($_FILES['submission_files']['name'][0])) {
            $upload_dir = 'uploads/assignments/';

            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            foreach ($_FILES['submission_files']['tmp_name'] as $key => $tmp_name) {
                $file_name = $_FILES['submission_files']['name'][$key];
                $file_size = $_FILES['submission_files']['size'][$key];
                $file_error = $_FILES['submission_files']['error'][$key];

                // Validate file size (10MB limit)
                if ($file_size > 10 * 1024 * 1024) {
                    $submission_msg = "File '$file_name' is too large. Maximum size is 10MB.";
                    break;
                }

                // Validate file type
                $allowed_types = ['pdf', 'doc', 'docx', 'txt', 'zip', 'rar'];
                $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                if (!in_array($file_extension, $allowed_types)) {
                    $submission_msg = "File '$file_name' is not a supported format.";
                    break;
                }

                // Generate unique filename
                $unique_filename = time() . '_' . $user_id . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file_name);
                $file_path = $upload_dir . $unique_filename;

                if (move_uploaded_file($tmp_name, $file_path)) {
                    $uploaded_files[] = $file_name . '|' . $unique_filename;
                } else {
                    $submission_msg = "Error uploading file '$file_name'.";
                    break;
                }
            }
        }

        if (empty($submission_msg)) {
            // Add file information to submission text
            $final_submission_text = $submission_text;
            if (!empty($uploaded_files)) {
                $final_submission_text .= "\n\n--- UPLOADED FILES ---\n";
                foreach ($uploaded_files as $file_info) {
                    list($original_name, $stored_name) = explode('|', $file_info);
                    $final_submission_text .= "File: $original_name|$stored_name\n";
                }
            }

            $final_submission_text = pg_escape_string($conn, $final_submission_text);

            $query = "INSERT INTO submissions (assessment_id, student_id, submission_text, submitted_at) 
                      VALUES ($assessment_id, $user_id, '$final_submission_text', NOW())";
            $res = pg_query($conn, $query);

            $submission_msg = $res ? 'Assignment submitted successfully!' : 'Error submitting assignment.';
        }
    }
}

// Handle grading (instructors only)
$grade_msg = '';
if (isset($_POST['grade_submission']) && $role === 'instructor') {
    $submission_id = (int) $_POST['submission_id'];
    $grade = (float) $_POST['grade'];
    $feedback = pg_escape_string($conn, trim($_POST['feedback']));

    $query = "UPDATE submissions SET grade = $grade, feedback = '$feedback', graded_at = NOW() 
              WHERE id = $submission_id";
    $res = pg_query($conn, $query);

    $grade_msg = $res ? 'Grade submitted successfully!' : 'Error submitting grade.';
}

// Get assignments based on user role
if ($role === 'instructor') {
    // Get assignments created by instructor
    $assignments_query = "SELECT a.*, c.title as course_title, c.course_code,
                         (SELECT COUNT(*) FROM submissions WHERE assessment_id = a.id) as submission_count
                         FROM assessments a 
                         JOIN courses c ON a.course_id = c.id 
                         WHERE a.type = 'assignment' AND c.instructor_id = $user_id 
                         ORDER BY a.due_date DESC";
} else {
    // Get assignments for enrolled courses
    $assignments_query = "SELECT a.*, c.title as course_title, c.course_code,
                         (SELECT COUNT(*) FROM submissions WHERE assessment_id = a.id AND student_id = $user_id) as submitted
                         FROM assessments a 
                         JOIN courses c ON a.course_id = c.id 
                         JOIN enrollments e ON c.id = e.course_id 
                         WHERE a.type = 'assignment' AND e.user_id = $user_id 
                         ORDER BY a.due_date DESC";
}

$assignments = pg_query($conn, $assignments_query);

// Get submissions for review (instructors) or user's submissions (students)
if ($role === 'instructor') {
    $submissions_query = "SELECT s.*, a.title as assignment_title, a.max_points, c.title as course_title,
                         u.username as student_name, u.email as student_email
                         FROM submissions s 
                         JOIN assessments a ON s.assessment_id = a.id 
                         JOIN courses c ON a.course_id = c.id 
                         JOIN users u ON s.student_id = u.id 
                         WHERE a.type = 'assignment' AND c.instructor_id = $user_id 
                         ORDER BY s.submitted_at DESC";
} else {
    $submissions_query = "SELECT s.*, a.title as assignment_title, a.max_points, c.title as course_title
                         FROM submissions s 
                         JOIN assessments a ON s.assessment_id = a.id 
                         JOIN courses c ON a.course_id = c.id 
                         WHERE s.student_id = $user_id AND a.type = 'assignment' 
                         ORDER BY s.submitted_at DESC";
}

$submissions = pg_query($conn, $submissions_query);

// Get courses for assignment creation
$courses_query = "SELECT * FROM courses WHERE instructor_id = $user_id ORDER BY created_at DESC";
$courses = pg_query($conn, $courses_query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Assignments - LMS</title>
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

        .assignment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .assignment-card {
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            padding: 1.5rem;
            transition: transform 0.2s, box-shadow 0.2s;
            border: 1px solid #e0e0e0;
        }

        .assignment-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0, 176, 155, 0.15);
        }

        .assignment-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #00b09b;
            margin-bottom: 0.5rem;
        }

        .assignment-course {
            display: inline-block;
            background: linear-gradient(135deg, #00b09b, #96c93d);
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .assignment-info {
            display: flex;
            gap: 1rem;
            margin: 0.5rem 0;
            font-size: 0.9rem;
            color: #666;
        }

        .assignment-description {
            color: #666;
            margin: 0.5rem 0;
            line-height: 1.4;
        }

        .assignment-actions {
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

        .status-graded {
            background: #d4edda;
            color: #155724;
        }

        .status-overdue {
            background: #f8d7da;
            color: #721c24;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(0, 176, 155, 0.13);
            padding: 2.5rem;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-content h3 {
            margin-top: 0;
            color: #00b09b;
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.7rem;
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            border-color: #00b09b;
            outline: none;
            box-shadow: 0 0 0 2px #00b09b22;
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

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .submissions-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .submissions-table th,
        .submissions-table td {
            padding: 0.8rem;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        .submissions-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #00b09b;
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

        .submission-text {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .file-upload-area {
            border: 2px dashed #ccc;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.2s;
            margin-bottom: 1rem;
        }

        .file-upload-area:hover {
            border-color: #00b09b;
        }

        .file-upload-area.dragover {
            border-color: #00b09b;
            background: #f0f9ff;
        }

        .file-upload-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        .file-upload-content i {
            font-size: 3rem;
            color: #00b09b;
        }

        .file-upload-content p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }

        .file-info {
            font-size: 0.8rem !important;
            color: #999 !important;
        }

        .file-list {
            margin-top: 10px;
        }

        .file-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 8px 12px;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }

        .file-item .file-name {
            flex-grow: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            margin-right: 10px;
        }

        .file-item .file-size {
            font-weight: 600;
            color: #00b09b;
            margin-right: 10px;
        }

        .file-item .file-remove {
            color: #dc3545;
            cursor: pointer;
            font-size: 1rem;
            padding: 2px;
        }

        .file-item .file-remove:hover {
            color: #c82333;
        }

        .file-upload-area {
            border: 2px dashed #ccc;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.2s;
            margin-bottom: 1rem;
        }

        .file-upload-area:hover {
            border-color: #00b09b;
        }

        .file-upload-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        .file-upload-content i {
            font-size: 3rem;
            color: #00b09b;
        }

        .file-upload-content p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }

        .file-list {
            list-style: none;
            padding: 0;
            margin-top: 10px;
        }

        .file-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f0f0f0;
            border-radius: 6px;
            padding: 8px 12px;
            margin-bottom: 5px;
            font-size: 0.8rem;
            color: #333;
        }

        .file-list li .file-name {
            flex-grow: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .file-list li .file-size {
            font-weight: 600;
            color: #00b09b;
        }

        .file-list li .file-remove {
            color: #dc3545;
            cursor: pointer;
            font-size: 1rem;
            margin-left: 10px;
        }

        @media (max-width: 768px) {
            .assignment-grid {
                grid-template-columns: 1fr;
            }

            .modal-content {
                padding: 1.5rem;
            }
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
        <?php if ($assignment_msg): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($assignment_msg); ?></div>
        <?php endif; ?>

        <?php if ($submission_msg): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($submission_msg); ?></div>
        <?php endif; ?>

        <?php if ($grade_msg): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($grade_msg); ?></div>
        <?php endif; ?>

        <!-- Assignments Section -->
        <div class="section-card">
            <div class="section-header">
                <h2><i class="fa fa-file-alt"></i> Assignments</h2>
                <?php if ($role === 'instructor'): ?>
                    <button class="btn-gradient" onclick="openModal('createAssignmentModal')">
                        <i class="fa fa-plus"></i> Create Assignment
                    </button>
                <?php endif; ?>
            </div>

            <div class="assignment-grid">
                <?php if ($assignments && pg_num_rows($assignments) > 0): ?>
                    <?php while ($assignment = pg_fetch_assoc($assignments)): ?>
                        <?php
                        $due_date = new DateTime($assignment['due_date']);
                        $now = new DateTime();
                        $is_overdue = $due_date < $now;
                        $status = '';

                        if ($role === 'student') {
                            if ($assignment['submitted'] > 0) {
                                $status = 'submitted';
                            } elseif ($is_overdue) {
                                $status = 'overdue';
                            } else {
                                $status = 'pending';
                            }
                        }
                        ?>
                        <div class="assignment-card">
                            <div class="assignment-title"><?php echo htmlspecialchars($assignment['title']); ?></div>
                            <div class="assignment-course"><?php echo htmlspecialchars($assignment['course_code']); ?> -
                                <?php echo htmlspecialchars($assignment['course_title']); ?>
                            </div>
                            <div class="assignment-info">
                                <span><i class="fa fa-calendar"></i> Due:
                                    <?php echo date('M j, Y', strtotime($assignment['due_date'])); ?></span>
                                <span><i class="fa fa-star"></i> <?php echo $assignment['max_points']; ?> points</span>
                                <?php if ($role === 'instructor'): ?>
                                    <span><i class="fa fa-users"></i> <?php echo $assignment['submission_count']; ?>
                                        submissions</span>
                                <?php endif; ?>
                            </div>
                            <div class="assignment-description"><?php echo htmlspecialchars($assignment['description']); ?>
                            </div>
                            <div class="assignment-actions">
                                <?php if ($role === 'student'): ?>
                                    <?php if ($assignment['submitted'] == 0): ?>
                                        <button class="btn-gradient"
                                            onclick="openSubmissionModal(<?php echo $assignment['id']; ?>, '<?php echo htmlspecialchars($assignment['title']); ?>')">
                                            <i class="fa fa-upload"></i> Submit Assignment
                                        </button>
                                    <?php else: ?>
                                        <span class="status-badge status-submitted">Submitted</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <button class="btn-secondary" onclick="viewSubmissions(<?php echo $assignment['id']; ?>)">
                                        <i class="fa fa-eye"></i> View Submissions
                                    </button>
                                <?php endif; ?>
                            </div>
                            <?php if ($role === 'student' && $status): ?>
                                <div style="margin-top: 0.5rem;">
                                    <span class="status-badge status-<?php echo $status; ?>">
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="text-align: center; color: #666; grid-column: 1 / -1;">
                        <i class="fa fa-file-alt" style="font-size: 3rem; margin-bottom: 1rem; color: #ccc;"></i>
                        <p>No assignments available yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Submissions Section -->
        <div class="section-card">
            <h2><i class="fa fa-list"></i>
                <?php echo $role === 'instructor' ? 'Student Submissions' : 'My Submissions'; ?></h2>
            <?php if ($submissions && pg_num_rows($submissions) > 0): ?>
                <table class="submissions-table">
                    <thead>
                        <tr>
                            <th>Assignment</th>
                            <th>Course</th>
                            <?php if ($role === 'instructor'): ?>
                                <th>Student</th>
                            <?php endif; ?>
                            <th>Submitted</th>
                            <th>Grade</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($submission = pg_fetch_assoc($submissions)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($submission['assignment_title']); ?></td>
                                <td><?php echo htmlspecialchars($submission['course_title']); ?></td>
                                <?php if ($role === 'instructor'): ?>
                                    <td><?php echo htmlspecialchars($submission['student_name']); ?></td>
                                <?php endif; ?>
                                <td><?php echo date('M j, Y g:i A', strtotime($submission['submitted_at'])); ?></td>
                                <td>
                                    <?php if ($submission['grade'] !== null): ?>
                                        <span class="grade-badge <?php
                                        echo $submission['grade'] >= 80 ? 'grade-excellent' :
                                            ($submission['grade'] >= 60 ? 'grade-good' : 'grade-poor');
                                        ?>">
                                            <?php echo $submission['grade']; ?>/<?php echo $submission['max_points']; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge status-pending">Not Graded</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn-secondary" onclick="viewSubmission(<?php echo $submission['id']; ?>)">
                                        <i class="fa fa-eye"></i> View
                                    </button>
                                    <?php if ($role === 'student' && $submission['grade'] === null): ?>
                                        <a href="edit_submission.php?id=<?php echo $submission['id']; ?>" class="btn-gradient"
                                            style="text-decoration: none; margin-left: 0.5rem;">
                                            <i class="fa fa-edit"></i> Edit
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($role === 'instructor' && $submission['grade'] === null): ?>
                                        <button class="btn-gradient"
                                            onclick="openGradeModal(<?php echo $submission['id']; ?>, <?php echo $submission['max_points']; ?>)">
                                            <i class="fa fa-star"></i> Grade
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; color: #666;">No submissions found.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create Assignment Modal (Instructors only) -->
    <?php if ($role === 'instructor'): ?>
        <div class="modal" id="createAssignmentModal">
            <div class="modal-content">
                <h3>Create New Assignment</h3>
                <form method="post">
                    <div class="form-group">
                        <label>Assignment Title</label>
                        <input type="text" name="assignment_title" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="assignment_description" rows="4" required></textarea>
                    </div>
                    <div class="form-group">
                        <label>Course</label>
                        <select name="course_id" required>
                            <option value="">Select Course</option>
                            <?php while ($course = pg_fetch_assoc($courses)): ?>
                                <option value="<?php echo $course['id']; ?>">
                                    <?php echo htmlspecialchars($course['course_code']); ?> -
                                    <?php echo htmlspecialchars($course['title']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Due Date</label>
                        <input type="datetime-local" name="due_date" required>
                    </div>
                    <div class="form-group">
                        <label>Maximum Points</label>
                        <input type="number" name="max_points" min="1" max="100" value="100" required>
                    </div>
                    <button type="submit" name="create_assignment" class="btn-gradient">Create Assignment</button>
                    <button type="button" class="btn-secondary"
                        onclick="closeModal('createAssignmentModal')">Cancel</button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Submit Assignment Modal -->
    <div class="modal" id="submitAssignmentModal">
        <div class="modal-content">
            <h3>Submit Assignment</h3>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="assessment_id" id="submission_assessment_id">
                <div class="form-group">
                    <label>Assignment</label>
                    <input type="text" id="submission_assignment_title" readonly>
                </div>
                <div class="form-group">
                    <label>Your Submission (Text)</label>
                    <textarea name="submission_text" rows="6" placeholder="Enter your assignment submission here..."
                        required></textarea>
                </div>
                <div class="form-group">
                    <label>Upload Files (Optional)</label>
                    <div class="file-upload-area" id="fileUploadArea">
                        <div class="file-upload-content">
                            <i class="fa fa-cloud-upload-alt"></i>
                            <p>Drag and drop files here or click to browse</p>
                            <p class="file-info">Supported formats: PDF, DOC, DOCX, TXT, ZIP, RAR (Max 10MB each)</p>
                        </div>
                        <input type="file" name="submission_files[]" id="fileInput" multiple
                            accept=".pdf,.doc,.docx,.txt,.zip,.rar" style="display: none;">
                    </div>
                    <div id="fileList" class="file-list"></div>
                </div>
                <button type="submit" name="submit_assignment" class="btn-gradient">Submit Assignment</button>
                <button type="button" class="btn-secondary"
                    onclick="closeModal('submitAssignmentModal')">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Grade Submission Modal -->
    <div class="modal" id="gradeSubmissionModal">
        <div class="modal-content">
            <h3>Grade Submission</h3>
            <form method="post">
                <input type="hidden" name="submission_id" id="grade_submission_id">
                <div class="form-group">
                    <label>Grade (out of <span id="max_points_display"></span>)</label>
                    <input type="number" name="grade" id="grade_input" min="0" step="0.1" required>
                </div>
                <div class="form-group">
                    <label>Feedback</label>
                    <textarea name="feedback" rows="4" placeholder="Provide feedback for the student..."></textarea>
                </div>
                <button type="submit" name="grade_submission" class="btn-gradient">Submit Grade</button>
                <button type="button" class="btn-secondary" onclick="closeModal('gradeSubmissionModal')">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) {
            document.getElementById(id).classList.add('active');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        function openSubmissionModal(assessmentId, assignmentTitle) {
            document.getElementById('submission_assessment_id').value = assessmentId;
            document.getElementById('submission_assignment_title').value = assignmentTitle;
            openModal('submitAssignmentModal');
        }

        function openGradeModal(submissionId, maxPoints) {
            document.getElementById('grade_submission_id').value = submissionId;
            document.getElementById('max_points_display').textContent = maxPoints;
            document.getElementById('grade_input').max = maxPoints;
            openModal('gradeSubmissionModal');
        }

        function viewSubmission(submissionId) {
            // Redirect to view submission page
            window.location.href = 'view_submission.php?id=' + submissionId;
        }

        function viewSubmissions(assignmentId) {
            // Redirect to view all submissions for an assignment
            window.location.href = 'view_submissions.php?assignment_id=' + assignmentId;
        }

        // File upload functionality
        const fileUploadArea = document.getElementById('fileUploadArea');
        const fileInput = document.getElementById('fileInput');
        const fileList = document.getElementById('fileList');
        let selectedFiles = [];

        // Click to browse files
        fileUploadArea.addEventListener('click', () => {
            fileInput.click();
        });

        // Handle file selection
        fileInput.addEventListener('change', (e) => {
            handleFiles(e.target.files);
        });

        // Drag and drop functionality
        fileUploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            fileUploadArea.classList.add('dragover');
        });

        fileUploadArea.addEventListener('dragleave', (e) => {
            e.preventDefault();
            fileUploadArea.classList.remove('dragover');
        });

        fileUploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            fileUploadArea.classList.remove('dragover');
            handleFiles(e.dataTransfer.files);
        });

        function handleFiles(files) {
            for (let file of files) {
                // Check file size (10MB limit)
                if (file.size > 10 * 1024 * 1024) {
                    alert(`File "${file.name}" is too large. Maximum size is 10MB.`);
                    continue;
                }

                // Check file type
                const allowedTypes = ['.pdf', '.doc', '.docx', '.txt', '.zip', '.rar'];
                const fileExtension = '.' + file.name.split('.').pop().toLowerCase();

                if (!allowedTypes.includes(fileExtension)) {
                    alert(`File "${file.name}" is not a supported format.`);
                    continue;
                }

                // Add file to list
                selectedFiles.push(file);
                displayFile(file);
            }
        }

        function displayFile(file) {
            const fileItem = document.createElement('div');
            fileItem.className = 'file-item';
            fileItem.innerHTML = `
                <span class="file-name">${file.name}</span>
                <span class="file-size">${formatFileSize(file.size)}</span>
                <span class="file-remove" onclick="removeFile('${file.name}')">
                    <i class="fa fa-times"></i>
                </span>
            `;
            fileList.appendChild(fileItem);
        }

        function removeFile(fileName) {
            selectedFiles = selectedFiles.filter(file => file.name !== fileName);
            updateFileList();
        }

        function updateFileList() {
            fileList.innerHTML = '';
            selectedFiles.forEach(file => displayFile(file));
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Close modal when clicking outside
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function (e) {
                if (e.target === modal) {
                    modal.classList.remove('active');
                }
            });
        });
    </script>
</body>

</html>