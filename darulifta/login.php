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

function generateCsrfToken() {
    if (empty($_SESSION['csrf_token']) || (isset($_SESSION['csrf_token_time']) && time() - $_SESSION['csrf_token_time'] > 1800)) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

logMessage('login.php', 'Script started');

if (isLoggedIn()) {
    logMessage('login.php', 'User already logged in, redirecting to home.php');
    header('Location: home.php');
    exit;
}

$errors = [];
$csrf_token = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && validateCsrfToken($_POST['csrf_token'])) {
    logMessage('login.php', 'POST request with valid CSRF token');
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username)) {
        $errors[] = 'Username is required.';
    }
    if (empty($password)) {
        $errors[] = 'Password is required.';
    }

    if (empty($errors)) {
        try {
            $stmt = $db->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                
                try {
                    $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $updateStmt->execute([$user['id']]);
                } catch (PDOException $e) {
                    logMessage('login.php', 'Failed to update last login: ' . $e->getMessage());
                }
                
                session_regenerate_id(true);
                logMessage('login.php', 'User logged in: ' . $username . ' (Role: ' . $user['role'] . ')');
                
                header('Location: home.php');
                exit;
            } else {
                $errors[] = 'Invalid username or password.';
                logMessage('login.php', 'Login failed: Invalid credentials for ' . $username);
            }
        } catch (PDOException $e) {
            logMessage('login.php', 'Database error: ' . $e->getMessage());
            $errors[] = 'An error occurred. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Darul Ifta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --dark-bg: #121212;
            --light-bg: #ffffff;
            --dark-text: #ffffff;
            --light-text: #333333;
            --primary-color: #4caf50;
            --primary-dark: #388e3c;
            --darker-bg: #1e1e1e;
            --light-gray: #b0b0b0;
            --divider-color: #333333;
        }
        [data-theme="light"] {
            --dark-bg: var(--light-bg);
            --light-text: var(--light-text);
            --darker-bg: #f5f5f5;
            --light-gray: #666666;
            --divider-color: #cccccc;
        }
        body {
            background: var(--dark-bg);
            color: var(--light-text);
            font-family: 'Segoe UI', sans-serif;
            min-height: 100vh;
            padding-top: 20px;
        }
        .login-container {
            max-width: 400px;
            margin: 2rem auto;
            padding: 2rem;
            background: var(--darker-bg);
            border-radius: 12px;
            border: 1px solid var(--divider-color);
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-header h1 {
            font-size: 2rem;
            color: var(--primary-color);
        }
        .form-control {
            background: var(--darker-bg);
            border: 1px solid var(--divider-color);
            color: var(--light-text);
            border-radius: 8px;
        }
        .form-control:focus {
            background: var(--darker-bg);
            border-color: var(--primary-color);
            box-shadow: none;
        }
        .btn-primary {
            background-color: var(--primary-color);
            border: none;
            border-radius: 8px;
            padding: 0.75rem;
            width: 100%;
        }
        .btn-primary:hover {
            background-color: var(--primary-dark);
        }
        .error-message {
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        .text-center a {
            color: var(--primary-color);
            text-decoration: none;
        }
        .text-center a:hover {
            color: var(--primary-dark);
        }
        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--primary-color);
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        .theme-toggle i {
            color: var(--light-text);
        }
        .alert {
            background-color: var(--darker-bg);
            border-color: var(--divider-color);
            color: var(--light-text);
        }
        .alert-danger {
            border-left: 4px solid #dc3545;
        }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()">
        <i class="fas fa-moon"></i>
    </button>
    <div class="login-container">
        <div class="login-header">
            <h1>Login</h1>
            <p>Access your Darul Ifta account</p>
        </div>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <form action="login.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary">Login</button>
        </form>
        <p class="text-center mt-3">
            Don't have an account? <a href="sign-up.php">Sign up here</a>
        </p>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <script>
        function toggleTheme() {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateToggleIcon(newTheme);
        }

        function updateToggleIcon(theme) {
            const toggleIcon = document.querySelector('.theme-toggle i');
            toggleIcon.className = theme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
        }

        const savedTheme = localStorage.getItem('theme') || 'dark';
        document.documentElement.setAttribute('data-theme', savedTheme);
        updateToggleIcon(savedTheme);
    </script>
</body>
</html>
<?php ob_end_flush(); ?>