<?php
session_start();
include 'db_connect.php';

$error = '';
$login_failed = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $email_escaped = pg_escape_string($conn, $email);

    $query = "SELECT * FROM users WHERE email = '$email_escaped'";
    $result = pg_query($conn, $query);

    if ($result && pg_num_rows($result) === 1) {
        $user = pg_fetch_assoc($result);
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            header("Location: main.php");
            exit;
        } else {
            $error = "Invalid email or password.";
            $login_failed = true;
        }
    } else {
        $error = "Invalid email or password.";
        $login_failed = true;
    }
}

if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin_dashboard.php');
        exit;
    } elseif ($_SESSION['role'] === 'instructor') {
        header('Location: instructor_dashboard.php');
        exit;
    } elseif ($_SESSION['role'] === 'student') {
        header('Location: student_dashboard.php');
        exit;
    } else {
        header('Location: main.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>User Login</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #00b09b, #96c93d);
            margin: 0;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .login-container {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
            width: 320px;
        }

        .logo {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .logo img {
            height: 60px;
            width: auto;
            margin-bottom: 0.5rem;
        }

        .logo h1 {
            margin: 0;
            color: #00b09b;
            font-size: 1.8rem;
            font-weight: 700;
        }

        h2 {
            text-align: center;
            margin-bottom: 1rem;
            color: #00b09b;
        }

        label {
            display: block;
            margin-top: 1rem;
            color: #333;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 0.5rem;
            margin-top: 0.25rem;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        button {
            margin-top: 1.5rem;
            width: 100%;
            padding: 0.75rem;
            background: #00b09b;
            border: none;
            border-radius: 4px;
            color: white;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #089e8a;
        }

        .error-message {
            color: red;
            margin-top: 1rem;
            text-align: center;
            display: none;
        }

        .login-link {
            margin-top: 1rem;
            text-align: center;
        }

        .login-link a {
            color: #00b09b;
            text-decoration: none;
        }

        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="logo">
            <img src="assets/logo.png" alt="Cesus Logo">
        </div>
        <h2>User Login</h2>
        <form method="post" action="login.php" id="login-form">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required />

            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required />

            <div id="login-error" class="error-message"></div>
            <button type="submit" name="login">Login</button>
        </form>
        <div class="login-link">
            <p>Don't have an account? <a href="register.php">Register here</a></p>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            <?php if ($login_failed): ?>
                var errorDiv = document.getElementById('login-error');
                errorDiv.style.display = 'block';
                errorDiv.textContent = '<?php echo htmlspecialchars($error); ?>';
            <?php endif; ?>
        });
    </script>
</body>

</html>