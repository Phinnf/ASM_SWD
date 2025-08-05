<?php
session_start();
include 'db_connect.php';

// Check if user is logged in and is instructor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    http_response_code(403);
    echo "Access denied. Only instructors can download submission files.";
    exit;
}

$user_id = $_SESSION['user_id'];
$filename = isset($_GET['file']) ? $_GET['file'] : '';
$submission_id = isset($_GET['submission_id']) ? (int) $_GET['submission_id'] : 0;

if (!$filename || !$submission_id) {
    http_response_code(400);
    echo "Invalid request parameters.";
    exit;
}

// Verify that the instructor has permission to access this submission
$permission_query = "SELECT 1 FROM submissions s 
                    JOIN assessments a ON s.assessment_id = a.id 
                    JOIN courses c ON a.course_id = c.id 
                    WHERE s.id = $submission_id AND c.instructor_id = $user_id";
$permission_result = pg_query($conn, $permission_query);

if (!$permission_result || pg_num_rows($permission_result) == 0) {
    http_response_code(403);
    echo "Access denied. You don't have permission to download this file.";
    exit;
}

// Sanitize filename to prevent directory traversal
$filename = basename($filename);
$file_path = 'uploads/assignments/' . $filename;

// Check if file exists
if (!file_exists($file_path)) {
    http_response_code(404);
    echo "File not found.";
    exit;
}

// Get file info
$file_info = pathinfo($file_path);
$original_name = $file_info['basename'];

// Set headers for file download
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $original_name . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

// Output file content
readfile($file_path);
exit;
?>