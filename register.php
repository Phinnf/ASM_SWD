<?php
session_start();
include 'db_connect.php';

$msg = '';
$username = '';
$email = '';

if (isset($_POST['register'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $password_confirm = trim($_POST['password_confirm']);
    $role = 'student'; // or let user choose

    // Password validation
    $pw_valid = strlen($password) > 6 &&
                preg_match('/[A-Z]/', $password) &&
                preg_match('/[@]/', $password);

    if ($username && $email && $password && $password_confirm) {
        if ($password !== $password_confirm) {
            $msg = '<span style="color:red;">Passwords do not match.</span>';
        } elseif (!$pw_valid) {
            $msg = '<span style="color:red;">Password must be >6 characters, contain an uppercase letter and "@" symbol.</span>';
        } else {
            // Check if username or email already exists
            $check = pg_query($conn, "SELECT 1 FROM users WHERE username='$username' OR email='$email'");
            if (pg_num_rows($check) > 0) {
                $msg = '<span style="color:red;">Username or email already exists.</span>';
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $query = "INSERT INTO users (username, email, password, role) VALUES ('$username', '$email', '$hashed', '$role')";
                $res = pg_query($conn, $query);
                $msg = $res ? '<span style="color:green;">Registration successful! <a href="index.php">Login here</a></span>' : '<span style="color:red;">Error registering account.</span>';
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
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>User Registration</title>
  <style>
    body {
      margin: 0;
      height: 100vh;
      background: linear-gradient(135deg, #00b09b, #96c93d);
      font-family: Arial, sans-serif;
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .register-container {
      background: white;
      padding: 2rem;
      border-radius: 8px;
      box-shadow: 0 8px 16px rgba(0, 0, 0, 0.25);
      width: 320px;
    }

    h2 {
      margin-bottom: 1rem;
      color: #333;
      text-align: center;
    }

    label {
      display: block;
      margin-top: 1rem;
      color: #333;
    }

    input[type="text"],
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

    .message {
      margin-top: 1rem;
      text-align: center;
      color: red;
    }

    .success {
      color: green;
    }
  </style>
  <script>
  document.addEventListener('DOMContentLoaded', function () {
    const pw = document.getElementById('password');
    const pwConfirm = document.getElementById('password_confirm');
    const errorDiv = document.getElementById('pw-error');

    function checkPwMatch() {
      let errors = [];
      if (pw.value.length <= 6) {
        errors.push('Password must be more than 6 characters.');
      }
      if (!/[A-Z]/.test(pw.value)) {
        errors.push('Password must contain an uppercase letter.');
      }
      if (!/[^a-zA-Z0-9]/.test(pw.value)) {
        errors.push('Password must contain a special symbol.');
      }
      if (pw.value && pwConfirm.value && pw.value !== pwConfirm.value) {
        errors.push('Passwords do not match.');
      }
      errorDiv.innerHTML = errors.join('<br>');
    }

    pw.addEventListener('input', checkPwMatch);
    pwConfirm.addEventListener('input', checkPwMatch);
  });

  function validateForm() {
    const pw = document.getElementById('password').value;
    const pwConfirm = document.getElementById('password_confirm').value;
    let errors = [];
    if (pw.length <= 6) {
      errors.push('Password must be more than 6 characters.');
    }
    if (!/[A-Z]/.test(pw)) {
      errors.push('Password must contain an uppercase letter.');
    }
    if (!/[^a-zA-Z0-9]/.test(pw)) {
      errors.push('Password must contain a special symbol.');
    }
    if (pw !== pwConfirm) {
      errors.push('Passwords do not match.');
    }
    if (errors.length > 0) {
      document.getElementById('pw-error').innerHTML = errors.join('<br>');
      return false;
    }
    return true;
  }
  </script>
</head>

<body>
  <div class="register-container">
    <h2>User Registration</h2>
    <?php if ($msg): ?>
      <div class="message"><?php echo $msg; ?></div>
    <?php endif; ?>
    <form method="post" action="register.php" onsubmit="return validateForm();">
      <label for="username">Username:</label>
      <input type="text" id="username" name="username" required value="<?php echo htmlspecialchars($username); ?>" />

      <label for="email">Email:</label>
      <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($email); ?>" />

      <label for="password">Password:</label>
      <input type="password" id="password" name="password" required />

      <label for="password_confirm">Re-enter Password:</label>
      <input type="password" id="password_confirm" name="password_confirm" required />
      <div id="pw-error" style="color:red; margin-top:0.5rem;"></div>
      <button type="submit" name="register">Register</button>
    </form>
    <div class="login-link">
      <p>Already have an account? <a href="index.php">Login here</a></p>
    </div>
  </div>
</body>

</html>
<?php
include 'db_connect.php';

$username = '';
$email = '';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username']);
  $email = trim($_POST['email']);
  $password = $_POST['password'];
  $password_confirm = $_POST['password_confirm'];

  if (empty($username) || empty($email) || empty($password) || empty($password_confirm)) {
    $error = "Please fill all fields.";
  } else if ($password !== $password_confirm) {
    $error = "Passwords do not match.";
  } else if (
    strlen($password) < 10 ||
    !preg_match('/[a-z]/', $password) ||
    !preg_match('/[A-Z]/', $password) ||
    !preg_match('/[\@\$\#\%\!\&\*\?\_\-]/', $password) ||
    preg_match('/\s/', $password)
  ) {
    $error = "Password must be at least 10 characters, contain uppercase and lowercase letters, at least one special character (@, $, #, %, !, &, *, ?, _, -), and no spaces.";
  } else {
    $email_escaped = pg_escape_string($conn, $email);
    $username_escaped = pg_escape_string($conn, $username);

    $check_query = "SELECT * FROM users WHERE email = '$email_escaped'";
    $check_result = pg_query($conn, $check_query);

    if (pg_num_rows($check_result) > 0) {
      $error = "Email already registered.";
    } else {
      $password_hash = password_hash($password, PASSWORD_DEFAULT);
      $created_at = date('Y-m-d H:i:s');

      $insert_query = "INSERT INTO users (username, email, password, created_at) VALUES ('$username_escaped', '$email_escaped', '$password_hash', '$created_at')";
      $insert_result = pg_query($conn, $insert_query);

      if ($insert_result) {
        $success = "Registration successful. You can now <a href='index.php'>login</a>.";
        // Clear inputs on success
        $username = '';
        $email = '';
      } else {
        $error = "Registration failed. Please try again.";
      }
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>User Registration</title>
  <style>
    body {
      margin: 0;
      height: 100vh;
      background: linear-gradient(135deg, #00b09b, #96c93d);
      font-family: Arial, sans-serif;
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .register-container {
      background: white;
      padding: 2rem;
      border-radius: 8px;
      box-shadow: 0 8px 16px rgba(0, 0, 0, 0.25);
      width: 320px;
    }

    h2 {
      margin-bottom: 1rem;
      color: #333;
      text-align: center;
    }

    label {
      display: block;
      margin-top: 1rem;
      color: #333;
    }

    input[type="text"],
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

    .message {
      margin-top: 1rem;
      text-align: center;
      color: red;
    }

    .success {
      color: green;
    }
  </style>
  <script>
    // Client-side password confirmation validation
    function validateForm() {
      const pw = document.getElementById('password').value;
      const pwConfirm = document.getElementById('password_confirm').value;
      if (pw !== pwConfirm) {
        ('Passwords do not match.');
        return false;
      }
      return true;
    }
  </script>
</head>

<body>
  <div class="register-container">
    <h2>User Registration</h2>
    <?php if (!empty($error)): ?>
      <p class="message"><?php echo htmlspecialchars($error); ?></p>
    <?php elseif (!empty($success)): ?>
      <p class="message success"><?php echo $success; ?></p>
    <?php endif; ?>
    <form method="post" action="register.php" onsubmit="return validateForm();">
      <label for="username">Username:</label>
      <input type="text" id="username" name="username" required value="<?php echo htmlspecialchars($username); ?>" />

      <label for="email">Email:</label>
      <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($email); ?>" />

      <label for="password">Password:</label>
      <input type="password" id="password" name="password" required />

      <label for="password_confirm">Re-enter Password:</label>
      <input type="password" id="password_confirm" name="password_confirm" required />

      <button type="submit">Register</button>
    </form>
    <div class="login-link">
      <p>Already have an account? <a href="index.php">Login here</a></p>
    </div>
  </div>
</body>

</html>