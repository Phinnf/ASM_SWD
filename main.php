<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: index.php");
  exit;
}
$username = $_SESSION['username'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <title>Main Page - LMS</title>
  <style>
    body {
      font-family: Arial, sans-serif;
    }

    .menu-bar {
      background-color: #00b09b;
      overflow: hidden;
    }

    .menu-bar a {
      float: left;
      color: white;
      padding: 14px 20px;
      text-decoration: none;
    }

    .menu-bar a:hover {
      background-color: #089e8a;
    }

    .dropdown {
      float: left;
      overflow: hidden;
    }

    .dropdown .dropbtn {
      font-size: 16px;
      border: none;
      outline: none;
      color: white;
      padding: 14px 20px;
      background-color: inherit;
      cursor: pointer;
    }

    .dropdown-content {
      display: none;
      position: absolute;
      background-color: #00b09b;
      min-width: 160px;
      z-index: 1;
    }

    .dropdown-content a {
      float: none;
      color: white;
      padding: 12px 16px;
      text-decoration: none;
      display: block;
      text-align: left;
    }

    .dropdown-content a:hover {
      background-color: #089e8a;
    }

    .dropdown:hover .dropdown-content {
      display: block;
    }

    .welcome {
      padding: 10px;
      color: #333;
    }
  </style>
</head>

<body>

  <div class="menu-bar">
    <a href="main.php">Home</a>
    <div class="dropdown">
      <button class="dropbtn">Courses</button>
      <div class="dropdown-content">
        <a href="my_courses.php">My Courses</a>
      </div>
    </div>
    <div class="dropdown">
      <button class="dropbtn">Assessments</button>
      <div class="dropdown-content">
        <a href="quizzes.php">Quizzes</a>
        <a href="assignments.php">Assignments</a>
      </div>
    </div>
    <a href="messages.php">Messages</a>
    <a href="analytics.php">Analytics</a>
    <a href="logout.php" style="float:right;">Logout</a>
  </div>

  <div class="welcome">
    <h2>Welcome, <?php echo htmlspecialchars($username); ?>!</h2>
    <p>Select an option from the menu to get started.</p>
  </div>

</body>

</html>