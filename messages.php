<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Handle sending a message
$msg = '';
if (isset($_POST['send_message'])) {
    $to_id = (int)$_POST['recipient_id'];
    $text = pg_escape_string($conn, trim($_POST['message_text']));
    if ($to_id && $text) {
        $query = "INSERT INTO messages (sender_id, receiver_id, course_id, message_text, sent_at) VALUES ($user_id, $to_id, NULL, '$text', NOW())";
        $res = pg_query($conn, $query);
        $msg = $res ? 'Message sent!' : 'Error sending message.';
    } else {
        $msg = 'Please select a recipient and enter a message.';
    }
}

// Get all users except self (for recipient dropdown)
$users = pg_query($conn, "SELECT id, username, email, role FROM users WHERE id != $user_id ORDER BY username");

// Inbox: messages received
$inbox = pg_query($conn, "SELECT m.*, u.username AS sender_name FROM messages m JOIN users u ON m.sender_id = u.id WHERE m.receiver_id = $user_id ORDER BY m.sent_at DESC");
// Outbox: messages sent
$outbox = pg_query($conn, "SELECT m.*, u.username AS receiver_name FROM messages m JOIN users u ON m.receiver_id = u.id WHERE m.sender_id = $user_id ORDER BY m.sent_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Messages</title>
<link rel="stylesheet" href="style.css" />
<style>
body { margin: 0; }
.menu-bar { background-color: #00b09b; overflow: hidden; }
.menu-bar a { float: left; color: white; text-align: center; padding: 14px 20px; text-decoration: none; }
.menu-bar a:hover { background-color: #089e8a; }
.container { max-width: 900px; margin: 2rem auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px #0001; padding: 2rem; }
h2 { color: #00b09b; }
table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
th, td { border: 1px solid #eee; padding: 8px; text-align: left; }
th { background: #f4f4f4; }
.section { margin-bottom: 2rem; }
</style>
</head>
<body>
<div class="menu-bar">
    <a href="main.php">Home</a>
    <a href="my_courses.php">My Courses</a>
    <a href="messages.php" style="background:#089e8a;">Messages</a>
    <a href="logout.php" style="float:right;">Logout</a>
</div>
<div class="container">
    <h2>Messages</h2>
    <?php if ($msg) echo '<div class="alert">' . htmlspecialchars($msg) . '</div>'; ?>
    <div class="section">
        <h3>Send a Message</h3>
        <form method="post">
            <label>To:</label>
            <select name="recipient_id" class="form-control" required>
                <option value="">Select recipient</option>
                <?php
                if ($users) {
                    while ($u = pg_fetch_assoc($users)) {
                        echo '<option value="' . $u['id'] . '">' . htmlspecialchars($u['username']) . ' (' . htmlspecialchars($u['role']) . ')</option>';
                    }
                }
                ?>
            </select>
            <label>Message:</label>
            <textarea name="message_text" class="form-control" required></textarea>
            <button class="btn btn-primary" type="submit" name="send_message">Send</button>
        </form>
    </div>
    <div class="section">
        <h3>Inbox</h3>
        <?php
        if ($inbox && pg_num_rows($inbox) > 0) {
            echo '<table><tr><th>From</th><th>Message</th><th>Date</th></tr>';
            while ($m = pg_fetch_assoc($inbox)) {
                echo '<tr><td>' . htmlspecialchars($m['sender_name']) . '</td><td>' . nl2br(htmlspecialchars($m['message_text'])) . '</td><td>' . htmlspecialchars($m['sent_at']) . '</td></tr>';
            }
            echo '</table>';
        } else {
            echo '<p>No messages received.</p>';
        }
        ?>
    </div>
    <div class="section">
        <h3>Outbox</h3>
        <?php
        if ($outbox && pg_num_rows($outbox) > 0) {
            echo '<table><tr><th>To</th><th>Message</th><th>Date</th></tr>';
            while ($m = pg_fetch_assoc($outbox)) {
                echo '<tr><td>' . htmlspecialchars($m['receiver_name']) . '</td><td>' . nl2br(htmlspecialchars($m['message_text'])) . '</td><td>' . htmlspecialchars($m['sent_at']) . '</td></tr>';
            }
            echo '</table>';
        } else {
            echo '<p>No messages sent.</p>';
        }
        ?>
    </div>
    <a href="main.php" style="display:block;margin:2rem auto;text-align:center;color:#00b09b;font-weight:bold;">&larr; Return to Menu</a>
</div>
</body>
</html> 