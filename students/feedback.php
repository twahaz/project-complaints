<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../index.php");
    exit();
}
require_once '../includes/connection.php';

$student_id = $_SESSION['user_id'];
$complaint_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Verify complaint belongs to student and is resolved
$check_sql = "SELECT id, complaint_number FROM complaints WHERE id = ? AND student_id = ? AND status = 'resolved'";
$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "ii", $complaint_id, $student_id);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);
$complaint = mysqli_fetch_assoc($check_result);
mysqli_stmt_close($check_stmt);

if (!$complaint) {
    header("Location: my_complaints.php?error=invalid_feedback");
    exit();
}

// Check if rating already exists
$rating_sql = "SELECT id FROM ratings WHERE complaint_id = ?";
$rating_stmt = mysqli_prepare($conn, $rating_sql);
mysqli_stmt_bind_param($rating_stmt, "i", $complaint_id);
mysqli_stmt_execute($rating_stmt);
$rating_result = mysqli_stmt_get_result($rating_stmt);
$already_rated = mysqli_fetch_assoc($rating_result);
mysqli_stmt_close($rating_stmt);

if ($already_rated) {
    header("Location: view_complaint.php?id=$complaint_id&msg=already_rated");
    exit();
}

$feedback_error = '';
$feedback_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $rating = intval($_POST['rating'] ?? 0);
    $comment = trim($_POST['feedback_comment'] ?? '');
    if ($rating < 1 || $rating > 5) {
        $feedback_error = "Please select a rating between 1 and 5 stars.";
    } else {
        $resolved_by = 1; // default
        $insert_rating = "INSERT INTO ratings (complaint_id, rating_score, feedback, resolved_by, created_at) VALUES (?, ?, ?, ?, NOW())";
        $ins_stmt = mysqli_prepare($conn, $insert_rating);
        if (!$ins_stmt) {
            $feedback_error = "Database error: " . mysqli_error($conn);
        } else {
            mysqli_stmt_bind_param($ins_stmt, "iisi", $complaint_id, $rating, $comment, $resolved_by);
            if (mysqli_stmt_execute($ins_stmt)) {
                $feedback_success = true;
            } else {
                $feedback_error = "Failed to save feedback: " . mysqli_stmt_error($ins_stmt);
            }
            mysqli_stmt_close($ins_stmt);
        }
    }
}
mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Rate Resolution - IAA CFMS</title>
    <link rel="icon" type="image/png" href="../images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f7fc;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .feedback-card {
            background: white;
            border-radius: 32px;
            padding: 32px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 12px 30px rgba(0,0,0,0.05);
            text-align: center;
        }
        .star-rating {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin: 20px 0;
            font-size: 2rem;
            cursor: pointer;
        }
        .star {
            color: #cbd5e1;
            transition: 0.2s;
        }
        .star.selected {
            color: #f5b042;
        }
        .feedback-card textarea {
            width: 100%;
            padding: 14px;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            font-family: inherit;
            margin: 20px 0;
        }
        .btn-submit {
            background: #0047AB;
            color: white;
            border: none;
            padding: 12px 28px;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-submit:hover {
            background: #003380;
        }
        .alert {
            padding: 12px;
            border-radius: 16px;
            margin-bottom: 20px;
        }
        .alert.error {
            background: #fee2e2;
            color: #b91c1c;
        }
        .alert.success {
            background: #e0f2e9;
            color: #1e7b4c;
        }
        .success-icon {
            font-size: 4rem;
            color: #2ecc71;
            margin-bottom: 16px;
        }
        a {
            color: #0047AB;
            text-decoration: none;
        }
        .rating-error {
            color: #e74c3c;
            font-size: 0.85rem;
            margin-top: -10px;
            margin-bottom: 10px;
            display: none;
        }
    </style>
</head>
<body>
<div class="feedback-card">
    <?php if ($feedback_success): ?>
        <div class="success-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <h2>Thank You!</h2>
        <p>Your feedback has been submitted successfully.</p>
        <p>You will be redirected shortly...</p>
        <div class="spinner" style="width: 30px; height: 30px; border: 3px solid #e0e7f0; border-top-color: #0047AB; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 20px auto;"></div>
        <style>
            @keyframes spin {
                to { transform: rotate(360deg); }
            }
        </style>
        <script>
            setTimeout(function() {
                window.location.href = 'view_complaint.php?id=<?php echo $complaint_id; ?>&msg=feedback_submitted';
            }, 2000);
        </script>
    <?php else: ?>
        <h2>Rate Your Experience</h2>
        <p>Complaint #<?php echo htmlspecialchars($complaint['complaint_number']); ?></p>
        <?php if ($feedback_error): ?>
            <div class="alert error"><?php echo htmlspecialchars($feedback_error); ?></div>
        <?php endif; ?>
        <form method="POST" id="feedbackForm">
            <div class="star-rating" id="starRating">
                <i class="fas fa-star star" data-value="1"></i>
                <i class="fas fa-star star" data-value="2"></i>
                <i class="fas fa-star star" data-value="3"></i>
                <i class="fas fa-star star" data-value="4"></i>
                <i class="fas fa-star star" data-value="5"></i>
            </div>
            <div id="ratingError" class="rating-error">Please select a rating</div>
            <input type="hidden" name="rating" id="ratingValue" value="0">
            <textarea name="feedback_comment" rows="4" placeholder="Optional: share more details about your experience..."></textarea>
            <button type="submit" name="submit_feedback" class="btn-submit">Submit Feedback</button>
        </form>
    <?php endif; ?>
</div>
<script>
    const stars = document.querySelectorAll('.star');
    const ratingInput = document.getElementById('ratingValue');
    const ratingError = document.getElementById('ratingError');
    const form = document.getElementById('feedbackForm');

    stars.forEach(star => {
        star.addEventListener('click', function() {
            const value = parseInt(this.getAttribute('data-value'));
            ratingInput.value = value;
            stars.forEach((s, idx) => {
                if (idx < value) s.classList.add('selected');
                else s.classList.remove('selected');
            });
            ratingError.style.display = 'none';
        });
    });

    form.addEventListener('submit', function(e) {
        if (parseInt(ratingInput.value) === 0) {
            e.preventDefault();
            ratingError.style.display = 'block';
        }
    });
</script>
</body>
</html>