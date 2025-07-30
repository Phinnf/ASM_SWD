<?php
session_start();
include 'db_connect.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$quiz_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$quiz_id) {
    header('Location: quizzes.php');
    exit;
}

// Get quiz details
$quiz_query = "SELECT * FROM quizzes WHERE id = $quiz_id";
$quiz_result = pg_query($conn, $quiz_query);

if (!$quiz_result || pg_num_rows($quiz_result) === 0) {
    header('Location: quizzes.php');
    exit;
}

$quiz = pg_fetch_assoc($quiz_result);

// Check if user has already completed this quiz
$completed_query = "SELECT * FROM quiz_results WHERE quiz_id = $quiz_id AND user_id = $user_id";
$completed_result = pg_query($conn, $completed_query);

if (pg_num_rows($completed_result) > 0) {
    $completed = pg_fetch_assoc($completed_result);
    $message = "You have already completed this quiz. Score: {$completed['score']}/{$completed['total_questions']} ({$completed['percentage']}%)";
}

// Handle quiz submission
if (isset($_POST['submit_quiz'])) {
    $score = 0;
    $total_questions = 0;

    // Get correct answers
    $correct_answers = pg_query($conn, "SELECT id, correct_answer FROM quiz_questions WHERE quiz_id = $quiz_id");
    $answers = [];
    while ($row = pg_fetch_assoc($correct_answers)) {
        $answers[$row['id']] = $row['correct_answer'];
    }

    // Check user answers
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'answer_') === 0) {
            $question_id = (int) substr($key, 7);
            if (isset($answers[$question_id]) && $value === $answers[$question_id]) {
                $score++;
            }
            $total_questions++;
        }
    }

    $percentage = $total_questions > 0 ? round(($score / $total_questions) * 100, 2) : 0;

    // Save result
    $insert_query = "INSERT INTO quiz_results (quiz_id, user_id, score, total_questions, percentage, submitted_at) 
                     VALUES ($quiz_id, $user_id, $score, $total_questions, $percentage, NOW())";
    pg_query($conn, $insert_query);

    $message = "Quiz completed! Score: $score/$total_questions ($percentage%)";
    $quiz_completed = true;
}

// Get quiz questions
$questions_query = "SELECT * FROM quiz_questions WHERE quiz_id = $quiz_id ORDER BY id";
$questions = pg_query($conn, $questions_query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Take Quiz - <?php echo htmlspecialchars($quiz['title']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            background: #f4f8fb;
            font-family: 'Roboto', Arial, sans-serif;
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
            font-weight: 500;
            transition: background 0.2s;
        }

        .menu-bar a:hover {
            background: rgba(0, 0, 0, 0.08);
            border-radius: 6px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .quiz-header {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 4px 24px rgba(0, 176, 155, 0.10);
            padding: 2rem;
            margin-bottom: 2rem;
            text-align: center;
        }

        .quiz-title {
            font-size: 2rem;
            font-weight: 700;
            color: #00b09b;
            margin-bottom: 0.5rem;
        }

        .quiz-info {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin: 1rem 0;
            color: #666;
        }

        .timer {
            background: linear-gradient(135deg, #ff5e62, #ff9966);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .question-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 4px 24px rgba(0, 176, 155, 0.10);
            padding: 2rem;
            margin-bottom: 1.5rem;
        }

        .question-number {
            background: linear-gradient(135deg, #00b09b, #96c93d);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 700;
            display: inline-block;
            margin-bottom: 1rem;
        }

        .question-text {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: #333;
            line-height: 1.5;
        }

        .options {
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
        }

        .option {
            display: flex;
            align-items: center;
            padding: 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
            background: #f8f9fa;
        }

        .option:hover {
            border-color: #00b09b;
            background: #e8f5f3;
        }

        .option input[type="radio"] {
            margin-right: 1rem;
            transform: scale(1.2);
        }

        .option label {
            cursor: pointer;
            font-size: 1rem;
            color: #333;
            flex: 1;
        }

        .btn-gradient {
            background: linear-gradient(135deg, #00b09b, #96c93d);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0.8rem 2rem;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-gradient:hover {
            background: linear-gradient(135deg, #96c93d, #00b09b);
        }

        .btn-secondary {
            background: #f8f9fa;
            color: #00b09b;
            border: 2px solid #00b09b;
            border-radius: 8px;
            padding: 0.8rem 2rem;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-secondary:hover {
            background: #00b09b;
            color: white;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            text-align: center;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            margin: 1rem 0;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #00b09b, #96c93d);
            transition: width 0.3s;
        }

        .quiz-actions {
            text-align: center;
            margin-top: 2rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            .quiz-header {
                padding: 1.5rem;
            }

            .question-card {
                padding: 1.5rem;
            }

            .quiz-info {
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>
</head>

<body>
    <div class="menu-bar">
        <a href="main.php"><i class="fa fa-home"></i> Home</a>
        <a href="quizzes.php"><i class="fa fa-question-circle"></i> Quizzes</a>
        <a href="my_courses.php"><i class="fa fa-book"></i> My Courses</a>
        <a href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="container">
        <?php if (isset($message)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <div class="quiz-actions">
                <a href="quizzes.php" class="btn-gradient">Back to Quizzes</a>
            </div>
        <?php else: ?>
            <div class="quiz-header">
                <div class="quiz-title"><?php echo htmlspecialchars($quiz['title']); ?></div>
                <div class="quiz-info">
                    <span><i class="fa fa-tag"></i> <?php echo htmlspecialchars($quiz['category']); ?></span>
                    <span><i class="fa fa-clock"></i> <span id="timer"><?php echo $quiz['time_limit']; ?>:00</span></span>
                    <span><i class="fa fa-question"></i> <?php echo pg_num_rows($questions); ?> questions</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" id="progress-fill" style="width: 0%"></div>
                </div>
            </div>

            <form method="post" id="quiz-form">
                <?php
                $question_count = 0;
                while ($question = pg_fetch_assoc($questions)):
                    $question_count++;
                    ?>
                    <div class="question-card">
                        <div class="question-number">Question <?php echo $question_count; ?></div>
                        <div class="question-text"><?php echo htmlspecialchars($question['question_text']); ?></div>
                        <div class="options">
                            <div class="option">
                                <input type="radio" name="answer_<?php echo $question['id']; ?>" value="A"
                                    id="q<?php echo $question['id']; ?>_a" required>
                                <label
                                    for="q<?php echo $question['id']; ?>_a"><?php echo htmlspecialchars($question['option_a']); ?></label>
                            </div>
                            <div class="option">
                                <input type="radio" name="answer_<?php echo $question['id']; ?>" value="B"
                                    id="q<?php echo $question['id']; ?>_b" required>
                                <label
                                    for="q<?php echo $question['id']; ?>_b"><?php echo htmlspecialchars($question['option_b']); ?></label>
                            </div>
                            <div class="option">
                                <input type="radio" name="answer_<?php echo $question['id']; ?>" value="C"
                                    id="q<?php echo $question['id']; ?>_c" required>
                                <label
                                    for="q<?php echo $question['id']; ?>_c"><?php echo htmlspecialchars($question['option_c']); ?></label>
                            </div>
                            <div class="option">
                                <input type="radio" name="answer_<?php echo $question['id']; ?>" value="D"
                                    id="q<?php echo $question['id']; ?>_d" required>
                                <label
                                    for="q<?php echo $question['id']; ?>_d"><?php echo htmlspecialchars($question['option_d']); ?></label>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>

                <div class="quiz-actions">
                    <button type="submit" name="submit_quiz" class="btn-gradient" id="submit-btn">
                        <i class="fa fa-check"></i> Submit Quiz
                    </button>
                    <a href="quizzes.php" class="btn-secondary">
                        <i class="fa fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script>
        // Timer functionality
        let timeLeft = <?php echo $quiz['time_limit'] * 60; ?>;
        const timerDisplay = document.getElementById('timer');
        const submitBtn = document.getElementById('submit-btn');
        const quizForm = document.getElementById('quiz-form');
        const progressFill = document.getElementById('progress-fill');
        const totalQuestions = <?php echo pg_num_rows($questions); ?>;
        let answeredQuestions = 0;

        function updateTimer() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            timerDisplay.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;

            if (timeLeft <= 0) {
                alert('Time is up! Quiz will be submitted automatically.');
                quizForm.submit();
                return;
            }

            timeLeft--;
        }

        function updateProgress() {
            const progress = (answeredQuestions / totalQuestions) * 100;
            progressFill.style.width = progress + '%';
        }

        // Update progress when answers are selected
        document.querySelectorAll('input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', function () {
                const questionId = this.name;
                const answeredInQuestion = document.querySelectorAll(`input[name="${questionId}"]:checked`).length;

                if (answeredInQuestion > 0) {
                    answeredQuestions++;
                }
                updateProgress();
            });
        });

        // Start timer
        const timerInterval = setInterval(updateTimer, 1000);
        updateTimer();
        updateProgress();

        // Confirm before leaving page
        window.addEventListener('beforeunload', function (e) {
            if (timeLeft > 0) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        // Confirm submission
        submitBtn.addEventListener('click', function (e) {
            const unanswered = totalQuestions - answeredQuestions;
            if (unanswered > 0) {
                if (!confirm(`You have ${unanswered} unanswered question(s). Are you sure you want to submit?`)) {
                    e.preventDefault();
                }
            }
        });
    </script>
</body>

</html>