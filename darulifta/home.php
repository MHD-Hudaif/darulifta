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

logMessage('home.php', 'Script started, Session ID: ' . session_id());

// Check for headers sent
if (headers_sent($file, $line)) {
    logMessage('home.php', "Headers sent in $file at line $line");
    die("Headers already sent in $file at line $line");
}

$is_logged_in = isLoggedIn();
$user = null;
if ($is_logged_in) {
    try {
        $stmt = $db->prepare("SELECT id, username, role FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            logMessage('home.php', 'No user found for ID ' . $_SESSION['user_id'] . ', redirecting to login.php');
            session_destroy();
            session_write_close();
            header('Location: login.php');
            exit;
        }
    } catch (PDOException $e) {
        logMessage('home.php', 'Database error: ' . $e->getMessage());
        session_destroy();
        session_write_close();
        header('Location: login.php');
        exit;
    }
}

$fatwas_query = $is_logged_in 
    ? "SELECT f.*, u1.username AS mufti_name, u2.username AS questioner_name, u1.id AS mufti_id, u2.id AS questioner_id 
       FROM fatwas f 
       LEFT JOIN users u1 ON f.mufti_id = u1.id 
       LEFT JOIN users u2 ON f.user_id = u2.id 
       WHERE f.status = 'Answered' 
       ORDER BY f.answered_at DESC LIMIT 5"
    : "SELECT f.*, u1.username AS mufti_name, u2.username AS questioner_name, u1.id AS mufti_id, u2.id AS questioner_id 
       FROM fatwas f 
       LEFT JOIN users u1 ON f.mufti_id = u1.id 
       LEFT JOIN users u2 ON f.user_id = u2.id 
       WHERE f.status = 'Answered' 
       ORDER BY f.answered_at DESC LIMIT 3";
try {
    $fatwas = $db->query($fatwas_query)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logMessage('home.php', 'Fatwas query error: ' . $e->getMessage());
    $fatwas = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - Darul Ifta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/animate.css@4.1.1/animate.min.css">
    <style>
        :root {
            --primary-color: #1a936f;
            --primary-light: #88d498;
            --primary-dark: #114b5f;
            --secondary-color: #c6dabf;
            --accent-color: #f3e9d2;
            --dark-bg: #0d1b2a;
            --darker-bg: #0a1423;
            --card-bg: #16213e;
            --text-light: #e0e1dd;
            --text-muted: #9a9b94;
            --border-color: #2a3a5a;
            --shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
            --transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            --gradient: linear-gradient(135deg, var(--primary-dark), var(--primary-color));
        }
        
        /* Light mode variables */
        .light-mode {
            --primary-color: #1a936f;
            --primary-light: #88d498;
            --primary-dark: #114b5f;
            --secondary-color: #f8f9fa;
            --accent-color: #f3e9d2;
            --dark-bg: #f8f9fa;
            --darker-bg: #e9ecef;
            --card-bg: #ffffff;
            --text-light: #212529;
            --text-muted: #6c757d;
            --border-color: #dee2e6;
            --shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
            --gradient: linear-gradient(135deg, var(--primary-color), var(--primary-light));
        }
        
        body {
            background-color: var(--dark-bg);
            color: var(--text-light);
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            padding-top: 70px;
            transition: var(--transition);
        }
        
        .main-container {
            max-width: 1400px;
            padding: 2rem;
            margin: 0 auto;
        }
        
        .hero-section {
            background: var(--gradient);
            color: white;
            padding: 4rem 2rem;
            margin-bottom: 3rem;
            border-radius: 16px;
            text-align: center;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
            animation: fadeInDown 0.8s;
        }
        
        .hero-section::before {
            content: "";
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            transform: rotate(30deg);
        }
        
        .hero-title {
            font-size: 3rem;
            margin-bottom: 1.5rem;
            position: relative;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .welcome-message {
            font-size: 1.25rem;
            margin-bottom: 2.5rem;
            opacity: 0.9;
            position: relative;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.6;
        }
        
        .btn-primary {
            background-color: white;
            color: var(--primary-dark);
            border: none;
            padding: 0.85rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            transition: var(--transition);
            margin: 0.5rem;
            box-shadow: var(--shadow);
            position: relative;
            letter-spacing: 0.5px;
        }
        
        .btn-primary:hover {
            background-color: var(--accent-color);
            color: var(--primary-dark);
            transform: translateY(-3px) scale(1.03);
            box-shadow: 0 12px 20px rgba(0,0,0,0.2);
        }
        
        .btn-outline {
            background-color: transparent;
            color: white;
            border: 2px solid white;
            padding: 0.85rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            transition: var(--transition);
            margin: 0.5rem;
            position: relative;
            letter-spacing: 0.5px;
        }
        
        .btn-outline:hover {
            background-color: rgba(255,255,255,0.1);
            color: white;
            transform: translateY(-3px) scale(1.03);
            box-shadow: 0 12px 20px rgba(0,0,0,0.2);
        }
        
        .section-title {
            color: var(--primary-light);
            margin-bottom: 2rem;
            font-size: 2rem;
            font-weight: 700;
            position: relative;
            display: inline-block;
            animation: fadeIn 0.8s;
        }
        
        .section-title::after {
            content: "";
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 60px;
            height: 4px;
            background-color: var(--primary-light);
            border-radius: 2px;
        }
        
        .fatwa-card {
            background-color: var(--card-bg);
            border: none;
            border-radius: 16px;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            transition: var(--transition);
            height: 100%;
            border: 1px solid var(--border-color);
            overflow: hidden;
        }
        
        .fatwa-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.3);
            border-color: var(--primary-color);
        }
        
        .card-header {
            background-color: rgba(0,0,0,0.2);
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            position: relative;
        }
        
        .fatwa-category {
            position: absolute;
            top: 0;
            right: 0;
            background-color: var(--primary-dark);
            color: white;
            padding: 0.25rem 1rem;
            border-bottom-left-radius: 8px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .fatwa-title {
            color: var(--primary-light);
            margin-bottom: 0.75rem;
            font-size: 1.5rem;
            font-weight: 600;
            line-height: 1.3;
        }
        
        .fatwa-meta {
            color: var(--text-muted);
            font-size: 0.95rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .fatwa-meta i {
            margin-right: 0.5rem;
            color: var(--primary-light);
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .fatwa-question {
            margin-bottom: 1.5rem;
            line-height: 1.7;
            font-size: 1.1rem;
            position: relative;
            padding-left: 1.5rem;
        }
        
        .fatwa-question::before {
            content: "Q:";
            position: absolute;
            left: 0;
            top: 0;
            color: var(--primary-light);
            font-weight: bold;
        }
        
        .fatwa-answer {
            margin-bottom: 1.5rem;
            line-height: 1.7;
            position: relative;
            max-height: 120px;
            overflow: hidden;
            transition: max-height 0.5s ease;
            padding-left: 1.5rem;
        }
        
        .fatwa-answer::before {
            content: "A:";
            position: absolute;
            left: 0;
            top: 0;
            color: var(--primary-color);
            font-weight: bold;
        }
        
        .fatwa-answer.expanded {
            max-height: none;
        }
        
        .fatwa-answer:not(.expanded)::after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: linear-gradient(transparent, var(--card-bg));
        }
        
        .fatwa-decision {
            color: var(--primary-light);
            margin-top: 1.5rem;
            font-weight: 500;
            padding: 1rem;
            background-color: rgba(26, 147, 111, 0.1);
            border-radius: 8px;
            display: none;
            border-left: 4px solid var(--primary-color);
        }
        
        .fatwa-answer.expanded + .fatwa-decision {
            display: block;
            animation: fadeIn 0.5s;
        }
        
        .toggle-answer {
            color: var(--primary-light);
            cursor: pointer;
            font-size: 0.95rem;
            margin-top: 1rem;
            display: inline-flex;
            align-items: center;
            transition: var(--transition);
            background-color: rgba(26, 147, 111, 0.1);
            padding: 0.5rem 1rem;
            border-radius: 50px;
        }
        
        .toggle-answer:hover {
            background-color: rgba(26, 147, 111, 0.2);
            color: var(--accent-color);
        }
        
        .toggle-answer i {
            margin-left: 0.5rem;
            transition: transform 0.3s ease;
        }
        
        .toggle-answer.expanded i {
            transform: rotate(180deg);
        }
        
        .fatwa-madhab {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-top: 0.75rem;
            display: inline-block;
            background-color: rgba(136, 212, 152, 0.1);
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
        }
        
        .profile-link {
            color: var(--primary-light);
            text-decoration: none;
            transition: var(--transition);
            font-weight: 500;
        }
        
        .profile-link:hover {
            color: var(--accent-color);
            text-decoration: underline;
        }
        
        .view-more {
            text-align: center;
            margin-top: 3rem;
        }
        
        .view-more-btn {
            background: var(--gradient);
            color: white;
            padding: 0.85rem 2.5rem;
            border-radius: 50px;
            font-weight: 600;
            transition: var(--transition);
            border: none;
            letter-spacing: 0.5px;
            box-shadow: var(--shadow);
        }
        
        .view-more-btn:hover {
            transform: translateY(-3px) scale(1.03);
            box-shadow: 0 15px 30px rgba(0,0,0,0.3);
            color: white;
        }
        
        .action-buttons {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .featured-fatwas {
            padding: 3rem 0;
        }
        
        .floating-shapes {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
        }
        
        .shape {
            position: absolute;
            opacity: 0.1;
            border-radius: 50%;
            background-color: white;
        }
        
        .shape-1 {
            width: 200px;
            height: 200px;
            top: -50px;
            left: -50px;
        }
        
        .shape-2 {
            width: 300px;
            height: 300px;
            bottom: -100px;
            right: -100px;
        }
        
        .shape-3 {
            width: 150px;
            height: 150px;
            top: 30%;
            right: 20%;
        }
        
        .badge-icon {
            width: 40px;
            height: 40px;
            background-color: var(--primary-dark);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            flex-shrink: 0;
        }
        
        .features-section {
            margin: 4rem 0;
        }
        
        .feature-card {
            background-color: var(--card-bg);
            border-radius: 16px;
            padding: 2rem;
            height: 100%;
            border: 1px solid var(--border-color);
            transition: var(--transition);
            box-shadow: var(--shadow);
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.2);
            border-color: var(--primary-color);
        }
        
        .feature-icon {
            font-size: 2.5rem;
            color: var(--primary-light);
            margin-bottom: 1.5rem;
        }
        
        .feature-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--primary-light);
        }
        
        .feature-desc {
            color: var(--text-muted);
            line-height: 1.7;
        }
        
        .testimonial-section {
            background: var(--gradient);
            padding: 4rem 0;
            margin: 4rem 0;
            border-radius: 16px;
            position: relative;
            overflow: hidden;
        }
        
        .testimonial-card {
            background-color: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 2rem;
            height: 100%;
            border: 1px solid rgba(255,255,255,0.2);
            transition: var(--transition);
        }
        
        .testimonial-text {
            font-style: italic;
            margin-bottom: 1.5rem;
            line-height: 1.7;
        }
        
        .testimonial-author {
            display: flex;
            align-items: center;
        }
        
        .author-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: white;
            color: var(--primary-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 1rem;
        }
        
        .author-info h5 {
            margin-bottom: 0.25rem;
            color: white;
        }
        
        .author-info p {
            color: rgba(255,255,255,0.8);
            margin-bottom: 0;
            font-size: 0.9rem;
        }
        
        .stats-section {
            margin: 4rem 0;
        }
        
        .stat-card {
            background-color: var(--card-bg);
            border-radius: 16px;
            padding: 2rem;
            text-align: center;
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: 700;
            color: var(--primary-light);
            margin-bottom: 0.5rem;
            line-height: 1;
        }
        
        .stat-label {
            color: var(--text-muted);
            font-size: 1.1rem;
        }
        
        .footer {
            background-color: var(--darker-bg);
            padding: 3rem 0;
            margin-top: 4rem;
            border-top: 1px solid var(--border-color);
        }
        
        .footer-logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-light);
            margin-bottom: 1.5rem;
            display: inline-block;
        }
        
        .footer-links h5 {
            color: var(--primary-light);
            margin-bottom: 1.5rem;
            font-size: 1.25rem;
        }
        
        .footer-links ul {
            list-style: none;
            padding: 0;
        }
        
        .footer-links li {
            margin-bottom: 0.75rem;
        }
        
        .footer-links a {
            color: var(--text-muted);
            text-decoration: none;
            transition: var(--transition);
        }
        
        .footer-links a:hover {
            color: var(--primary-light);
            padding-left: 5px;
        }
        
        .social-links {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .social-link {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--card-bg);
            color: var(--text-light);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }
        
        .social-link:hover {
            background-color: var(--primary-color);
            color: white;
            transform: translateY(-3px);
        }
        
        .copyright {
            margin-top: 3rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
            color: var(--text-muted);
            text-align: center;
        }
        
        @media (max-width: 992px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .section-title {
                font-size: 1.75rem;
            }
        }
        
        @media (max-width: 768px) {
            .main-container {
                padding: 1.5rem;
            }
            
            .hero-title {
                font-size: 2rem;
            }
            
            .welcome-message {
                font-size: 1.1rem;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn-primary, .btn-outline {
                width: 100%;
                max-width: 300px;
                margin: 0.5rem 0;
            }
            
            .section-title {
                font-size: 1.5rem;
            }
            
            .feature-card, .testimonial-card, .stat-card {
                margin-bottom: 1.5rem;
            }
        }
        
        /* Animations */
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
        
        .floating {
            animation: float 6s ease-in-out infinite;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-container">
        <?php if ($is_logged_in): ?>
            <div class="hero-section">
                <div class="floating-shapes">
                    <div class="shape shape-1"></div>
                    <div class="shape shape-2"></div>
                    <div class="shape shape-3"></div>
                </div>
                <h1 class="hero-title">Assalamu Alaikum, <?php echo htmlspecialchars($user['username']); ?></h1>
                <p class="welcome-message">Access authentic Islamic rulings and guidance from qualified scholars. Our platform connects you with knowledgeable muftis who can provide well-researched answers to your religious questions.</p>
                <div class="action-buttons">
                    <a href="ask-for-fatwa.php" class="btn btn-primary">
                        <i class="fas fa-question-circle me-2"></i>Ask a Fatwa
                    </a>
                    <?php if ($user['role'] === 'mufti'): ?>
                        <a href="mufti.php" class="btn btn-outline">
                            <i class="fas fa-gavel me-2"></i>Mufti Dashboard
                        </a>
                    <?php endif; ?>
                    <?php if ($user['role'] === 'admin'): ?>
                        <a href="admin.php" class="btn btn-outline">
                            <i class="fas fa-cog me-2"></i>Admin Panel
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <section class="featured-fatwas">
                <h2 class="section-title animate__animated animate__fadeIn">Recent Fatwas</h2>
                
                <div class="row">
                    <?php foreach ($fatwas as $fatwa): ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="fatwa-card animate__animated animate__fadeInUp">
                                <div class="card-header">
                                    <div class="fatwa-category"><?php echo htmlspecialchars($fatwa['category'] ?? 'General'); ?></div>
                                    <h3 class="fatwa-title"><?php echo htmlspecialchars($fatwa['title']); ?></h3>
                                    <div class="fatwa-meta">
                                        <span><i class="fas fa-user"></i>By <a href="overview.php?id=<?php echo $fatwa['questioner_id']; ?>" class="profile-link"><?php echo htmlspecialchars($fatwa['questioner_name'] ?? 'Anonymous'); ?></a></span>
                                        <span><i class="fas fa-calendar-alt"></i><?php echo date('M j, Y', strtotime($fatwa['created_at'] ?? 'now')); ?></span>
                                    </div>
                                    <span class="fatwa-madhab"><i class="fas fa-book"></i> <?php echo htmlspecialchars($fatwa['madhab'] ?? 'Not specified'); ?></span>
                                </div>
                                <div class="card-body">
                                    <p class="fatwa-question"><?php echo htmlspecialchars($fatwa['question'] ?? 'No question provided'); ?></p>
                                    <?php if (!empty($fatwa['answer'])): ?>
                                        <p class="fatwa-answer"><?php echo htmlspecialchars($fatwa['answer']); ?></p>
                                        <p class="fatwa-decision">
                                            <i class="fas fa-scale-balanced me-2"></i>
                                            <strong>Decision:</strong> <?php echo htmlspecialchars($fatwa['final_decision'] ?? 'Not specified'); ?>
                                        </p>
                                        <div class="fatwa-meta mt-3">
                                            <span><i class="fas fa-user-tie"></i>Answered by <a href="overview.php?id=<?php echo $fatwa['mufti_id']; ?>" class="profile-link"><?php echo htmlspecialchars($fatwa['mufti_name'] ?? 'Unknown Mufti'); ?></a></span>
                                            <span><i class="fas fa-calendar-alt"></i><?php echo date('M j, Y', strtotime($fatwa['answered_at'] ?? 'now')); ?></span>
                                        </div>
                                        <a href="#" class="toggle-answer"><span>Read Full Answer</span><i class="fas fa-chevron-down"></i></a>
                                    <?php else: ?>
                                        <p class="fatwa-answer">This question is yet to be answered by our scholars...</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="view-more">
                    <a href="fatwas.php" class="btn view-more-btn">
                        <i class="fas fa-book-open me-2"></i>View All Fatwas
                    </a>
                </div>
            </section>
            
            <section class="features-section">
                <h2 class="section-title animate__animated animate__fadeIn">Why Choose Darul Ifta?</h2>
                <div class="row">
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="feature-card animate__animated animate__fadeInUp" data-delay="100">
                            <div class="feature-icon">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <h3 class="feature-title">Qualified Scholars</h3>
                            <p class="feature-desc">Our muftis are thoroughly vetted and have extensive knowledge in Islamic jurisprudence, ensuring authentic and reliable answers.</p>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="feature-card animate__animated animate__fadeInUp" data-delay="200">
                            <div class="feature-icon">
                                <i class="fas fa-balance-scale"></i>
                            </div>
                            <h3 class="feature-title">Multiple Madhabs</h3>
                            <p class="feature-desc">Get answers according to different schools of thought (Hanafi, Shafi'i, Maliki, Hanbali) to understand various perspectives.</p>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="feature-card animate__animated animate__fadeInUp" data-delay="300">
                            <div class="feature-icon">
                                <i class="fas fa-lock"></i>
                            </div>
                            <h3 class="feature-title">Confidentiality</h3>
                            <p class="feature-desc">Your questions remain private and are handled with the utmost discretion and respect for your privacy.</p>
                        </div>
                    </div>
                </div>
            </section>
            
    
        <?php else: ?>
            <div class="hero-section">
                <div class="floating-shapes">
                    <div class="shape shape-1"></div>
                    <div class="shape shape-2"></div>
                    <div class="shape shape-3"></div>
                </div>
                <h1 class="hero-title">Welcome to Darul Ifta</h1>
                <p class="welcome-message">Your trusted platform for authentic Islamic rulings and guidance from qualified scholars. Connect with knowledgeable muftis and get answers to your religious questions based on Quran, Sunnah, and classical Islamic scholarship.</p>
                <div class="action-buttons">
                    <a href="login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt me-2"></i>Login
                    </a>
                    <a href="sign-up.php" class="btn btn-outline">
                        <i class="fas fa-user-plus me-2"></i>Create Account
                    </a>
                </div>
            </div>
            
            <section class="featured-fatwas">
                <h2 class="section-title animate__animated animate__fadeIn">Featured Fatwas</h2>
                
                <div class="row">
                    <?php foreach ($fatwas as $fatwa): ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="fatwa-card animate__animated animate__fadeInUp">
                                <div class="card-header">
                                    <div class="fatwa-category"><?php echo htmlspecialchars($fatwa['category'] ?? 'General'); ?></div>
                                    <h3 class="fatwa-title"><?php echo htmlspecialchars($fatwa['title']); ?></h3>
                                    <div class="fatwa-meta">
                                        <span><i class="fas fa-user"></i>By <a href="overview.php?id=<?php echo $fatwa['questioner_id']; ?>" class="profile-link"><?php echo htmlspecialchars($fatwa['questioner_name'] ?? 'Anonymous'); ?></a></span>
                                        <span><i class="fas fa-calendar-alt"></i><?php echo date('M j, Y', strtotime($fatwa['created_at'] ?? 'now')); ?></span>
                                    </div>
                                    <span class="fatwa-madhab"><i class="fas fa-book"></i> <?php echo htmlspecialchars($fatwa['madhab'] ?? 'Not specified'); ?></span>
                                </div>
                                <div class="card-body">
                                    <p class="fatwa-question"><?php echo htmlspecialchars($fatwa['question'] ?? 'No question provided'); ?></p>
                                    <?php if (!empty($fatwa['answer'])): ?>
                                        <p class="fatwa-answer"><?php echo htmlspecialchars($fatwa['answer']); ?></p>
                                        <p class="fatwa-decision">
                                            <i class="fas fa-scale-balanced me-2"></i>
                                            <strong>Decision:</strong> <?php echo htmlspecialchars($fatwa['final_decision'] ?? 'Not specified'); ?>
                                        </p>
                                        <div class="fatwa-meta mt-3">
                                            <span><i class="fas fa-user-tie"></i>Answered by <a href="overview.php?id=<?php echo $fatwa['mufti_id']; ?>" class="profile-link"><?php echo htmlspecialchars($fatwa['mufti_name'] ?? 'Unknown Mufti'); ?></a></span>
                                            <span><i class="fas fa-calendar-alt"></i><?php echo date('M j, Y', strtotime($fatwa['answered_at'] ?? 'now')); ?></span>
                                        </div>
                                        <a href="#" class="toggle-answer"><span>Read Full Answer</span><i class="fas fa-chevron-down"></i></a>
                                    <?php else: ?>
                                        <p class="fatwa-answer">This question is yet to be answered by our scholars...</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="view-more">
                    <a href="fatwas.php" class="btn view-more-btn">
                        <i class="fas fa-book-open me-2"></i>Browse All Fatwas
                    </a>
                </div>
            </section>
            
            <section class="features-section">
                <h2 class="section-title animate__animated animate__fadeIn">Why Choose Darul Ifta?</h2>
                <div class="row">
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="feature-card animate__animated animate__fadeInUp" data-delay="100">
                            <div class="feature-icon">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <h3 class="feature-title">Qualified Scholars</h3>
                            <p class="feature-desc">Our muftis are thoroughly vetted and have extensive knowledge in Islamic jurisprudence, ensuring authentic and reliable answers.</p>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="feature-card animate__animated animate__fadeInUp" data-delay="200">
                            <div class="feature-icon">
                                <i class="fas fa-balance-scale"></i>
                            </div>
                            <h3 class="feature-title">Multiple Madhabs</h3>
                            <p class="feature-desc">Get answers according to different schools of thought (Hanafi, Shafi'i, Maliki, Hanbali) to understand various perspectives.</p>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="feature-card animate__animated animate__fadeInUp" data-delay="300">
                            <div class="feature-icon">
                                <i class="fas fa-lock"></i>
                            </div>        
            <section class="features-section">
                <h2 class="section-title animate__animated animate__fadeIn">Ready to Get Started?</h2>
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="feature-card animate__animated animate__fadeInUp" data-delay="100">
                            <div class="feature-icon">
                                <i class="fas fa-question-circle"></i>
                            </div>
                            <h3 class="feature-title">Ask a Question</h3>
                            <p class="feature-desc">Submit your religious questions to our panel of scholars and receive well-researched answers based on authentic Islamic sources.</p>
                            <a href="sign-up.php" class="btn btn-outline mt-3">Sign Up Now</a>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <div class="feature-card animate__animated animate__fadeInUp" data-delay="200">
                            <div class="feature-icon">
                                <i class="fas fa-book"></i>
                            </div>
                            <h3 class="feature-title">Browse Fatwas</h3>
                            <p class="feature-desc">Explore our extensive database of answered questions on various topics including worship, transactions, family matters, and more.</p>
                            <a href="fatwas.php" class="btn btn-primary mt-3">View Fatwas</a>
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>
        <footer class="footer">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="footer-brand">
                    <span class="footer-logo">Darul Ifta</span>
                    <p class="text-muted mt-2">Providing authentic Islamic rulings based on Quran, Sunnah, and classical scholarship since 2010.</p>
                    <div class="social-links">
                        <a href="#" class="social-link" data-bs-toggle="tooltip" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-link" data-bs-toggle="tooltip" title="Twitter"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-link" data-bs-toggle="tooltip" title="YouTube"><i class="fab fa-youtube"></i></a>
                        <a href="#" class="social-link" data-bs-toggle="tooltip" title="Instagram"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-2 col-md-4 mb-4">
                <div class="footer-links">
                    <h5>Quick Links</h5>
                    <ul>
                        <li><a href="home.php">Home</a></li>
                        <li><a href="fatwas.php">Fatwas</a></li>
                        <li><a href="ask-for-fatwa.php">Ask a Question</a></li>
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="contact.php">Contact</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-4 mb-4">
                <div class="footer-links">
                    <h5>Resources</h5>
                    <ul>
                        <li><a href="madhab-guide.php">Madhab Guide</a></li>
                        <li><a href="glossary.php">Islamic Glossary</a></li>
                        <li><a href="articles.php">Articles</a></li>
                        <li><a href="muftis.php">Our Muftis</a></li>
                        <li><a href="faq.php">FAQ</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-4 mb-4">
                <div class="footer-links">
                    <h5>Legal</h5>
                    <ul>
                        <li><a href="terms.php">Terms of Service</a></li>
                        <li><a href="privacy.php">Privacy Policy</a></li>
                        <li><a href="disclaimer.php">Disclaimer</a></li>
                        <li><a href="cookies.php">Cookie Policy</a></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="copyright">
            <p>&copy; <?php echo date('Y'); ?> Darul Ifta. All rights reserved.</p>
            <p class="text-muted small">The fatwas on this site are based on the scholars' understanding of Islamic jurisprudence. For personal situations, please consult a local scholar.</p>
        </div>
    </div>
</footer>
       
    
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle answer visibility
            document.querySelectorAll('.toggle-answer').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const answer = this.previousElementSibling.previousElementSibling;
                    const decision = this.previousElementSibling;
                    const isExpanded = this.classList.contains('expanded');
                    
                    this.classList.toggle('expanded');
                    answer.classList.toggle('expanded');
                    this.querySelector('span').textContent = isExpanded ? 'Read Full Answer' : 'Hide Answer';
                    
                    // Scroll to show more of the answer if expanding
                    if (!isExpanded) {
                        this.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                });
            });
            
            // Animate elements when they come into view
            const animateOnScroll = () => {
                const elements = document.querySelectorAll('.animate__animated');
                
                elements.forEach(element => {
                    const elementPosition = element.getBoundingClientRect().top;
                    const windowHeight = window.innerHeight;
                    
                    if (elementPosition < windowHeight - 100) {
                        const animation = element.getAttribute('class').split('animate__')[1];
                        element.classList.add('animate__' + animation);
                        
                        // Add delay if specified
                        const delay = element.getAttribute('data-delay');
                        if (delay) {
                            element.style.animationDelay = delay + 'ms';
                        }
                    }
                });
            };
            
            // Initial check
            animateOnScroll();
            
            // Check on scroll
            window.addEventListener('scroll', animateOnScroll);
            
            // Theme switching
            const themeToggle = document.querySelectorAll('input[name="theme"]');
            const saveThemeBtn = document.getElementById('saveTheme');
            
            if (saveThemeBtn) {
                saveThemeBtn.addEventListener('click', function() {
                    const selectedTheme = document.querySelector('input[name="theme"]:checked').value;
                    setTheme(selectedTheme);
                    
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('settingsModal'));
                    modal.hide();
                });
            }
            
            function setTheme(theme) {
                if (theme === 'light') {
                    document.body.classList.add('light-mode');
                } else {
                    document.body.classList.remove('light-mode');
                }
                localStorage.setItem('theme', theme);
            }
            
            // Load saved theme
            const savedTheme = localStorage.getItem('theme') || 'dark';
            setTheme(savedTheme);
            
            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Add floating animation to elements
            const floatingElements = document.querySelectorAll('.floating');
            floatingElements.forEach(el => {
                el.style.animationDelay = Math.random() * 2 + 's';
            });
        });
    </script>
</body>
</html>
<?php
ob_end_flush();
?>