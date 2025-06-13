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

logMessage('overview.php', 'Script started, Session ID: ' . session_id());

if (headers_sent($file, $line)) {
    logMessage('overview.php', "Headers sent in $file at line $line");
    die("Headers already sent in $file at line $line");
}

if (!isLoggedIn()) {
    logMessage('overview.php', 'User not logged in, redirecting to login');
    session_write_close();
    header('Location: /darulifta_local/login');
    exit;
}

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : $_SESSION['user_id'];

try {
    $stmt = $db->prepare("SELECT id, username, role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        logMessage('overview.php', 'No user found for ID ' . $user_id);
        session_write_close();
        header('Location: /darulifta_local/home');
        exit;
    }
} catch (PDOException $e) {
    logMessage('overview.php', 'Database error fetching user: ' . $e->getMessage());
    $user = null;
}

try {
    $stmt = $db->prepare("SELECT f.*, u.username AS mufti_name 
                          FROM fatwas f 
                          LEFT JOIN users u ON f.mufti_id = u.id 
                          WHERE f.user_id = ? 
                          ORDER BY f.submitted_at DESC LIMIT 10");
    $stmt->execute([$user_id]);
    $asked_fatwas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logMessage('overview.php', 'Database error fetching asked fatwas: ' . $e->getMessage());
    $asked_fatwas = [];
}

$answered_fatwas = [];
if ($user['role'] === 'mufti') {
    try {
        $stmt = $db->prepare("SELECT f.*, u.username AS questioner_name 
                              FROM fatwas f 
                              LEFT JOIN users u ON f.user_id = u.id 
                              WHERE f.mufti_id = ? 
                              ORDER BY f.answered_at DESC LIMIT 10");
        $stmt->execute([$user_id]);
        $answered_fatwas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logMessage('overview.php', 'Database error fetching answered fatwas: ' . $e->getMessage());
        $answered_fatwas = [];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Darul Ifta</title>
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
        .profile-header {
            background: var(--darker-bg);
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            text-align: center;
            border: 1px solid var(--divider-color);
        }
        .profile-header h1 {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        .profile-details p {
            font-size: 1.1rem;
            color: var(--light-gray);
            margin-bottom: 0.5rem;
        }
        .profile-details strong {
            color: var(--primary-color);
        }
        .fatwa-item {
            background: var(--darker-bg);
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            border: 1px solid var(--divider-color);
        }
        .fatwa-item h3 {
            font-size: 1.25rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        .fatwa-meta {
            color: var(--medium-gray);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        .fatwa-meta i {
            margin-right: 0.5rem;
            color: var(--primary-color);
        }
        .section-title {
            color: var(--primary-color);
            font-size: 1.5rem;
            margin-bottom: 1rem;
            font-weight: 600;
            position: relative;
            display: inline-block;
        }
        .section-title::after {
            content: "";
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 50px;
            height: 3px;
            background-color: var(--primary-color);
        }
        .text-center a {
            color: var(--primary-color);
            text-decoration: none;
        }
        .text-center a:hover {
            color: var(--light-text);
            text-decoration: underline;
        }
        @media (max-width: 768px) {
            .container {
                padding: 1.5rem;
            }
            .fatwa-item h3 {
                font-size: 1.1rem;
            }
            .profile-header h2 {
                font-size: 1.75rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container">
        <?php if ($user): ?>
            <div class="profile-header">
                <h1><?php echo htmlspecialchars($user['username']); ?>'s Profile</h1>
                <div class="profile-details">
                    <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                    <p><strong>Role:</strong> <?php echo ucfirst(htmlspecialchars($user['role'])); ?></p>
                    <p><strong>Mobile Number:</strong> Not set (contact admin to update)</p>
                </div>
            </div>

            <h2 class="section-title">Fatwas Asked</h2>
            <?php if (empty($asked_fatwas)): ?>
                <p class="text-center">No fatwas asked yet.</p>
            <?php else: ?>
                <?php foreach ($asked_fatwas as $fatwa): ?>
                    <div class="fatwa-item">
                        <h3><?php echo htmlspecialchars($fatwa['title']); ?></h3>
                        <div class="fatwa-meta">
                            <p><i class="fas fa-tag"></i><strong>Category:</strong> <?php echo htmlspecialchars($fatwa['category'] ?? 'General'); ?></p>
                            <p><i class="fas fa-book"></i><strong>Madhab:</strong> <?php echo htmlspecialchars($fatwa['madhab'] ?? 'Not specified'); ?></p>
                            <p><i class="fas fa-info-circle"></i><strong>Status:</strong> <?php echo htmlspecialchars($fatwa['status']); ?></p>
                            <p><i class="fas fa-calendar-alt"></i><strong>Submitted:</strong> <?php echo date('d M Y, H:i', strtotime($fatwa['submitted_at'])); ?></p>
                            <?php if ($fatwa['status'] === 'Answered'): ?>
                                <p><i class="fas fa-user-tie"></i><strong>Answered by:</strong> <?php echo htmlspecialchars($fatwa['mufti_name'] ?? 'Unknown Mufti'); ?></p>
                                <p><i class="fas fa-calendar-check"></i><strong>Answered on:</strong> <?php echo date('d M Y, H:i', strtotime($fatwa['answered_at'])); ?></p>
                            <?php endif; ?>
                        </div>
                        <p><strong>Details:</strong> <?php echo htmlspecialchars($fatwa['details']); ?></p>
                        <?php if ($fatwa['status'] === 'Answered'): ?>
                            <p><strong>Answer:</strong> <?php echo htmlspecialchars($fatwa['answer'] ?? 'No answer provided'); ?></p>
                            <p><strong>Final Decision:</strong> <?php echo htmlspecialchars($fatwa['final_decision'] ?? 'Not specified'); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if ($user['role'] === 'mufti'): ?>
                <h2 class="section-title">Fatwas Answered</h2>
                <?php if (empty($answered_fatwas)): ?>
                    <p class="text-center">No fatwas answered yet.</p>
                <?php else: ?>
                    <?php foreach ($answered_fatwas as $fatwa): ?>
                        <div class="fatwa-item">
                            <h3><?php echo htmlspecialchars($fatwa['title']); ?></h3>
                            <div class="fatwa-meta">
                                <p><i class="fas fa-tag"></i><strong>Category:</strong> <?php echo htmlspecialchars($fatwa['category'] ?? 'General'); ?></p>
                                <p><i class="fas fa-book"></i><strong>Madhab:</strong> <?php echo htmlspecialchars($fatwa['madhab'] ?? 'Not specified'); ?></p>
                                <p><i class="fas fa-user"></i><strong>Asked by:</strong> <a href="/darulifta_local/profile?id=<?php echo $fatwa['user_id']; ?>" class="text-decoration-none"><?php echo htmlspecialchars($fatwa['questioner_name'] ?? 'Anonymous'); ?></a></p>
                                <p><i class="fas fa-calendar-alt"></i><strong>Submitted:</strong> <?php echo date('d M Y, H:i', strtotime($fatwa['submitted_at'])); ?></p>
                                <p><i class="fas fa-calendar-check"></i><strong>Answered on:</strong> <?php echo date('d M Y, H:i', strtotime($fatwa['answered_at'])); ?></p>
                            </div>
                            <p><strong>Details:</strong> <?php echo htmlspecialchars($fatwa['details']); ?></p>
                            <p><strong>Answer:</strong> <?php echo htmlspecialchars($fatwa['answer'] ?? 'No answer provided'); ?></p>
                            <p><strong>Final Decision:</strong> <?php echo htmlspecialchars($fatwa['final_decision'] ?? 'Not specified'); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>

        <?php else: ?>
            <p class="text-center">User not found.</p>
        <?php endif; ?>
        <p class="text-center">
            <a href="home.php">Back to Home</a>
        </p>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>