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

logMessage('fatwa.php', 'Script started, Session ID: ' . session_id());

if (!isLoggedIn()) {
    logMessage('fatwa.php', 'Not logged in, redirecting to login.php');
    header('Location: login.php');
    exit;
}

// Initialize session array for voted fatwas
if (!isset($_SESSION['voted_fatwas'])) {
    $_SESSION['voted_fatwas'] = [];
}

// Check for fatwa ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    logMessage('fatwa.php', 'Invalid or missing fatwa ID, redirecting to fatwas.php');
    $_SESSION['error'] = 'Invalid fatwa ID.';
    header('Location: fatwas.php');
    exit;
}

$fatwa_id = (int)$_GET['id'];

// Handle helpful vote
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_helpful']) && !in_array($fatwa_id, $_SESSION['voted_fatwas'])) {
    try {
        $stmt = $db->prepare("UPDATE fatwas SET helpful_count = helpful_count + 1 WHERE id = ?");
        $stmt->execute([$fatwa_id]);
        $_SESSION['voted_fatwas'][] = $fatwa_id;
        logMessage('fatwa.php', 'User ' . $_SESSION['user_id'] . ' marked fatwa ' . $fatwa_id . ' as helpful');
        $_SESSION['success'] = 'Thank you for marking this fatwa as helpful!';
        header("Location: fatwa.php?id=$fatwa_id");
        exit;
    } catch (PDOException $e) {
        logMessage('fatwa.php', 'Error updating helpful count: ' . $e->getMessage());
        $_SESSION['error'] = 'An error occurred while marking the fatwa as helpful.';
    }
}

// Fetch fatwa details
try {
    $stmt = $db->prepare("
        SELECT f.*, u1.username AS questioner_name, u2.username AS mufti_name, u1.id AS questioner_id, u2.id AS mufti_id
        FROM fatwas f
        LEFT JOIN users u1 ON f.user_id = u1.id
        LEFT JOIN users u2 ON f.mufti_id = u2.id
        WHERE f.id = ?
    ");
    $stmt->execute([$fatwa_id]);
    $fatwa = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$fatwa) {
        logMessage('fatwa.php', 'Fatwa ID ' . $fatwa_id . ' not found, redirecting to fatwas.php');
        $_SESSION['error'] = 'Fatwa not found.';
        header('Location: fatwas.php');
        exit;
    }
} catch (PDOException $e) {
    logMessage('fatwa.php', 'Database error: ' . $e->getMessage());
    $_SESSION['error'] = 'An error occurred while fetching the fatwa.';
    header('Location: fatwas.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($fatwa['title']); ?> - Darul Ifta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: var(--dark-bg);
            color: var(--light-text);
            font-family: 'Segoe UI', sans-serif;
            min-height: 100vh;
            padding-top: 70px;
        }
        .container {
            max-width: 800px;
            padding: 2rem;
            margin: 0 auto;
        }
        .fatwa-container {
            background: var(--darker-bg);
            border-radius: 12px;
            padding: 2rem;
            border: 1px solid var(--divider-color);
            box-shadow: var(--shadow);
        }
        .fatwa-title {
            color: var(--primary-color);
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        .fatwa-meta {
            color: var(--medium-gray);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        .fatwa-meta i {
            margin-right: 0.5rem;
            color: var(--primary-color);
        }
        .fatwa-meta a {
            color: var(--primary-color);
            text-decoration: none;
        }
        .fatwa-meta a:hover {
            color: var(--light-text);
            text-decoration: underline;
        }
        .fatwa-details {
            margin-bottom: 1.5rem;
            line-height: 1.6;
            font-size: 1.1rem;
        }
        .fatwa-answer {
            margin-bottom: 1.5rem;
            line-height: 1.6;
            font-size: 1.1rem;
            background: rgba(76, 175, 80, 0.1);
            padding: 1rem;
            border-radius: 8px;
        }
        .fatwa-decision {
            color: var(--primary-color);
            font-weight: 500;
            padding: 0.75rem;
            background: rgba(76, 175, 80, 0.1);
            border-radius: 6px;
            margin-bottom: 1rem;
        }
        .btn-primary {
            background-color: var(--primary-color);
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            transition: var(--transition);
        }
        .btn-primary:hover {
            background-color: var(--primary-dark);
        }
        .btn-primary:disabled {
            background-color: var(--medium-gray);
            cursor: not-allowed;
        }
        .btn-back {
            background: var(--dark-gray);
            color: var(--light-text);
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            transition: var(--transition);
        }
        .btn-back:hover {
            background: var(--medium-gray);
        }
        .alert {
            background: var(--darker-bg);
            color: var(--light-text);
            border-left: 4px solid var(--primary-color);
            margin-bottom: 1rem;
        }
        .helpful-count {
            color: var(--light-gray);
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        @media (max-width: 768px) {
            .container {
                padding: 1.5rem;
            }
            .fatwa-title {
                font-size: 1.75rem;
            }
            .fatwa-container {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <div class="fatwa-container">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($_SESSION['success']); ?>
                    <?php unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($_SESSION['error']); ?>
                    <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <h1 class="fatwa-title"><?php echo htmlspecialchars($fatwa['title']); ?></h1>
            <div class="fatwa-meta">
                <p><i class="fas fa-user"></i> Asked by: <a href="profile.php?id=<?php echo htmlspecialchars($fatwa['questioner_id'] ?? ''); ?>"><?php echo htmlspecialchars($fatwa['questioner_name'] ?? 'Anonymous'); ?></a></p>
                <p><i class="fas fa-tag"></i> Category: <?php echo htmlspecialchars($fatwa['category'] ?? 'General'); ?></p>
                <p><i class="fas fa-book"></i> Madhab: <?php echo htmlspecialchars($fatwa['madhab'] ?? 'Not specified'); ?></p>
                <p><i class="fas fa-info-circle"></i> Status: <?php echo htmlspecialchars($fatwa['status']); ?></p>
                <p><i class="fas fa-calendar-alt"></i> Submitted: <?php echo date('F j, Y, H:i', strtotime($fatwa['submitted_at'])); ?></p>
                <?php if ($fatwa['status'] === 'Answered' && $fatwa['mufti_id']): ?>
                    <p><i class="fas fa-user-tie"></i> Answered by: <a href="profile.php?id=<?php echo htmlspecialchars($fatwa['mufti_id'] ?? ''); ?>"><?php echo htmlspecialchars($fatwa['mufti_name'] ?? 'Unknown Mufti'); ?></a></p>
                    <p><i class="fas fa-calendar-check"></i> Answered on: <?php echo date('F j, Y, H:i', strtotime($fatwa['answered_at'])); ?></p>
                <?php endif; ?>
            </div>
            <div class="fatwa-details">
                <h3>Details</h3>
                <p><?php echo htmlspecialchars($fatwa['details']); ?></p>
            </div>
            <?php if ($fatwa['status'] === 'Answered' && !empty($fatwa['answer'])): ?>
                <div class="fatwa-answer">
                    <h3>Answer</h3>
                    <p><?php echo htmlspecialchars($fatwa['answer']); ?></p>
                </div>
                <div class="fatwa-decision">
                    <i class="fas fa-scale-balanced me-2"></i>
                    Decision: <?php echo htmlspecialchars($fatwa['final_decision'] ?? 'Not specified'); ?>
                </div>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="mark_helpful" value="1">
                    <button type="submit" class="btn btn-primary" <?php echo in_array($fatwa_id, $_SESSION['voted_fatwas']) ? 'disabled' : ''; ?>>
                        <i class="fas fa-thumbs-up me-2"></i>Mark as Helpful
                    </button>
                </form>
                <p class="helpful-count">
                    <i class="fas fa-thumbs-up me-1"></i>
                    <?php echo $fatwa['helpful_count']; ?> user<?php echo $fatwa['helpful_count'] == 1 ? '' : 's'; ?> found this helpful
                </p>
            <?php else: ?>
                <p class="fatwa-answer">This fatwa is pending an answer.</p>
            <?php endif; ?>
            <a href="fatwas.php" class="btn btn-back mt-3">
                <i class="fas fa-arrow-left me-2"></i>Back to Fatwas
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>