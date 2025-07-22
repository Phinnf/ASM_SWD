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

// Get instructor's courses
$courses = pg_query($conn, "SELECT * FROM courses WHERE instructor_id = $user_id ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Instructor Dashboard</title>
    <link rel="stylesheet" href="style.css" />
    <style>
        body {
            margin: 0;
            background: #f4f8f7;
        }

        .section-card {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 4px 24px #00b09b22;
            padding: 2.5rem 2rem 2rem 2rem;
            margin: 2rem auto;
            max-width: 1100px;
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

        .action-bar .btn-gradient:hover {
            background: linear-gradient(135deg, #96c93d, #00b09b);
        }

        .styled-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            margin-bottom: 1.5rem;
        }

        .styled-table th,
        .styled-table td {
            border: 1px solid #e0e0e0;
            padding: 12px 14px;
            text-align: left;
        }

        .styled-table th {
            background: #f4f4f4;
            color: #00b09b;
            font-weight: 600;
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
            border-radius: 6px;
            padding: 0.3rem 0.8rem;
            margin-right: 0.5rem;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.2s;
            display: inline-flex;
            align-items: center;
        }

        .action-icons button:last-child {
            margin-right: 0;
        }

        .action-icons button:hover {
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
            border-radius: 14px;
            box-shadow: 0 4px 24px #00b09b22;
            padding: 2rem 2.5rem;
            min-width: 340px;
            max-width: 95vw;
        }

        .modal-content h3 {
            margin-top: 0;
            color: #00b09b;
        }

        .modal-content label {
            display: block;
            margin-top: 1rem;
            font-weight: 500;
        }

        .modal-content input,
        .modal-content textarea {
            width: 100%;
            padding: 0.5rem;
            margin-top: 0.3rem;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 1rem;
        }

        .modal-content .btn-gradient {
            margin-top: 1.2rem;
            width: 100%;
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
    </script>
</head>

<body>
    <div class="section-card">
        <div class="section-header">
            <h2>Manage Courses</h2>
            <div class="action-bar">
                <button class="btn-gradient" onclick="openModal('addCourseModal')">&#43; Add Course</button>
                <button class="btn-gradient" onclick="openModal('uploadMaterialModal')">&#128190; Upload
                    Material</button>
                <button class="btn-gradient" onclick="openModal('enrollStudentModal')">&#128101; Enroll Student</button>
                <a href="main.php" class="btn-gradient" style="text-decoration:none;display:inline-block;">&larr; Return
                    to Menu</a>
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
                        echo '<form method="post" style="display:inline;" onsubmit="return confirm(\'Are you sure you want to delete this course and all its materials?\');"><input type="hidden" name="delete_course_id" value="' . $c['id'] . '"><button type="submit">&#128465; Delete</button></form>';
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
                <button type="button" class="btn-gradient" style="background:#eee;color:#00b09b;font-weight:bold;"
                    onclick="closeModal('addCourseModal')">Cancel</button>
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
                <button type="button" class="btn-gradient" style="background:#eee;color:#00b09b;font-weight:bold;"
                    onclick="closeModal('editCourseModal')">Cancel</button>
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
                <button type="button" class="btn-gradient" style="background:#eee;color:#00b09b;font-weight:bold;"
                    onclick="closeModal('uploadMaterialModal')">Cancel</button>
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
                <button type="button" class="btn-gradient" style="background:#f4f8f7;color:#00b09b;font-weight:bold;"
                    onclick="closeModal('enrollStudentModal')">Cancel</button>
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
                        echo '<form method="post" style="display:inline;" onsubmit="return confirm(\'Are you sure you want to delete this material?\');">';
                        echo '<input type="hidden" name="delete_material_id" value="' . $m['id'] . '">';
                        echo '<button type="submit" class="btn-gradient" style="background:#e74c3c; color:#fff;">Remove</button>';
                        echo '</form>';
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
                        echo '<form method="post" style="display:inline;" onsubmit="return confirm(\'Are you sure you want to remove this student from the course?\');">';
                        echo '<input type="hidden" name="remove_student_id" value="' . $s['id'] . '">';
                        echo '<input type="hidden" name="remove_course_id" value="' . $c['id'] . '">';
                        echo '<button type="submit" class="btn-gradient" style="background:#e74c3c; color:#fff;">Remove</button>';
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
</body>

</html>