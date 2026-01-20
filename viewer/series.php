<?php
session_start();
require_once '../config/db_connect.php';

// --- 1. AUTH & MEMBERSHIP ---
$isLoggedIn = isset($_SESSION['user_id']);
$user_id = $isLoggedIn ? $_SESSION['user_id'] : null;
$username = $isLoggedIn ? $_SESSION['username'] : 'Guest';
$membership = 'free';

if ($isLoggedIn) {
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

// --- 2. FETCH SERIES ---
$seriesList = [];
$sql = "SELECT series_id, title, poster_url, is_premium FROM series WHERE approval_status = 'approved' ORDER BY created_at DESC LIMIT 50";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $seriesList[] = $row;
    }
}

// --- 3. HERO DATA (Newest Series) ---
$heroSeries = !empty($seriesList) ? $seriesList[0] : null;
$heroAction = "";
if ($heroSeries) {
    // ALWAYS link to Trailer Page first
    $heroAction = "window.location.href='watchTrailer1.php?id=" . $heroSeries['series_id'] . "'";
} else {
    $heroAction = "window.location.href='series.php'";
}

// Helper to render card
function renderSeriesCard($series)
{
    $imgSrc = $series['poster_url'];
    if (!filter_var($imgSrc, FILTER_VALIDATE_URL)) {
        $imgSrc = '../' . $imgSrc;
    }

    // Link to Trailer Page
    $link = "window.location.href='watchTrailer1.php?id=" . $series['series_id'] . "'";
    $premiumBadge = ($series['is_premium'] == 1) ? '<span class="badge badge-paid">Premium</span>' : '<span class="badge badge-free">Free</span>';
    $filterClass = ($series['is_premium'] == 1) ? 'paid' : 'free';

    echo '
    <div class="card series-item ' . $filterClass . '" onclick="' . $link . '">
        ' . $premiumBadge . '
        <img src="' . htmlspecialchars($imgSrc) . '" onerror="this.src=\'../assets/logo.png\'">
        <div class="card-info"><div class="series-name">' . htmlspecialchars($series['title']) . '</div></div>
    </div>';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Series - MSP Platform</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            if (savedTheme === 'light') document.documentElement.setAttribute('data-theme', 'light');
        })();
    </script>

    <style>
        /* Reusing styles from movie.php for consistency */
        :root {
            --bg-body: #020b1f;
            --bg-card: #1f2940;
            --bg-nav: linear-gradient(to bottom, rgba(2, 11, 31, 0.95), transparent);
            --text-main: white;
            --text-sub: #ddd;
            --dropdown-bg: #0b1326;
        }

        [data-theme="light"] {
            --bg-body: #f0f2f5;
            --bg-card: #ffffff;
            --bg-nav: rgba(255, 255, 255, 0.95);
            --text-main: #1c1e21;
            --text-sub: #444;
            --dropdown-bg: #ffffff;
        }

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

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 5%;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            background: var(--bg-nav);
            transition: background 0.3s;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 25px;
        }

        .nav-links a {
            color: var(--text-sub);
            font-weight: 500;
            font-size: 15px;
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
            border: 1px solid var(--bg-card);
            border-radius: 4px;
            display: none;
            z-index: 1001;
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
            text-decoration: none;
        }

        .logout-dropdown button:hover,
        .logout-dropdown a:hover {
            background: #e50914;
            color: white;
        }

        /* HERO */
        .hero {
            position: relative;
            height: 60vh;
            width: 100%;
            overflow: hidden;
            display: flex;
            align-items: center;
            padding-left: 50px;
            color: white;
        }

        .hero-background {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center;
            z-index: 0;
        }

        .hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to right, rgba(0, 0, 0, 0.9), rgba(0, 0, 0, 0.2));
            z-index: 1;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 600px;
        }

        .hero-title {
            font-size: 3.5rem;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .hero-desc {
            font-size: 1.1rem;
            margin-bottom: 20px;
            color: #ddd;
            line-height: 1.5;
            max-height: 100px;
            overflow: hidden;
        }

        .btn-watch {
            padding: 10px 25px;
            font-size: 1rem;
            background-color: #e50914;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 4px;
        }

        .container {
            padding: 40px 5%;
        }

        .section-title {
            font-size: 24px;
            margin-bottom: 20px;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--text-main);
        }

        .filter-tabs {
            display: flex;
            gap: 15px;
        }

        .tab {
            padding: 8px 24px;
            border-radius: 20px;
            background: var(--bg-card);
            color: var(--text-main);
            cursor: pointer;
            font-size: 14px;
            transition: 0.3s;
            border: 1px solid transparent;
        }

        .tab.active,
        .tab:hover {
            background: #e50914;
            color: white;
        }

        .series-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 25px;
        }

        .card {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            background: var(--bg-card);
            transition: 0.3s;
            cursor: pointer;
            border: 1px solid var(--bg-card);
            aspect-ratio: 2/3;
        }

        .card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            background-color: #333;
        }

        .card:hover {
            transform: translateY(-10px);
            border-color: #e50914;
        }

        .badge {
            position: absolute;
            top: 10px;
            left: 10px;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            z-index: 2;
        }

        .badge-free {
            background: #2ecc71;
            color: white;
        }

        .badge-paid {
            background: #ffd700;
            color: #000;
        }

        .card-info {
            position: absolute;
            bottom: 0;
            width: 100%;
            padding: 15px 10px;
            background: linear-gradient(transparent, rgba(0, 0, 0, 0.9));
            text-align: center;
            color: white;
        }

        .series-name {
            font-size: 14px;
            font-weight: 500;
        }

        .footer {
            background-color: var(--bg-body);
            padding: 50px 5% 20px;
            border-top: 1px solid var(--bg-card);
            margin-top: 80px;
        }

        .footer-container {
            max-width: 1000px;
            margin: 0 auto;
            text-align: center;
            color: #888;
            font-size: 12px;
        }

        .btn-signin {
            padding: 8px 16px;
            background-color: #e50914;
            color: white;
            border-radius: 4px;
            font-weight: 500;
            font-size: 14px;
        }
    </style>
</head>

<body>

    <nav class="navbar">
        <div style="font-size: 30px;"><img src="../assets/logo.png" class="logo-img"></div>
        <ul class="nav-links">
            <li><a href="../index.php">Home</a></li>
            <li><a href="movie.php">Movies</a></li>
            <li><a href="series.php" class="active">Series</a></li>
            <li><a href="forum.php">Forum</a></li>
            <li><a href="subscription.php">Subscribe</a></li>
        </ul>
        <div class="nav-icons">
            <div class="search-container">
                <input type="text" class="search-input" placeholder="Search..." id="searchInput">
                <i class="fa-solid fa-magnifying-glass" id="searchIcon"></i>
            </div>
            <i class="fa-regular fa-bell"></i>
            <div class="user-container" id="userArea">
                <span id="user-display-name" style="<?php echo ($membership === 'premium') ? 'color: #FFD700;' : ''; ?>">
                    <?php if ($isLoggedIn) echo htmlspecialchars($username); ?>
                    <?php if ($membership === 'premium') echo ' <i class="fa-solid fa-crown"></i>'; ?>
                </span>
                <?php if ($isLoggedIn): ?>
                    <i class="fa-solid fa-circle-user"></i>
                    <div class="logout-dropdown" id="logoutDropdown">
                        <a href="userDashboard.php">Dashboard</a>
                        <a href="../actions/logout.php">Log Out</a>
                    </div>
                <?php else: ?>
                    <a href="../auth/auth.php" class="btn-signin">Sign In</a>
                <?php endif; ?>
            </div>
            <i class="fa-solid fa-moon" id="theme-toggle"></i>
        </div>
    </nav>

    <header class="hero">
        <div class="hero-background" id="heroBackdrop"></div>
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <h1 class="hero-title" id="heroTitle"><?php echo $heroSeries ? htmlspecialchars($heroSeries['title']) : 'Welcome to Series'; ?></h1>
            <p class="hero-desc" id="heroDesc"><?php echo $heroSeries ? 'Loading series details...' : 'Discover the best series on MSP.'; ?></p>

            <?php if ($heroSeries): ?>
                <button class="btn-watch" onclick="<?php echo $heroAction; ?>">
                    <i class="fa-solid fa-play"></i> Watch Trailer
                </button>
            <?php endif; ?>
        </div>
    </header>

    <main class="container">
        <div class="section-title">
            Explore Series
            <div class="filter-tabs">
                <div class="tab active" onclick="filterSeries('all')">All</div>
                <div class="tab" onclick="filterSeries('free')">Free</div>
                <div class="tab" onclick="filterSeries('paid')">Premium</div>
            </div>
        </div>

        <div class="series-grid" id="seriesGrid">
            <?php
            if (empty($seriesList)) {
                echo '<p style="color:#777;">No series available yet.</p>';
            } else {
                foreach ($seriesList as $series) {
                    renderSeriesCard($series);
                }
            }
            ?>
        </div>
    </main>

    <footer class="footer">
        <div class="footer-container">
            <p>© 1997-2026 MSP - Movie Streaming Platform, Inc.</p>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // HERO API FETCH
            const heroTitle = "<?php echo $heroSeries ? htmlspecialchars($heroSeries['title']) : ''; ?>";
            const apiKey = '96878691f0272aade53fca27ac2a739f';

            if (heroTitle) {
                fetch(`https://api.themoviedb.org/3/search/tv?api_key=${apiKey}&query=${encodeURIComponent(heroTitle)}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.results && data.results.length > 0) {
                            const show = data.results[0];
                            if (show.backdrop_path) {
                                document.getElementById('heroBackdrop').style.backgroundImage = `url('https://image.tmdb.org/t/p/original${show.backdrop_path}')`;
                            } else {
                                document.getElementById('heroBackdrop').style.background = 'linear-gradient(to bottom, #1f2940, #020b1f)';
                            }
                            if (show.overview) {
                                document.getElementById('heroDesc').innerText = show.overview;
                            }
                        }
                    });
            } else {
                document.getElementById('heroBackdrop').style.backgroundImage = "url('https://images.unsplash.com/photo-1574375927938-d5a98e8ffe85?q=80&w=2669')";
            }

            // UI LOGIC
            const themeToggle = document.getElementById('theme-toggle');
            const body = document.body;
            themeToggle.addEventListener('click', () => {
                const isLight = body.getAttribute('data-theme') === 'light';
                if (isLight) {
                    body.removeAttribute('data-theme');
                    localStorage.setItem('theme', 'dark');
                } else {
                    body.setAttribute('data-theme', 'light');
                    localStorage.setItem('theme', 'light');
                }
            });

            const userArea = document.getElementById('userArea');
            const logoutDropdown = document.getElementById('logoutDropdown');
            if (userArea && logoutDropdown) {
                userArea.addEventListener('click', (e) => {
                    e.stopPropagation();
                    logoutDropdown.style.display = (logoutDropdown.style.display === 'block') ? 'none' : 'block';
                });
                document.addEventListener('click', () => logoutDropdown.style.display = 'none');
            }
        });

        function filterSeries(type) {
            const items = document.querySelectorAll('.series-item');
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => {
                tab.classList.remove('active');
                if (tab.textContent.toLowerCase() === type || (type === 'paid' && tab.textContent === 'Premium')) tab.classList.add('active');
                if (type === 'all' && tab.textContent === 'All') tab.classList.add('active');
            });
            items.forEach(item => {
                item.style.display = (type === 'all' || item.classList.contains(type)) ? 'block' : 'none';
            });
        }
    </script>
</body>

</html>