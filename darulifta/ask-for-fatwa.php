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

logMessage('ask-for-fatwa.php', 'Script started');

if (!isLoggedIn()) {
    logMessage('ask-for-fatwa.php', 'Not logged in, redirecting to login.php');
    header('Location: login.php');
    exit;
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $details = trim($_POST['details'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $madhab = in_array($_POST['madhab'] ?? '', ['Hanafi', 'Shafi', 'Common']) ? $_POST['madhab'] : '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (empty($title)) $errors[] = 'Title is required.';
    if (empty($details)) $errors[] = 'Details are required.';
    if (!in_array($category, ['Salah', 'Zakah', 'Hajj', 'Tahara', 'Sawm', 'Aqeedah', 'General', 'Other'])) $errors[] = 'Valid category is required.';
    if (empty($madhab)) $errors[] = 'Madhab is required.';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $errors[] = 'Invalid CSRF token.';
        logMessage('ask-for-fatwa.php', 'Invalid CSRF token');
    }

    if (empty($errors)) {
        try {
            $stmt = $db->prepare("INSERT INTO fatwas (user_id, title, details, category, madhab, status, submitted_at) VALUES (?, ?, ?, ?, ?, 'Pending', NOW())");
            $stmt->execute([$_SESSION['user_id'], $title, $details, $category, $madhab]);
            logMessage('ask-for-fatwa.php', 'Fatwa submitted by user_id ' . $_SESSION['user_id']);
            $success = 'Fatwa submitted successfully.';
        } catch (PDOException $e) {
            logMessage('ask-for-fatwa.php', 'Database error: ' . $e->getMessage());
            $errors[] = 'An error occurred while submitting the fatwa.';
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
    <title>Ask a Fatwa - Darul Ifta</title>
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
            max-width: 800px;
            margin: 0 auto;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            font-size: 0.95rem;
            color: var(--light-gray);
            margin-bottom: 0.5rem;
        }
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.6rem;
            border: 1px solid var(--divider-color);
            border-radius: 8px;
            background: var(--darker-bg);
            color: var(--light-text);
            font-size: 1rem;
        }
        .form-group select option {
            background: var(--darker-bg);
            color: var(--light-text);
        }
        .form-group select:focus,
        .form-group input:focus,
        .form-group textarea:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.2);
        }
        .form-group textarea {
            min-height: 150px;
            resize: vertical;
        }
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            transition: var(--transition);
        }
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        .alert {
            margin-bottom: 1rem;
            background-color: var(--darker-bg);
            border-color: var(--divider-color);
            color: var(--light-text);
            border-radius: 8px;
        }
        .alert-danger {
            border-left: 4px solid #dc3545;
        }
        .alert-success {
            border-left: 4px solid var(--primary-color);
        }
        @media (max-width: 768px) {
            .container {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <h2>Ask a Fatwa</h2>
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

        <form action="ask-for-fatwa.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div class="form-group">
                <label for="title">Title</label>
                <input type="text" id="title" name="title" value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" required>
            </div>
            <div class="form-group">
                <label for="details">Details</label>
                <textarea id="details" name="details" required><?php echo isset($_POST['details']) ? htmlspecialchars($_POST['details']) : ''; ?></textarea>
            </div>
            <div class="form-group">
                <label for="category">Category</label>
                <select id="category" name="category" required>
                    <option value="">Select a category</option>
                    <option value="Salah" <?php echo (isset($_POST['category']) && $_POST['category'] === 'Salah') ? 'selected' : ''; ?>>Salah</option>
                    <option value="Zakah" <?php echo (isset($_POST['category']) && $_POST['category'] === 'Zakah') ? 'selected' : ''; ?>>Zakah</option>
                    <option value="Hajj" <?php echo (isset($_POST['category']) && $_POST['category'] === 'Hajj') ? 'selected' : ''; ?>>Hajj</option>
                    <option value="Tahara" <?php echo (isset($_POST['category']) && $_POST['category'] === 'Tahara') ? 'selected' : ''; ?>>Tahara</option>
                    <option value="Sawm" <?php echo (isset($_POST['category']) && $_POST['category'] === 'Sawm') ? 'selected' : ''; ?>>Sawm (Fasting)</option>
                    <option value="Nikah" <?php echo (isset($_POST['category']) && $_POST['category'] === 'Nikah') ? 'selected' : ''; ?>>Aqeedah</option>
                    <option value="General" <?php echo (isset($_POST['category']) && $_POST['category'] === 'General') ? 'selected' : ''; ?>>General</option>
                    <option value="Other" <?php echo (isset($_POST['category']) && $_POST['category'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            <div class="form-group">
                <label for="madhab">Madhab</label>
                <select id="madhab" name="madhab" required>
                    <option value="">Select a madhab</option>
                    <option value="Hanafi" <?php echo (isset($_POST['madhab']) && $_POST['madhab'] === 'Hanafi') ? 'selected' : ''; ?>>Hanafi</option>
                    <option value="Shafi" <?php echo (isset($_POST['madhab']) && $_POST['madhab'] === 'Shafi') ? 'selected' : ''; ?>>Shafi</option>
                    <option value="Common" <?php echo (isset($_POST['madhab']) && $_POST['madhab'] === 'Common') ? 'selected' : ''; ?>>Common</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Submit Fatwa</button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>