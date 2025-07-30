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
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Roboto', Arial, sans-serif;
      background: #f4f8fb;
      margin: 0;
      padding: 0;
    }

    .menu-bar {
      background: linear-gradient(90deg, #00b09b 0%, #96c93d 100%);
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
      padding: 0 30px;
      display: flex;
      align-items: center;
      height: 60px;
    }

    .menu-bar a,
    .menu-bar .dropbtn {
      color: white;
      padding: 0 18px;
      text-decoration: none;
      font-size: 17px;
      line-height: 60px;
      background: none;
      border: none;
      cursor: pointer;
      font-weight: 500;
      transition: background 0.2s;
    }

    .menu-bar a:hover,
    .menu-bar .dropbtn:hover {
      background: rgba(0, 0, 0, 0.08);
      border-radius: 6px;
    }

    .menu-bar .dropdown {
      position: relative;
      display: inline-block;
    }

    .menu-bar .dropdown-content {
      display: none;
      position: absolute;
      background: #fff;
      min-width: 170px;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
      z-index: 1;
      border-radius: 8px;
      margin-top: 8px;
      overflow: hidden;
    }

    .menu-bar .dropdown-content a {
      color: #333;
      padding: 12px 18px;
      text-decoration: none;
      display: block;
      background: #fff;
      transition: background 0.2s;
    }

    .menu-bar .dropdown-content a:hover {
      background: #f4f8fb;
    }

    .menu-bar .dropdown.open .dropdown-content {
      display: block;
    }

    .menu-bar .logout {
      margin-left: auto;
      background: #ff5e62;
      background: linear-gradient(90deg, #ff9966 0%, #ff5e62 100%);
      border-radius: 6px;
      font-weight: 700;
      transition: background 0.2s;
    }

    .hero {
      background: linear-gradient(90deg, #00b09b 0%, #96c93d 100%);
      color: white;
      padding: 56px 0 40px 0;
      text-align: center;
      box-shadow: 0 4px 24px rgba(0, 0, 0, 0.04);
      margin-bottom: 0;
    }

    .hero h1 {
      font-size: 2.6rem;
      margin: 0 0 10px 0;
      font-weight: 700;
      letter-spacing: 1px;
    }

    .hero p {
      font-size: 1.2rem;
      margin: 0;
      opacity: 0.95;
    }

    .dashboard {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      align-items: stretch;
      gap: 40px 36px;
      margin: 48px auto 0 auto;
      max-width: 1300px;
      min-height: 60vh;
      padding: 48px 36px 48px 36px;
    }

    .card {
      background: #f8fafc;
      border-radius: 18px;
      box-shadow: 0 4px 24px rgba(0, 0, 0, 0.07);
      padding: 44px 32px 36px 32px;
      width: 270px;
      min-height: 320px;
      text-align: center;
      transition: transform 0.18s, box-shadow 0.18s;
      cursor: pointer;
      text-decoration: none;
      color: #222;
      position: relative;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: flex-start;
    }

    .card:hover {
      transform: translateY(-6px) scale(1.03);
      box-shadow: 0 8px 32px rgba(0, 176, 155, 0.13);
      background: linear-gradient(90deg, #e0ffe7 0%, #f4f8fb 100%);
    }

    .card .icon {
      font-size: 2.6rem;
      margin-bottom: 12px;
      display: block;
    }

    .card-title {
      font-size: 1.18rem;
      font-weight: 700;
      margin-bottom: 8px;
      letter-spacing: 0.5px;
    }

    .card-desc {
      font-size: 0.98rem;
      color: #666;
      opacity: 0.85;
    }

    @media (max-width: 1100px) {
      .dashboard {
        max-width: 98vw;
        padding: 24px 2vw 24px 2vw;
        gap: 24px 12px;
      }

      .card {
        width: 90vw;
        min-width: 220px;
        max-width: 98vw;
        padding: 28px 2vw 20px 2vw;
      }
    }

    @media (max-width: 700px) {
      .dashboard {
        flex-direction: column;
        align-items: center;
        gap: 18px;
        padding: 10px 0 10px 0;
      }

      .card {
        width: 96vw;
        min-width: 0;
        padding: 18px 2vw 14px 2vw;
        min-height: 220px;
      }
    }
  </style>
  <!-- Icons: Font Awesome CDN -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
  <div class="menu-bar">
    <a href="main.php"><i class="fa fa-home"></i> Home</a>
    <div class="dropdown">
      <button class="dropbtn"><i class="fa fa-book"></i> Courses</button>
      <div class="dropdown-content">
        <a href="my_courses.php">My Courses</a>
      </div>
    </div>
    <div class="dropdown">
      <button class="dropbtn"><i class="fa fa-tasks"></i> Assessments</button>
      <div class="dropdown-content">
        <a href="quizzes.php">Quizzes</a>
        <a href="assignments.php">Assignments</a>
      </div>
    </div>
    <a href="messages.php"><i class="fa fa-envelope"></i> Messages</a>
    <a href="profile.php"><i class="fa fa-user"></i> Profile</a>
    <a href="analytics.php"><i class="fa fa-chart-bar"></i> Analytics</a>
    <a href="logout.php" class="logout"><i class="fa fa-sign-out-alt"></i> Logout</a>
  </div>

  <div class="hero">
    <h1>Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
    <p>Your personalized Learning Management System dashboard.<br> Select an option below to get started.</p>
  </div>

  <div class="dashboard">
    <!-- Pomodoro Timer Card -->
    <div class="card" style="position: relative; overflow: visible; min-width: 260px;">
      <span class="icon"><i class="fa fa-clock"></i></span>
      <div class="card-title">Pomodoro Timer</div>
      <div style="display: flex; flex-direction: column; align-items: center;">
        <svg id="pomo-circle" width="140" height="140" style="margin-bottom: 10px;">
          <circle cx="70" cy="70" r="65" stroke="#eee" stroke-width="8" fill="none" />
          <circle id="pomo-progress" cx="70" cy="70" r="65" stroke="#00b09b" stroke-width="8" fill="none"
            stroke-linecap="round" stroke-dasharray="408" stroke-dashoffset="0" />
        </svg>
        <div id="pomo-time" style="font-size:2.2rem; font-weight:700; margin-bottom:10px;">25:00</div>
        <div style="display:flex; gap:10px;">
          <button id="pomo-start"
            style="background:#4285f4;color:#fff;border:none;border-radius:20px;padding:8px 22px;font-weight:600;cursor:pointer;">Start</button>
          <button id="pomo-pause"
            style="background:#f4f8fb;color:#222;border:1px solid #4285f4;border-radius:20px;padding:8px 22px;font-weight:600;cursor:pointer;display:none;">Pause</button>
          <button id="pomo-reset"
            style="background:none;color:#ff5e62;border:1px solid #ff5e62;border-radius:20px;padding:8px 22px;font-weight:600;cursor:pointer;">Reset</button>
        </div>
      </div>
    </div>
    <a class="card" href="my_courses.php">
      <span class="icon"><i class="fa fa-book-open"></i></span>
      <div class="card-title">My Courses</div>
      <div class="card-desc">View and manage your enrolled courses.</div>
    </a>
    <a class="card" href="quizzes.php">
      <span class="icon"><i class="fa fa-question-circle"></i></span>
      <div class="card-title">Quizzes</div>
      <div class="card-desc">Take quizzes and check your progress.</div>
    </a>

    <a class="card" href="assignments.php">
      <span class="icon"><i class="fa fa-file-alt"></i></span>
      <div class="card-title">Assignments</div>
      <div class="card-desc">Submit and review your assignments.</div>
    </a>
    <a class="card" href="messages.php">
      <span class="icon"><i class="fa fa-envelope"></i></span>
      <div class="card-title">Messages</div>
      <div class="card-desc">Check messages from instructors and peers.</div>
    </a>
    <a class="card" href="analytics.php">
      <span class="icon"><i class="fa fa-chart-bar"></i></span>
      <div class="card-title">Analytics</div>
      <div class="card-desc">Track your learning analytics and stats.</div>
    </a>
    <a class="card" href="profile.php">
      <span class="icon"><i class="fa fa-user"></i></span>
      <div class="card-title">My Profile</div>
      <div class="card-desc">Manage your account settings and view activity.</div>
    </a>
  </div>

  <script>
    // Dropdown toggle logic
    document.querySelectorAll('.menu-bar .dropdown .dropbtn').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        // Close other open dropdowns
        document.querySelectorAll('.menu-bar .dropdown').forEach(function (drop) {
          if (drop !== btn.parentElement) {
            drop.classList.remove('open');
          }
        });
        // Toggle this dropdown
        btn.parentElement.classList.toggle('open');
      });
    });
    // Close dropdowns when clicking outside
    document.addEventListener('click', function () {
      document.querySelectorAll('.menu-bar .dropdown').forEach(function (drop) {
        drop.classList.remove('open');
      });
    });

    // Pomodoro Timer Logic
    const pomoTime = document.getElementById('pomo-time');
    const pomoStart = document.getElementById('pomo-start');
    const pomoPause = document.getElementById('pomo-pause');
    const pomoReset = document.getElementById('pomo-reset');
    const pomoProgress = document.getElementById('pomo-progress');
    let pomoTotal = 25 * 60; // 25 minutes
    let pomoCurrent = pomoTotal;
    let pomoInterval = null;
    const circleLen = 2 * Math.PI * 65;
    pomoProgress.setAttribute('stroke-dasharray', circleLen);
    pomoProgress.setAttribute('stroke-dashoffset', 0);
    function updatePomoDisplay() {
      const min = Math.floor(pomoCurrent / 60).toString().padStart(2, '0');
      const sec = (pomoCurrent % 60).toString().padStart(2, '0');
      pomoTime.textContent = `${min}:${sec}`;
      const percent = 1 - (pomoCurrent / pomoTotal);
      pomoProgress.setAttribute('stroke-dashoffset', percent * circleLen);
    }
    function startPomo() {
      if (pomoInterval) return;
      pomoInterval = setInterval(() => {
        if (pomoCurrent > 0) {
          pomoCurrent--;
          updatePomoDisplay();
        } else {
          clearInterval(pomoInterval);
          pomoInterval = null;
          pomoStart.style.display = 'inline-block';
          pomoPause.style.display = 'none';
          alert('Pomodoro complete!');
        }
      }, 1000);
      pomoStart.style.display = 'none';
      pomoPause.style.display = 'inline-block';
    }
    function pausePomo() {
      if (pomoInterval) {
        clearInterval(pomoInterval);
        pomoInterval = null;
        pomoStart.style.display = 'inline-block';
        pomoPause.style.display = 'none';
      }
    }
    function resetPomo() {
      pausePomo();
      pomoCurrent = pomoTotal;
      updatePomoDisplay();
    }
    pomoStart && pomoStart.addEventListener('click', startPomo);
    pomoPause && pomoPause.addEventListener('click', pausePomo);
    pomoReset && pomoReset.addEventListener('click', resetPomo);
    updatePomoDisplay();
  </script>

</body>

</html>