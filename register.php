<?php
session_start();
include 'db_connect.php';

$msg = '';
$username = '';
$email = '';
$selected_role = 'student';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username']);
  $email = trim($_POST['email']);
  $password = trim($_POST['password']);
  $password_confirm = trim($_POST['password_confirm']);
  $role = $_POST['role'];

  // Password validation
  $pw_valid = strlen($password) >= 8 &&
    preg_match('/[A-Z]/', $password) &&
    preg_match('/[a-z]/', $password) &&
    preg_match('/[0-9]/', $password) &&
    preg_match('/[@#$%^&*!]/', $password);

  if ($username && $email && $password && $password_confirm) {
    if ($password !== $password_confirm) {
      $msg = '<span style="color:red;">Passwords do not match.</span>';
    } elseif (!$pw_valid) {
      $msg = '<span style="color:red;">Password must be at least 8 characters, contain uppercase, lowercase, number, and special character (@#$%^&*!).</span>';
    } elseif ($role === 'admin') {
      $msg = '<span style="color:red;">Admin registration is not allowed. Please contact system administrator.</span>';
    } else {
      // Check if username or email already exists
      $username_escaped = pg_escape_string($conn, $username);
      $email_escaped = pg_escape_string($conn, $email);

      $check = pg_query($conn, "SELECT 1 FROM users WHERE username='$username_escaped' OR email='$email_escaped'");
      if (pg_num_rows($check) > 0) {
        $msg = '<span style="color:red;">Username or email already exists.</span>';
      } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $created_at = date('Y-m-d H:i:s');
        $query = "INSERT INTO users (username, email, password, role, created_at) VALUES ('$username_escaped', '$email_escaped', '$hashed', '$role', '$created_at')";
        $res = pg_query($conn, $query);
        if ($res) {
          $msg = '<span style="color:green;">Registration successful! <a href="login.php">Login here</a></span>';
          // Clear form on success
          $username = '';
          $email = '';
          $selected_role = 'student';
        } else {
          $msg = '<span style="color:red;">Error registering account: ' . pg_last_error($conn) . '</span>';
        }
      }
    }
  } else {
    $msg = '<span style="color:red;">Please fill all fields.</span>';
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cesus - User Registration</title>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Roboto', sans-serif;
      background: linear-gradient(135deg, #00b09b 0%, #96c93d 100%);
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 20px;
    }

    .register-container {
      background: white;
      padding: 2.5rem;
      border-radius: 15px;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
      width: 100%;
      max-width: 450px;
      position: relative;
    }

    .logo {
      text-align: center;
      margin-bottom: 2rem;
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
      margin-bottom: 2rem;
      color: #333;
      font-weight: 500;
    }

    .form-group {
      margin-bottom: 1.5rem;
    }

    label {
      display: block;
      margin-bottom: 0.5rem;
      color: #555;
      font-weight: 500;
    }

    input[type="text"],
    input[type="email"],
    input[type="password"],
    select {
      width: 100%;
      padding: 0.75rem;
      border: 2px solid #e1e5e9;
      border-radius: 8px;
      font-size: 1rem;
      transition: border-color 0.3s, box-shadow 0.3s;
    }

    input[type="text"]:focus,
    input[type="email"]:focus,
    input[type="password"]:focus,
    select:focus {
      outline: none;
      border-color: #00b09b;
      box-shadow: 0 0 0 3px rgba(0, 176, 155, 0.1);
    }

    .role-selector {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 1rem;
      margin-bottom: 1.5rem;
    }

    .role-option {
      position: relative;
    }

    .role-option input[type="radio"] {
      display: none;
    }

    .role-option label {
      display: block;
      padding: 1rem;
      border: 2px solid #e1e5e9;
      border-radius: 8px;
      text-align: center;
      cursor: pointer;
      transition: all 0.3s;
      background: white;
    }

    .role-option input[type="radio"]:checked+label {
      border-color: #00b09b;
      background: #f0fdfa;
      color: #00b09b;
    }

    .role-icon {
      font-size: 1.5rem;
      margin-bottom: 0.5rem;
      display: block;
    }

    button {
      width: 100%;
      padding: 1rem;
      background: linear-gradient(135deg, #00b09b 0%, #96c93d 100%);
      border: none;
      border-radius: 8px;
      color: white;
      font-size: 1.1rem;
      font-weight: 600;
      cursor: pointer;
      transition: transform 0.3s, box-shadow 0.3s;
    }

    button:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(0, 176, 155, 0.3);
    }

    .message {
      margin: 1rem 0;
      padding: 1rem;
      border-radius: 8px;
      text-align: center;
      font-weight: 500;
    }

    .message.error {
      background: #fef2f2;
      color: #dc2626;
      border: 1px solid #fecaca;
    }

    .message.success {
      background: #f0fdf4;
      color: #16a34a;
      border: 1px solid #bbf7d0;
    }

    .login-link {
      margin-top: 2rem;
      text-align: center;
      color: #666;
    }

    .login-link a {
      color: #00b09b;
      text-decoration: none;
      font-weight: 600;
    }

    .login-link a:hover {
      text-decoration: underline;
    }

    .password-strength {
      margin-top: 0.5rem;
      font-size: 0.9rem;
    }

    .strength-bar {
      height: 4px;
      background: #e1e5e9;
      border-radius: 2px;
      margin-top: 0.5rem;
      overflow: hidden;
    }

    .strength-fill {
      height: 100%;
      transition: width 0.3s, background 0.3s;
    }

    .weak {
      background: #dc2626;
      width: 25%;
    }

    .fair {
      background: #f59e0b;
      width: 50%;
    }

    .good {
      background: #10b981;
      width: 75%;
    }

    .strong {
      background: #059669;
      width: 100%;
    }
  </style>
</head>

<body>
  <div class="register-container">
    <div class="logo">
      <img src="assets/logo.png" alt="Cesus Logo">
    </div>
    <h2>Create Your Account</h2>

    <?php if ($msg): ?>
      <div class="message <?php echo strpos($msg, 'successful') !== false ? 'success' : 'error'; ?>">
        <?php echo $msg; ?>
      </div>
    <?php endif; ?>

    <form method="post" action="register.php" id="registerForm">
      <div class="form-group">
        <label for="username">Full Name</label>
        <input type="text" id="username" name="username" required value="<?php echo htmlspecialchars($username); ?>"
          placeholder="Enter your full name">
      </div>

      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($email); ?>"
          placeholder="Enter your email">
      </div>

      <div class="form-group">
        <label>I am a:</label>
        <div class="role-selector">
          <div class="role-option">
            <input type="radio" id="role-student" name="role" value="student" <?php echo $selected_role === 'student' ? 'checked' : ''; ?>>
            <label for="role-student">
              <i class="fas fa-user-graduate role-icon"></i>
              Student
            </label>
          </div>
          <div class="role-option">
            <input type="radio" id="role-instructor" name="role" value="instructor" <?php echo $selected_role === 'instructor' ? 'checked' : ''; ?>>
            <label for="role-instructor">
              <i class="fas fa-chalkboard-teacher role-icon"></i>
              Instructor
            </label>
          </div>
        </div>
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required placeholder="Create a strong password">
        <div class="password-strength">
          <div class="strength-bar">
            <div class="strength-fill" id="strengthFill"></div>
          </div>
          <span id="strengthText">Password strength</span>
        </div>
      </div>

      <div class="form-group">
        <label for="password_confirm">Confirm Password</label>
        <input type="password" id="password_confirm" name="password_confirm" required
          placeholder="Confirm your password">
      </div>

      <button type="submit" name="register">Create Account</button>
    </form>

    <div class="login-link">
      <p>Already have an account? <a href="login.php">Sign in here</a></p>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const password = document.getElementById('password');
      const passwordConfirm = document.getElementById('password_confirm');
      const strengthFill = document.getElementById('strengthFill');
      const strengthText = document.getElementById('strengthText');
      const form = document.getElementById('registerForm');

      function checkPasswordStrength(password) {
        let strength = 0;
        let feedback = [];

        if (password.length >= 8) strength += 1;
        if (/[a-z]/.test(password)) strength += 1;
        if (/[A-Z]/.test(password)) strength += 1;
        if (/[0-9]/.test(password)) strength += 1;
        if (/[@#$%^&*!]/.test(password)) strength += 1;

        switch (strength) {
          case 0:
          case 1:
            return { class: 'weak', text: 'Very Weak' };
          case 2:
            return { class: 'fair', text: 'Fair' };
          case 3:
            return { class: 'good', text: 'Good' };
          case 4:
          case 5:
            return { class: 'strong', text: 'Strong' };
        }
      }

      function updatePasswordStrength() {
        const strength = checkPasswordStrength(password.value);
        strengthFill.className = 'strength-fill ' + strength.class;
        strengthText.textContent = strength.text;
      }

      function validateForm() {
        const pw = password.value;
        const pwConfirm = passwordConfirm.value;

        if (pw !== pwConfirm) {
          alert('Passwords do not match!');
          return false;
        }

        const strength = checkPasswordStrength(pw);
        if (strength.class === 'weak' || strength.class === 'fair') {
          alert('Please choose a stronger password!');
          return false;
        }

        return true;
      }

      password.addEventListener('input', updatePasswordStrength);
      form.addEventListener('submit', function (e) {
        if (!validateForm()) {
          e.preventDefault();
        }
      });
    });
  </script>
</body>

</html>