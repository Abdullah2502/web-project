<?php
session_start();
require_once '../config/db_connect.php';

// --- 1. AUTH CHECK ---
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/auth.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// --- 2. VALIDATE INPUT ---
if (!isset($_GET['id'])) {
    die("Movie ID not specified.");
}

$movie_id = intval($_GET['id']);

// --- 3. FETCH MOVIE DETAILS ---
$sql = "SELECT * FROM movies WHERE movie_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $movie_id);
$stmt->execute();
$result = $stmt->get_result();
$movie = $result->fetch_assoc();

if (!$movie) {
    die("Movie not found.");
}

// Fix video path if needed (handle relative paths)
$video_url = $movie['video_url'];
if (!filter_var($video_url, FILTER_VALIDATE_URL)) {
    $video_url = '../' . $video_url;
}

// --- 4. FETCH RESUME TIME (The Logic) ---
$start_time = 0; // Default to 0 (beginning)

$history_sql = "SELECT progress_seconds FROM watch_history 
                WHERE user_id = ? AND content_type = 'movie' AND content_id = ?";
$h_stmt = $conn->prepare($history_sql);
$h_stmt->bind_param("ii", $user_id, $movie_id);
$h_stmt->execute();
$h_res = $h_stmt->get_result();

if ($h_row = $h_res->fetch_assoc()) {
    $start_time = intval($h_row['progress_seconds']);
}
$h_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Watching: <?php echo htmlspecialchars($movie['title']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #000;
            color: #fff;
            font-family: sans-serif;
            overflow: hidden;
        }

        /* Back Button Overlay */
        .back-btn {
            position: absolute;
            top: 20px;
            left: 20px;
            z-index: 10;
            background: rgba(0, 0, 0, 0.5);
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            transition: 0.3s;
        }

        .back-btn:hover {
            background: #e50914;
        }

        /* Video Player */
        .video-container {
            width: 100vw;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        video {
            width: 100%;
            height: 100%;
            object-fit: contain;
            /* Ensures entire video is visible */
        }

        /* Resume Toast Notification */
        .resume-toast {
            position: absolute;
            top: 80px;
            left: 20px;
            z-index: 10;
            background: rgba(46, 204, 113, 0.9);
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            font-size: 14px;
            opacity: 0;
            transition: opacity 0.5s;
            pointer-events: none;
        }
    </style>
</head>

<body>

    <a href="../index.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i> Back to Home</a>

    <div id="resumeToast" class="resume-toast">
        Resuming playback from <span id="timeDisplay">00:00</span>
    </div>

    <div class="video-container">
        <video id="mainPlayer" controls autoplay controlsList="nodownload" oncontextmenu="return false;">
            <source src="<?php echo htmlspecialchars($video_url); ?>" type="video/mp4">
            Your browser does not support HTML5 video.
        </video>
    </div>

    <script>
        const video = document.getElementById('mainPlayer');
        const startTime = <?php echo $start_time; ?>; // PHP Variable injected into JS
        const movieId = <?php echo $movie_id; ?>;
        const toast = document.getElementById('resumeToast');
        const timeDisplay = document.getElementById('timeDisplay');

        // --- A. RESUME LOGIC ---
        video.addEventListener('loadedmetadata', function() {
            if (startTime > 0) {
                // Set the player time
                video.currentTime = startTime;

                // Show Notification
                const minutes = Math.floor(startTime / 60);
                const seconds = Math.floor(startTime % 60);
                timeDisplay.innerText = `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;

                toast.style.opacity = '1';
                setTimeout(() => {
                    toast.style.opacity = '0';
                }, 4000);
            }
        });

        // --- B. SAVE PROGRESS LOGIC (Update DB every 10 seconds) ---
        // This ensures the "Resume" feature works next time!
        setInterval(() => {
            if (!video.paused && video.currentTime > 0) {
                saveProgress(video.currentTime);
            }
        }, 10000); // Save every 10 seconds

        // Save when user leaves the page
        window.addEventListener('beforeunload', () => {
            saveProgress(video.currentTime);
        });

        function saveProgress(time) {
            // Using fetch to call a backend API
            const formData = new FormData();
            formData.append('content_type', 'movie');
            formData.append('content_id', movieId);
            formData.append('time', time);
            // We also need duration for percentage calc
            formData.append('duration', video.duration || 0);

            navigator.sendBeacon('../actions/update_history.php', formData);
        }
    </script>

</body>

</html>