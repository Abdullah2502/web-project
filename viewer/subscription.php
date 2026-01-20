<?php
session_start();
require_once '../config/db_connect.php'; // Corrected path to your config

// --- 1. AUTH & MEMBERSHIP LOGIC ---
$isLoggedIn = isset($_SESSION['user_id']);
$user_id = $isLoggedIn ? $_SESSION['user_id'] : null;
$username = $isLoggedIn ? $_SESSION['username'] : 'Guest';
$membership = 'free'; // Default status

// Fetch current membership status from DB if logged in
if ($isLoggedIn) {
    // Changed table to 'viewers' and column to 'subscription_plan'
    $stmt = $conn->prepare("SELECT subscription_plan FROM viewers WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($user_row = $res->fetch_assoc()) {
            $membership = $user_row['subscription_plan'];
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription - MSP Platform</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            if (savedTheme === 'light') document.documentElement.setAttribute('data-theme', 'light');
        })();
    </script>

    <style>
        /* --- THEME VARIABLES --- */
        :root {
            --bg-body: #020b1f;
            --bg-nav: linear-gradient(to bottom, rgba(2, 11, 31, 0.95), transparent);
            --bg-card: #0b1326;
            --bg-card-featured: #0d172e;
            --card-border: #1f2940;
            --text-main: white;
            --text-sub: #aaa;
            --nav-link: #ddd;
            --btn-idle: #1f2940;
            --dropdown-bg: #0b1326;
        }

        [data-theme="light"] {
            --bg-body: #f0f2f5;
            --bg-nav: rgba(255, 255, 255, 0.95);
            --bg-card: #ffffff;
            --bg-card-featured: #f9f9f9;
            --card-border: #ddd;
            --text-main: #1c1e21;
            --text-sub: #555;
            --nav-link: #333;
            --btn-idle: #e4e6eb;
            --dropdown-bg: #ffffff;
        }

        /* --- RESET & GLOBAL --- */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', sans-serif;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            overflow-x: hidden;
            transition: background 0.3s, color 0.3s;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        /* --- NAVBAR --- */
        .navbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 5%;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            background: var(--bg-nav);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            transition: background 0.3s;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 25px;
        }

        .nav-links a {
            color: var(--nav-link);
            font-weight: 500;
            font-size: 15px;
            transition: 0.3s;
        }

        .nav-links a.active {
            color: #e50914;
        }

        .nav-icons {
            display: flex;
            gap: 20px;
            font-size: 18px;
            align-items: center;
            color: #ccc;
        }

        .logo-img {
            height: 35px;
            cursor: pointer;
        }

        .search-container {
            display: flex;
            align-items: center;
            position: relative;
        }

        .search-input {
            width: 0;
            opacity: 0;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid #444;
            color: var(--text-main);
            padding: 5px 0;
            border-radius: 4px;
            outline: none;
            transition: all 0.4s ease;
            font-size: 14px;
        }

        .search-input.active {
            width: 180px;
            opacity: 1;
            padding: 5px 10px;
            margin-right: 10px;
        }

        .fa-magnifying-glass {
            cursor: pointer;
            transition: 0.3s;
        }

        #user-display-name {
            font-size: 14px;
            color: #e50914;
            font-weight: 600;
            cursor: pointer;
        }

        .user-container {
            position: relative;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .logout-dropdown {
            position: absolute;
            top: 40px;
            right: 0;
            background: var(--dropdown-bg);
            border: 1px solid var(--card-border);
            border-radius: 4px;
            display: none;
            z-index: 1001;
            overflow: hidden;
            min-width: 140px;
        }

        .logout-dropdown button,
        .logout-dropdown a {
            display: block;
            background: none;
            border: none;
            color: var(--text-main);
            padding: 10px 20px;
            cursor: pointer;
            width: 100%;
            text-align: left;
            font-size: 14px;
            transition: 0.3s;
            text-decoration: none;
        }

        .logout-dropdown button:hover,
        .logout-dropdown a:hover {
            background: #e50914;
            color: white;
        }

        #theme-toggle {
            cursor: pointer;
            transition: 0.3s;
        }

        #theme-toggle:hover {
            color: #e50914;
        }

        /* --- SUBSCRIPTION CONTENT --- */
        .sub-container {
            padding: 150px 15% 50px;
            text-align: center;
            min-height: 80vh;
        }

        .sub-header h1 {
            font-size: 40px;
            margin-bottom: 10px;
        }

        .sub-header p {
            color: var(--text-sub);
            margin-bottom: 50px;
        }

        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            max-width: 1000px;
            margin: 0 auto;
        }

        .price-card {
            background: var(--bg-card);
            border: 1px solid var(--card-border);
            padding: 40px;
            border-radius: 12px;
            transition: 0.4s;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .price-card:hover {
            transform: translateY(-10px);
            border-color: #e50914;
            box-shadow: 0 10px 30px rgba(229, 9, 20, 0.2);
        }

        .price-card.featured {
            border: 2px solid #e50914;
            background: var(--bg-card-featured);
        }

        .featured-tag {
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            background: #e50914;
            color: white;
            padding: 5px 20px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .plan-name {
            font-size: 20px;
            color: var(--text-sub);
            margin-bottom: 15px;
        }

        .plan-price {
            font-size: 45px;
            font-weight: bold;
            margin-bottom: 25px;
        }

        .plan-price span {
            font-size: 16px;
            color: #666;
        }

        .features-list {
            list-style: none;
            text-align: left;
            margin-bottom: 35px;
            flex-grow: 1;
        }

        .features-list li {
            margin-bottom: 15px;
            font-size: 15px;
            color: var(--text-sub);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .features-list li i.fa-check {
            color: #2ecc71;
        }

        .features-list li i.fa-xmark {
            color: #e50914;
        }

        .btn-sub {
            background: var(--btn-idle);
            color: var(--text-main);
            padding: 15px;
            border-radius: 6px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: 0.3s;
        }

        .price-card.featured .btn-sub {
            background: #e50914;
            color: white;
        }

        .btn-sub:hover {
            background: #b20710;
            color: white;
            transform: scale(1.02);
        }

        /* --- FOOTER --- */
        .footer {
            background-color: var(--bg-body);
            padding: 50px 5% 20px;
            border-top: 1px solid var(--card-border);
            margin-top: 80px;
        }

        .footer-container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .footer-socials {
            margin-bottom: 25px;
        }

        .footer-socials a {
            font-size: 24px;
            margin-right: 25px;
            color: var(--text-main);
            transition: 0.3s;
        }

        .footer-links ul {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            list-style: none;
            gap: 15px;
        }

        .footer-links a {
            color: #808080;
            font-size: 13px;
            transition: 0.3s;
        }

        .copyright {
            color: #808080;
            font-size: 11px;
            margin-top: 30px;
            text-align: center;
        }

        /* SweetAlert Red Theme Customization */
        .swal2-popup {
            background: var(--bg-card) !important;
            color: var(--text-main) !important;
            border: 1px solid var(--card-border) !important;
        }

        .swal2-title {
            color: var(--text-main) !important;
        }

        .swal2-html-container {
            color: var(--text-sub) !important;
        }

        .swal2-confirm {
            background-color: #e50914 !important;
            border-radius: 6px !important;
        }

        .swal2-cancel {
            background-color: #444 !important;
            border-radius: 6px !important;
        }

        @media(max-width: 768px) {
            .sub-container {
                padding: 120px 5% 50px;
            }

            .nav-links {
                display: none;
            }

            .footer-links ul {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>

<body>

    <nav class="navbar">
        <a href="../index.php"><img src="../assets/logo.png" class="logo-img" alt="MSP"></a>
        <ul class="nav-links">
            <li><a href="../index.php">Home</a></li>
            <li><a href="movie.php">Movies</a></li>
            <li><a href="series.php">Series</a></li>
            <li><a href="forum.php">Forum</a></li>
            <li><a href="subscription.php" class="active">Subscribe</a></li>
        </ul>
        <div class="nav-icons">
            <div class="search-container">
                <input type="text" class="search-input" placeholder="Search..." id="searchInput">
                <i class="fa-solid fa-magnifying-glass" id="searchIcon"></i>
            </div>
            <i class="fa-regular fa-bell"></i>

            <div class="user-container" id="userArea">
                <span id="user-display-name" style="<?php echo ($membership === 'premium') ? 'color: #FFD700;' : ''; ?>">
                    <?php echo htmlspecialchars($username ?: "Login"); ?>
                    <?php if ($membership === 'premium'): ?>
                        <i class="fa-solid fa-crown" style="font-size:10px; margin-left:5px;"></i>
                    <?php endif; ?>
                </span>
                <i class="fa-solid fa-circle-user"></i>

                <?php if ($isLoggedIn): ?>
                    <div class="logout-dropdown" id="logoutDropdown">
                        <a href="userDashboard.php">Dashboard</a>
                        <a href="../actions/logout.php">Log Out</a>
                    </div>
                <?php else: ?>
                <?php endif; ?>
            </div>
            <i class="fa-solid fa-moon" id="theme-toggle"></i>
        </div>
    </nav>

    <main class="sub-container">
        <div class="sub-header">
            <h1>Upgrade Your Experience</h1>
            <p>Access premium content and watch in the highest quality available.</p>
        </div>

        <div class="pricing-grid">
            <div class="price-card">
                <div class="plan-name">Basic (Free)</div>
                <div class="plan-price">0 BDT<span>/month</span></div>
                <ul class="features-list">
                    <li><i class="fa-solid fa-check"></i> Standard Library Access</li>
                    <li><i class="fa-solid fa-check"></i> 480p Streaming</li>
                    <li><i class="fa-solid fa-check"></i> 1 Active Screen</li>
                    <li><i class="fa-solid fa-xmark"></i> Ad-Free Content</li>
                </ul>
                <?php if ($membership === 'premium'): ?>
                    <button class="btn-sub" onclick="handleDowngrade()">Switch to Basic</button>
                <?php else: ?>
                    <button class="btn-sub" style="background: #2ecc71; cursor: default;">Your Current Plan</button>
                <?php endif; ?>
            </div>

            <div class="price-card featured">
                <div class="featured-tag">RECOMMENDED</div>
                <div class="plan-name">Premium</div>
                <div class="plan-price">500 BDT<span>/month</span></div>
                <ul class="features-list">
                    <li><i class="fa-solid fa-check"></i> Full Premium Library</li>
                    <li><i class="fa-solid fa-check"></i> 4K + HDR Streaming</li>
                    <li><i class="fa-solid fa-check"></i> 4 Active Screens</li>
                    <li><i class="fa-solid fa-check"></i> Ad-Free Experience</li>
                    <li><i class="fa-solid fa-check"></i> Download to Watch Offline</li>
                </ul>
                <?php if ($membership === 'premium'): ?>
                    <button class="btn-sub" style="background: #2ecc71; cursor: default;">Plan Active</button>
                <?php else: ?>
                    <button class="btn-sub" onclick="handleSubscription('premium')">Upgrade Now</button>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer class="footer">
        <div class="footer-container">
            <div class="footer-socials">
                <a href="#"><i class="fa-brands fa-facebook-f"></i></a>
                <a href="#"><i class="fa-brands fa-instagram"></i></a>
                <a href="#"><i class="fa-brands fa-twitter"></i></a>
            </div>
            <div class="footer-links">
                <ul>
                    <li><a href="#">Help Center</a></li>
                    <li><a href="#">Privacy</a></li>
                    <li><a href="#">Contact Us</a></li>
                    <li><a href="#">Jobs</a></li>
                </ul>
            </div>
            <p class="copyright">© 1997-2026 MSP - Movie Streaming Platform, Inc.</p>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const themeToggle = document.getElementById('theme-toggle');
            const body = document.body;

            themeToggle.addEventListener('click', () => {
                if (body.getAttribute('data-theme') === 'light') {
                    body.removeAttribute('data-theme');
                    themeToggle.classList.replace('fa-sun', 'fa-moon');
                    localStorage.setItem('theme', 'dark');
                } else {
                    body.setAttribute('data-theme', 'light');
                    themeToggle.classList.replace('fa-moon', 'fa-sun');
                    localStorage.setItem('theme', 'light');
                }
            });

            if (localStorage.getItem('theme') === 'light') {
                body.setAttribute('data-theme', 'light');
                themeToggle.classList.replace('fa-moon', 'fa-sun');
            }

            const userArea = document.getElementById('userArea');
            const logoutDropdown = document.getElementById('logoutDropdown');

            userArea.addEventListener('click', function(e) {
                const isLoggedIn = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
                if (isLoggedIn) {
                    e.stopPropagation();
                    if (logoutDropdown) {
                        logoutDropdown.style.display = (logoutDropdown.style.display === 'block') ? 'none' : 'block';
                    }
                } else {
                    // Redirect to login if clicked and not logged in
                    window.location.href = '../auth/auth.php';
                }
            });

            const searchIcon = document.getElementById('searchIcon');
            const searchInput = document.getElementById('searchInput');

            searchIcon.addEventListener('click', (e) => {
                e.stopPropagation();
                searchInput.classList.toggle('active');
                if (searchInput.classList.contains('active')) searchInput.focus();
            });

            document.addEventListener('click', function() {
                if (logoutDropdown) logoutDropdown.style.display = 'none';
                if (searchInput) searchInput.classList.remove('active');
            });
        });

        function handleSubscription(plan) {
            const isLoggedIn = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;

            if (!isLoggedIn) {
                Swal.fire({
                    title: 'Start Your Journey',
                    text: 'Please login or register to subscribe to the Premium plan.',
                    icon: 'warning',
                    confirmButtonText: 'Go to Login',
                    confirmButtonColor: '#e50914'
                }).then(() => {
                    window.location.href = "../auth/auth.php";
                });
            } else {
                // Redirect to dummy transaction or payment page
                window.location.href = "dummyTransactions.php";
            }
        }

        function handleDowngrade() {
            Swal.fire({
                title: 'Are you sure?',
                text: "You will lose 4K + HDR streaming and ad-free experience.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, Downgrade',
                cancelButtonText: 'Keep Premium',
                confirmButtonColor: '#e50914',
                cancelButtonColor: '#444'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Ensure this file exists or point to appropriate action
                    window.location.href = "../actions/process_downgrade.php";
                }
            });
        }
    </script>
</body>

</html>