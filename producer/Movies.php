<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'producer') {
    header("Location: ../auth/auth.php");
    exit;
}

$producer_id = $_SESSION['user_id'];
$error_msg = '';
$success_msg = '';

// Handle movie upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_movie'])) {
    $title = mysqli_real_escape_string($conn, $_POST['title'] ?? '');
    $description = mysqli_real_escape_string($conn, $_POST['description'] ?? '');
    $genre = mysqli_real_escape_string($conn, $_POST['genre'] ?? '');
    $release_year = intval($_POST['release_year'] ?? 0);
    $duration = intval($_POST['duration'] ?? 0);
    $price = floatval($_POST['price'] ?? 0);
    $is_premium = isset($_POST['is_premium']) ? 1 : 0;
    
    if ($title && $duration > 0 && $price >= 0) {
        // Handle poster file upload
        $poster_url = '/uploads/thumbnails/default.jpg';
        if (isset($_FILES['poster']) && $_FILES['poster']['error'] === UPLOAD_ERR_OK) {
            $poster_name = 'poster_' . $producer_id . '_' . time() . '_' . basename($_FILES['poster']['name']);
            $poster_path = '../uploads/thumbnails/' . $poster_name;
            if (move_uploaded_file($_FILES['poster']['tmp_name'], $poster_path)) {
                $poster_url = '/uploads/thumbnails/' . $poster_name;
            }
        }
        
        // Handle video file upload
        $video_url = '/uploads/movies/default.mp4';
        if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
            $video_name = 'movie_' . $producer_id . '_' . time() . '_' . basename($_FILES['video']['name']);
            $video_path = '../uploads/movies/' . $video_name;
            if (move_uploaded_file($_FILES['video']['tmp_name'], $video_path)) {
                $video_url = '/uploads/movies/' . $video_name;
            }
        }
        
        $query = "INSERT INTO movies (title, description, genre, release_year, duration_minutes, price, is_premium, poster_url, video_url, uploaded_by, approval_status, created_at)
                  VALUES ('$title', '$description', '$genre', $release_year, $duration, $price, $is_premium, '$poster_url', '$video_url', $producer_id, 'pending', NOW())";
        
        if (mysqli_query($conn, $query)) {
            $success_msg = "Movie uploaded successfully! Awaiting admin approval.";
        } else {
            $error_msg = "Error uploading movie: " . mysqli_error($conn);
        }
    } else {
        $error_msg = "Please fill in all required fields (Title, Duration, Price).";
    }
}

// Get producer's movies
$movies_query = "SELECT * FROM movies WHERE uploaded_by = $producer_id ORDER BY created_at DESC";
$movies_result = mysqli_query($conn, $movies_query) or $movies_result = null;
$producer_movies = [];
if ($movies_result) {
    while ($row = mysqli_fetch_assoc($movies_result)) {
        $producer_movies[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movies</title>
    <link rel="stylesheet" href="../assets/Dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <nav class="navbar">
        <div class="nav-container">
            <a href="Dashboard.php" class="logo-btn hover-glow">
                <img src="../assets/logo.png" alt="Logo" class="logo-img">
            </a>

            <ul class="nav-links">
                <li><a href="Dashboard.php" class="hover-glow">Dashboard</a></li>
                <li><a href="Movies.php" class="hover-glow" style="color:#228EE5;">Movies</a></li>
                <li><a href="Series.php" class="hover-glow">Series</a></li>
                <li><a href="Forum.php" class="hover-glow">Forum</a></li>
                <li><a href="Chat.php" class="hover-glow">Chat</a></li>
            </ul>

            <div class="nav-icons">
                <div class="search-container">
                    <input type="text" id="navSearch" class="search-input-nav" placeholder="Type to search...">
                    <button class="icon-btn hover-glow search-btn-nav" onclick="toggleSearch()">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </button>
                </div>
                <div class="notification-wrapper">
                    <button class="icon-btn hover-glow" onclick="toggleNotifications()">
                        <i class="fa-solid fa-bell"></i>
                        <span
                            style="position:absolute; top:0; right:0; width:8px; height:8px; background:red; border-radius:50%;"></span>
                    </button>

                    <div id="notificationDropdown" class="notification-dropdown">
                        <div class="notification-header">
                            <span>Notifications</span>
                            <span class="mark-read">Mark all as read</span>
                        </div>
                        <div class="notification-list">

                            <div class="notif-item">
                                <div class="notif-content">
                                    <h4>New User Registration</h4>
                                    <p>User <b>@john_doe</b> has requested to join.</p>
                                    <span class="notif-time">2 mins ago</span>
                                </div>
                            </div>

                            <div class="notif-item">
                                <div class="notif-content">
                                    <h4>Movie Approved</h4>
                                    <p><b>Inception</b> was approved by admin.</p>
                                    <span class="notif-time">1 hour ago</span>
                                </div>
                            </div>

                            <div class="notif-item">
                                <div class="notif-content">
                                    <h4>System Alert</h4>
                                    <p>High server load detected.</p>
                                    <span class="notif-time">3 hours ago</span>
                                </div>
                            </div>

                            <div class="notif-item">
                                <div class="notif-content">
                                    <h4>New Comment</h4>
                                    <p>New comment on <b>Interstellar</b>.</p>
                                    <span class="notif-time">5 hours ago</span>
                                </div>
                            </div>

                            <div class="notif-item">
                                <div class="notif-content">
                                    <h4>Payment Received</h4>
                                    <p>Subscription #9928 processed.</p>
                                    <span class="notif-time">1 day ago</span>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
                <a href="UserProfile.php" class="icon-btn hover-glow"><i class="fa-solid fa-user"></i></a>
                <button class="icon-btn hover-glow"><i class="fa-solid fa-sun"></i></button>
            </div>
        </div>
    </nav>

    <div class="main-content">
        <h1 class="welcome-text">Movies</h1>

        <?php if ($error_msg): ?>
            <div style="background-color: #ffebee; color: #c62828; padding: 12px; border-radius: 4px; margin-bottom: 20px;">
                <?php echo htmlspecialchars($error_msg); ?>
            </div>
        <?php endif; ?>

        <?php if ($success_msg): ?>
            <div style="background-color: #e8f5e9; color: #2e7d32; padding: 12px; border-radius: 4px; margin-bottom: 20px;">
                <?php echo htmlspecialchars($success_msg); ?>
            </div>
        <?php endif; ?>

        <section class="movie-control-panel">
            <div class="section-tab">Advance Search</div>
            <div class="panel-content">
                <div class="panel-icon">
                    <i class="fa-solid fa-film"></i>
                </div>
                <div class="panel-form">
                    <div class="input-row">
                        <select id="search-year" class="select-field">
                            <option value="">Select Year</option>
                        </select>
                        <input type="text" class="input-field" placeholder="Country">
                        <input type="text" class="input-field" placeholder="Actor">
                        <input type="text" class="input-field" placeholder="Director">
                    </div>

                    <div class="search-wrapper">
                        <input type="text" class="input-field" placeholder="Search for movies...">
                        <button class="search-icon-btn"><i class="fa-solid fa-magnifying-glass"></i></button>
                    </div>

                    <div class="input-row" style="margin-top: 15px;">
                        <input type="text" class="input-field" placeholder="Genre (e.g. Action, Comedy, Sci-Fi)">
                    </div>
                </div>
            </div>
        </section>

        <section class="movie-control-panel">
            <div class="section-tab">Upload Movie</div>
            <div class="panel-content">
                <div class="panel-icon">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                </div>
                <form class="panel-form" method="POST" enctype="multipart/form-data">
                    <div class="input-row">
                        <input type="text" name="title" class="input-field" placeholder="Movie Title" required>
                        <select name="release_year" id="release_year" class="select-field" required>
                            <option value="">Select Year</option>
                        </select>
                        <input type="text" name="country" class="input-field" placeholder="Country">
                    </div>

                    <div class="input-row">
                        <input type="text" name="director" class="input-field" placeholder="Creator/Director">
                        <input type="text" name="actor" class="input-field" placeholder="Main Actor">
                        <input type="text" name="genre" class="input-field" placeholder="Genres (e.g. Action, Drama)" required>
                    </div>

                    <div class="input-row">
                        <input type="number" name="duration" class="input-field" placeholder="Duration (minutes)" min="1" required>
                        <input type="number" name="price" class="input-field" placeholder="Price ($)" step="0.01" min="0" required>
                        <input type="text" name="description" class="input-field" placeholder="Description">
                    </div>

                    <input type="file" id="posterFileInput" name="poster" style="display: none;" accept="image/*">
                    <input type="file" id="videoFileInput" name="video" style="display: none;" accept="video/*, .mkv, .mp4, .avi">

                    <div class="input-row" style="margin-top: 15px;">
                        <button type="button" class="btn-upload"
                            style="background-color: var(--bg-element); border: 1px solid var(--accent-color); color: var(--accent-color);"
                            onclick="document.getElementById('posterFileInput').click()">
                            <i class="fa-solid fa-image"></i> Select Poster
                        </button>
                        <button type="button" class="btn-upload"
                            style="background-color: var(--bg-element); border: 1px solid var(--accent-color); color: var(--accent-color);"
                            onclick="document.getElementById('videoFileInput').click()">
                            <i class="fa-solid fa-film"></i> Select Movie
                        </button>
                    </div>

                    <button type="submit" name="upload_movie" class="btn-upload" style="margin-top: 15px;">
                        <i class="fa-solid fa-cloud-arrow-up"></i> Upload Movie
                    </button>
                </form>
            </div>
        </section>

        <section class="section-container blue-border">
            <div class="section-tab">My Movies</div>

            <div class="movie-grid">
                <?php foreach ($producer_movies as $movie): ?>
                    <div class="movie-card">
                        <div class="badge-overlay <?php echo ($movie['is_premium'] ? 'badge-premium' : 'badge-free'); ?>">
                            <?php echo ($movie['is_premium'] ? 'PREMIUM' : 'FREE'); ?>
                        </div>
                        <img src="<?php echo htmlspecialchars($movie['poster_url']); ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>" class="movie-poster">
                        <div class="movie-overlay">
                            <h3 class="overlay-title"><?php echo htmlspecialchars($movie['title']); ?></h3>
                            <p class="overlay-genre"><?php echo htmlspecialchars($movie['genre']); ?></p>
                            <p style="color:#ccc; font-size:12px;">Status: <strong><?php echo ucfirst($movie['approval_status']); ?></strong></p>
                            <p style="color:#ccc; font-size:12px; margin-top:10px;">$<?php echo number_format($movie['price'], 2); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($producer_movies)): ?>
                    <p style="color: #aaa; padding: 20px;">No movies uploaded yet. Upload your first movie above!</p>
                <?php endif; ?>
            </div>
        </section>
    </div>

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
        function populateYears(elementId) {
            const select = document.getElementById(elementId);
            const currentYear = new Date().getFullYear();
            const startYear = 1970;

            for (let i = currentYear; i >= startYear; i--) {
                let option = document.createElement('option');
                option.value = i;
                option.text = i;
                select.appendChild(option);
            }
        }
        populateYears('search-year');
        populateYears('release_year');

        document.getElementById('posterFileInput').addEventListener('change', function () {
            if (this.files && this.files.length > 0) {
                const fileName = this.files[0].name;
                const label = document.querySelector('button[onclick*="posterFileInput"]');
                if (label) label.innerHTML = '<i class="fa-solid fa-image"></i> ' + fileName;
            }
        });

        document.getElementById('videoFileInput').addEventListener('change', function () {
            if (this.files && this.files.length > 0) {
                const fileName = this.files[0].name;
                const label = document.querySelector('button[onclick*="videoFileInput"]');
                if (label) label.innerHTML = '<i class="fa-solid fa-film"></i> ' + fileName;
            }
        });
    </script>

    <script src="../assets/theme.js"></script>
    <script src="../assets/notification.js"></script>

</body>

</html>
