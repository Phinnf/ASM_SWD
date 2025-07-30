<?php
// Setup script for Quiz System
include 'db_connect.php';

echo "<h2>Setting up Quiz System Database Tables...</h2>";

// Read and execute the SQL file
$sql_file = 'create_quiz_tables.sql';
if (file_exists($sql_file)) {
    $sql_content = file_get_contents($sql_file);

    // Split SQL statements
    $statements = explode(';', $sql_content);

    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            $result = pg_query($conn, $statement);
            if ($result) {
                echo "<p style='color: green;'>✓ Executed: " . substr($statement, 0, 50) . "...</p>";
            } else {
                echo "<p style='color: red;'>✗ Error executing: " . substr($statement, 0, 50) . "...</p>";
                echo "<p style='color: red;'>Error: " . pg_last_error($conn) . "</p>";
            }
        }
    }

    echo "<h3 style='color: green;'>Quiz system setup completed!</h3>";
    echo "<p><a href='quizzes.php'>Go to Quizzes</a> | <a href='question_of_day.php'>Go to Question of Day</a></p>";
} else {
    echo "<p style='color: red;'>SQL file not found: $sql_file</p>";
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Quiz System Setup</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: #f4f8fb;
        }

        h2,
        h3 {
            color: #00b09b;
        }

        a {
            color: #00b09b;
            text-decoration: none;
            font-weight: bold;
        }

        a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <h1>Quiz System Setup</h1>
    <p>This script sets up the database tables for the quiz system.</p>
</body>

</html>