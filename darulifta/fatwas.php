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

logMessage('fatwas.php', 'Script started, Session ID: ' . session_id());

if (!isLoggedIn()) {
    logMessage('fatwas.php', 'Not logged in, redirecting to login.php');
    header('Location: login.php');
    exit;
}

$items_per_page = 10;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$madhab = isset($_GET['madhab']) ? trim($_GET['madhab']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$sort = in_array($_GET['sort'] ?? '', ['newest', 'oldest', 'title_asc', 'title_desc']) ? $_GET['sort'] : 'newest';

$whereClauses = [];
$params = [];
$types = '';

if ($search) {
    $whereClauses[] = "(f.title LIKE ? OR f.details LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'ss';
}
if ($category) {
    $whereClauses[] = "f.category = ?";
    $params[] = $category;
    $types .= 's';
}
if ($madhab) {
    $whereClauses[] = "f.madhab = ?";
    $params[] = $madhab;
    $types .= 's';
}
if ($status) {
    $whereClauses[] = "f.status = ?";
    $params[] = $status;
    $types .= 's';
}

$whereSql = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$orderBy = match ($sort) {
    'oldest' => 'f.submitted_at ASC',
    'title_asc' => 'f.title ASC',
    'title_desc' => 'f.title DESC',
    default => 'f.submitted_at DESC'
};

try {
    $countQuery = "SELECT COUNT(*) FROM fatwas f $whereSql";
    $stmt = $db->prepare($countQuery);
    if ($params) {
        $stmt->bindValue(1, $params[0], PDO::PARAM_STR);
        if (isset($params[1])) $stmt->bindValue(2, $params[1], PDO::PARAM_STR);
        for ($i = 2; $i < count($params); $i++) {
            $stmt->bindValue($i + 1, $params[$i], PDO::PARAM_STR);
        }
    }
    $stmt->execute();
    $total_fatwas = $stmt->fetchColumn();
    $total_pages = ceil($total_fatwas / $items_per_page);

    $query = "SELECT f.*, u.username AS user_name, m.username AS mufti_name, u.id AS user_id, m.id AS mufti_id
              FROM fatwas f 
              JOIN users u ON f.user_id = u.id 
              LEFT JOIN users m ON f.mufti_id = m.id 
              $whereSql 
              ORDER BY $orderBy 
              LIMIT ? OFFSET ?";
    $stmt = $db->prepare($query);
    if ($params) {
        for ($i = 0; $i < count($params); $i++) {
            $stmt->bindValue($i + 1, $params[$i], PDO::PARAM_STR);
        }
        $stmt->bindValue(count($params) + 1, $items_per_page, PDO::PARAM_INT);
        $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(1, $items_per_page, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    }
    $stmt->execute();
    $fatwas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    logMessage('fatwas.php', 'Fetched ' . count($fatwas) . ' fatwas for page ' . $current_page);
} catch (PDOException $e) {
    logMessage('fatwas.php', 'Database error: ' . $e->getMessage());
    $fatwas = [];
    $total_pages = 1;
    $total_fatwas = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Fatwas - Darul Ifta</title>
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
        .main-container {
            max-width: 1200px;
            padding: 2rem;
            margin: 0 auto;
        }
        .header-section {
            background: var(--darker-bg);
            padding: 2rem;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 2rem;
            border: 1px solid var(--divider-color);
        }
        .header-section h1 {
            color: var(--primary-color);
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        .search-filter-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        .search-bar {
            flex: 1;
            position: relative;
        }
        .search-bar input {
            width: 100%;
            padding: 0.75rem 2.5rem 0.75rem 1rem;
            border: 1px solid var(--divider-color);
            border-radius: 8px;
            background: var(--darker-bg);
            color: var(--light-text);
            font-size: 1rem;
        }
        .search-bar button {
            position: absolute;
            right: 0.5rem;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: var(--primary-color);
            font-size: 1.2rem;
            cursor: pointer;
        }
        .filter-group {
            display: flex;
            gap: 1rem;
        }
        .filter-group select {
            padding: 0.75rem;
            border: 1px solid var(--divider-color);
            border-radius: 8px;
            background: var(--darker-bg);
            color: var(--light-text);
            font-size: 0.95rem;
            min-width: 150px;
        }
        .filter-group select option {
            background: var(--darker-bg);
            color: var(--light-text);
        }
        .fatwa-card {
            background: var(--darker-bg);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--divider-color);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .fatwa-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
        }
        .fatwa-title {
            font-size: 1.5rem;
            color: var(--primary-color);
            margin-bottom: 0.75rem;
        }
        .fatwa-title a {
            color: var(--primary-color);
            text-decoration: none;
        }
        .fatwa-title a:hover {
            text-decoration: underline;
        }
        .fatwa-meta {
            color: var(--light-gray);
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
        }
        .fatwa-details {
            line-height: 1.6;
            margin-bottom: 1rem;
            color: var(--light-text);
        }
        .fatwa-answer {
            display: none;
            margin-top: 1rem;
            padding: 1rem;
            background: rgba(76, 175, 80, 0.1);
            border-radius: 8px;
        }
        .answer-toggle {
            color: var(--primary-color);
            cursor: pointer;
            font-size: 0.9rem;
            text-decoration: none;
        }
        .answer-toggle:hover {
            color: var(--light-text);
        }
        .btn-primary {
            background-color: var(--primary-color);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            transition: background-color 0.3s ease;
        }
        .btn-primary:hover {
            background-color: var(--primary-dark);
        }
        .pagination {
            justify-content: center;
            margin-top: 2rem;
        }
        .pagination a {
            color: var(--primary-color);
            padding: 0.5rem 1rem;
            margin: 0 0.2rem;
            border: 1px solid var(--divider-color);
            border-radius: 8px;
            text-decoration: none;
            transition: background-color 0.3s ease;
        }
        .pagination a:hover {
            background: rgba(76, 175, 80, 0.1);
        }
        .pagination .active {
            background: var(--primary-color);
            color: var(--light-text);
            border-color: var(--primary-color);
        }
        .no-results {
            text-align: center;
            color: var(--light-gray);
            padding: 2rem;
        }
        @media (max-width: 768px) {
            .main-container {
                padding: 1.5rem;
            }
            .header-section h1 {
                font-size: 2rem;
            }
            .search-filter-bar {
                flex-direction: column;
            }
            .filter-group {
                flex-direction: column;
                width: 100%;
            }
            .filter-group select {
                width: 100%;
            }
            .fatwa-title {
                font-size: 1.25rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="main-container">
        <div class="header-section">
            <h1>All Fatwas</h1>
            <p>Explore all fatwas submitted to Darul Ifta</p>
            <p><strong>Total Fatwas:</strong> <?php echo $total_fatwas; ?></p>
        </div>

        <div class="search-filter-bar">
            <div class="search-bar">
                <form method="GET" action="fatwas.php">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search fatwas by title or details">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
            </div>
            <div class="filter-group">
                <select name="category" onchange="this.form.submit()" form="filterForm">
                    <option value="">All Categories</option>
                    <option value="Salah" <?php echo $category === 'Salah' ? 'selected' : ''; ?>>Salah</option>
                    <option value="Zakah" <?php echo $category === 'Zakah' ? 'selected' : ''; ?>>Zakah</option>
                    <option value="Hajj" <?php echo $category === 'Hajj' ? 'selected' : ''; ?>>Hajj</option>
                    <option value="Tahara" <?php echo $category === 'Tahara' ? 'selected' : ''; ?>>Tahara</option>
                    <option value="Sawm" <?php echo $category === 'Sawm' ? 'selected' : ''; ?>>Sawm</option>
                    <option value="Aqeedah" <?php echo $category === 'Aqeedah' ? 'selected' : ''; ?>>Aqeedah</option>
                    <option value="General" <?php echo $category === 'General' ? 'selected' : ''; ?>>General</option>
                    <option value="Other" <?php echo $category === 'Other' ? 'selected' : ''; ?>>Other</option>
                </select>
                <select name="madhab" onchange="this.form.submit()" form="filterForm">
                    <option value="">All Madhabs</option>
                    <option value="Hanafi" <?php echo $madhab === 'Hanafi' ? 'selected' : ''; ?>>Hanafi</option>
                    <option value="Shafi" <?php echo $madhab === 'Shafi' ? 'selected' : ''; ?>>Shafi</option>
                    <option value="Common" <?php echo $madhab === 'Common' ? 'selected' : ''; ?>>Common</option>
                </select>
                <select name="status" onchange="this.form.submit()" form="filterForm">
                    <option value="">All Statuses</option>
                    <option value="Pending" <?php echo $status === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Answered" <?php echo $status === 'Answered' ? 'selected' : ''; ?>>Answered</option>
                </select>
                <select name="sort" onchange="this.form.submit()" form="filterForm">
                    <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                    <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                    <option value="title_asc" <?php echo $sort === 'title_asc' ? 'selected' : ''; ?>>Title A-Z</option>
                    <option value="title_desc" <?php echo $sort === 'title_desc' ? 'selected' : ''; ?>>Title Z-A</option>
                </select>
                <form id="filterForm" method="GET" action="fatwas.php" style="display: none;">
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                </form>
            </div>
        </div>

        <?php if (empty($fatwas)): ?>
            <div class="no-results">
                <p>No fatwas found matching your criteria.</p>
            </div>
        <?php else: ?>
            <p class="text-muted mb-3">Showing <?php echo ($offset + 1); ?>-<?php echo min($offset + $items_per_page, $total_fatwas); ?> of <?php echo $total_fatwas; ?> fatwas</p>
            <?php foreach ($fatwas as $index => $fatwa): ?>
                <div class="fatwa-card">
                    <h3 class="fatwa-title">
                        <a href="fatwa.php?id=<?php echo htmlspecialchars($fatwa['id']); ?>">
                            <?php echo htmlspecialchars($fatwa['title']); ?>
                        </a>
                    </h3>
                    <div class="fatwa-meta">
                        <p><i class="fas fa-user"></i>Asked by: <a href="profile.php?id=<?php echo htmlspecialchars($fatwa['user_id']); ?>"><?php echo htmlspecialchars($fatwa['user_name']); ?></a></p>
                        <p><i class="fas fa-tag"></i>Category: <?php echo htmlspecialchars($fatwa['category'] ?? 'General'); ?></p>
                        <p><i class="fas fa-book"></i>Madhab: <?php echo htmlspecialchars($fatwa['madhab'] ?? 'Not specified'); ?></p>
                        <p><i class="fas fa-info-circle"></i>Status: <?php echo htmlspecialchars($fatwa['status']); ?></p>
                        <p><i class="fas fa-calendar-alt"></i>Submitted: <?php echo date('F j, Y, H:i', strtotime($fatwa['submitted_at'])); ?></p>
                        <?php if ($fatwa['status'] === 'Answered' && $fatwa['mufti_id']): ?>
                            <p><i class="fas fa-user-tie"></i>Answered by: <a href="profile.php?id=<?php echo htmlspecialchars($fatwa['mufti_id']); ?>"><?php echo htmlspecialchars($fatwa['mufti_name'] ?? 'Unknown Mufti'); ?></a></p>
                            <p><i class="fas fa-calendar-check"></i>Answered on: <?php echo date('F j, Y, H:i', strtotime($fatwa['answered_at'])); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="fatwa-details">
                        <strong>Details:</strong> <?php echo htmlspecialchars($fatwa['details']); ?>
                    </div>
                    <?php if ($fatwa['status'] === 'Answered'): ?>
                        <a href="#" class="answer-toggle" data-target="answer-<?php echo $index; ?>">Show Answer</a>
                        <div id="answer-<?php echo $index; ?>" class="fatwa-answer">
                            <p><strong>Answer:</strong> <?php echo htmlspecialchars($fatwa['answer'] ?? 'No answer provided'); ?></p>
                            <p><strong>Final Decision:</strong> <?php echo htmlspecialchars($fatwa['final_decision'] ?? 'Not specified'); ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if ($fatwa['status'] === 'Pending' && hasRole($db, 'mufti')): ?>
                        <a href="answer-fatwa.php?fatwa_id=<?php echo htmlspecialchars($fatwa['id']); ?>" class="btn btn-primary mt-2">Answer Fatwa</a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <nav aria-label="Fatwa pagination" class="pagination">
                <?php if ($current_page > 1): ?>
                    <a href="fatwas.php?page=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&madhab=<?php echo urlencode($madhab); ?>&status=<?php echo urlencode($status); ?>&sort=<?php echo $sort; ?>">Previous</a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="fatwas.php?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&madhab=<?php echo urlencode($madhab); ?>&status=<?php echo urlencode($status); ?>&sort=<?php echo $sort; ?>" class="<?php echo $i === $current_page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php if ($current_page < $total_pages): ?>
                    <a href="fatwas.php?page=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>&madhab=<?php echo urlencode($madhab); ?>&status=<?php echo urlencode($status); ?>&sort=<?php echo $sort; ?>">Next</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js"></script>
    <script>
        document.querySelectorAll('.answer-toggle').forEach(toggle => {
            toggle.addEventListener('click', (e) => {
                e.preventDefault();
                const targetId = toggle.getAttribute('data-target');
                const answerContent = document.getElementById(targetId);
                const isVisible = answerContent.style.display === 'block';
                answerContent.style.display = isVisible ? 'none' : 'block';
                toggle.textContent = isVisible ? 'Show Answer' : 'Hide Answer';
            });
        });

        document.querySelectorAll('.fatwa-card').forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(() => {
                card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>