<?php
ob_start();
require_once 'config.php';

function logMessage($file, $message) {
    error_log("[$file] " . date('Y-m-d H:i:s') . ": $message");
}

function isLoggedIn() {
    $loggedIn = !empty($_SESSION['user_id']);
    logMessage('admin.php', 'isLoggedIn: ' . ($loggedIn ? 'true' : 'false') . ', user_id=' . ($_SESSION['user_id'] ?? 'unset'));
    return $loggedIn;
}

function hasRole($role) {
    global $db;
    if (!isLoggedIn()) {
        logMessage('admin.php', 'hasRole: Not logged in');
        return false;
    }
    try {
        $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_role = $stmt->fetchColumn();
        $hasRole = $user_role && strcasecmp($user_role, $role) === 0;
        logMessage('admin.php', "hasRole('$role') for user_id {$_SESSION['user_id']}: " . ($hasRole ? 'true' : 'false') . ", found role: " . ($user_role ?: 'none'));
        return $hasRole;
    } catch (PDOException $e) {
        logMessage('admin.php', 'hasRole error: ' . $e->getMessage());
        return false;
    }
}

if (!isLoggedIn() || !hasRole('admin')) {
    logMessage('admin.php', 'Unauthorized access, redirecting to login.php');
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    logMessage('admin.php', "CSRF token generated: {$_SESSION['csrf_token']}");
}

try {
    $total_users = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $total_muftis = $db->query("SELECT COUNT(*) FROM users WHERE role = 'mufti'")->fetchColumn();
    $total_fatwas = $db->query("SELECT COUNT(*) FROM fatwas")->fetchColumn();
    $answered_fatwas = $db->query("SELECT COUNT(*) FROM fatwas WHERE status = 'Answered'")->fetchColumn();
    $users = $db->query("SELECT id, username, role, created_at FROM users ORDER BY created_at DESC LIMIT 10")->fetchAll();
    $fatwas = $db->query("SELECT f.id, f.title, f.status, f.submitted_at, u.username AS asker FROM fatwas f JOIN users u ON f.user_id = u.id ORDER BY f.submitted_at DESC LIMIT 5")->fetchAll();
} catch (PDOException $e) {
    logMessage('admin.php', 'Database error: ' . $e->getMessage());
    $total_users = $total_muftis = $total_fatwas = $answered_fatwas = 0;
    $users = $fatwas = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['role'], $_POST['csrf_token'])) {
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        logMessage('admin.php', 'Invalid CSRF token');
        header('Location: admin.php');
        exit;
    }
    $user_id = (int)$_POST['user_id'];
    $new_role = in_array($_POST['role'], ['user', 'admin', 'mufti']) ? $_POST['role'] : 'user';
    try {
        $stmt = $db->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$new_role, $user_id]);
        logMessage('admin.php', "Role changed for user $user_id to $new_role");
        header('Location: admin.php');
        exit;
    } catch (PDOException $e) {
        logMessage('admin.php', 'Error changing role: ' . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Darul Ifta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: var(--dark-bg);
            color: var(--light-text);
            font-family: 'Segoe UI', sans-serif;
            padding-top: 70px;
        }
        .admin-container {
            max-width: 1400px;
            padding: 2rem;
            margin: auto;
        }
        .admin-header {
            background: var(--primary-color);
            color: var(--light-text);
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            border: 1px solid var(--divider-color);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: var(--darker-bg);
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            border: 1px solid var(--divider-color);
        }
        .stat-card h3 {
            font-size: 2rem;
            color: var(--primary-color);
        }
        .admin-section {
            background: var(--darker-bg);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid var(--divider-color);
        }
        .section-title {
            color: var(--primary-color);
            font-size: 1.5rem;
            border-bottom: 1px solid var(--divider-color);
            padding-bottom: 0.5rem;
        }
        .table {
            color: var(--light-text);
        }
        .table th {
            background: var(--darker-bg);
            color: var(--primary-color);
        }
        .role-select {
            background: var(--darker-bg);
            color: var(--light-text);
            border: 1px solid var(--divider-color);
            border-radius: 6px;
            padding: 0.375rem 0.75rem;
        }
        .btn-admin {
            background: var(--primary-color);
            color: var(--light-text);
            border-radius: 6px;
        }
        .badge-pending {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
        }
        .badge-answered {
            background: rgba(40, 167, 69, 0.2);
            color: var(--primary-color);
        }
        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--primary-color);
            color: var(--light-text);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.5rem;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="admin-container">
        <div class="admin-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="fas fa-shield-alt me-2"></i>Admin Dashboard</h1>
                    <p>Manage users and fatwas</p>
                </div>
                <div class="d-flex align-items-center">
                    <div class="user-avatar">
                        <?php
                        $stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        echo strtoupper(substr($stmt->fetchColumn(), 0, 1));
                        ?>
                    </div>
                    <div>
                        <div class="fw-bold"><?php echo htmlspecialchars($admin_username ?? 'Admin'); ?></div>
                        <small class="text-white-50">Administrator</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo $total_users; ?></h3>
                <p><i class="fas fa-users me-2"></i>Total Users</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $total_muftis; ?></h3>
                <p><i class="fas fa-user-tie me-2"></i>Muftis</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $total_fatwas; ?></h3>
                <p><i class="fas fa-question-circle me-2"></i>Total Fatwas</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $answered_fatwas; ?></h3>
                <p><i class="fas fa-check-circle me-2"></i>Answered Fatwas</p>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-8">
                <div class="admin-section">
                    <h2 class="section-title"><i class="fas fa-users-cog me-2"></i>User Management</h2>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar">
                                                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($user['username']); ?></div>
                                                    <small class="text-muted">ID: <?php echo $user['id']; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <form action="admin.php" method="POST">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <select name="role" class="role-select" onchange="this.form.submit()">
                                                    <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                                                    <option value="mufti" <?php echo $user['role'] === 'mufti' ? 'selected' : ''; ?>>Mufti</option>
                                                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                </select>
                                            </form>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <a href="overview.php?id=<?php echo $user['id']; ?>" class="btn btn-admin">
                                                <i class="fas fa-eye me-1"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
               
                    <h2 class="section-title"><i class="fas fa-question-circle me-2"></i>Recent Fatwas</h2>
                    <div class="list-group">
                        <?php foreach ($fatwas as $fatwa): ?>
                            <a href="fatwa.php?id=<?php echo $fatwa['id']; ?>" class="list-group-item list-group-item-action bg-transparent border-dark text-light">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="fw-bold"><?php echo htmlspecialchars($fatwa['title']); ?></div>
                                    <span class="badge <?php echo $fatwa['status'] === 'Answered' ? 'badge-answered' : 'badge-pending'; ?>">
                                        <?php echo htmlspecialchars($fatwa['status']); ?>
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between mt-2">
                                    <small class="text-muted">By <?php echo htmlspecialchars($fatwa['asker']); ?></small>
                                    <small class="text-muted"><?php echo date('M d', strtotime($fatwa['submitted_at'])); ?></small>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.com/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js"></script>
    <script>
        document.querySelectorAll('.role-select').forEach(function(select) {
            select.addEventListener('change', function() {
                if (confirm('Are you sure you want to change this user\'s role?')) {
                    this.form.submit();
                } else {
                    this.value = this.getAttribute('data-original-value');
                }
            });
            select.setAttribute('data-original-value', select.value);
        });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>