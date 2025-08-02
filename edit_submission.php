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

// Only students can edit submissions
if ($role !== 'student') {
    header('Location: assignments.php');
    exit;
}

// Get submission ID from URL
$submission_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$submission_id) {
    header('Location: assignments.php');
    exit;
}

// Get submission details
$submission_query = "SELECT s.*, a.title as assignment_title, a.description as assignment_description, 
                           a.max_points, a.due_date, c.title as course_title, c.course_code
                    FROM submissions s 
                    JOIN assessments a ON s.assessment_id = a.id 
                    JOIN courses c ON a.course_id = c.id 
                    WHERE s.id = $submission_id AND s.student_id = $user_id";

$submission_result = pg_query($conn, $submission_query);

if (!$submission_result || pg_num_rows($submission_result) == 0) {
    header('Location: assignments.php');
    exit;
}

$submission = pg_fetch_assoc($submission_result);

// Check if assignment is still editable (not graded and not past due date)
$due_date = new DateTime($submission['due_date']);
$now = new DateTime();
$is_overdue = $due_date < $now;
$is_graded = $submission['grade'] !== null;

if ($is_graded) {
    header('Location: view_submission.php?id=' . $submission_id);
    exit;
}

// Handle form submission
$edit_msg = '';
if (isset($_POST['update_submission'])) {
    $submission_text = pg_escape_string($conn, trim($_POST['submission_text']));
    
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
                $edit_msg = "File '$file_name' is too large. Maximum size is 10MB.";
                break;
            }

            // Validate file type
            $allowed_types = ['pdf', 'doc', 'docx', 'txt', 'zip', 'rar'];
            $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (!in_array($file_extension, $allowed_types)) {
                $edit_msg = "File '$file_name' is not a supported format.";
                break;
            }

            // Generate unique filename
            $unique_filename = time() . '_' . $user_id . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file_name);
            $file_path = $upload_dir . $unique_filename;

            if (move_uploaded_file($tmp_name, $file_path)) {
                $uploaded_files[] = $file_name . '|' . $unique_filename;
            } else {
                $edit_msg = "Error uploading file '$file_name'.";
                break;
            }
        }
    }

    if (empty($edit_msg)) {
        // Add file information to submission text
        $final_submission_text = $submission_text;
        if (!empty($uploaded_files)) {
            $final_submission_text .= "\n\n--- UPLOADED FILES ---\n";
            foreach ($uploaded_files as $file_info) {
                list($original_name, $stored_name) = explode('|', $file_info);
                $final_submission_text .= "File: $original_name\n";
            }
        }

        $final_submission_text = pg_escape_string($conn, $final_submission_text);

        $query = "UPDATE submissions SET submission_text = '$final_submission_text', submitted_at = NOW() 
                  WHERE id = $submission_id";
        $res = pg_query($conn, $query);

        if ($res) {
            $edit_msg = 'Assignment updated successfully!';
            // Refresh submission data
            $submission_result = pg_query($conn, $submission_query);
            $submission = pg_fetch_assoc($submission_result);
        } else {
            $edit_msg = 'Error updating assignment.';
        }
    }
}

// Extract current submission text and files
$submission_text = $submission['submission_text'];
$text_parts = explode('--- UPLOADED FILES ---', $submission_text);
$current_text = trim($text_parts[0]);
$current_files = [];
if (count($text_parts) > 1) {
    $files_section = $text_parts[1];
    $files = explode("\n", trim($files_section));
    foreach ($files as $file_line) {
        if (strpos($file_line, 'File:') === 0) {
            $current_files[] = trim(substr($file_line, 5));
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Edit Submission - LMS</title>
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
            max-width: 800px;
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

        .assignment-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .info-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1rem;
            border-left: 4px solid #00b09b;
        }

        .info-label {
            font-weight: 600;
            color: #666;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .info-value {
            font-size: 1rem;
            font-weight: 700;
            color: #333;
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

        .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s;
            min-height: 200px;
            resize: vertical;
        }

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

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
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

        .current-files {
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .current-files h4 {
            color: #00b09b;
            margin-bottom: 1rem;
        }

        .file-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fff;
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

        .file-item .file-remove {
            color: #dc3545;
            cursor: pointer;
            font-size: 1rem;
            padding: 2px;
        }

        .file-item .file-remove:hover {
            color: #c82333;
        }

        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .warning-box i {
            color: #856404;
            margin-right: 0.5rem;
        }

        @media (max-width: 768px) {
            .assignment-info {
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
        <a href="main.php"><i class="fa fa-home"></i> Home</a>
        <a href="my_courses.php"><i class="fa fa-book"></i> My Courses</a>
        <a href="quizzes.php"><i class="fa fa-question-circle"></i> Quizzes</a>
        <a href="assignments.php"><i class="fa fa-file-alt"></i> Assignments</a>
        <a href="messages.php"><i class="fa fa-envelope"></i> Messages</a>
        <a href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="container">
        <?php if ($edit_msg): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($edit_msg); ?></div>
        <?php endif; ?>

        <!-- Assignment Details -->
        <div class="section-card">
            <div class="section-header">
                <h2><i class="fa fa-edit"></i> Edit Assignment Submission</h2>
                <a href="view_submission.php?id=<?php echo $submission_id; ?>" class="btn-secondary">
                    <i class="fa fa-eye"></i> View Submission
                </a>
            </div>

            <div class="assignment-info">
                <div class="info-card">
                    <div class="info-label">Assignment</div>
                    <div class="info-value"><?php echo htmlspecialchars($submission['assignment_title']); ?></div>
                </div>
                <div class="info-card">
                    <div class="info-label">Course</div>
                    <div class="info-value"><?php echo htmlspecialchars($submission['course_code']); ?> -
                        <?php echo htmlspecialchars($submission['course_title']); ?></div>
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

            <?php if ($is_overdue): ?>
                <div class="warning-box">
                    <i class="fa fa-exclamation-triangle"></i>
                    <strong>Warning:</strong> This assignment is overdue. Your submission may not be accepted.
                </div>
            <?php endif; ?>

            <!-- Edit Form -->
            <form method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Your Submission (Text)</label>
                    <textarea name="submission_text" placeholder="Enter your assignment submission here..." required><?php echo htmlspecialchars($current_text); ?></textarea>
                </div>

                <?php if (!empty($current_files)): ?>
                    <div class="current-files">
                        <h4><i class="fa fa-paperclip"></i> Current Files</h4>
                        <?php foreach ($current_files as $file): ?>
                            <div class="file-item">
                                <span class="file-name"><?php echo htmlspecialchars($file); ?></span>
                                <span style="color: #666; font-size: 0.9rem;">Currently uploaded</span>
                            </div>
                        <?php endforeach; ?>
                        <p style="color: #666; font-size: 0.9rem; margin-top: 0.5rem;">
                            <i class="fa fa-info-circle"></i> Current files will be replaced with new uploads.
                        </p>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>Upload New Files (Optional)</label>
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

                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="submit" name="update_submission" class="btn-gradient">
                        <i class="fa fa-save"></i> Update Submission
                    </button>
                    <a href="view_submission.php?id=<?php echo $submission_id; ?>" class="btn-secondary">
                        <i class="fa fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
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
    </script>
</body>

</html> 