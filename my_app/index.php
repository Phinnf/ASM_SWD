<?php
// index.php - Login page with green linear gradient background
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>User Login</title>
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
  .login-container {
    background: white;
    padding: 2rem;
    border-radius: 8px;
    box-shadow: 0 8px 16px rgba(0,0,0,0.25);
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
  .register-link {
    margin-top: 1rem;
    text-align: center;
  }
  .register-link a {
    color: #00b09b;
    text-decoration: none;
  }
  .register-link a:hover {
    text-decoration: underline;
  }
</style>
</head>
<body>
  <div class="login-container">
    <h2>User Login</h2>
    <form method="post" action="login.php">
      <label for="email">Email:</label>
      <input type="email" id="email" name="email" required />

      <label for="password">Password:</label>
      <input type="password" id="password" name="password" required />

      <button type="submit">Login</button>
    </form>
    <div class="register-link">
      <p>Don't have an account? <a href="register.php">Register here</a></p>
    </div>
  </div>
</body>
</html>
