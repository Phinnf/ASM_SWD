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
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: 220px;
            background: linear-gradient(135deg, #00b09b, #96c93d);
            color: #fff;
            padding: 2rem 1rem;
            min-height: 100vh;
        }

        .sidebar h2 {
            color: #fff;
            margin-bottom: 2rem;
        }

        .sidebar a {
            display: block;
            color: #fff;
            text-decoration: none;
            margin-bottom: 1.2rem;
            font-weight: 500;
            transition: color 0.2s;
        }

        .sidebar a:hover {
            color: #333;
        }

        .main-content {
            margin-left: 240px;
            padding: 2rem;
        }

        .section {
            background: #fff;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px #0001;
        }

        h2 {
            color: #00b09b;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        th,
        td {
            border: 1px solid #eee;
            padding: 8px;
            text-align: left;
        }

        th {
            background: #f4f4f4;
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <h2>Instructor</h2>
        <a href="#courses">My Courses</a>
        <a href="#create">Create Course</a>
        <a href="#upload">Upload Material</a>
        <a href="#enroll">Enroll Student</a>
        <a href="main.php">&larr; Return to Menu</a>
        <a href="logout.php">Logout</a>
    </div>
    <div class="main-content">
        <div class="section" id="courses">
            <h2>My Courses</h2>
            <?php
            if ($courses && pg_num_rows($courses) > 0) {
                echo '<table><tr><th>Course Code</th><th>Title</th><th>Description</th><th>Created</th></tr>';
                while ($c = pg_fetch_assoc($courses)) {
                    echo '<tr><td>' . htmlspecialchars($c['course_code']) . '</td><td>' . htmlspecialchars($c['title']) . '</td><td>' . htmlspecialchars($c['description']) . '</td><td>' . htmlspecialchars($c['created_at']) . '</td></tr>';
                }
                echo '</table>';
            } else {
                echo '<p>No courses found.</p>';
            }
            ?>
        </div>
        <div class="section" id="create">
            <h2>Create Course</h2>
            <?php if ($course_msg)
                echo '<div class="alert">' . htmlspecialchars($course_msg) . '</div>'; ?>
            <form method="post">
                <label>Course Code</label><input class="form-control" type="text" name="course_code" required />
                <label>Title</label><input class="form-control" type="text" name="title" required />
                <label>Description</label><textarea class="form-control" name="description" required></textarea>
                <button class="btn btn-primary" type="submit" name="create_course">Create</button>
            </form>
        </div>
        <div class="section" id="upload">
            <h2>Upload Material</h2>
            <?php if ($material_msg)
                echo '<div class="alert">' . htmlspecialchars($material_msg) . '</div>'; ?>
            <form method="post" enctype="multipart/form-data">
                <label>Course</label>
                <select class="form-control" name="material_course_id" required>
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
                <label>Title</label><input class="form-control" type="text" name="material_title" required />
                <label>File</label><input class="form-control" type="file" name="material_file" required />
                <button class="btn btn-primary" type="submit" name="upload_material">Upload</button>
            </form>
        </div>
        <div class="section" id="enroll">
            <h2>Enroll Student</h2>
            <?php if ($enroll_msg)
                echo '<div class="alert">' . htmlspecialchars($enroll_msg) . '</div>'; ?>
            <form method="post">
                <label>Course</label>
                <select class="form-control" name="enroll_course_id" required>
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
                <label>Student Email</label><input class="form-control" type="email" name="student_email" required />
                <button class="btn btn-primary" type="submit" name="enroll_student">Enroll</button>
            </form>
        </div>
        <div class="section">
            <h2>Course Details</h2>
            <?php
            $courses4 = pg_query($conn, "SELECT * FROM courses WHERE instructor_id = $user_id ORDER BY created_at DESC");
            if ($courses4 && pg_num_rows($courses4) > 0) {
                while ($c = pg_fetch_assoc($courses4)) {
                    echo '<h3>' . htmlspecialchars($c['title']) . ' (' . htmlspecialchars($c['course_code']) . ')</h3>';
                    // Materials
                    $mats = pg_query($conn, "SELECT * FROM materials WHERE course_id = " . $c['id'] . " ORDER BY uploaded_at DESC");
                    if ($mats && pg_num_rows($mats) > 0) {
                        echo '<b>Materials:</b><table><tr><th>Title</th><th>File</th><th>Uploaded</th></tr>';
                        while ($m = pg_fetch_assoc($mats)) {
                            echo '<tr><td>' . htmlspecialchars($m['title']) . '</td><td><a href="uploads/' . htmlspecialchars($m['file_path']) . '" target="_blank">Download</a></td><td>' . htmlspecialchars($m['uploaded_at']) . '</td></tr>';
                        }
                        echo '</table>';
                    } else {
                        echo '<b>No materials uploaded.</b>';
                    }
                    // Enrolled students
                    $studs = pg_query($conn, "SELECT u.username, u.email FROM enrollments e JOIN users u ON e.user_id = u.id WHERE e.course_id = " . $c['id']);
                    if ($studs && pg_num_rows($studs) > 0) {
                        echo '<b>Enrolled Students:</b><table><tr><th>Username</th><th>Email</th></tr>';
                        while ($s = pg_fetch_assoc($studs)) {
                            echo '<tr><td>' . htmlspecialchars($s['username']) . '</td><td>' . htmlspecialchars($s['email']) . '</td></tr>';
                        }
                        echo '</table>';
                    } else {
                        echo '<b>No students enrolled.</b>';
                    }
                    echo '<hr />';
                }
            } else {
                echo '<p>No courses found.</p>';
            }
            ?>
        </div>
    </div>
</body>

</html>