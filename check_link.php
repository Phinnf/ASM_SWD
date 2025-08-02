<?php
session_start();
include 'db_connect.php';

// Check if user is logged in and is instructor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check if URL is provided
if (!isset($_POST['url'])) {
    http_response_code(400);
    echo json_encode(['error' => 'URL is required']);
    exit;
}

$url = $_POST['url'];

// Validate URL format
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['valid' => false, 'reason' => 'Invalid URL format']);
    exit;
}

// Check if URL is accessible
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_NOBODY, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo json_encode(['valid' => false, 'reason' => 'Connection error: ' . $error]);
    exit;
}

if ($httpCode >= 200 && $httpCode < 400) {
    echo json_encode(['valid' => true, 'http_code' => $httpCode]);
} else {
    echo json_encode(['valid' => false, 'reason' => 'HTTP error: ' . $httpCode]);
}
?> 