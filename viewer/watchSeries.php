<?php
session_start();
require_once '../config/db_connect.php';

// 1. CHECK LOGIN
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/auth.php");
    exit();
}
$user_id = $_SESSION['user_id'];

// 2. CHECK ID
if (!isset($_GET['id'])) exit("No series specified.");
$series_id = intval($_GET['id']);

// 3. FETCH SERIES INFO
$sql = "SELECT * FROM series WHERE series_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $series_id);
$stmt->execute();
$series = $stmt->get_result()->fetch_assoc();

if (!$series) exit("Series not found.");

// 4. FETCH EPISODES (Dynamic)
$episodes = [];
$epSql = "SELECT * FROM episodes WHERE series_id = ? ORDER BY episode_number ASC";
$epStmt = $conn->prepare($epSql);
$epStmt->bind_param("i", $series_id);
$epStmt->execute();
$epRes = $epStmt->get_result();

while ($row = $epRes->fetch_assoc()) {
    $episodes[] = [
        'file' => "../" . $row['video_url'],
        'title' => $row['title'],
        'thumb' => "../" . $series['poster_url'] // Fallback thumb
    ];
}

// Fallback if no episodes uploaded yet: Use main series file as Ep 1
if (empty($episodes)) {
    // Note: Assuming 'video_url' might exist in series table, usually it's in episodes. 
    // If not, we just show a placeholder or the trailer.
    // For now, let's assume we use the series poster as a placeholder video if real video missing.
    $episodes[] = [
        'file' => "movie.mp4", // Default dummy if DB empty
        'title' => "Full Series / Episode 1",
        'thumb' => "../" . $series['poster_url']
    ];
}

// 5. FETCH RESUME TIME
$resumeTime = 0;
$prog_sql = "SELECT progress_seconds FROM watch_history WHERE user_id = ? AND content_id = ? AND content_type = 'series'";
$prog_stmt = $conn->prepare($prog_sql);
$prog_stmt->bind_param("ii", $user_id, $series_id);
$prog_stmt->execute();
if ($row = $prog_stmt->get_result()->fetch_assoc()) {
    $resumeTime = $row['progress_seconds'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Watching: <?php echo htmlspecialchars($series['title']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', sans-serif;
        }

        body {
            background: #000;
            color: white;
            overflow: hidden;
        }

        .player-layout {
            display: flex;
            height: 100vh;
            width: 100vw;
        }

        .video-side {
            flex: 3;
            background: #000;
            position: relative;
        }

        #episodePlayer {
            width: 100%;
            height: 100%;
            outline: none;
        }

        .episode-side {
            flex: 1;
            background: #050b1a;
            border-left: 1px solid #222;
            overflow-y: auto;
            padding: 20px;
        }

        .ep-card {
            display: flex;
            gap: 15px;
            background: #111;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 12px;
            cursor: pointer;
            transition: 0.3s;
            border: 1px solid transparent;
        }

        .ep-card:hover,
        .ep-card.active {
            background: #1a1a1a;
            border-color: #e50914;
        }

        .ep-card img {
            width: 100px;
            height: 60px;
            object-fit: cover;
        }

        .back-nav {
            position: absolute;
            top: 20px;
            left: 20px;
            z-index: 100;
            background: rgba(0, 0, 0, 0.6);
            padding: 10px;
            border-radius: 50%;
            color: white;
            border: none;
            cursor: pointer;
        }
    </style>
</head>

<body>

    <button onclick="history.back()" class="back-nav"><i class="fa-solid fa-arrow-left"></i></button>

    <div class="player-layout">
        <div class="video-side">
            <video id="episodePlayer" controls autoplay controlsList="nodownload">
                <source id="videoSource" src="" type="video/mp4">
            </video>
        </div>

        <div class="episode-side">
            <h2 style="color:#e50914; margin-bottom:20px;"><?php echo htmlspecialchars($series['title']); ?></h2>
            <div id="episodeList"></div>
        </div>
    </div>

    <script>
        const seriesId = <?php echo $series_id; ?>;
        const episodes = <?php echo json_encode($episodes); ?>;
        const player = document.getElementById('episodePlayer');
        const listDiv = document.getElementById('episodeList');
        let currentFile = "";
        let watchInterval;

        // Init: Play first episode
        if (episodes.length > 0) playEpisode(episodes[0].file, 0);

        function playEpisode(file, index) {
            currentFile = file;
            player.src = file;
            player.play();
            renderList(index);
            startTracking();
        }

        function renderList(activeIndex) {
            listDiv.innerHTML = "";
            episodes.forEach((ep, i) => {
                const activeClass = i === activeIndex ? 'active' : '';
                listDiv.innerHTML += `
                    <div class="ep-card ${activeClass}" onclick="playEpisode('${ep.file}', ${i})">
                        <img src="${ep.thumb}" onerror="this.src='../assets/logo.png'">
                        <div>
                            <b>Ep ${i+1}: ${ep.title}</b>
                            <span style="font-size:12px; color:#888;">Playing</span>
                        </div>
                    </div>
                `;
            });
        }

        function startTracking() {
            if (watchInterval) clearInterval(watchInterval);
            watchInterval = setInterval(() => {
                if (!player.paused) {
                    const fd = new FormData();
                    fd.append('content_id', seriesId);
                    fd.append('content_type', 'series');
                    fd.append('progress', player.currentTime);
                    fetch('../actions/update_history.php', {
                        method: 'POST',
                        body: fd
                    });
                }
            }, 5000);
        }
    </script>
</body>

</html>