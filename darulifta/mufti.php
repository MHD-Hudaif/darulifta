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

logMessage('mufti.php', 'Script started, Session ID: ' . session_id());

if (!isLoggedIn() || !hasRole($db, 'mufti')) {
    logMessage('mufti.php', 'Access denied, redirecting to login.php');
    header('Location: login.php');
    exit;
}

try {
    $stmt = $db->prepare("SELECT f.*, u.username AS user_name FROM fatwas f JOIN users u ON f.user_id = u.id WHERE f.status = 'Pending' AND (f.mufti_id IS NULL OR f.mufti_id = ?) ORDER BY f.submitted_at DESC LIMIT 5");
    $stmt->execute([$_SESSION['user_id']]);
    $fatwas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logMessage('mufti.php', 'Database error: ' . $e->getMessage());
    $fatwas = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mufti Dashboard - Darul Ifta</title>
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
            padding: 2rem;
            max-width: 1000px;
            margin: 0 auto;
        }
        .dashboard-header {
            background: var(--darker-bg);
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 2rem;
            border: 1px solid var(--divider-color);
        }
        .fatwa-card {
            background: var(--darker-bg);
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            border: 1px solid var(--divider-color);
        }
        .fatwa-card .initials {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: var(--light-text);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 1rem;
        }
        .fatwa-card h3 {
            font-size: 1.25rem;
            color: var(--primary-color);
        }
        .fatwa-card p {
            color: var(--light-gray);
        }
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
        }
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        .pagination {
            justify-content: center;
            margin-top: 2rem;
        }
        .pagination a {
            color: var(--primary-color);
            padding: 0.5rem 1rem;
            text-decoration: none;
            border: 1px solid var(--divider-color);
            border-radius: 8px;
            margin: 0 0.2rem;
        }
        .pagination a:hover {
            background: rgba(255, 255, 255, 0.1);
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <div class="dashboard-header">
            <h2>Mufti Dashboard</h2>
            <p>Review and answer fatwas</p>
            <p><strong>Pending Fatwas:</strong> <?php echo count($fatwas); ?></p>
        </div>

        <h3>Pending Fatwas</h3>
        <p class="text-muted">Showing 1-<?php echo count($fatwas); ?> of <?php echo count($fatwas); ?> fatwas</p>

        <?php if (empty($fatwas)): ?>
            <p>No pending fatwas available.</p>
        <?php else: ?>
            <?php foreach ($fatwas as $fatwa): ?>
                <div class="fatwa-card">
                    <div class="d-flex align-items-center">
                        <div class="initials"><?php echo strtoupper(substr($fatwa['user_name'], 0, 2)); ?></div>
                        <div>
                            <strong><?php echo htmlspecialchars($fatwa['user_name']); ?></strong>
                            <p class="text-muted"><?php echo date('d M Y', strtotime($fatwa['submitted_at'])); ?> - <?php echo htmlspecialchars($fatwa['status']); ?></p>
                        </div>
                    </div>
                    <h3><?php echo htmlspecialchars($fatwa['title']); ?></h3>
                    <p><?php echo htmlspecialchars($fatwa['details']); ?></p>
                    <p>
                        <strong>Category:</strong> <?php echo htmlspecialchars($fatwa['category'] ?? 'N/A'); ?> |
                        <strong>Madhab:</strong> <?php echo htmlspecialchars($fatwa['madhab']); ?>
                    </p>
                    <p><span>Helpful (<?php echo $fatwa['helpful_count'] ?? 0; ?>)</span></p>
                    <a href="answer-fatwa.php?fatwa_id=<?php echo htmlspecialchars($fatwa['id']); ?>" class="btn btn-primary">Answer Fatwa</a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <nav aria-label="Page navigation" class="pagination">
            <a href="#">1</a>
        </nav>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js"></script>
</body>
</html>
<?php
ob_end_flush();
?>