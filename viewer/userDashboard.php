<?php
session_start();
require_once '../config/db_connect.php';

// 1. Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/auth.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = isset($_SESSION['username']) ? $_SESSION['username'] : "User";
$membership = 'free'; // Default
$email = ''; // Default

// 2. Fetch User Membership & Email
$sql = "SELECT v.subscription_plan, u.email 
        FROM viewers v 
        JOIN users u ON v.user_id = u.user_id 
        WHERE v.user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    $membership = $row['subscription_plan'];
    $email = $row['email'];
}
$stmt->close();

// 3. Calculate Total Minutes Watched
$total_minutes = 0;
$stmt_mins = $conn->prepare("SELECT SUM(watched_minutes) as total FROM watch_history WHERE user_id = ?");
$stmt_mins->bind_param("i", $user_id);
$stmt_mins->execute();
$res_mins = $stmt_mins->get_result();
if ($row_mins = $res_mins->fetch_assoc()) {
    $total_minutes = (int)$row_mins['total'];
}
$stmt_mins->close();

// 4. Fetch "Continue Watching" (MOVIES, EPISODES, AND SERIES)
$resume_items = [];

$sql_resume = "
    SELECT 
        h.history_id,
        h.content_type,
        h.content_id, 
        h.progress_seconds, 
        h.last_watched_at,
        
        -- Movie Details
        m.title as movie_title,
        m.movie_id,
        
        -- Episode Details (linked to a series)
        e.episode_id,
        e.season_number,
        e.episode_number,
        e.title as episode_title,
        s_ep.series_id as ep_series_id,
        s_ep.title as ep_series_title,

        -- Series Details (direct watch history)
        s_direct.series_id as direct_series_id,
        s_direct.title as direct_series_title

    FROM watch_history h
    LEFT JOIN movies m ON h.content_type = 'movie' AND h.content_id = m.movie_id
    LEFT JOIN episodes e ON h.content_type = 'episode' AND h.content_id = e.episode_id
    LEFT JOIN series s_ep ON e.series_id = s_ep.series_id
    LEFT JOIN series s_direct ON h.content_type = 'series' AND h.content_id = s_direct.series_id
    WHERE h.user_id = ?
    ORDER BY h.last_watched_at DESC
    LIMIT 6";

$stmt_resume = $conn->prepare($sql_resume);
$stmt_resume->bind_param("i", $user_id);
$stmt_resume->execute();
$res_resume = $stmt_resume->get_result();

while ($row = $res_resume->fetch_assoc()) {
    // Determine what kind of content we found and if it's valid
    if ($row['content_type'] == 'movie' && $row['movie_title']) {
        $resume_items[] = $row;
    } elseif ($row['content_type'] == 'episode' && $row['ep_series_title']) {
        $resume_items[] = $row;
    } elseif ($row['content_type'] == 'series' && $row['direct_series_title']) {
        $resume_items[] = $row;
    }
}
$started_count = count($resume_items);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - MSP Streaming</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #020b1f;
            color: white;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 5%;
            background: rgba(2, 11, 31, 0.95);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 5%;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 50px;
        }

        .stat-card {
            background: #1f2940;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .stat-card i {
            font-size: 40px;
            color: #e50914;
            margin-bottom: 15px;
        }

        .stat-card h2 {
            font-size: 32px;
            margin-bottom: 5px;
        }

        .stat-card p {
            color: #888;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 1px;
        }

        .status-tag {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-free {
            background: #2ecc71;
            color: white;
        }

        .status-premium {
            background: #ffd700;
            color: black;
        }

        .section-title {
            margin-bottom: 25px;
            font-size: 24px;
            border-left: 4px solid #e50914;
            padding-left: 15px;
        }

        .resume-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .resume-card {
            background: #1f2940;
            border-radius: 10px;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: 0.3s;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .resume-card:hover {
            border-color: #e50914;
            transform: translateY(-5px);
        }

        .resume-info b {
            display: block;
            font-size: 16px;
            margin-bottom: 5px;
            color: #fff;
        }

        .resume-info span {
            color: #aaa;
            font-size: 13px;
        }

        .resume-info .sub-text {
            font-size: 12px;
            color: #e50914;
            font-weight: 600;
            display: block;
            margin-top: 2px;
        }

        .play-btn {
            width: 45px;
            height: 45px;
            background: #e50914;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: 0.3s;
            flex-shrink: 0;
            margin-left: 15px;
        }

        .play-btn:hover {
            transform: scale(1.1);
            background: #b20710;
        }

        .no-data {
            color: #777;
            font-style: italic;
            margin-top: 10px;
        }

        .btn-back {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: 0.3s;
        }

        .btn-back:hover {
            background: rgba(255, 255, 255, 0.2);
        }
    </style>
</head>

<body>

    <nav class="navbar">
        <img src="../assets/logo.png" style="height:30px;" alt="MSP">
        <a href="../index.php" class="btn-back">Back to Home</a>
    </nav>

    <div class="container">
        <h1 style="margin-bottom: 30px;">Welcome Back, <span style="color:#e50914;"><?php echo htmlspecialchars($username); ?></span></h1>
        <p style="color:#888; margin-top:-20px; margin-bottom:30px;"><?php echo htmlspecialchars($email); ?></p>

        <div class="stats-grid">
            <div class="stat-card">
                <i class="fa-solid fa-clock"></i>
                <h2 id="totalMinutes"><?php echo $total_minutes; ?></h2>
                <p>Minutes Watched</p>
            </div>
            <div class="stat-card">
                <i class="fa-solid fa-crown"></i>
                <div style="margin-top: 10px;">
                    <span class="status-tag status-<?php echo strtolower($membership); ?>">
                        <?php echo htmlspecialchars($membership); ?>
                    </span>
                </div>
                <p style="margin-top: 15px;">Membership Plan</p>
            </div>
            <div class="stat-card">
                <i class="fa-solid fa-film"></i>
                <h2><?php echo $started_count; ?></h2>
                <p>Recently Watched</p>
            </div>
        </div>

        <h3 class="section-title">Continue Watching</h3>
        <div class="resume-list" id="resumeList">
            <?php if ($started_count > 0): ?>
                <?php foreach ($resume_items as $item):
                    // Calculate Time
                    $timeInSeconds = $item['progress_seconds'];
                    $minutes = floor($timeInSeconds / 60);
                    $seconds = floor($timeInSeconds % 60);
                    $formattedTime = $minutes . "m " . ($seconds < 10 ? '0' . $seconds : $seconds) . "s";

                    // Initial variables
                    $displayTitle = "Unknown Title";
                    $displaySub = "";
                    $playLink = "#";

                    // Logic to display correct data based on type
                    if ($item['content_type'] === 'movie') {
                        $displayTitle = htmlspecialchars($item['movie_title']);
                        $displaySub = "Movie";
                        $playLink = "watchTrailer.php?id=" . $item['movie_id'];
                    } elseif ($item['content_type'] === 'episode') {
                        $displayTitle = htmlspecialchars($item['ep_series_title']);
                        $displaySub = "S" . $item['season_number'] . " E" . $item['episode_number'] . ": " . htmlspecialchars($item['episode_title']);
                        $playLink = "watchTrailer1.php?id=" . $item['ep_series_id'];
                    } elseif ($item['content_type'] === 'series') {
                        $displayTitle = htmlspecialchars($item['direct_series_title']);
                        $displaySub = "Series";
                        $playLink = "watchTrailer1.php?id=" . $item['direct_series_id'];
                    }
                ?>
                    <div class="resume-card">
                        <div class="resume-info">
                            <b><?php echo $displayTitle; ?></b>
                            <span class="sub-text"><?php echo $displaySub; ?></span>

                            <span>Stopped at: <?php echo $formattedTime; ?></span>
                            <br>
                            <span style="font-size: 11px; opacity: 0.6;">Last watched: <?php echo date('M d, Y', strtotime($item['last_watched_at'])); ?></span>
                        </div>
                        <a href="<?php echo $playLink; ?>" class="play-btn" title="Resume">
                            <i class="fa-solid fa-play"></i>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class='no-data'>You haven't started any movies or series yet. Go watch something!</p>
            <?php endif; ?>
        </div>
    </div>

</body>

</html>