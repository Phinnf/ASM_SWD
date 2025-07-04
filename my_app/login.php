<?php
session_start();
include 'db_connect.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $email_escaped = pg_escape_string($conn, $email);

    $query = "SELECT * FROM users WHERE email = '$email_escaped'";
    $result = pg_query($conn, $query);

    if ($result && pg_num_rows($result) === 1) {
        $user = pg_fetch_assoc($result);
        if (password_verify($password, $user['password'])) {
            // Login success
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            header("Location: main.php"); // Change to your landing page
            exit;
        } else {
            $error = "Invalid email or password.";
        }
    } else {
        $error = "Invalid email or password.";
    }
}

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

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Login Result</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 2rem;
            background: #f4f4f4;
        }

        .message {
            max-width: 400px;
            margin: 2rem auto;
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        a {
            color: #00b09b;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="message">
        <h2>Login Status</h2>
        <p><?php echo $error ? htmlspecialchars($error) : 'Please enter your credentials.'; ?></p>
        <p><a href="index.php">Back to login</a></p>
    </div>
</body>

</html>