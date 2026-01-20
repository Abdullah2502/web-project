<?php
session_start();
require_once '../config/db_connect.php';

// 1. CHECK LOGIN
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/auth.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// 2. VALIDATE MOVIE ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "No movie specified.";
    exit();
}
$movie_id = intval($_GET['id']);

// 3. FETCH MOVIE DETAILS & CHECK PREMIUM
$sql = "SELECT * FROM movies WHERE movie_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $movie_id);
$stmt->execute();
$movie = $stmt->get_result()->fetch_assoc();

if (!$movie) {
    echo "Movie not found.";
    exit();
}

// 4. CHECK SUBSCRIPTION (If movie is Premium)
if ($movie['is_premium'] == 1) {
    // Check user's plan
    $sub_sql = "SELECT subscription_plan FROM viewers WHERE user_id = ?";
    $sub_stmt = $conn->prepare($sub_sql);
    $sub_stmt->bind_param("i", $user_id);
    $sub_stmt->execute();
    $sub_res = $sub_stmt->get_result();
    $user_plan = $sub_res->fetch_assoc()['subscription_plan'] ?? 'free';

    if ($user_plan !== 'premium') {
        echo "<script>alert('This is a Premium movie. Please upgrade to watch.'); window.location.href='subscription.html';</script>";
        exit();
    }
}

// 5. FETCH RESUME TIME (From watch_history)
$resumeTime = 0;
// We check for existing progress in 'watch_history'
$prog_sql = "SELECT progress_seconds FROM watch_history WHERE user_id = ? AND content_id = ? AND content_type = 'movie'";
$prog_stmt = $conn->prepare($prog_sql);
$prog_stmt->bind_param("ii", $user_id, $movie_id);
$prog_stmt->execute();
$prog_res = $prog_stmt->get_result();

if ($row = $prog_res->fetch_assoc()) {
    $resumeTime = $row['progress_seconds'];
}

// Prepare Video Path
$video_src = "../" . $movie['video_url'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Watching: <?php echo htmlspecialchars($movie['title']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family: 'Segoe UI', sans-serif;}
        body { background-color:#000; color:white; overflow:hidden; }
        .video-container { width: 100vw; height: 100vh; position: relative; background: #000; }
        #fullPlayer { width: 100%; height: 100%; outline: none; }
        .overlay-controls {
            position: absolute; top: 20px; left: 20px; z-index: 10;
            display: flex; align-items: center; gap: 20px;
            background: rgba(0,0,0,0.5); padding: 10px 20px; border-radius: 50px;
            transition: opacity 0.3s;
        }
        .back-btn { color: white; text-decoration: none; font-size: 18px; }
        .tracking-status {
            position: absolute; bottom: 20px; right: 20px;
            font-size: 12px; color: rgba(255,255,255,0.5);
            background: rgba(0,0,0,0.6); padding: 5px 15px; border-radius: 4px;
            pointer-events: none;
        }
    </style>
</head>
<body>

    <div class="video-container">
        <div class="overlay-controls" id="topControls">
            <a href="movie.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i></a>
            <span id="movieTitleDisplay"><?php echo htmlspecialchars($movie['title']); ?></span>
        </div>

        <video id="fullPlayer" controls autoplay controlsList="nodownload">
            <source src="<?php echo htmlspecialchars($video_src); ?>" type="video/mp4">
            Your browser does not support the video tag.
        </video>

        <div class="tracking-status" id="trackStatus">
            <i class="fa-solid fa-circle-notch fa-spin"></i> Ready
        </div>
    </div>

    <script>
        const movieId = <?php echo $movie_id; ?>;
        const dbResumeTime = <?php echo (float)$resumeTime; ?>;
        const player = document.getElementById('fullPlayer');
        let watchInterval;
        let hasResumed = false;

        // 1. Resume Playback Logic
        player.addEventListener('loadedmetadata', () => {
            // Only resume if stored time is significant (> 10 seconds) and not near end
            if (!hasResumed && dbResumeTime > 10 && dbResumeTime < (player.duration - 10)) {
                player.currentTime = dbResumeTime;
                hasResumed = true;
                console.log("Resumed at: " + dbResumeTime);
            }
        });

        // 2. Track Progress when playing
        player.onplaying = () => {
            if (!watchInterval) {
                watchInterval = setInterval(updateWatchProgress, 5000); // Sync every 5 sec
            }
        };

        // 3. Stop tracking when paused or ended
        player.onpause = () => stopTracking();
        player.onended = () => stopTracking();

        function stopTracking() {
            clearInterval(watchInterval);
            watchInterval = null;
            // Final sync on pause/end
            updateWatchProgress();
        }

        // 4. AJAX Sync function (Sends ID instead of Title)
        function updateWatchProgress() {
            if (!player.paused || player.ended) {
                const currentTime = player.currentTime;
                
                const formData = new FormData();
                formData.append('content_id', movieId);
                formData.append('content_type', 'movie');
                formData.append('progress', currentTime);
                formData.append('duration', player.duration);

                // Pointing to a new helper file for cleaner logic
                fetch('../actions/update_history.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(data => {
                    const status = document.getElementById('trackStatus');
                    // status.innerHTML = `<i class="fa-solid fa-check"></i> Synced`; 
                    // Optional: Only uncomment above line for debugging, otherwise keep it subtle
                })
                .catch(err => console.error('Sync Error:', err));
            }
        }

        // Hide controls on idle
        let timeout;
        const topControls = document.getElementById('topControls');
        document.onmousemove = () => {
            topControls.style.opacity = "1";
            clearTimeout(timeout);
            timeout = setTimeout(() => { topControls.style.opacity = "0"; }, 3000);
        };
    </script>
</body>
</html>