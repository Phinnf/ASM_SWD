<?php
// Fix Duplicate Quiz Questions Script
include '../db_connect.php';

echo "<h2>Fixing Duplicate Quiz Questions...</h2>";

// First, let's check for duplicate questions
$check_duplicates_query = "
    SELECT quiz_id, question_text, COUNT(*) as duplicate_count
    FROM quiz_questions 
    GROUP BY quiz_id, question_text 
    HAVING COUNT(*) > 1
    ORDER BY quiz_id, question_text";

$duplicates_result = pg_query($conn, $check_duplicates_query);

if (pg_num_rows($duplicates_result) > 0) {
    echo "<h3>Found Duplicate Questions:</h3>";
    echo "<ul>";
    while ($duplicate = pg_fetch_assoc($duplicates_result)) {
        echo "<li>Quiz ID: {$duplicate['quiz_id']} - Question: " . htmlspecialchars(substr($duplicate['question_text'], 0, 50)) . "... (Count: {$duplicate['duplicate_count']})</li>";
    }
    echo "</ul>";

    // Fix duplicates by keeping only the first occurrence
    $fix_duplicates_query = "
        DELETE FROM quiz_questions 
        WHERE id NOT IN (
            SELECT MIN(id) 
            FROM quiz_questions 
            GROUP BY quiz_id, question_text
        )";

    $fix_result = pg_query($conn, $fix_duplicates_query);

    if ($fix_result) {
        echo "<p style='color: green;'>✓ Successfully removed duplicate questions!</p>";
    } else {
        echo "<p style='color: red;'>✗ Error removing duplicates: " . pg_last_error($conn) . "</p>";
    }
} else {
    echo "<p style='color: green;'>✓ No duplicate questions found!</p>";
}

// Now let's create proper quiz tables if they don't exist
echo "<h3>Setting up Quiz Tables...</h3>";

$create_tables_sql = "
-- Create quizzes table if it doesn't exist
CREATE TABLE IF NOT EXISTS quizzes (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(100),
    time_limit INTEGER DEFAULT 30,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create quiz_questions table if it doesn't exist
CREATE TABLE IF NOT EXISTS quiz_questions (
    id SERIAL PRIMARY KEY,
    quiz_id INTEGER REFERENCES quizzes(id) ON DELETE CASCADE,
    question_text TEXT NOT NULL,
    option_a VARCHAR(255) NOT NULL,
    option_b VARCHAR(255) NOT NULL,
    option_c VARCHAR(255) NOT NULL,
    option_d VARCHAR(255) NOT NULL,
    correct_answer CHAR(1) NOT NULL CHECK (correct_answer IN ('A', 'B', 'C', 'D')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create quiz_results table if it doesn't exist
CREATE TABLE IF NOT EXISTS quiz_results (
    id SERIAL PRIMARY KEY,
    quiz_id INTEGER REFERENCES quizzes(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    score INTEGER NOT NULL,
    total_questions INTEGER NOT NULL,
    percentage DECIMAL(5,2) NOT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add unique constraint to prevent future duplicates
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint 
        WHERE conname = 'unique_quiz_question'
    ) THEN
        ALTER TABLE quiz_questions 
        ADD CONSTRAINT unique_quiz_question 
        UNIQUE (quiz_id, question_text);
    END IF;
END$$;

-- Add indexes for better performance
CREATE INDEX IF NOT EXISTS idx_quiz_questions_quiz_id ON quiz_questions(quiz_id);
CREATE INDEX IF NOT EXISTS idx_quiz_results_quiz_id ON quiz_results(quiz_id);
CREATE INDEX IF NOT EXISTS idx_quiz_results_user_id ON quiz_results(user_id);
CREATE INDEX IF NOT EXISTS idx_quizzes_created_by ON quizzes(created_by);
";

// Execute the SQL statements
$statements = explode(';', $create_tables_sql);
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

// Insert sample quiz data if no quizzes exist
$check_quizzes = pg_query($conn, "SELECT COUNT(*) as count FROM quizzes");
$quiz_count = pg_fetch_assoc($check_quizzes)['count'];

if ($quiz_count == 0) {
    echo "<h3>Creating Sample Quiz Data...</h3>";

    // Insert sample quiz
    $insert_quiz = "INSERT INTO quizzes (title, description, category, time_limit, created_by) 
                    VALUES ('Sample Programming Quiz', 'Test your programming knowledge', 'Programming', 15, 1)";
    $quiz_result = pg_query($conn, $insert_quiz);

    if ($quiz_result) {
        $quiz_id = pg_last_oid($quiz_result);
        echo "<p style='color: green;'>✓ Created sample quiz with ID: $quiz_id</p>";

        // Insert sample questions
        $sample_questions = [
            [
                'question' => 'What does HTML stand for?',
                'option_a' => 'Hyper Text Markup Language',
                'option_b' => 'High Tech Modern Language',
                'option_c' => 'Home Tool Markup Language',
                'option_d' => 'Hyperlink and Text Markup Language',
                'correct' => 'A'
            ],
            [
                'question' => 'Which programming language is known as the "language of the web"?',
                'option_a' => 'Java',
                'option_b' => 'Python',
                'option_c' => 'JavaScript',
                'option_d' => 'C++',
                'correct' => 'C'
            ],
            [
                'question' => 'What is the purpose of CSS?',
                'option_a' => 'To create databases',
                'option_b' => 'To style web pages',
                'option_c' => 'To write server code',
                'option_d' => 'To create animations',
                'correct' => 'B'
            ]
        ];

        foreach ($sample_questions as $question) {
            $insert_question = "INSERT INTO quiz_questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_answer) 
                               VALUES ($quiz_id, '{$question['question']}', '{$question['option_a']}', '{$question['option_b']}', '{$question['option_c']}', '{$question['option_d']}', '{$question['correct']}')";
            $question_result = pg_query($conn, $insert_question);

            if ($question_result) {
                echo "<p style='color: green;'>✓ Added question: " . htmlspecialchars(substr($question['question'], 0, 30)) . "...</p>";
            } else {
                echo "<p style='color: red;'>✗ Error adding question: " . pg_last_error($conn) . "</p>";
            }
        }
    } else {
        echo "<p style='color: red;'>✗ Error creating sample quiz: " . pg_last_error($conn) . "</p>";
    }
} else {
    echo "<p style='color: green;'>✓ Quiz data already exists ($quiz_count quizzes found)</p>";
}

echo "<h3 style='color: green;'>Quiz system cleanup completed!</h3>";
echo "<p><a href='../quizzes.php'>Go to Quizzes</a> | <a href='../index.php'>Go to Home</a></p>";
?>

<!DOCTYPE html>
<html>

<head>
    <title>Fix Duplicate Quiz Questions</title>
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

        ul {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        li {
            margin-bottom: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
    </style>
</head>

<body>
    <h1>Quiz System Cleanup</h1>
    <p>This script fixes duplicate quiz questions and sets up the quiz system properly.</p>
</body>

</html>