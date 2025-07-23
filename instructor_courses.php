<?php
session_start();
include 'db_connect.php';

// Redirect if not logged in or not instructor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header('Location: index.php');
    exit;
}
$user_id = $_SESSION['user_id'];

// Handle course creation
$course_msg = '';
if (isset($_POST['create_course'])) {
    $code = pg_escape_string($conn, trim($_POST['course_code']));
    $title = pg_escape_string($conn, trim($_POST['title']));
    $desc = pg_escape_string($conn, trim($_POST['description']));
    $query = "INSERT INTO courses (course_code, title, description, instructor_id, created_at) VALUES ('$code', '$title', '$desc', $user_id, NOW())";
    $res = pg_query($conn, $query);
    $course_msg = $res ? 'Course created successfully.' : 'Error creating course.';
}

// Handle material upload
$material_msg = '';
if (isset($_POST['upload_material'])) {
    $course_id = (int) $_POST['material_course_id'];
    $title = pg_escape_string($conn, trim($_POST['material_title']));
    if (isset($_FILES['material_file']) && $_FILES['material_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['material_file']['tmp_name'];
        $file_name = time() . '_' . basename($_FILES['material_file']['name']);
        $file_path = 'uploads/' . $file_name;
        if (move_uploaded_file($file_tmp, $file_path)) {
            $query = "INSERT INTO materials (course_id, title, file_path, uploaded_at) VALUES ($course_id, '$title', '$file_name', NOW())";
            $res = pg_query($conn, $query);
            $material_msg = $res ? 'Material uploaded.' : 'DB error uploading material.';
        } else {
            $material_msg = 'File upload failed.';
        }
    } else {
        $material_msg = 'No file selected or upload error.';
    }
}

// Handle student enrollment
$enroll_msg = '';
if (isset($_POST['enroll_student'])) {
    $course_id = (int) $_POST['enroll_course_id'];
    $student_email = pg_escape_string($conn, trim($_POST['student_email']));
    $user_q = pg_query($conn, "SELECT id FROM users WHERE email = '$student_email' AND role = 'student'");
    if ($user_q && pg_num_rows($user_q) === 1) {
        $student = pg_fetch_assoc($user_q);
        $student_id = $student['id'];
        $chk = pg_query($conn, "SELECT 1 FROM enrollments WHERE user_id = $student_id AND course_id = $course_id");
        if ($chk && pg_num_rows($chk) === 0) {
            $ins = pg_query($conn, "INSERT INTO enrollments (user_id, course_id, enrollment_date) VALUES ($student_id, $course_id, NOW())");
            $enroll_msg = $ins ? 'Student enrolled.' : 'Error enrolling student.';
        } else {
            $enroll_msg = 'Student already enrolled.';
        }
    } else {
        $enroll_msg = 'Student not found.';
    }
}

// Handle course deletion
if (isset($_POST['delete_course_id'])) {
    $del_course_id = (int) $_POST['delete_course_id'];
    // Delete materials and their files
    $mat_res = pg_query($conn, "SELECT file_path FROM materials WHERE course_id = $del_course_id");
    if ($mat_res) {
        while ($mat = pg_fetch_assoc($mat_res)) {
            $file = 'uploads/' . $mat['file_path'];
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
    pg_query($conn, "DELETE FROM materials WHERE course_id = $del_course_id");
    pg_query($conn, "DELETE FROM enrollments WHERE course_id = $del_course_id");
    pg_query($conn, "DELETE FROM courses WHERE id = $del_course_id");
    $course_msg = 'Course and related data deleted.';
}
// Handle material deletion
if (isset($_POST['delete_material_id'])) {
    $del_material_id = (int) $_POST['delete_material_id'];
    $mat_res = pg_query($conn, "SELECT file_path FROM materials WHERE id = $del_material_id");
    if ($mat_res && pg_num_rows($mat_res) === 1) {
        $mat = pg_fetch_assoc($mat_res);
        $file = 'uploads/' . $mat['file_path'];
        if (file_exists($file)) {
            unlink($file);
        }
    }
    pg_query($conn, "DELETE FROM materials WHERE id = $del_material_id");
    $material_msg = 'Material deleted.';
}
// Handle student removal from course
if (isset($_POST['remove_student_id']) && isset($_POST['remove_course_id'])) {
    $remove_student_id = (int) $_POST['remove_student_id'];
    $remove_course_id = (int) $_POST['remove_course_id'];
    pg_query($conn, "DELETE FROM enrollments WHERE user_id = $remove_student_id AND course_id = $remove_course_id");
    $enroll_msg = 'Student removed from course.';
}

// PHP: Handle material edit
if (isset($_POST['edit_material_id'])) {
    $edit_material_id = (int) $_POST['edit_material_id'];
    $edit_title = pg_escape_string($conn, trim($_POST['edit_material_title']));
    $edit_file_uploaded = isset($_FILES['edit_material_file']) && $_FILES['edit_material_file']['error'] === UPLOAD_ERR_OK;
    $update_query = "UPDATE materials SET title = '$edit_title' WHERE id = $edit_material_id";
    $file_msg = '';
    if ($edit_file_uploaded) {
        $file_tmp = $_FILES['edit_material_file']['tmp_name'];
        $file_name = time() . '_' . basename($_FILES['edit_material_file']['name']);
        $file_path = 'uploads/' . $file_name;
        if (move_uploaded_file($file_tmp, $file_path)) {
            // Remove old file
            $old = pg_query($conn, "SELECT file_path FROM materials WHERE id = $edit_material_id");
            if ($old && pg_num_rows($old) === 1) {
                $old_row = pg_fetch_assoc($old);
                $old_file = 'uploads/' . $old_row['file_path'];
                if (file_exists($old_file))
                    unlink($old_file);
            }
            $update_query = "UPDATE materials SET title = '$edit_title', file_path = '$file_name' WHERE id = $edit_material_id";
        } else {
            $file_msg = 'File upload failed.';
        }
    }
    $res = pg_query($conn, $update_query);
    $material_msg = ($res ? 'Material updated.' : 'Error updating material.') . ($file_msg ? ' ' . $file_msg : '');
}

// Get instructor's courses
$courses = pg_query($conn, "SELECT * FROM courses WHERE instructor_id = $user_id ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Instructor Dashboard</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
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

        .menu-bar a,
        .menu-bar .dropbtn {
            color: white;
            padding: 0 18px;
            text-decoration: none;
            font-size: 17px;
            line-height: 60px;
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.2s;
        }

        .menu-bar a:hover,
        .menu-bar .dropbtn:hover {
            background: rgba(0, 0, 0, 0.08);
            border-radius: 6px;
        }

        .menu-bar .logout {
            margin-left: auto;
            background: linear-gradient(90deg, #00b09b 0%, #96c93d 100%);
            color: #fff !important;
            border-radius: 8px;
            font-weight: 700;
            padding: 0 22px;
            height: 40px;
            display: flex;
            align-items: center;
            margin-top: 10px;
            margin-bottom: 10px;
            box-shadow: 0 2px 8px #00b09b22;
            border: none;
            transition: background 0.2s;
        }

        .menu-bar .logout:hover {
            background: linear-gradient(90deg, #96c93d 0%, #00b09b 100%);
            color: #fff;
        }

        .section-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 4px 24px rgba(0, 176, 155, 0.10);
            padding: 2.5rem 2.5rem 2rem 2.5rem;
            margin: 2.5rem auto;
            max-width: 1200px;
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
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .action-bar .btn-gradient {
            background: linear-gradient(135deg, #00b09b, #96c93d);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0.6rem 1.5rem;
            font-size: 1rem;
            font-weight: bold;
            margin-left: 1rem;
            cursor: pointer;
            transition: background 0.2s, box-shadow 0.2s;
            box-shadow: 0 2px 8px #00b09b22;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .action-bar .btn-gradient i {
            margin-right: 0.5rem;
        }

        .action-bar .btn-gradient:hover {
            background: linear-gradient(135deg, #96c93d, #00b09b);
        }

        .styled-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            margin-bottom: 1.5rem;
            font-size: 1.05rem;
            border-radius: 12px;
            overflow: hidden;
        }

        .styled-table th,
        .styled-table td {
            border: 1px solid #e0e0e0;
            padding: 12px 14px;
            text-align: left;
            vertical-align: middle;
        }

        .styled-table th {
            background: #f4f4f4;
            color: #00b09b;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .styled-table tr:nth-child(even) {
            background: #f8f8f8;
        }

        .styled-table tr:hover {
            background: #eafaf1;
        }

        .action-icons button {
            background: linear-gradient(135deg, #00b09b, #96c93d);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0.5rem 1.1rem;
            margin-right: 0.5rem;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.2s, box-shadow 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-weight: 600;
        }

        .action-icons button.edit-material-btn {
            background: linear-gradient(135deg, #00b09b, #96c93d);
        }

        .action-icons button.edit-material-btn:hover {
            background: linear-gradient(135deg, #96c93d, #00b09b);
        }

        .action-icons button.remove-btn {
            background: linear-gradient(135deg, #e74c3c, #ff5e62);
        }

        .action-icons button.remove-btn:hover {
            background: linear-gradient(135deg, #ff5e62, #e74c3c);
        }

        .action-icons button:last-child {
            margin-right: 0;
        }

        .action-icons button:not(.remove-btn):hover {
            background: linear-gradient(135deg, #96c93d, #00b09b);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.25);
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
            padding: 2.5rem 2.5rem 2rem 2.5rem;
            min-width: 340px;
            max-width: 95vw;
            font-family: 'Roboto', Arial, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: stretch;
        }

        .modal-content h3 {
            margin-top: 0;
            color: #00b09b;
            font-size: 1.6rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-bottom: 1.2rem;
        }

        .modal-content label {
            display: block;
            margin-top: 1rem;
            font-weight: 500;
            color: #222;
            margin-bottom: 0.3rem;
        }

        .modal-content input,
        .modal-content textarea,
        .modal-content select {
            width: 100%;
            padding: 0.7rem 1rem;
            margin-top: 0.3rem;
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            background: #f8fafc;
            margin-bottom: 0.5rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .modal-content input:focus,
        .modal-content textarea:focus,
        .modal-content select:focus {
            border-color: #00b09b;
            outline: none;
            box-shadow: 0 0 0 2px #00b09b22;
        }

        .modal-content .btn-gradient {
            margin-top: 1.2rem;
            width: 100%;
            background: linear-gradient(135deg, #00b09b, #96c93d);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0.7rem 1.5rem;
            font-size: 1.08rem;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 2px 8px #00b09b22;
            transition: background 0.2s, box-shadow 0.2s;
            margin-bottom: 0.5rem;
        }

        .modal-content .btn-gradient:hover {
            background: linear-gradient(135deg, #96c93d, #00b09b);
        }

        .modal-content .btn-cancel {
            background: #fff;
            color: #00b09b;
            border: 2px solid #00b09b;
            font-weight: bold;
            border-radius: 8px;
            padding: 0.7rem 1.5rem;
            font-size: 1.08rem;
            cursor: pointer;
            margin-bottom: 0.5rem;
            transition: background 0.2s, color 0.2s, border 0.2s;
        }

        .modal-content .btn-cancel:hover {
            background: #f4f8fb;
            color: #089e8a;
            border: 2px solid #089e8a;
        }

        .no-data {
            color: #888;
            font-style: italic;
            margin: 0.5rem 0 1rem 0;
        }

        @media (max-width: 900px) {
            .section-card {
                padding: 1rem 0.5rem;
            }

            .modal-content {
                padding: 1rem 0.5rem;
            }
        }
    </style>
    <script>
        function openModal(id) {
            document.getElementById(id).classList.add('active');
        }
        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        // Modern Confirm Modal Logic
        let confirmCallback = null;
        function showConfirmModal(message, onConfirm) {
            document.getElementById('confirmModalMessage').textContent = message;
            document.getElementById('confirmModal').classList.add('active');
            confirmCallback = onConfirm;
        }
        function hideConfirmModal() {
            document.getElementById('confirmModal').classList.remove('active');
            confirmCallback = null;
        }
        function confirmModalYes() {
            if (confirmCallback) confirmCallback();
            hideConfirmModal();
        }
    </script>
</head>

<body>
    <div class="menu-bar">
        <a href="main.php"><i class="fa fa-home"></i> Home</a>
        <a href="logout.php" class="logout"><i class="fa fa-sign-out-alt"></i> Logout</a>
    </div>
    <div class="section-card">
        <div class="section-header">
            <h2><i class="fa fa-chalkboard-teacher"></i> Manage Courses</h2>
            <div class="action-bar">
                <button class="btn-gradient" onclick="openModal('addCourseModal')"><i class="fa fa-plus"></i> Add
                    Course</button>
                <button class="btn-gradient" onclick="openModal('uploadMaterialModal')"><i class="fa fa-upload"></i>
                    Upload Material</button>
                <button class="btn-gradient" onclick="openModal('enrollStudentModal')"><i class="fa fa-user-plus"></i>
                    Enroll Student</button>
                <a href="main.php" class="btn-gradient" style="text-decoration:none;display:inline-block;"><i
                        class="fa fa-arrow-left"></i> Return to Menu</a>
            </div>
        </div>
        <table class="styled-table">
            <thead>
                <tr>
                    <th>Course Code</th>
                    <th>Title</th>
                    <th>Description</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($courses && pg_num_rows($courses) > 0) {
                    pg_result_seek($courses, 0);
                    while ($c = pg_fetch_assoc($courses)) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($c['course_code']) . '</td>';
                        echo '<td>' . htmlspecialchars($c['title']) . '</td>';
                        echo '<td>' . htmlspecialchars($c['description']) . '</td>';
                        echo '<td>' . htmlspecialchars($c['created_at']) . '</td>';
                        echo '<td class="action-icons">';
                        echo '<button onclick="openEditModal('
                            . $c['id'] . ', '
                            . '\'' . addslashes(htmlspecialchars($c['course_code'])) . '\', '
                            . '\'' . addslashes(htmlspecialchars($c['title'])) . '\', '
                            . '\'' . addslashes(htmlspecialchars($c['description'])) . '\''
                            . ')">&#9998; Edit</button>';
                        echo '<form method="post" style="display:inline;" class="confirm-delete-course"><input type="hidden" name="delete_course_id" value="' . $c['id'] . '"><button type="submit" class="remove-btn"><i class="fa fa-trash"></i> Delete</button></form>';
                        echo '</td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="5" style="text-align:center;color:#888;">No courses found.</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
    <!-- Add Course Modal -->
    <div class="modal" id="addCourseModal">
        <div class="modal-content">
            <h3>Add Course</h3>
            <form method="post">
                <label>Course Code</label>
                <input type="text" name="course_code" required />
                <label>Title</label>
                <input type="text" name="title" required />
                <label>Description</label>
                <textarea name="description" required></textarea>
                <button class="btn-gradient" type="submit" name="create_course">Add Course</button>
                <button type="button" class="btn-cancel" onclick="closeModal('addCourseModal')">Cancel</button>
            </form>
        </div>
    </div>
    <!-- Edit Course Modal (populated by JS) -->
    <div class="modal" id="editCourseModal">
        <div class="modal-content">
            <h3>Edit Course</h3>
            <form method="post">
                <input type="hidden" name="edit_course_id" id="edit_course_id" />
                <label>Course Code</label>
                <input type="text" name="edit_course_code" id="edit_course_code" required />
                <label>Title</label>
                <input type="text" name="edit_title" id="edit_title" required />
                <label>Description</label>
                <textarea name="edit_description" id="edit_description" required></textarea>
                <button class="btn-gradient" type="submit" name="update_course">Update Course</button>
                <button type="button" class="btn-cancel" onclick="closeModal('editCourseModal')">Cancel</button>
            </form>
        </div>
    </div>
    <!-- Upload Material Modal -->
    <div class="modal" id="uploadMaterialModal">
        <div class="modal-content">
            <h3>Upload Material</h3>
            <form method="post" enctype="multipart/form-data">
                <label>Course</label>
                <select name="material_course_id" required>
                    <option value="">Select Course</option>
                    <?php
                    $courses2 = pg_query($conn, "SELECT * FROM courses WHERE instructor_id = $user_id ORDER BY created_at DESC");
                    if ($courses2) {
                        while ($c = pg_fetch_assoc($courses2)) {
                            echo '<option value="' . $c['id'] . '">' . htmlspecialchars($c['title']) . '</option>';
                        }
                    }
                    ?>
                </select>
                <label>Title</label>
                <input type="text" name="material_title" required />
                <label>File</label>
                <input type="file" name="material_file" required />
                <button class="btn-gradient" type="submit" name="upload_material">Upload</button>
                <button type="button" class="btn-cancel" onclick="closeModal('uploadMaterialModal')">Cancel</button>
            </form>
        </div>
    </div>
    <!-- Enroll Student Modal -->
    <div class="modal" id="enrollStudentModal">
        <div class="modal-content">
            <h3>Enroll Student</h3>
            <form method="post">
                <label>Course</label>
                <select name="enroll_course_id" required>
                    <option value="">Select Course</option>
                    <?php
                    $courses3 = pg_query($conn, "SELECT * FROM courses WHERE instructor_id = $user_id ORDER BY created_at DESC");
                    if ($courses3) {
                        while ($c = pg_fetch_assoc($courses3)) {
                            echo '<option value="' . $c['id'] . '">' . htmlspecialchars($c['title']) . '</option>';
                        }
                    }
                    ?>
                </select>
                <label>Student Email</label>
                <input type="email" name="student_email" required />
                <button class="btn-gradient" type="submit" name="enroll_student">Enroll</button>
                <button type="button" class="btn-cancel" onclick="closeModal('enrollStudentModal')">Cancel</button>
            </form>
        </div>
    </div>
    <!-- Edit Material Modal (single, populated by JS) -->
    <div class="modal" id="editMaterialModal">
        <div class="modal-content">
            <h3>Edit Material</h3>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="edit_material_id" id="edit_material_id" />
                <label>Title</label>
                <input type="text" name="edit_material_title" id="edit_material_title" required />
                <label>Replace File (optional)</label>
                <input type="file" name="edit_material_file" />
                <button class="btn-gradient" type="submit">Update Material</button>
                <button type="button" class="btn-cancel" onclick="closeModal('editMaterialModal')">Cancel</button>
            </form>
        </div>
    </div>
    <script>
        function openEditModal(id, code, title, desc) {
            document.getElementById('edit_course_id').value = id;
            document.getElementById('edit_course_code').value = code;
            document.getElementById('edit_title').value = title;
            document.getElementById('edit_description').value = desc;
            openModal('editCourseModal');
        }

        // Attach modern confirm modal to delete/remove actions
        document.addEventListener('DOMContentLoaded', function () {
            // Delete course
            document.querySelectorAll('.confirm-delete-course').forEach(function (form) {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    showConfirmModal('Are you sure you want to delete this course and all its materials?', function () {
                        form.submit();
                    });
                });
            });
            // Delete material
            document.querySelectorAll('.confirm-delete-material').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    var form = btn.closest('form');
                    showConfirmModal('Are you sure you want to delete this material?', function () {
                        form.submit();
                    });
                });
            });
            // Remove student
            document.querySelectorAll('.confirm-remove-student').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    var form = btn.closest('form');
                    showConfirmModal('Are you sure you want to remove this student from the course?', function () {
                        form.submit();
                    });
                });
            });
        });

        // JS: Attach edit-material-btn click to open modal and populate fields
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.edit-material-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    document.getElementById('edit_material_id').value = btn.getAttribute('data-id');
                    document.getElementById('edit_material_title').value = btn.getAttribute('data-title');
                    openModal('editMaterialModal');
                });
            });
        });
    </script>
    <!-- Course Details Section: Enrolled Students and Materials -->
    <div class="section-card">
        <h2 style="color:#00b09b;">Course Details</h2>
        <?php
        $courses4 = pg_query($conn, "SELECT * FROM courses WHERE instructor_id = $user_id ORDER BY created_at DESC");
        if ($courses4 && pg_num_rows($courses4) > 0) {
            while ($c = pg_fetch_assoc($courses4)) {
                echo '<div style="margin-bottom:2.5rem;">';
                echo '<h3 style="color:#00b09b; margin-bottom:0.5rem;">' . htmlspecialchars($c['title']) . ' (' . htmlspecialchars($c['course_code']) . ')</h3>';
                // Materials
                $mats = pg_query($conn, "SELECT * FROM materials WHERE course_id = " . $c['id'] . " ORDER BY uploaded_at DESC");
                echo '<div style="margin-bottom:1.2rem;">';
                echo '<b>Materials:</b>';
                if ($mats && pg_num_rows($mats) > 0) {
                    echo '<table class="styled-table" style="margin-top:0.5rem;">';
                    echo '<tr><th>Title</th><th>File</th><th>Uploaded</th><th>Action</th></tr>';
                    while ($m = pg_fetch_assoc($mats)) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($m['title']) . '</td>';
                        echo '<td><a href="uploads/' . htmlspecialchars($m['file_path']) . '" target="_blank">Download</a></td>';
                        echo '<td>' . htmlspecialchars($m['uploaded_at']) . '</td>';
                        echo '<td>';
                        echo '<div class="action-icons" style="display:inline-flex;align-items:center;">';
                        echo '<button type="button" class="btn-gradient edit-material-btn" data-id="' . $m['id'] . '" data-title="' . htmlspecialchars($m['title']) . '"><i class="fa fa-pen"></i> Edit</button> ';
                        echo '<form method="post" style="display:inline;" class="confirm-delete-material">';
                        echo '<input type="hidden" name="delete_material_id" value="' . $m['id'] . '">';
                        echo '<button type="submit" class="remove-btn"><i class="fa fa-trash"></i> Remove</button>';
                        echo '</form>';
                        echo '</div>';
                        echo '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                } else {
                    echo '<div class="no-data">No materials uploaded.</div>';
                }
                echo '</div>';
                // Enrolled students
                $studs = pg_query($conn, "SELECT u.id, u.username, u.email FROM enrollments e JOIN users u ON e.user_id = u.id WHERE e.course_id = " . $c['id']);
                echo '<div>';
                echo '<b>Enrolled Students:</b>';
                if ($studs && pg_num_rows($studs) > 0) {
                    echo '<table class="styled-table" style="margin-top:0.5rem;">';
                    echo '<tr><th>Username</th><th>Email</th><th>Action</th></tr>';
                    while ($s = pg_fetch_assoc($studs)) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($s['username']) . '</td>';
                        echo '<td>' . htmlspecialchars($s['email']) . '</td>';
                        echo '<td>';
                        echo '<form method="post" style="display:inline;" class="confirm-remove-student-form">';
                        echo '<input type="hidden" name="remove_student_id" value="' . $s['id'] . '">';
                        echo '<input type="hidden" name="remove_course_id" value="' . $c['id'] . '">';
                        echo '<button type="submit" class="remove-btn action-btn confirm-remove-student"><i class="fa fa-user-minus"></i> Remove</button>';
                        echo '</form>';
                        echo '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                } else {
                    echo '<div class="no-data">No students enrolled.</div>';
                }
                echo '</div>';
                echo '</div>';
            }
        } else {
            echo '<p>No courses found.</p>';
        }
        ?>
    </div>
    <!-- Modern Confirm Modal -->
    <div class="modal" id="confirmModal">
        <div class="modal-content" style="max-width:400px;text-align:center;">
            <h3 style="margin-bottom:1.2rem;">Please Confirm</h3>
            <div id="confirmModalMessage" style="margin-bottom:1.5rem;font-size:1.1rem;color:#333;"></div>
            <div style="display:flex;gap:16px;justify-content:center;">
                <button class="btn-gradient" style="background:#e74c3c; color:#fff;min-width:100px;"
                    onclick="confirmModalYes()">Confirm</button>
                <button class="btn-gradient" style="background:#eee;color:#00b09b;font-weight:bold;min-width:100px;"
                    onclick="hideConfirmModal()">Cancel</button>
            </div>
        </div>
    </div>
</body>

</html>