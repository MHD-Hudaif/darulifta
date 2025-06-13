<?php
ob_start();
require_once 'config.php';

function logMessage($file, $message) {
    error_log("[$file] " . date('Y-m-d H:i:s') . ": $message");
}

function isLoggedIn() {
    $loggedIn = !empty($_SESSION['user_id']);
    logMessage('isLoggedIn', 'isLoggedIn: ' . ($loggedIn ? 'true' : 'false') . ', user_id=' . ($_SESSION['user_id'] ?? 'unset'));
    return $loggedIn;
}

function hasRole($db, $role) {
    if (!isLoggedIn()) {
        logMessage('hasRole', 'hasRole: Not logged in');
        return false;
    }
    try {
        $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $userRole = $stmt->fetchColumn();
        $hasRole = $userRole && strcasecmp($userRole, $role) === 0;
        logMessage('hasRole', "hasRole('$role') for user_id {$_SESSION['user_id']}: " . ($hasRole ? 'true' : 'false') . ", found role: " . ($userRole ?: 'none'));
        return $hasRole;
    } catch (PDOException $e) {
        logMessage('hasRole', 'hasRole error: ' . $e->getMessage());
        return false;
    }
}

logMessage('answer-fatwa.php', 'Script started, Session ID: ' . session_id());

if (!isLoggedIn() || !hasRole($db, 'mufti')) {
    logMessage('answer-fatwa.php', 'Access denied, redirecting to login.php');
    header('Location: login.php');
    exit;
}

$fatwa = null;
$errors = [];
$success = '';

if (!isset($_GET['fatwa_id']) || !is_numeric($_GET['fatwa_id'])) {
    logMessage('answer-fatwa.php', 'Invalid or missing fatwa_id');
    header('Location: mufti.php');
    exit;
}

$fatwa_id = (int)$_GET['fatwa_id'];

try {
    $stmt = $db->prepare("SELECT f.*, u.username AS user_name FROM fatwas f JOIN users u ON f.user_id = u.id WHERE f.id = ? AND f.status = 'Pending'");
    $stmt->execute([$fatwa_id]);
    $fatwa = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$fatwa) {
        logMessage('answer-fatwa.php', "Fatwa ID $fatwa_id not found or not pending");
        header('Location: mufti.php');
        exit;
    }
} catch (PDOException $e) {
    logMessage('answer-fatwa.php', 'Database error fetching fatwa: ' . $e->getMessage());
    $errors[] = 'An error occurred. Please try again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    $answer = trim($_POST['answer'] ?? '');
    $final_decision = trim($_POST['final_decision'] ?? '');
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (empty($answer)) {
        $errors[] = 'Answer is required.';
    }
    if (empty($final_decision)) {
        $errors[] = 'Final decision is required.';
    }
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $errors[] = 'Invalid CSRF token.';
        logMessage('answer-fatwa.php', 'Invalid CSRF token');
    }

    if (empty($errors)) {
        try {
            $stmt = $db->prepare("UPDATE fatwas SET status = 'Answered', answer = ?, final_decision = ?, answered_at = NOW(), mufti_id = ? WHERE id = ?");
            $stmt->execute([$answer, $final_decision, $_SESSION['user_id'], $fatwa_id]);
            logMessage('answer-fatwa.php', "Fatwa ID $fatwa_id answered by user_id {$_SESSION['user_id']}");
            $success = 'Fatwa answered successfully.';
            header('Location: mufti.php');
            exit;
        } catch (PDOException $e) {
            logMessage('answer-fatwa.php', 'Database error updating fatwa: ' . $e->getMessage());
            $errors[] = 'An error occurred while saving the answer.';
        }
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Answer Fatwa - Darul Ifta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #121212;
            color: #fff;
            font-family: 'Segoe UI', sans-serif;
            min-height: 100vh;
            padding-top: 70px;
        }
        .container {
            padding: 2rem;
            max-width: 800px;
            margin: 0 auto;
        }
        .fatwa-details {
            background: rgba(0, 0, 0, 0.85);
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        .fatwa-details h3 {
            color: #4caf50;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            font-size: 0.95rem;
            color: #ddd;
            margin-bottom: 0.5rem;
        }
        .form-group textarea {
            width: 100%;
            padding: 0.6rem;
            border: 1px solid #333;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            min-height: 150px;
        }
        .form-group input {
            width: 100%;
            padding: 0.6rem;
            border: 1px solid #333;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
        }
        .btn-primary {
            background-color: #4caf50;
            border-color: #4caf50;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
        }
        .btn-primary:hover {
            background-color: #45a049;
            border-color: #45a049;
        }
        .alert {
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <h2>Answer Fatwa</h2>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success">
                <p><?php echo htmlspecialchars($success); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($fatwa): ?>
            <div class="fatwa-details">
                <h3><?php echo htmlspecialchars($fatwa['title']); ?></h3>
                <p><strong>Submitted by:</strong> <?php echo htmlspecialchars($fatwa['user_name']); ?></p>
                <p><strong>Date:</strong> <?php echo date('d M Y', strtotime($fatwa['submitted_at'])); ?></p>
                <p><strong>Category:</strong> <?php echo htmlspecialchars($fatwa['category'] ?? 'N/A'); ?></p>
                <p><strong>Madhab:</strong> <?php echo htmlspecialchars($fatwa['madhab']); ?></p>
                <p><strong>Details:</strong> <?php echo htmlspecialchars($fatwa['details']); ?></p>
            </div>

            <form action="answer-fatwa.php?fatwa_id=<?php echo htmlspecialchars($fatwa_id); ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="form-group">
                    <label for="answer">Your Answer</label>
                    <textarea id="answer" name="answer" required></textarea>
                </div>
                <div class="form-group">
                    <label for="final_decision">Final Decision</label>
                    <input type="text" id="final_decision" name="final_decision" required>
                </div>
                <button type="submit" class="btn btn-primary">Submit Answer</button>
            </form>
        <?php else: ?>
            <p>Fatwa not found.</p>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js"></script>
</body>
</html>
<?php
ob_end_flush();
?>