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

// Handle series upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_series'])) {
    $title = mysqli_real_escape_string($conn, $_POST['title'] ?? '');
    $description = mysqli_real_escape_string($conn, $_POST['description'] ?? '');
    $genre = mysqli_real_escape_string($conn, $_POST['genre'] ?? '');
    $release_year = intval($_POST['release_year'] ?? 0);
    $is_premium = isset($_POST['is_premium']) ? 1 : 0;
    
    if ($title && $genre) {
        // Handle poster file upload
        $poster_url = '/uploads/thumbnails/default.jpg';
        if (isset($_FILES['poster']) && $_FILES['poster']['error'] === UPLOAD_ERR_OK) {
            $poster_name = 'poster_' . $producer_id . '_' . time() . '_' . basename($_FILES['poster']['name']);
            $poster_path = '../uploads/thumbnails/' . $poster_name;
            if (move_uploaded_file($_FILES['poster']['tmp_name'], $poster_path)) {
                $poster_url = '/uploads/thumbnails/' . $poster_name;
            }
        }
        
        $query = "INSERT INTO series (title, description, genre, release_year, is_premium, poster_url, uploaded_by, approval_status, created_at)
                  VALUES ('$title', '$description', '$genre', $release_year, $is_premium, '$poster_url', $producer_id, 'pending', NOW())";
        
        if (mysqli_query($conn, $query)) {
            $success_msg = "Series uploaded successfully! Awaiting admin approval.";
        } else {
            $error_msg = "Error uploading series: " . mysqli_error($conn);
        }
    } else {
        $error_msg = "Please fill in all required fields (Title, Genre).";
    }
}

// Get producer's series
$series_query = "SELECT * FROM series WHERE uploaded_by = $producer_id ORDER BY created_at DESC";
$series_result = mysqli_query($conn, $series_query) or $series_result = null;
$producer_series = [];
if ($series_result) {
    while ($row = mysqli_fetch_assoc($series_result)) {
        $producer_series[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Series & TV Shows</title>
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
                <li><a href="Movies.php" class="hover-glow">Movies</a></li>
                <li><a href="Series.php" class="hover-glow" style="color:#228EE5;">Series</a></li>
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
                        </div>
                    </div>
                </div>
                <a href="UserProfile.php" class="icon-btn hover-glow"><i class="fa-solid fa-user"></i></a>
                <button class="icon-btn hover-glow"><i class="fa-solid fa-sun"></i></button>
            </div>
        </div>
    </nav>

    <div class="main-content">
        <h1 class="welcome-text">TV Series</h1>

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
                    <i class="fa-solid fa-tv"></i>
                </div>
                <div class="panel-form">
                    <div class="input-row">
                        <select id="search-year" class="select-field">
                            <option value="">Select Year</option>
                        </select>
                        <input type="text" class="input-field" placeholder="Country">
                        <input type="text" class="input-field" placeholder="Actor">
                        <input type="text" class="input-field" placeholder="Creator/Director">
                    </div>

                    <div class="search-wrapper">
                        <input type="text" class="input-field" placeholder="Search for TV shows...">
                        <button class="search-icon-btn"><i class="fa-solid fa-magnifying-glass"></i></button>
                    </div>

                    <div class="input-row" style="margin-top: 15px;">
                        <input type="text" class="input-field" placeholder="Genre (e.g. Drama, Sitcom, Sci-Fi)">
                    </div>
                </div>
            </div>
        </section>

        <section class="movie-control-panel">
            <div class="section-tab">Upload Series</div>
            <div class="panel-content">
                <div class="panel-icon">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                </div>
                <form class="panel-form" method="POST" enctype="multipart/form-data">
                    <div class="input-row">
                        <input type="text" name="title" class="input-field" placeholder="Series Title" required>
                        <select name="release_year" id="release_year" class="select-field" required>
                            <option value="">Select Year</option>
                        </select>
                        <input type="text" name="country" class="input-field" placeholder="Country">
                    </div>

                    <div class="input-row">
                        <input type="text" name="creator" class="input-field" placeholder="Creator/Director">
                        <input type="text" name="actor" class="input-field" placeholder="Main Actor">
                        <input type="text" name="genre" class="input-field" placeholder="Genres (e.g. Action, Drama)" required>
                    </div>

                    <input type="text" name="description" class="input-field" placeholder="Description" style="margin-top: 15px;">

                    <input type="file" id="posterFileInput" name="poster" style="display: none;" accept="image/*">
                    <input type="file" id="seriesFileInput" name="series" style="display: none;" accept="video/*, .mkv, .mp4, .avi">

                    <div class="input-row" style="margin-top: 15px;">
                        <button type="button" class="btn-upload"
                            style="background-color: var(--bg-element); border: 1px solid var(--accent-color); color: var(--accent-color);"
                            onclick="document.getElementById('posterFileInput').click()">
                            <i class="fa-solid fa-image"></i> Select Poster
                        </button>
                        <button type="button" class="btn-upload"
                            style="background-color: var(--bg-element); border: 1px solid var(--accent-color); color: var(--accent-color);"
                            onclick="document.getElementById('seriesFileInput').click()">
                            <i class="fa-solid fa-film"></i> Select Series
                        </button>
                    </div>

                    <button type="submit" name="upload_series" class="btn-upload" style="margin-top: 15px;">
                        <i class="fa-solid fa-cloud-arrow-up"></i> Upload Series
                    </button>
                </form>
            </div>
        </section>

        <section class="section-container blue-border">
            <div class="section-tab">My Series</div>

            <div class="movie-grid">
                <?php foreach ($producer_series as $series): ?>
                    <a href="SeriesDetail.php?id=<?php echo intval($series['id']); ?>" class="movie-card">
                        <div class="badge-overlay <?php echo ($series['is_premium'] ? 'badge-premium' : 'badge-free'); ?>">
                            <?php echo ($series['is_premium'] ? 'PREMIUM' : 'FREE'); ?>
                        </div>
                        <img src="<?php echo htmlspecialchars($series['poster_url']); ?>" alt="<?php echo htmlspecialchars($series['title']); ?>" class="movie-poster">
                        <div class="movie-overlay">
                            <h3 class="overlay-title"><?php echo htmlspecialchars($series['title']); ?></h3>
                            <p class="overlay-genre"><?php echo htmlspecialchars($series['genre']); ?></p>
                            <p style="color:#ccc; font-size:12px;">Status: <strong><?php echo ucfirst($series['approval_status']); ?></strong></p>
                            <p style="color:#ccc; font-size:12px; margin-top:10px;">Click for Details</p>
                        </div>
                    </a>
                <?php endforeach; ?>
                
                <?php if (empty($producer_series)): ?>
                    <p style="color: #aaa; padding: 20px;">No series uploaded yet. Upload your first series above!</p>
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

        document.getElementById('seriesFileInput').addEventListener('change', function () {
            if (this.files && this.files.length > 0) {
                const fileName = this.files[0].name;
                const label = document.querySelector('button[onclick*="seriesFileInput"]');
                if (label) label.innerHTML = '<i class="fa-solid fa-film"></i> ' + fileName;
            }
        });
    </script>

    <script src="../assets/theme.js"></script>
    <script src="../assets/notification.js"></script>

</body>

</html>
