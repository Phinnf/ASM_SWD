<?php
session_start();
include 'db_connect.php';

// Redirect if not logged in or not student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: index.php');
    exit;
}
$user_id = $_SESSION['user_id'];

// Get student's enrolled courses
$courses = pg_query($conn, "SELECT c.* FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE e.user_id = $user_id ORDER BY c.created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Student Dashboard</title>
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
        <h2>Student</h2>
        <a href="#courses">My Courses</a>
        <a href="main.php">&larr; Return to Menu</a>
        <a href="logout.php">Logout</a>
    </div>
    <div class="main-content">
        <div class="section" id="courses">
            <h2>My Courses</h2>
            <?php
            if ($courses && pg_num_rows($courses) > 0) {
                echo '<table><tr><th>Course Code</th><th>Title</th><th>Description</th><th>Materials</th></tr>';
                while ($c = pg_fetch_assoc($courses)) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($c['course_code']) . '</td>';
                    echo '<td>' . htmlspecialchars($c['title']) . '</td>';
                    echo '<td>' . htmlspecialchars($c['description']) . '</td>';
                    // Materials
                    $mats = pg_query($conn, "SELECT * FROM materials WHERE course_id = " . $c['id'] . " ORDER BY uploaded_at DESC");
                    echo '<td>';
                    if ($mats && pg_num_rows($mats) > 0) {
                        echo '<ul style="margin:0; padding-left:18px;">';
                        while ($m = pg_fetch_assoc($mats)) {
                            echo '<li><a href="uploads/' . htmlspecialchars($m['file_path']) . '" target="_blank">' . htmlspecialchars($m['title']) . '</a></li>';
                        }
                        echo '</ul>';
                    } else {
                        echo 'No materials';
                    }
                    echo '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<p>No enrolled courses found.</p>';
            }
            ?>
        </div>
    </div>
</body>

</html>