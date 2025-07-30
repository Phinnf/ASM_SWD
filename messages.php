<?php
session_start();
include 'db_connect.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Handle new message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message_text'])) {
    $msg = trim($_POST['message_text']);
    if ($msg !== '') {
        $msg_esc = pg_escape_string($conn, $msg);
        $query = "INSERT INTO messages (sender_id, message_text, sent_at) VALUES ($user_id, '$msg_esc', NOW())";
        pg_query($conn, $query);
        header('Location: messages.php'); // Prevent resubmission
        exit;
    }
}
// Fetch all messages (global chat)
$msg_query = "SELECT m.*, u.username FROM messages m JOIN users u ON m.sender_id = u.id ORDER BY m.sent_at ASC, m.id ASC";
$msg_res = pg_query($conn, $msg_query);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Global Chat</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        .menu-bar a {
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

        .menu-bar a:hover {
            background: rgba(0, 0, 0, 0.08);
            border-radius: 6px;
        }

        .menu-bar .logout {
            margin-left: auto;
            background: linear-gradient(90deg, #ff9966 0%, #ff5e62 100%);
            border-radius: 6px;
            font-weight: 700;
            transition: background 0.2s;
        }

        .chat-container {
            display: flex;
            height: calc(100vh - 60px);
            background: #f4f8fb;
        }

        .sidebar {
            width: 220px;
            background: linear-gradient(135deg, #00b09b, #96c93d);
            color: #fff;
            padding: 2rem 1rem;
            min-height: 100%;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .sidebar h2 {
            color: #fff;
            margin-bottom: 2rem;
            font-size: 1.3rem;
            font-weight: 700;
        }

        .sidebar .user-list {
            margin-top: 1rem;
            font-size: 1rem;
            color: #eafaf1;
            opacity: 0.7;
        }

        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #fff;
            border-radius: 18px 0 0 0;
            margin: 2.5rem 2.5rem 2.5rem 0;
            box-shadow: 0 4px 24px rgba(0, 176, 155, 0.10);
            overflow: hidden;
        }

        .chat-header {
            background: linear-gradient(90deg, #00b09b 0%, #96c93d 100%);
            color: #fff;
            padding: 1.2rem 2rem;
            font-size: 1.4rem;
            font-weight: 700;
            letter-spacing: 1px;
            border-bottom: 1.5px solid #e0e0e0;
        }

        .chat-messages {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
            background: #f8fafc;
            display: flex;
            flex-direction: column;
        }

        .message {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1.2rem;
            gap: 1rem;
        }

        .message .avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, #00b09b, #96c93d);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.3rem;
            font-weight: 700;
            flex-shrink: 0;
        }

        .message-content {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px #00b09b11;
            padding: 0.8rem 1.2rem;
            min-width: 120px;
            max-width: 600px;
            word-break: break-word;
        }

        .message-header {
            font-size: 1rem;
            font-weight: 700;
            color: #00b09b;
            margin-bottom: 0.2rem;
            display: flex;
            align-items: center;
            gap: 0.7rem;
        }

        .message-time {
            font-size: 0.92rem;
            color: #888;
            font-weight: 400;
        }

        .chat-form {
            padding: 1.2rem 2rem;
            background: #f4f8fb;
            border-top: 1.5px solid #e0e0e0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .chat-form textarea {
            flex: 1;
            resize: none;
            border-radius: 8px;
            border: 1.5px solid #e0e0e0;
            padding: 0.8rem 1rem;
            font-size: 1.08rem;
            font-family: 'Roboto', Arial, sans-serif;
            background: #f8fafc;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .chat-form textarea:focus {
            border-color: #00b09b;
            outline: none;
            box-shadow: 0 0 0 2px #00b09b22;
        }

        .chat-form button {
            background: linear-gradient(135deg, #00b09b, #96c93d);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0.7rem 1.5rem;
            font-size: 1.08rem;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 2px 8px #00b09b22;
            transition: background 0.2s, box-shadow 0.2s;
        }

        .chat-form button:hover {
            background: linear-gradient(135deg, #96c93d, #00b09b);
        }

        @media (max-width: 900px) {
            .chat-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                min-height: 0;
                flex-direction: row;
                justify-content: space-between;
                padding: 1rem;
            }

            .chat-main {
                margin: 0;
                border-radius: 0;
            }
        }

        @media (max-width: 700px) {
            .chat-main {
                margin: 0;
                border-radius: 0;
            }

            .chat-header,
            .chat-form {
                padding: 1rem;
            }

            .chat-messages {
                padding: 1rem;
            }
        }
    </style>
</head>

<body>
    <div class="menu-bar">
        <a href="main.php"><i class="fa fa-home"></i> Home</a>
        <a href="logout.php" class="logout"><i class="fa fa-sign-out-alt"></i> Logout</a>
    </div>
    <div class="chat-container">
        <div class="sidebar"
            style="background:linear-gradient(135deg,#00b09b,#96c93d);display:flex;align-items:center;justify-content:center;">
            <div
                style="background: #fff; color: #00b09b; border-radius: 14px; box-shadow: 0 2px 12px #00b09b22; padding: 1.5rem 1.2rem; max-width: 180px; text-align: center; font-size: 1.08rem; font-weight: 500;">
                <i class="fa fa-lightbulb" style="font-size:1.5rem;color:#96c93d;margin-bottom:0.5rem;"></i><br>
                <span style="font-weight:700;">Note:</span><br>
                This is a global chat. Everyone can see your messages. Be respectful and have fun!
            </div>
        </div>
        <div class="chat-main">
            <div class="chat-header"><i class="fa fa-comments"></i> Global Chat</div>
            <div class="chat-messages" id="chat-messages">
                <?php
                if ($msg_res && pg_num_rows($msg_res) > 0) {
                    while ($row = pg_fetch_assoc($msg_res)) {
                        $is_me = $row['sender_id'] == $user_id;
                        $initial = strtoupper(substr($row['username'], 0, 1));
                        $time = date('M d, H:i', strtotime($row['sent_at']));
                        echo '<div class="message" style="' . ($is_me ? 'flex-direction:row-reverse;' : '') . '">';
                        echo '<div class="avatar" title="' . htmlspecialchars($row['username']) . '">' . htmlspecialchars($initial) . '</div>';
                        echo '<div class="message-content">';
                        echo '<div class="message-header">' . htmlspecialchars($row['username']);
                        echo ' <span class="message-time">' . htmlspecialchars($time) . '</span>';
                        echo '</div>';
                        echo '<div>' . nl2br(htmlspecialchars($row['message_text'])) . '</div>';
                        echo '</div>';
                        echo '</div>';
                    }
                } else {
                    echo '<div style="color:#888;text-align:center;">No messages yet. Start the conversation!</div>';
                }
                ?>
            </div>
            <form class="chat-form" method="post" autocomplete="off">
                <textarea name="message_text" rows="2" placeholder="Type your message..." required
                    maxlength="1000"></textarea>
                <button type="submit"><i class="fa fa-paper-plane"></i> Send</button>
            </form>
        </div>
    </div>
    <script>
        // Auto-scroll to latest message
        const chatMessages = document.getElementById('chat-messages');
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    </script>
</body>

</html>