<?php
ob_start();
require_once 'config.php';

$isLoggedIn = !empty($_SESSION['user_id']);
$username = 'Guest';
$role = 'user';
$initials = 'G';

if ($isLoggedIn) {
    try {
        $stmt = $db->prepare("SELECT username, role FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $username = htmlspecialchars($user['username']);
            $role = htmlspecialchars($user['role']);
            $initials = strtoupper(substr($username, 0, 1));
        } else {
            error_log("header.php: No user found for ID {$_SESSION['user_id']}, redirecting to login");
            $isLoggedIn = false;
            $username = 'Guest';
            $role = 'user';
        }
    } catch (PDOException $e) {
        error_log("header.php: Database error: " . $e->getMessage());
        $isLoggedIn = false;
    }
}
?>

<style>
:root {
    --primary-color: #38a169; /* More vibrant green */
    --primary-dark: #2c7a4d; /* Darker shade for contrast */
    --primary-light: #9ae6b4; /* Lighter accent */
    --dark-bg: #1a202c; /* Slightly lighter dark background */
    --darker-bg: #171923; /* Darker for contrast */
    --light-text: #f7fafc; /* Softer white */
    --light-gray: #e2e8f0; /* Better light gray */
    --medium-gray: #718096; /* Better medium gray */
    --dark-gray: #4a5568; /* Better dark gray */
    --divider-color: #2d3748; /* Better divider color */
    --shadow: 0 4px 12px rgba(0, 0, 0, 0.15); /* Softer shadow */
    --transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); /* Smoother transition */
    
    /* Light mode variables */
    --light-bg: #f8f9fa;
    --light-darker-bg: #ffffff;
    --dark-text: #2d3748;
    --light-divider: #edf2f7;
    --light-medium-gray: #718096;
}

body.light-mode {
    --dark-bg: var(--light-bg);
    --darker-bg: var(--light-darker-bg);
    --light-text: var(--dark-text);
    --light-gray: var(--dark-gray);
    --medium-gray: var(--light-medium-gray);
    --divider-color: var(--light-divider);
    --shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.navbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 2rem;
    background-color: var(--dark-bg);
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    box-shadow: var(--shadow);
    border-bottom: 1px solid var(--divider-color);
    backdrop-filter: blur(8px); /* Adds subtle frosted glass effect */
}

.brand {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 1.25rem;
    color: var(--light-text);
    text-decoration: none;
    font-weight: 600;
    transition: var(--transition);
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; /* Better font stack */
}

.brand:hover {
    color: var(--primary-light);
    transform: translateX(2px);
}

.brand-logo {
    width: 32px;
    height: 32px;
    object-fit: contain;
    transition: var(--transition);
}

.brand:hover .brand-logo {
    transform: rotate(-5deg) scale(1.05);
}

.nav-controls {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.hamburger {
    width: 28px;
    height: 20px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    cursor: pointer;
    position: relative;
    z-index: 1100;
}

.hamburger span {
    height: 3px;
    width: 100%;
    background-color: var(--light-text);
    transition: var(--transition);
    transform-origin: left center;
    border-radius: 3px;
}

.hamburger.active span:nth-child(1) {
    transform: translateY(7px) rotate(45deg);
    width: 120%;
    background-color: var(--primary-color);
}

.hamburger.active span:nth-child(2) {
    opacity: 0;
    transform: scaleX(0);
}

.hamburger.active span:nth-child(3) {
    transform: translateY(-7px) rotate(-45deg);
    width: 120%;
    background-color: var(--primary-color);
}

.profile-pic {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background-color: var(--primary-dark);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-weight: 600;
    transition: var(--transition);
    border: 2px solid rgba(255,255,255,0.1);
}

.profile-pic:hover {
    transform: scale(1.1);
    box-shadow: 0 0 0 3px rgba(56, 161, 105, 0.3);
    background-color: var(--primary-color);
}

.side-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 0;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(5px);
    transition: var(--transition);
    z-index: 1050;
    opacity: 0;
    visibility: hidden;
}

.side-overlay.show {
    width: 100%;
    opacity: 1;
    visibility: visible;
}

.side-menu {
    position: absolute;
    top: 0;
    left: -300px;
    width: 280px;
    height: 100%;
    background: var(--darker-bg);
    padding: 5rem 1rem 1rem;
    transition: var(--transition);
    overflow-y: auto;
    box-shadow: 4px 0 15px rgba(0,0,0,0.1);
}

.side-overlay.show .side-menu {
    left: 0;
}

.side-menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    color: var(--light-gray);
    padding: 0.85rem 1.25rem;
    margin-bottom: 0.25rem;
    border-radius: 6px;
    text-decoration: none;
    transition: var(--transition);
    font-size: 0.95rem;
}

.side-menu a:hover {
    background-color: rgba(56, 161, 105, 0.1);
    color: var(--primary-light);
    padding-left: 1.5rem;
    transform: translateX(5px);
}

.side-menu a svg {
    width: 20px;
    height: 20px;
    stroke-width: 1.5;
}

.side-menu .divider {
    height: 1px;
    background-color: var(--divider-color);
    margin: 1.25rem 0;
}

.side-menu .menu-header {
    color: var(--medium-gray);
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin: 1.5rem 0 0.75rem;
    padding: 0 1.25rem;
    font-weight: 600;
}

.close-sidebar {
    position: absolute;
    top: 1.25rem;
    right: 1.25rem;
    background: none;
    border: none;
    color: var(--light-gray);
    font-size: 1.75rem;
    cursor: pointer;
    z-index: 1100;
    transition: var(--transition);
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

.close-sidebar:hover {
    color: var(--primary-color);
    background-color: rgba(255,255,255,0.05);
    transform: rotate(90deg);
}

.dropdown-menu {
    position: absolute;
    top: 65px;
    right: 1rem;
    width: 250px;
    background-color: var(--darker-bg);
    border-radius: 10px;
    box-shadow: var(--shadow);
    opacity: 0;
    transition: all 0.2s ease-out;
    transform: translateY(-15px);
    pointer-events: none;
    border: 1px solid var(--divider-color);
    z-index: 1100;
    padding: 0.5rem 0;
}

.dropdown-menu.show {
    opacity: 1;
    transform: translateY(0);
    pointer-events: auto;
}

.dropdown-header {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--divider-color);
}

.dropdown-header strong {
    display: block;
    color: var(--light-text);
    margin-bottom: 0.25rem;
    font-size: 1rem;
}

.dropdown-header small {
    color: var(--medium-gray);
    font-size: 0.8rem;
}

.dropdown-section {
    padding: 0.5rem 0;
}

.dropdown-section a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0.75rem 1.25rem;
    color: var(--light-gray);
    text-decoration: none;
    transition: var(--transition);
    font-size: 0.9rem;
}

.dropdown-section a:hover {
    background-color: rgba(56, 161, 105, 0.1);
    color: var(--primary-light);
    padding-left: 1.5rem;
}

.dropdown-section a svg {
    width: 16px;
    height: 16px;
    stroke-width: 2;
}

.dropdown-section-title {
    color: var(--medium-gray);
    font-size: 0.75rem;
    padding: 0.5rem 1.25rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 600;
}

/* Modal styles */
.modal-content {
    background-color: var(--dark-bg);
    color: var(--light-text);
    border: 1px solid var(--divider-color);
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
}

.modal-header {
    border-bottom: 1px solid var(--divider-color);
    padding: 1.25rem;
}

.modal-title {
    color: var(--primary-color);
    font-weight: 600;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    border-top: 1px solid var(--divider-color);
    padding: 1.25rem;
}

.form-check-input {
    background-color: var(--darker-bg);
    border: 1px solid var(--light-gray);
    width: 1.1em;
    height: 1.1em;
    margin-top: 0.15em;
}

.form-check-input:checked {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

.form-check-label {
    color: var(--light-text);
    margin-left: 0.5rem;
    font-size: 0.95rem;
}

.btn-primary {
    background-color: var(--primary-color);
    border: none;
    border-radius: 8px;
    padding: 0.6rem 1.25rem;
    transition: var(--transition);
    font-weight: 500;
}

.btn-primary:hover {
    background-color: var(--primary-dark);
    transform: translateY(-1px);
}

.btn-secondary {
    background-color: var(--dark-gray);
    border: none;
    border-radius: 8px;
    padding: 0.6rem 1.25rem;
    color: var(--light-text);
    transition: var(--transition);
    font-weight: 500;
}

.btn-secondary:hover {
    background-color: var(--medium-gray);
    transform: translateY(-1px);
}

@media (max-width: 768px) {
    .navbar {
        padding: 0.75rem 1.25rem;
    }
    
    .brand {
        font-size: 1.15rem;
        gap: 10px;
    }
    
    .dropdown-menu {
        width: 240px;
        right: 0.75rem;
    }
    
    .modal-header, .modal-body, .modal-footer {
        padding: 1rem;
    }
}
</style>

<nav class="navbar">
    <div class="hamburger" id="hamburger">
        <span></span>
        <span></span>
        <span></span>
    </div>
    <a href="home.php" class="brand">
        <img src="logo.png" alt="Darul Ifta Logo" class="brand-logo" onerror="this.style.display='none'">
        <span>Darul Ifta</span>
    </a>
    <div class="nav-controls">
        <div class="profile-pic" id="profilePic"><?php echo htmlspecialchars($initials); ?></div>
    </div>
</nav>

<div class="side-overlay" id="sideOverlay">
    <div class="side-menu">
        <button class="close-sidebar" id="closeSidebar">×</button>
        <div class="menu-header">Main Menu</div>
        <a href="home.php">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>
            Home
        </a>
        <a href="ask-for-fatwa.php">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="10" y1="9" x2="14" y2="9"></line>
                <line x1="10" y1="13" x2="14" y2="13"></line>
                <line x1="10" y1="17" x2="18" y2="17"></line>
            </svg>
            Ask a Fatwa
        </a>
        <div class="divider"></div>
        <?php if ($isLoggedIn): ?>
            <div class="menu-header">Your Account</div>
            <a href="overview.php">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                Profile
            </a>
            <?php if ($role === 'mufti'): ?>
                <a href="mufti.php">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                        <path d="M2 17l10 5 10-5"></path>
                        <path d="M2 12l10 5 10-5"></path>
                    </svg>
                    Mufti Dashboard
                </a>
            <?php endif; ?>
            <?php if ($role === 'admin'): ?>
                <a href="admin.php">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    Admin Dashboard
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="dropdown-menu" id="profileDropdown">
    <div class="dropdown-header">
        <strong><?php echo htmlspecialchars($username); ?></strong>
        <small>Role: <?php echo htmlspecialchars($role); ?></small>
    </div>
    <div class="dropdown-section">
        <div class="dropdown-section-title">Account</div>
        <?php if ($isLoggedIn): ?>
            <a href="overview.php">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                Profile
            </a>
            <a href="#" data-bs-toggle="modal" data-bs-target="#settingsModal">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                </svg>
                Settings
            </a>
            <a href="logout.php">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
                Log Out
            </a>
        <?php else: ?>
            <a href="login.php">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                    <polyline points="10 17 15 12 10 7"></polyline>
                    <line x1="15" y1="12" x2="3" y2="12"></line>
                </svg>
                Login
            </a>
            <a href="sign-up.php">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="8.5" cy="7" r="4"></circle>
                    <line x1="20" y1="8" x2="20" y2="14"></line>
                    <line x1="23" y1="11" x2="17" y2="11"></line>
                </svg>
                Sign Up
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Settings Modal -->
<div class="modal fade" id="settingsModal" tabindex="-1" aria-labelledby="settingsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="settingsModalLabel">Settings</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6 class="mb-3">Theme Preference</h6>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="themeSwitch">
                    <label class="form-check-label" for="themeSwitch">Dark Mode</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="saveTheme">Save</button>
            </div>
        </div>
    </div>
</div>

<script>
function toggleDropdown() {
    document.getElementById("profileDropdown").classList.toggle("show");
}

function toggleMenu() {
    document.getElementById("hamburger").classList.toggle("active");
    document.getElementById("sideOverlay").classList.toggle("show");
}

function closeMenu() {
    document.getElementById("hamburger").classList.remove("active");
    document.getElementById("sideOverlay").classList.remove("show");
}

document.getElementById("profilePic").addEventListener("click", toggleDropdown);
document.getElementById("hamburger").addEventListener("click", toggleMenu);
document.getElementById("closeSidebar").addEventListener("click", closeMenu);

document.addEventListener("click", function(e) {
    if (!e.target.closest("#profileDropdown") && !e.target.closest("#profilePic")) {
        document.getElementById("profileDropdown").classList.remove("show");
    }
    if (!e.target.closest("#sideOverlay") && !e.target.closest("#hamburger")) {
        closeMenu();
    }
});

// Theme switching logic
function setTheme(theme) {
    if (theme === 'light') {
        document.body.classList.add('light-mode');
        document.getElementById('themeSwitch').checked = false;
    } else {
        document.body.classList.remove('light-mode');
        document.getElementById('themeSwitch').checked = true;
    }
    localStorage.setItem('theme', theme);
}

// Load saved theme on page load
document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = localStorage.getItem('theme') || 'dark';
    setTheme(savedTheme);
    
    // Theme switch toggle
    const themeSwitch = document.getElementById('themeSwitch');
    if (themeSwitch) {
        themeSwitch.addEventListener('change', function() {
            const newTheme = this.checked ? 'dark' : 'light';
            setTheme(newTheme);
        });
    }
});

// Save theme on button click
document.getElementById('saveTheme').addEventListener('click', function() {
    const selectedTheme = document.getElementById('themeSwitch').checked ? 'dark' : 'light';
    localStorage.setItem('theme', selectedTheme);
    const modal = bootstrap.Modal.getInstance(document.getElementById('settingsModal'));
    modal.hide();
});

// Close dropdown when clicking outside
window.addEventListener('click', function(e) {
    if (!e.target.matches('.profile-pic') && !e.target.closest('.dropdown-menu')) {
        const dropdown = document.getElementById('profileDropdown');
        if (dropdown.classList.contains('show')) {
            dropdown.classList.remove('show');
        }
    }
});
</script>

<?php
ob_end_flush();
?>