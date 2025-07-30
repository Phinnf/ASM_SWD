<?php
// Fix duplicate quizzes script
include 'db_connect.php';

echo "<h2>Fixing Duplicate Quiz Entries...</h2>";

// First, let's see what duplicates we have
$duplicate_check = "SELECT title, COUNT(*) as count FROM quizzes GROUP BY title HAVING COUNT(*) > 1";
$duplicate_result = pg_query($conn, $duplicate_check);

if (pg_num_rows($duplicate_result) > 0) {
    echo "<h3>Found duplicate quizzes:</h3>";
    while ($row = pg_fetch_assoc($duplicate_result)) {
        echo "<p>• {$row['title']} - {$row['count']} duplicates</p>";
    }

    // Remove duplicates, keeping only the first one for each title
    $remove_duplicates = "
        DELETE FROM quizzes 
        WHERE id NOT IN (
            SELECT MIN(id) 
            FROM quizzes 
            GROUP BY title
        )
    ";

    $result = pg_query($conn, $remove_duplicates);
    if ($result) {
        echo "<p style='color: green;'>✓ Removed duplicate quiz entries</p>";
    } else {
        echo "<p style='color: red;'>✗ Error removing duplicates: " . pg_last_error($conn) . "</p>";
    }
} else {
    echo "<p style='color: green;'>✓ No duplicate quizzes found</p>";
}

// Add unique constraint to prevent future duplicates
$add_constraint = "ALTER TABLE quizzes ADD CONSTRAINT unique_quiz_title UNIQUE (title)";
$result = pg_query($conn, $add_constraint);
if ($result) {
    echo "<p style='color: green;'>✓ Added unique constraint on quiz titles</p>";
} else {
    echo "<p style='color: orange;'>⚠ Constraint might already exist: " . pg_last_error($conn) . "</p>";
}

// Update the quiz questions to point to the correct quiz IDs
echo "<h3>Fixing quiz questions...</h3>";
$fix_questions = "
    UPDATE quiz_questions 
    SET quiz_id = (
        SELECT MIN(q.id) 
        FROM quizzes q 
        WHERE q.title = (
            SELECT title 
            FROM quizzes 
            WHERE id = quiz_questions.quiz_id
        )
    )
    WHERE quiz_id IN (
        SELECT q.id 
        FROM quizzes q 
        WHERE q.id NOT IN (
            SELECT MIN(id) 
            FROM quizzes 
            GROUP BY title
        )
    )
";
$result = pg_query($conn, $fix_questions);
if ($result) {
    echo "<p style='color: green;'>✓ Fixed quiz question references</p>";
} else {
    echo "<p style='color: orange;'>⚠ No question references to fix</p>";
}

// Clean up orphaned quiz questions
$clean_orphans = "DELETE FROM quiz_questions WHERE quiz_id NOT IN (SELECT id FROM quizzes)";
$result = pg_query($conn, $clean_orphans);
if ($result) {
    echo "<p style='color: green;'>✓ Cleaned up orphaned questions</p>";
} else {
    echo "<p style='color: orange;'>⚠ No orphaned questions found</p>";
}

// Show final quiz count
$final_count = pg_query($conn, "SELECT COUNT(*) as count FROM quizzes");
$count = pg_fetch_assoc($final_count);
echo "<h3 style='color: green;'>✓ Database cleanup completed!</h3>";
echo "<p>Total quizzes remaining: <strong>{$count['count']}</strong></p>";

echo "<p><a href='quizzes.php'>Go to Quizzes</a> | <a href='setup_quiz_system.php'>Re-run Setup</a></p>";
?>

<!DOCTYPE html>
<html>

<head>
    <title>Fix Duplicate Quizzes</title>
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

        p {
            margin: 10px 0;
        }
    </style>
</head>

<body>
    <h1>Fix Duplicate Quizzes</h1>
    <p>This script removes duplicate quiz entries and prevents future duplicates.</p>
</body>

</html>