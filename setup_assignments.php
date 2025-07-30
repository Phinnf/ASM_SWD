<?php
// Setup script for Assignment System
include 'db_connect.php';

echo "<h2>Setting up Assignment System Database Updates...</h2>";

// SQL commands to set up the assignment system
$sql_commands = "
-- Update assessments table to support assignments
-- Add missing fields for assignment functionality

-- Add max_points column if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'assessments' AND column_name = 'max_points'
    ) THEN
        ALTER TABLE assessments ADD COLUMN max_points INTEGER DEFAULT 100;
    END IF;
END$$;

-- Add feedback column to submissions if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'submissions' AND column_name = 'feedback'
    ) THEN
        ALTER TABLE submissions ADD COLUMN feedback TEXT;
    END IF;
END$$;

-- Add graded_at column to submissions if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'submissions' AND column_name = 'graded_at'
    ) THEN
        ALTER TABLE submissions ADD COLUMN graded_at TIMESTAMP;
    END IF;
END$$;

-- Insert sample assignment data
INSERT INTO assessments (course_id, title, description, due_date, type, max_points) 
SELECT 
    c.id as course_id,
    'Database Design Project' as title,
    'Create a comprehensive database design for an e-commerce system. Include ER diagrams, normalization, and SQL queries.' as description,
    (CURRENT_DATE + INTERVAL '7 days')::timestamp as due_date,
    'assignment' as type,
    100 as max_points
FROM courses c 
WHERE c.id = 1
ON CONFLICT DO NOTHING;

INSERT INTO assessments (course_id, title, description, due_date, type, max_points) 
SELECT 
    c.id as course_id,
    'C# Programming Assignment' as title,
    'Develop a console application that demonstrates object-oriented programming concepts including inheritance, polymorphism, and encapsulation.' as description,
    (CURRENT_DATE + INTERVAL '5 days')::timestamp as due_date,
    'assignment' as type,
    85 as max_points
FROM courses c 
WHERE c.id = 2
ON CONFLICT DO NOTHING;

-- Update existing submissions to have proper structure
UPDATE submissions SET 
    feedback = COALESCE(feedback, ''),
    graded_at = COALESCE(graded_at, submitted_at)
WHERE feedback IS NULL OR graded_at IS NULL;

SELECT 'Assessment table updated successfully!' as message;
";

// Execute the SQL commands
$result = pg_query($conn, $sql_commands);
if ($result) {
    echo "<p style='color: green;'>✓ Successfully executed assignment system setup</p>";
    echo "<p style='color: green;'>✓ Added max_points column to assessments table</p>";
    echo "<p style='color: green;'>✓ Added feedback column to submissions table</p>";
    echo "<p style='color: green;'>✓ Added graded_at column to submissions table</p>";
    echo "<p style='color: green;'>✓ Created sample assignments</p>";
    echo "<p style='color: green;'>✓ Updated existing data structure</p>";
} else {
    echo "<p style='color: red;'>✗ Error executing SQL: " . pg_last_error($conn) . "</p>";
}

echo "<h3 style='color: green;'>Assignment system setup completed!</h3>";
echo "<p><a href='assignments.php'>Go to Assignments</a> | <a href='main.php'>Go to Dashboard</a></p>";
?>

<!DOCTYPE html>
<html>

<head>
    <title>Assignment System Setup</title>
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
    <h1>Assignment System Setup</h1>
    <p>This script updates the database tables for the assignment system.</p>
</body>

</html>