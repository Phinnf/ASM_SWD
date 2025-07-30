<?php
session_start();
include 'db_connect.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$username = $_SESSION['username'];

// Handle quiz creation
$quiz_msg = '';
if (isset($_POST['create_quiz'])) {
    $title = pg_escape_string($conn, trim($_POST['quiz_title']));
    $description = pg_escape_string($conn, trim($_POST['quiz_description']));
    $category = pg_escape_string($conn, trim($_POST['quiz_category']));
    $time_limit = (int) $_POST['time_limit'];

    $query = "INSERT INTO quizzes (title, description, category, time_limit, created_by, created_at) 
              VALUES ('$title', '$description', '$category', $time_limit, $user_id, NOW())";
    $res = pg_query($conn, $query);

    if ($res) {
        $quiz_id = pg_last_oid($res);
        $quiz_msg = 'Quiz created successfully! Quiz ID: ' . $quiz_id;
    } else {
        $quiz_msg = 'Error creating quiz.';
    }
}

// Handle question creation
$question_msg = '';
if (isset($_POST['add_question'])) {
    $quiz_id = (int) $_POST['question_quiz_id'];
    $question_text = pg_escape_string($conn, trim($_POST['question_text']));
    $option_a = pg_escape_string($conn, trim($_POST['option_a']));
    $option_b = pg_escape_string($conn, trim($_POST['option_b']));
    $option_c = pg_escape_string($conn, trim($_POST['option_c']));
    $option_d = pg_escape_string($conn, trim($_POST['option_d']));
    $correct_answer = pg_escape_string($conn, trim($_POST['correct_answer']));

    $query = "INSERT INTO quiz_questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_answer) 
              VALUES ($quiz_id, '$question_text', '$option_a', '$option_b', '$option_c', '$option_d', '$correct_answer')";
    $res = pg_query($conn, $query);

    $question_msg = $res ? 'Question added successfully!' : 'Error adding question.';
}

// Handle quiz submission
if (isset($_POST['submit_quiz'])) {
    $quiz_id = (int) $_POST['quiz_id'];
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

    $quiz_msg = "Quiz completed! Score: $score/$total_questions ($percentage%)";
}

// Get quizzes
$quizzes_query = "SELECT q.*, u.username as creator_name, 
                  (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) as question_count,
                  (SELECT COUNT(*) FROM quiz_results WHERE quiz_id = q.id) as attempt_count
                  FROM quizzes q 
                  LEFT JOIN users u ON q.created_by = u.id 
                  ORDER BY q.created_at DESC";
$quizzes = pg_query($conn, $quizzes_query);

// Get user's quiz results
$results_query = "SELECT qr.*, q.title as quiz_title, q.category 
                  FROM quiz_results qr 
                  JOIN quizzes q ON qr.quiz_id = q.id 
                  WHERE qr.user_id = $user_id 
                  ORDER BY qr.submitted_at DESC";
$user_results = pg_query($conn, $results_query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Quizzes - LMS</title>
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

        .menu-bar .logout {
            margin-left: auto;
            background: linear-gradient(90deg, #ff9966 0%, #ff5e62 100%);
            border-radius: 6px;
            font-weight: 700;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .section-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 4px 24px rgba(0, 176, 155, 0.10);
            padding: 2.5rem;
            margin-bottom: 2rem;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .section-header h2 {
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
            color: #00b09b;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-gradient {
            background: linear-gradient(135deg, #00b09b, #96c93d);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0.6rem 1.5rem;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-gradient:hover {
            background: linear-gradient(135deg, #96c93d, #00b09b);
        }

        .quiz-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .quiz-card {
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            padding: 1.5rem;
            transition: transform 0.2s, box-shadow 0.2s;
            border: 1px solid #e0e0e0;
        }

        .quiz-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0, 176, 155, 0.15);
        }

        .quiz-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #00b09b;
            margin-bottom: 0.5rem;
        }

        .quiz-category {
            display: inline-block;
            background: linear-gradient(135deg, #00b09b, #96c93d);
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .quiz-stats {
            display: flex;
            gap: 1rem;
            margin: 0.5rem 0;
            font-size: 0.9rem;
            color: #666;
        }

        .quiz-description {
            color: #666;
            margin: 0.5rem 0;
            line-height: 1.4;
        }

        .quiz-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .btn-secondary {
            background: #f8f9fa;
            color: #00b09b;
            border: 2px solid #00b09b;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-secondary:hover {
            background: #00b09b;
            color: white;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(0, 176, 155, 0.13);
            padding: 2.5rem;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-content h3 {
            margin-top: 0;
            color: #00b09b;
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.7rem;
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            border-color: #00b09b;
            outline: none;
            box-shadow: 0 0 0 2px #00b09b22;
        }

        .question-form {
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 1.5rem;
            margin: 1rem 0;
            background: #f8f9fa;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .results-table th,
        .results-table td {
            padding: 0.8rem;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        .results-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #00b09b;
        }

        .score-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .score-excellent {
            background: #d4edda;
            color: #155724;
        }

        .score-good {
            background: #fff3cd;
            color: #856404;
        }

        .score-poor {
            background: #f8d7da;
            color: #721c24;
        }

        @media (max-width: 768px) {
            .quiz-grid {
                grid-template-columns: 1fr;
            }

            .modal-content {
                padding: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <div class="menu-bar">
        <a href="main.php"><i class="fa fa-home"></i> Home</a>
        <a href="my_courses.php"><i class="fa fa-book"></i> My Courses</a>
        <a href="quizzes.php"><i class="fa fa-question-circle"></i> Quizzes</a>
        <a href="messages.php"><i class="fa fa-envelope"></i> Messages</a>
        <a href="logout.php" class="logout"><i class="fa fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="container">
        <?php if ($quiz_msg): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($quiz_msg); ?></div>
        <?php endif; ?>

        <div class="section-card">
            <div class="section-header">
                <h2><i class="fa fa-question-circle"></i> Quiz System</h2>
                <div>
                    <button class="btn-gradient" onclick="openModal('createQuizModal')">
                        <i class="fa fa-plus"></i> Create Quiz
                    </button>
                    <button class="btn-gradient" onclick="openModal('addQuestionModal')">
                        <i class="fa fa-plus"></i> Add Question
                    </button>
                </div>
            </div>

            <div class="quiz-grid">
                <?php if ($quizzes && pg_num_rows($quizzes) > 0): ?>
                    <?php while ($quiz = pg_fetch_assoc($quizzes)): ?>
                        <div class="quiz-card">
                            <div class="quiz-title"><?php echo htmlspecialchars($quiz['title']); ?></div>
                            <div class="quiz-category"><?php echo htmlspecialchars($quiz['category']); ?></div>
                            <div class="quiz-description"><?php echo htmlspecialchars($quiz['description']); ?></div>
                            <div class="quiz-stats">
                                <span><i class="fa fa-question"></i> <?php echo $quiz['question_count']; ?> questions</span>
                                <span><i class="fa fa-users"></i> <?php echo $quiz['attempt_count']; ?> attempts</span>
                                <span><i class="fa fa-clock"></i> <?php echo $quiz['time_limit']; ?> min</span>
                            </div>
                            <div class="quiz-actions">
                                <a href="take_quiz.php?id=<?php echo $quiz['id']; ?>" class="btn-gradient">
                                    <i class="fa fa-play"></i> Take Quiz
                                </a>
                                <?php if ($role === 'instructor' || $quiz['created_by'] == $user_id): ?>
                                    <button class="btn-secondary" onclick="editQuiz(<?php echo $quiz['id']; ?>)">
                                        <i class="fa fa-edit"></i> Edit
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="text-align: center; color: #666; grid-column: 1 / -1;">
                        <i class="fa fa-question-circle" style="font-size: 3rem; margin-bottom: 1rem; color: #ccc;"></i>
                        <p>No quizzes available yet. Create the first quiz!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- My Results Section -->
        <div class="section-card">
            <h2><i class="fa fa-chart-line"></i> My Quiz Results</h2>
            <?php if ($user_results && pg_num_rows($user_results) > 0): ?>
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>Quiz</th>
                            <th>Category</th>
                            <th>Score</th>
                            <th>Percentage</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($result = pg_fetch_assoc($user_results)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($result['quiz_title']); ?></td>
                                <td><?php echo htmlspecialchars($result['category']); ?></td>
                                <td><?php echo $result['score']; ?>/<?php echo $result['total_questions']; ?></td>
                                <td>
                                    <span class="score-badge <?php
                                    echo $result['percentage'] >= 80 ? 'score-excellent' :
                                        ($result['percentage'] >= 60 ? 'score-good' : 'score-poor');
                                    ?>">
                                        <?php echo $result['percentage']; ?>%
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($result['submitted_at'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; color: #666;">No quiz results yet. Take some quizzes to see your progress!</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create Quiz Modal -->
    <div class="modal" id="createQuizModal">
        <div class="modal-content">
            <h3>Create New Quiz</h3>
            <form method="post">
                <div class="form-group">
                    <label>Quiz Title</label>
                    <input type="text" name="quiz_title" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="quiz_description" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="quiz_category" required>
                        <option value="">Select Category</option>
                        <option value="C# Programming">C# Programming</option>
                        <option value="Database Management">Database Management</option>
                        <option value="SQL">SQL</option>
                        <option value="Web Development">Web Development</option>
                        <option value="General Knowledge">General Knowledge</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Time Limit (minutes)</label>
                    <input type="number" name="time_limit" min="5" max="120" value="30" required>
                </div>
                <button type="submit" name="create_quiz" class="btn-gradient">Create Quiz</button>
                <button type="button" class="btn-secondary" onclick="closeModal('createQuizModal')">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Add Question Modal -->
    <div class="modal" id="addQuestionModal">
        <div class="modal-content">
            <h3>Add Question</h3>
            <form method="post">
                <div class="form-group">
                    <label>Select Quiz</label>
                    <select name="question_quiz_id" required>
                        <option value="">Select Quiz</option>
                        <?php
                        $quiz_options = pg_query($conn, "SELECT id, title FROM quizzes ORDER BY created_at DESC");
                        while ($quiz = pg_fetch_assoc($quiz_options)):
                            ?>
                            <option value="<?php echo $quiz['id']; ?>"><?php echo htmlspecialchars($quiz['title']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Question Text</label>
                    <textarea name="question_text" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label>Option A</label>
                    <input type="text" name="option_a" required>
                </div>
                <div class="form-group">
                    <label>Option B</label>
                    <input type="text" name="option_b" required>
                </div>
                <div class="form-group">
                    <label>Option C</label>
                    <input type="text" name="option_c" required>
                </div>
                <div class="form-group">
                    <label>Option D</label>
                    <input type="text" name="option_d" required>
                </div>
                <div class="form-group">
                    <label>Correct Answer</label>
                    <select name="correct_answer" required>
                        <option value="">Select Correct Answer</option>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                        <option value="D">D</option>
                    </select>
                </div>
                <button type="submit" name="add_question" class="btn-gradient">Add Question</button>
                <button type="button" class="btn-secondary" onclick="closeModal('addQuestionModal')">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) {
            document.getElementById(id).classList.add('active');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        function editQuiz(quizId) {
            // Redirect to edit quiz page
            window.location.href = 'edit_quiz.php?id=' + quizId;
        }

        // Close modal when clicking outside
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function (e) {
                if (e.target === modal) {
                    modal.classList.remove('active');
                }
            });
        });
    </script>
</body>

</html>