<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: index.php');
    exit;
}
if ($_SESSION['role'] === 'instructor') {
    header('Location: instructor_courses.php');
    exit;
} elseif ($_SESSION['role'] === 'student') {
    header('Location: student_courses.php');
    exit;
} else {
    // Optionally handle other roles
    header('Location: main.php');
    exit;
}
?>
