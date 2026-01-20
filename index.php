<?php
session_start();
require_once './config/db_connect.php';

// --- 1. CHECK LOGIN STATUS ---
$is_logged_in = isset($_SESSION['user_id']);
$user_id = $is_logged_in ? $_SESSION['user_id'] : null;
$username = $is_logged_in ? $_SESSION['username'] : 'Guest';
$is_premium = false;

// --- 2. FETCH PREMIUM STATUS ---
if ($is_logged_in) {
    $sql = "SELECT subscription_plan FROM viewers WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if ($row['subscription_plan'] === 'premium') {
                $is_premium = true;
            }
        }
        $stmt->close();
    }
}

// --- 3. FETCH MOVIES ---
$movies = [];
$sql_movies = "SELECT movie_id, title, poster_url, is_premium, created_at FROM movies WHERE approval_status = 'approved' ORDER BY created_at DESC LIMIT 8";
$result_movies = $conn->query($sql_movies);
if ($result_movies && $result_movies->num_rows > 0) {
    while ($row = $result_movies->fetch_assoc()) {
        $row['content_type'] = 'movie';
        $movies[] = $row;
    }
}

// --- 4. FETCH SERIES ---
$series = [];
$sql_series = "SELECT series_id, title, poster_url, is_premium, created_at FROM series WHERE approval_status = 'approved' ORDER BY created_at DESC LIMIT 8";
$result_series = $conn->query($sql_series);
if ($result_series && $result_series->num_rows > 0) {
    while ($row = $result_series->fetch_assoc()) {
        $row['content_type'] = 'series';
        $series[] = $row;
    }
}

// --- 5. FETCH CONTINUE WATCHING (Logged In Only) ---
$continue_watching = [];
if ($is_logged_in) {
    $sql_history = "
        SELECT 
            h.content_type, 
            h.content_id, 
            h.progress_seconds,
            -- Get Title
            COALESCE(m.title, s_ep.title, s_direct.title) as title,
            -- Get Poster
            COALESCE(m.poster_url, s_ep.poster_url, s_direct.poster_url) as poster_url,
            -- Get IDs
            m.movie_id,
            COALESCE(s_ep.series_id, s_direct.series_id) as final_series_id,
            -- Get Duration
            COALESCE(m.duration_minutes, e.duration_minutes, 45) as duration_mins
        FROM watch_history h
        LEFT JOIN movies m ON h.content_type = 'movie' AND h.content_id = m.movie_id
        LEFT JOIN episodes e ON h.content_type = 'episode' AND h.content_id = e.episode_id
        LEFT JOIN series s_ep ON e.series_id = s_ep.series_id
        LEFT JOIN series s_direct ON h.content_type = 'series' AND h.content_id = s_direct.series_id
        WHERE h.user_id = ?
        ORDER BY h.last_watched_at DESC
        LIMIT 5";

    $stmt = $conn->prepare($sql_history);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res_hist = $stmt->get_result();

    while ($row = $res_hist->fetch_assoc()) {
        if (!empty($row['title'])) {
            $continue_watching[] = $row;
        }
    }
    $stmt->close();
}

// --- 6. PREPARE HERO SLIDESHOW ITEMS ---
$heroItems = [];

// Add top 3 movies
for ($i = 0; $i < min(3, count($movies)); $i++) {
    $item = $movies[$i];
    $item['id'] = $item['movie_id'];
    $heroItems[] = $item;
}

// Add top 2 series
for ($i = 0; $i < min(2, count($series)); $i++) {
    $item = $series[$i];
    $item['id'] = $item['series_id'];
    $heroItems[] = $item;
}

shuffle($heroItems);

if (empty($heroItems)) {
    $heroItems[] = [
        'id' => 0,
        'title' => 'Welcome to MSP',
        'content_type' => 'movie',
        'overview' => 'Discover the best movies and series on our platform.'
    ];
}

// Helper to render STANDARD card (Trending sections)
function renderCard($item, $type)
{
    $imgSrc = $item['poster_url'];
    if (!filter_var($imgSrc, FILTER_VALIDATE_URL)) {
        $imgSrc = './' . $imgSrc;
    }

    $idKey = ($type === 'movie') ? 'movie_id' : 'series_id';
    $id = $item[$idKey];

    // Standard cards go to Trailer/Detail pages
    $destinationPage = ($type === 'series') ? 'viewer/watchTrailer1.php' : 'viewer/watchTrailer.php';

    $link = "window.location.href='" . $destinationPage . "?id=" . $id . "'";
    $premiumBadge = ($item['is_premium'] == 1) ? '<span style="position:absolute; top:10px; right:10px; background:#ffd700; color:#000; padding:2px 6px; font-size:10px; border-radius:4px; font-weight:bold; z-index:2;">PREMIUM</span>' : '';

    echo '
    <div class="card" onclick="' . $link . '">
        ' . $premiumBadge . '
        <img src="' . htmlspecialchars($imgSrc) . '" alt="' . htmlspecialchars($item['title']) . '" onerror="this.src=\'assets/logo.png\'">
        <div class="card-caption">' . htmlspecialchars($item['title']) . '</div>
        <button class="play-btn"><i class="fa-solid fa-play"></i></button>
    </div>';
}

// Helper to render CONTINUE WATCHING Card (Direct to Player)
function renderContinueCard($item)
{
    $imgSrc = $item['poster_url'];
    if (!filter_var($imgSrc, FILTER_VALIDATE_URL)) {
        $imgSrc = './' . $imgSrc;
    }

    // Logic to determine link and ID
    $isMovie = ($item['content_type'] === 'movie');

    // FIX: Link directly to player pages
    $linkPage = $isMovie ? 'viewer/watchMovie.php' : 'viewer/watchSeries.php';
    $id = $isMovie ? $item['movie_id'] : $item['final_series_id'];

    // Calculate Progress %
    $duration = isset($item['duration_mins']) ? intval($item['duration_mins']) : 0;
    if ($duration <= 0) $duration = 45; // Default fallback

    $totalSeconds = $duration * 60;
    $percent = 0;

    if ($totalSeconds > 0) {
        $percent = ($item['progress_seconds'] / $totalSeconds) * 100;
        if ($percent > 100) $percent = 100;
    }

    echo '
    <div class="card" onclick="window.location.href=\'' . $linkPage . '?id=' . $id . '\'">
        <img src="' . htmlspecialchars($imgSrc) . '" alt="' . htmlspecialchars($item['title']) . '" onerror="this.src=\'assets/logo.png\'">
        
        <div class="progress-bar-container">
            <div class="progress-fill" style="width: ' . $percent . '%;"></div>
        </div>
        
        <div class="card-caption">' . htmlspecialchars($item['title']) . '</div>
        <button class="play-btn"><i class="fa-solid fa-play"></i></button>
    </div>';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MSP - Movie Streaming Platform</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* --- THEME VARIABLES --- */
        :root {
            --bg-body: #020b1f;
            --bg-card: #1f2940;
            --bg-faq: #0b1326;
            --bg-faq-ans: #161d2f;
            --text-main: white;
            --text-sub: #ccc;
            --nav-bg: linear-gradient(to bottom, rgba(2, 11, 31, 0.95), transparent);
        }

        [data-theme="light"] {
            --bg-body: #f0f2f5;
            --bg-card: #ffffff;
            --bg-faq: #ffffff;
            --bg-faq-ans: #e4e6eb;
            --text-main: #1c1e21;
            --text-sub: #65676b;
            --nav-bg: linear-gradient(to bottom, rgba(255, 255, 255, 0.95), transparent);
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

        a {
            text-decoration: none;
            color: inherit;
        }

        /* NAVBAR */
        .navbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 5%;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            background: var(--nav-bg);
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

        /* SEARCH & USER */
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

        .fa-magnifying-glass:hover {
            color: #e50914;
            cursor: pointer;
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
            background: var(--bg-faq);
            border: 1px solid var(--bg-card);
            border-radius: 4px;
            display: none;
        }

        .hero-title {
            font-size: 2.5rem;
        }

        .card {
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

        /* HERO SLIDESHOW */
        .hero {
            position: relative;
            height: 70vh;
            width: 100%;
            overflow: hidden;
            display: flex;
            align-items: center;
        }

        .hero-slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            transition: opacity 1s ease-in-out;
            z-index: 0;
            display: flex;
            align-items: center;
            padding-left: 50px;
        }

        .hero-slide.active {
            opacity: 1;
            z-index: 1;
        }

        .hero-background {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center;
            z-index: -1;
        }

        .hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to right, rgba(0, 0, 0, 0.9) 0%, rgba(0, 0, 0, 0.5) 50%, rgba(0, 0, 0, 0.1) 100%);
            z-index: 0;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 600px;
            padding-top: 50px;
            transform: translateY(20px);
            transition: transform 1s ease-out;
        }

        .hero-slide.active .hero-content {
            transform: translateY(0);
        }

        .hero-title {
            font-size: 4rem;
            margin-bottom: 10px;
            font-weight: 800;
            line-height: 1.1;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }

        .hero-desc {
            font-size: 1.1rem;
            margin-bottom: 25px;
            color: #ddd;
            line-height: 1.5;
            max-height: 100px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.8);
        }

        .hero-indicators {
            position: absolute;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 10px;
            z-index: 10;
        }

        .indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.5);
            cursor: pointer;
            transition: 0.3s;
        }

        .indicator.active {
            background-color: #e50914;
            transform: scale(1.2);
        }

        .btn-watch {
            padding: 12px 30px;
            font-size: 1.1rem;
            background-color: #e50914;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 4px;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(229, 9, 20, 0.4);
            transition: 0.3s;
        }

        .btn-watch:hover {
            transform: scale(1.05);
            background-color: #f40612;
        }

        /* CARDS & GENERAL */
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

        .see-more-btn {
            color: #e50914;
            font-size: 14px;
            cursor: pointer;
            padding: 5px 10px;
            border: 1px solid transparent;
            transition: 0.3s;
        }

        .see-more-btn:hover {
            border: 1px solid #e50914;
            border-radius: 4px;
        }

        .movie-row {
            display: flex;
            gap: 15px;
            overflow-x: auto;
            padding-bottom: 20px;
            scrollbar-width: none;
        }

        .movie-row::-webkit-scrollbar {
            display: none;
        }

        .hidden-row {
            display: none;
            margin-top: 20px;
            animation: fadeIn 0.5s ease forwards;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card {
            max-width: 140px;
            aspect-ratio: 2/3;
            border-radius: 12px;
            overflow: hidden;
            background: var(--bg-card);
            transition: 0.3s;
            cursor: pointer;
            border: 1px solid var(--bg-card);
            position: relative;
            min-width: 140px;
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

        .card .play-btn {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0, 0, 0, 0.6);
            color: white;
            border: none;
            font-size: 24px;
            border-radius: 50%;
            padding: 15px;
            display: none;
            cursor: pointer;
        }

        .card:hover .play-btn {
            display: block;
        }

        .card-caption {
            padding: 10px;
            font-size: 14px;
            text-align: center;
            position: absolute;
            bottom: 0;
            width: 100%;
            background: linear-gradient(transparent, rgba(0, 0, 0, 0.8));
            color: white;
        }

        /* Progress Bar Styles for Continue Watching */
        .progress-bar-container {
            position: absolute;
            bottom: 35px;
            /* Above the caption */
            left: 0;
            width: 100%;
            height: 4px;
            background: rgba(255, 255, 255, 0.3);
        }

        .progress-fill {
            height: 100%;
            background: #ffee06;
            transition: width 0.3s;
        }

        /* CHARACTER SECTION STYLES */
        .char-row {
            display: flex;
            justify-content: center;
            gap: 25px;
            flex-wrap: wrap;
            margin-bottom: 50px;
            min-height: 120px;
            /* Prevent collapse before loading */
        }

        .char-item {
            text-align: center;
            width: 100px;
            transition: 0.3s;
        }

        .char-img {
            width: 85px;
            height: 85px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e50914;
            transition: 0.3s;
            cursor: pointer;
            background-color: #333;
        }

        .char-img:hover {
            transform: scale(1.1);
            box-shadow: 0 0 15px rgba(229, 9, 20, 0.5);
        }

        .char-name {
            font-size: 12px;
            margin-top: 8px;
            color: var(--text-sub);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* FOOTER & FAQ */
        .faq-title {
            text-align: center;
            font-size: 28px;
            margin-bottom: 40px;
            color: var(--text-main);
            margin-top: 40px;
        }

        .faq-item {
            background: var(--bg-faq);
            border: 1px solid var(--bg-card);
            border-radius: 10px;
            margin-bottom: 12px;
            overflow: hidden;
            cursor: pointer;
            transition: 0.3s;
            color: var(--text-main);
        }

        .faq-question {
            padding: 18px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
            background: var(--bg-faq-ans);
        }

        .faq-answer p {
            padding: 0 25px 20px 25px;
            color: var(--text-sub);
            line-height: 1.6;
            font-size: 14px;
        }

        .faq-item.active .faq-answer {
            max-height: 200px;
        }

        .faq-item.active .faq-question i {
            transform: rotate(180deg);
            color: #e50914;
        }

        .footer {
            background-color: var(--bg-body);
            padding: 50px 5% 20px;
            border-top: 1px solid var(--bg-card);
            margin-top: 50px;
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

        .footer-socials a:hover {
            color: #e50914;
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

        .footer-links a:hover {
            color: var(--text-main);
            text-decoration: underline;
        }

        .copyright {
            color: #808080;
            font-size: 11px;
            margin-top: 20px;
        }

        .btn-signin {
            padding: 8px 16px;
            background-color: #e50914;
            color: white;
            border-radius: 4px;
            font-weight: 500;
            font-size: 14px;
        }

        .btn-signin:hover {
            background-color: #b20710;
        }

        @media(max-width:768px) {
            .nav-links {
                display: none;
            }

            .hero-title {
                font-size: 2.5rem;
            }

            .card {
                min-width: 120px;
            }

            .footer-links ul {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>

<body>

    <nav class="navbar">
        <div style="font-size: 30px;"><img src="assets/logo.png" class="logo-img"></div>
        <ul class="nav-links">
            <li><a href="index.php" class="active">Home</a></li>
            <li><a href="viewer/movie.php">Movies</a></li>
            <li><a href="viewer/series.php">Series</a></li>
            <li><a href="viewer/forum.php">Forum</a></li>
            <li><a href="viewer/subscription.php">Subscribe</a></li>
        </ul>
        <div class="nav-icons">
            <div class="search-container">
                <input type="text" class="search-input" placeholder="Search..." id="searchInput">
                <i class="fa-solid fa-magnifying-glass" id="searchIcon"></i>
            </div>

            <i class="fa-regular fa-bell"></i>
            <?php if ($is_logged_in): ?>
                <div class="user-container" id="userArea">
                    <span id="user-display-name" style="<?php echo $is_premium ? 'color: #FFD700;' : ''; ?>">
                        <?php echo htmlspecialchars($username); ?>
                        <?php if ($is_premium): echo '<i class="fa-solid fa-crown" style="font-size: 10px; margin-left: 4px;"></i>';
                        endif; ?>
                    </span>
                    <i class="fa-solid fa-circle-user"></i>
                    <div class="logout-dropdown" id="logoutDropdown">
                        <button onclick="window.location.href='viewer/userDashboard.php'">Dashboard</button>
                        <a href="actions/logout.php">Log Out</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="auth/auth.php" class="btn-signin">Sign In</a>
            <?php endif; ?>
            <i class="fa-solid fa-moon" id="theme-toggle"></i>
        </div>
    </nav>

    <header class="hero" id="heroSlider">
        <?php foreach ($heroItems as $index => $item):
            $targetFile = ($item['content_type'] === 'series') ? 'viewer/watchTrailer1.php' : 'viewer/watchTrailer.php';
        ?>
            <div class="hero-slide <?php echo $index === 0 ? 'active' : ''; ?>"
                data-title="<?php echo htmlspecialchars($item['title']); ?>"
                data-type="<?php echo $item['content_type']; ?>">

                <div class="hero-background" id="bg-<?php echo $index; ?>"></div>
                <div class="hero-overlay"></div>
                <div class="hero-content">
                    <div style="color: #e50914; font-weight:bold; margin-bottom:5px; text-transform:uppercase; letter-spacing:1px; font-size: 0.9rem;">
                        Trending <?php echo ucfirst($item['content_type']); ?>
                    </div>
                    <h1 class="hero-title"><?php echo htmlspecialchars($item['title']); ?></h1>
                    <p class="hero-desc" id="desc-<?php echo $index; ?>">Loading details...</p>
                    <button class="btn-watch" onclick="window.location.href='<?php echo $targetFile; ?>?id=<?php echo $item['id']; ?>&type=<?php echo $item['content_type']; ?>'">
                        <i class="fa-solid fa-play"></i> Watch Trailer
                    </button>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="hero-indicators">
            <?php foreach ($heroItems as $index => $item): ?>
                <div class="indicator <?php echo $index === 0 ? 'active' : ''; ?>" onclick="goToSlide(<?php echo $index; ?>)"></div>
            <?php endforeach; ?>
        </div>
    </header>

    <div class="container">
        <div class="section-title">
            Trending Movies
            <?php if (count($movies) > 4): ?>
                <span class="see-more-btn" id="seeMoreMoviesBtn">See More</span>
            <?php endif; ?>
        </div>
        <div class="movie-row">
            <?php
            if (empty($movies)) {
                echo '<p style="color:var(--text-sub); padding:10px;">No movies available yet.</p>';
            } else {
                for ($i = 0; $i < min(4, count($movies)); $i++) {
                    renderCard($movies[$i], 'movie');
                }
            }
            ?>
        </div>
        <div class="movie-row hidden-row" id="moreMoviesRow">
            <?php
            if (count($movies) > 4) {
                for ($i = 4; $i < min(8, count($movies)); $i++) {
                    renderCard($movies[$i], 'movie');
                }
            }
            ?>
        </div>
    </div>

    <div class="container">
        <div class="section-title">
            Trending Series
            <?php if (count($series) > 4): ?>
                <span class="see-more-btn" id="seeMoreSeriesBtn">See More</span>
            <?php endif; ?>
        </div>
        <div class="movie-row">
            <?php
            if (empty($series)) {
                echo '<p style="color:var(--text-sub); padding:10px;">No series available yet.</p>';
            } else {
                for ($i = 0; $i < min(4, count($series)); $i++) {
                    renderCard($series[$i], 'series');
                }
            }
            ?>
        </div>
        <div class="movie-row hidden-row" id="moreSeriesRow">
            <?php
            if (count($series) > 4) {
                for ($i = 4; $i < min(8, count($series)); $i++) {
                    renderCard($series[$i], 'series');
                }
            }
            ?>
        </div>
    </div>

    <?php if ($is_logged_in && !empty($continue_watching)): ?>
        <div class="container">
            <div class="section-title">Continue Watching</div>
            <div class="movie-row">
                <?php
                foreach ($continue_watching as $item) {
                    renderContinueCard($item);
                }
                ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="container">
        <div class="section-title">Popular Characters</div>
        <div class="char-row" id="popularCharactersRow">
            <p style="color: var(--text-sub);">Loading trending stars...</p>
        </div>
    </div>

    <h2 class="faq-title">Frequently Asked Questions</h2>
    <div style="max-width:800px; margin:0 auto; padding: 0 20px;">
        <div class="faq-item">
            <div class="faq-question"><span>What is MSP?</span><i class="fa-solid fa-chevron-down"></i></div>
            <div class="faq-answer">
                <p>MSP is a streaming service that offers a wide variety of content.</p>
            </div>
        </div>
        <div class="faq-item">
            <div class="faq-question"><span>How much does MSP cost?</span><i class="fa-solid fa-chevron-down"></i></div>
            <div class="faq-answer">
                <p>Plans range from $9.99 to $19.99 a month.</p>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="footer-container">
            <div class="footer-socials">
                <a href="#"><i class="fa-brands fa-facebook-f"></i></a>
                <a href="#"><i class="fa-brands fa-instagram"></i></a>
            </div>
            <p class="copyright">© 1997-2026 MSP - Movie Streaming Platform, Inc.</p>
        </div>
    </footer>

    <script>
        const apiKey = '96878691f0272aade53fca27ac2a739f';

        // --- 1. HERO SLIDESHOW LOGIC ---
        const slides = document.querySelectorAll('.hero-slide');
        const indicators = document.querySelectorAll('.indicator');
        let currentSlide = 0;
        let slideInterval;

        function showSlide(index) {
            slides.forEach((slide, i) => {
                slide.classList.remove('active');
                indicators[i].classList.remove('active');
            });
            slides[index].classList.add('active');
            indicators[index].classList.add('active');
            currentSlide = index;
        }

        function nextSlide() {
            let next = (currentSlide + 1) % slides.length;
            showSlide(next);
        }

        window.goToSlide = function(index) {
            showSlide(index);
            resetTimer();
        }

        function resetTimer() {
            clearInterval(slideInterval);
            slideInterval = setInterval(nextSlide, 6000);
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Start Slider
            if (slides.length > 1) {
                resetTimer();
            }

            // Fetch Data for EACH hero slide
            slides.forEach((slide, index) => {
                const title = slide.dataset.title;
                const type = slide.dataset.type;
                const endpoint = (type === 'series') ? 'tv' : 'movie';

                if (title) {
                    const url = `https://api.themoviedb.org/3/search/${endpoint}?api_key=${apiKey}&query=${encodeURIComponent(title)}`;
                    fetch(url)
                        .then(res => res.json())
                        .then(data => {
                            if (data.results && data.results.length > 0) {
                                const item = data.results[0];
                                if (item.backdrop_path) {
                                    document.getElementById(`bg-${index}`).style.backgroundImage = `url('https://image.tmdb.org/t/p/original${item.backdrop_path}')`;
                                } else {
                                    document.getElementById(`bg-${index}`).style.background = 'linear-gradient(to bottom, #1f2940, #020b1f)';
                                }
                                if (item.overview) {
                                    document.getElementById(`desc-${index}`).innerText = item.overview;
                                }
                            }
                        })
                        .catch(err => console.log('Hero Fetch Error:', err));
                }
            });

            // --- 2. FETCH POPULAR CHARACTERS (FIXED) ---
            const charContainer = document.getElementById('popularCharactersRow');
            if (charContainer) {
                const charUrl = `https://api.themoviedb.org/3/person/popular?api_key=${apiKey}&language=en-US&page=1`;

                fetch(charUrl)
                    .then(res => res.json())
                    .then(data => {
                        charContainer.innerHTML = ''; // Clear loading text
                        // We only want the top 8
                        const people = data.results.slice(0, 8);

                        people.forEach(person => {
                            // Create elements
                            const div = document.createElement('div');
                            div.className = 'char-item';

                            const img = document.createElement('img');
                            img.className = 'char-img';
                            // Use a placeholder if no profile image
                            if (person.profile_path) {
                                img.src = `https://image.tmdb.org/t/p/w200${person.profile_path}`;
                            } else {
                                img.src = 'assets/logo.png'; // Fallback
                            }

                            const nameDiv = document.createElement('div');
                            nameDiv.className = 'char-name';
                            nameDiv.innerText = person.name;

                            div.appendChild(img);
                            div.appendChild(nameDiv);
                            charContainer.appendChild(div);
                        });
                    })
                    .catch(err => {
                        console.log('Character Fetch Error:', err);
                        charContainer.innerHTML = '<p style="color:red">Failed to load characters.</p>';
                    });
            }

            // --- 3. SEE MORE BUTTONS ---
            const toggleSection = (btnId, rowId) => {
                const btn = document.getElementById(btnId);
                const row = document.getElementById(rowId);
                if (btn && row) {
                    btn.addEventListener('click', function() {
                        const isHidden = window.getComputedStyle(row).display === "none";
                        if (isHidden) {
                            row.style.display = "flex";
                            this.textContent = "See Less";
                        } else {
                            row.style.display = "none";
                            this.textContent = "See More";
                        }
                    });
                }
            };

            toggleSection('seeMoreMoviesBtn', 'moreMoviesRow');
            toggleSection('seeMoreSeriesBtn', 'moreSeriesRow');

            // --- 4. THEME & MENU ---
            const themeToggle = document.getElementById('theme-toggle');
            const body = document.body;

            if (localStorage.getItem('theme') === 'light') {
                body.setAttribute('data-theme', 'light');
                themeToggle.classList.replace('fa-moon', 'fa-sun');
            }

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

            const userArea = document.getElementById('userArea');
            const logoutDropdown = document.getElementById('logoutDropdown');
            if (userArea && logoutDropdown) {
                userArea.addEventListener('click', function(e) {
                    e.stopPropagation();
                    logoutDropdown.style.display = (logoutDropdown.style.display === 'block') ? 'none' : 'block';
                });
                document.addEventListener('click', function() {
                    logoutDropdown.style.display = 'none';
                });
            }

            const searchIcon = document.getElementById('searchIcon');
            const searchInput = document.getElementById('searchInput');
            searchIcon.addEventListener('click', (e) => {
                e.stopPropagation();
                searchInput.classList.toggle('active');
                if (searchInput.classList.contains('active')) searchInput.focus();
            });
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.search-container')) searchInput.classList.remove('active');
            });

            const faqs = document.querySelectorAll(".faq-item");
            faqs.forEach(faq => {
                faq.addEventListener("click", () => {
                    faq.classList.toggle("active");
                    faqs.forEach(other => {
                        if (other !== faq) other.classList.remove("active");
                    });
                });
            });
        });
    </script>
</body>

</html>