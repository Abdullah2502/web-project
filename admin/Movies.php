<?php
session_start();
require_once '../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
    echo "<script>
            alert('❌ SERVER ERROR: The file is larger than the server allows.\\n\\nPlease check your php.ini settings for upload_max_filesize and post_max_size.');
            window.location.href='Movies.php';
          </script>";
    exit();
}

// --- 1. HANDLE UPLOAD ---
$msg = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_movie'])) {
    $title = $_POST['title'];
    $year = $_POST['year'];
    $genre = $_POST['genre'];
    $description = $title;
    $price = 0.00;
    $is_premium = 0;
    $uploaded_by = $_SESSION['user_id'] ?? 1;

    // File Upload Logic
    $poster_path = "";
    $video_path = "";

    // Poster
    if (!empty($_FILES['poster']['name'])) {
        $target_dir = "../uploads/thumbnails/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $poster_name = time() . "_p_" . basename($_FILES['poster']['name']);
        move_uploaded_file($_FILES['poster']['tmp_name'], $target_dir . $poster_name);
        $poster_path = "uploads/thumbnails/" . $poster_name;
    }

    // Video
    if (!empty($_FILES['video']['name'])) {
        $target_dir = "../uploads/movies/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $video_name = time() . "_v_" . basename($_FILES['video']['name']);
        move_uploaded_file($_FILES['video']['tmp_name'], $target_dir . $video_name);
        $video_path = "uploads/movies/" . $video_name;
    }

    // NOTE: Default status for admin upload is 'approved'
    $sql = "INSERT INTO movies (title, description, release_year, genre, poster_url, video_url, is_premium, price, uploaded_by, approval_status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssisssidi", $title, $description, $year, $genre, $poster_path, $video_path, $is_premium, $price, $uploaded_by);

    if ($stmt->execute()) {
        echo "<script>alert('Movie Uploaded Successfully!'); window.location.href='Movies.php';</script>";
    } else {
        echo "<script>alert('Error: " . $conn->error . "');</script>";
    }
}

// --- 2. SEARCH & FETCH (UPDATED: Only Approved Movies) ---
// Base condition is now checking for 'approved' status
$where = ["approval_status = 'approved'"];
$params = [];
$types = "";

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $where[] = "title LIKE ?";
    $params[] = "%" . $_GET['search'] . "%";
    $types .= "s";
}
if (isset($_GET['year']) && !empty($_GET['year'])) {
    $where[] = "release_year = ?";
    $params[] = $_GET['year'];
    $types .= "i";
}
if (isset($_GET['genre']) && !empty($_GET['genre'])) {
    $where[] = "genre LIKE ?";
    $params[] = "%" . $_GET['genre'] . "%";
    $types .= "s";
}

$sql_fetch = "SELECT * FROM movies WHERE " . implode(" AND ", $where) . " ORDER BY created_at DESC";
$stmt = $conn->prepare($sql_fetch);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
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
                <li><a href="Users.php" class="hover-glow">Users</a></li>
                <li><a href="Movies.php" class="hover-glow" style="color:#228EE5;">Movies</a></li>
                <li><a href="Series.php" class="hover-glow">Series</a></li>
                <li><a href="Wallet.php" class="hover-glow">Wallet</a></li>
                <li><a href="adminForum.php" class="hover-glow">Forum</a></li>
                <li><a href="chat.php" class="hover-glow">Chat</a></li>
            </ul>
            <div class="nav-icons">
                <div class="search-container">
                    <input type="text" id="navSearch" class="search-input-nav" placeholder="Type to search...">
                    <button class="icon-btn hover-glow search-btn-nav"><i
                            class="fa-solid fa-magnifying-glass"></i></button>
                </div>
                <div class="notification-wrapper">
                    <button class="icon-btn hover-glow"><i class="fa-solid fa-bell"></i></button>
                </div>
                <a href="adminProfile.php" class="icon-btn hover-glow"><i class="fa-solid fa-user"></i></a>
                <a href="../actions/logout.php" class="icon-btn hover-glow" title="Logout" style="color: #e50914;">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </a>
                <button class="icon-btn hover-glow"><i class="fa-solid fa-sun"></i></button>
            </div>
        </div>
    </nav>

    <div class="main-content">
        <h1 class="welcome-text">Movies</h1>

        <section class="movie-control-panel">
            <div class="section-tab">Advance Search</div>
            <div class="panel-content">
                <div class="panel-icon"><i class="fa-solid fa-film"></i></div>
                <form method="GET" class="panel-form" style="width:100%;">
                    <div class="input-row">
                        <select name="year" id="search-year" class="select-field">
                            <option value="">Select Year</option>
                        </select>
                        <input type="text" class="input-field" placeholder="Country (Not used)">
                        <input type="text" class="input-field" placeholder="Actor (Not used)">
                        <input type="text" class="input-field" placeholder="Director (Not used)">
                    </div>

                    <div class="search-wrapper">
                        <input type="text" name="search" class="input-field" placeholder="Search for movies..."
                            value="<?php echo $_GET['search'] ?? ''; ?>">
                        <button type="submit" class="search-icon-btn"><i
                                class="fa-solid fa-magnifying-glass"></i></button>
                    </div>

                    <div class="input-row" style="margin-top: 15px;">
                        <input type="text" name="genre" class="input-field" placeholder="Genre (e.g. Action)"
                            value="<?php echo $_GET['genre'] ?? ''; ?>">
                    </div>
                </form>
            </div>
        </section>

        <section class="movie-control-panel">
            <div class="section-tab">Upload Movie</div>
            <div class="panel-content">
                <div class="panel-icon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
                <form method="POST" enctype="multipart/form-data" class="panel-form" style="width:100%;">
                    <input type="hidden" name="upload_movie" value="1">

                    <div class="input-row">
                        <input type="text" name="title" class="input-field" placeholder="Movie Title" required>
                        <select name="year" id="upload-year" class="select-field" required>
                            <option value="">Select Year</option>
                        </select>
                        <input type="text" class="input-field" placeholder="Country (Optional)">
                    </div>

                    <div class="input-row">
                        <input type="text" class="input-field" placeholder="Director (Optional)">
                        <input type="text" class="input-field" placeholder="Main Actor (Optional)">
                        <input type="text" name="genre" class="input-field" placeholder="Genres (e.g. Action)" required>
                    </div>

                    <input type="file" name="poster" id="posterFileInput" style="display: none;" accept="image/*"
                        required>
                    <input type="file" name="video" id="seriesFileInput" style="display: none;" accept="video/*, .mkv, .mp4, .avi, .mov"
                        required>

                    <div class="input-row" style="margin-top: 15px;">
                        <button type="button" id="posterBtn" class="btn-upload"
                            style="background-color: var(--bg-element); border: 1px solid var(--accent-color); color: var(--accent-color);"
                            onclick="document.getElementById('posterFileInput').click()">
                            <i class="fa-solid fa-image"></i> Select Poster
                        </button>
                        <button type="button" id="videoBtn" class="btn-upload"
                            style="background-color: var(--bg-element); border: 1px solid var(--accent-color); color: var(--accent-color);"
                            onclick="document.getElementById('seriesFileInput').click()">
                            <i class="fa-solid fa-film"></i> Select Movie
                        </button>
                    </div>

                    <button type="submit" class="btn-upload" style="margin-top: 15px;">
                        <i class="fa-solid fa-cloud-arrow-up"></i> Upload Movie
                    </button>
                </form>
            </div>
        </section>

        <section class="section-container blue-border">
            <div class="section-tab">Library</div>

            <div class="movie-grid" id="movie-grid-container">
                <?php while ($row = $result->fetch_assoc()): ?>
                    <?php
                    $badgeClass = $row['is_premium'] ? 'badge-premium' : 'badge-free';
                    $badgeText = $row['is_premium'] ? 'PREMIUM' : 'FREE';
                    $poster = !empty($row['poster_url']) ? "../" . $row['poster_url'] : '../assets/logo.png';
                    ?>
                    <a href="MovieDetail.php?id=<?php echo $row['movie_id']; ?>" class="movie-card">
                        <div class="badge-overlay <?php echo $badgeClass; ?>"><?php echo $badgeText; ?></div>
                        <img src="<?php echo $poster; ?>" alt="<?php echo $row['title']; ?>" class="movie-poster">
                        <div class="movie-overlay">
                            <h3 class="overlay-title"><?php echo htmlspecialchars($row['title']); ?></h3>
                            <p class="overlay-genre"><?php echo htmlspecialchars($row['genre']); ?></p>
                            <p style="color:#ccc; font-size:12px; margin-top:10px;">Click for Details</p>
                        </div>
                    </a>
                <?php endwhile; ?>

                <?php if ($result->num_rows == 0): ?>
                    <p style="color:#888; text-align:center; width:100%;">No approved movies found.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <footer class="footer">
        <div class="footer-container">
            <p class="copyright">© 1997-2026 MSP - Movie Streaming Platform, Inc.</p>
        </div>
    </footer>

    <script>
        // 1. Populate Years
        function populateYears(elementId) {
            const select = document.getElementById(elementId);
            const currentYear = new Date().getFullYear();
            for (let i = currentYear; i >= 1970; i--) {
                let option = document.createElement('option');
                option.value = i;
                option.text = i;
                select.appendChild(option);
            }
        }
        populateYears('search-year');
        populateYears('upload-year');

        // 2. Button Filename Feedback
        document.getElementById('posterFileInput').addEventListener('change', function() {
            if (this.files[0]) {
                const btn = document.getElementById('posterBtn');
                btn.innerHTML = '<i class="fa-solid fa-check"></i> ' + this.files[0].name;
                btn.style.borderColor = "#28a745";
                btn.style.color = "#28a745";
            }
        });

        document.getElementById('seriesFileInput').addEventListener('change', function() {
            if (this.files[0]) {
                const btn = document.getElementById('videoBtn');
                btn.innerHTML = '<i class="fa-solid fa-check"></i> ' + this.files[0].name;
                btn.style.borderColor = "#28a745";
                btn.style.color = "#28a745";
            }
        });

        // 3. POPUP ALERT: Check File Size BEFORE Submitting
        document.querySelector('form[method="POST"]').onsubmit = function(e) {
            const videoInput = document.getElementById('seriesFileInput');
            const maxSize = 2.5 * 1024 * 1024 * 1024; // 2GB in bytes

            if (videoInput.files && videoInput.files[0]) {
                if (videoInput.files[0].size > maxSize) {
                    alert("⚠️ POPUP ALERT: The selected file is too large!\n\nLimit: 2.5GB\nYour File: " + (videoInput
                        .files[0].size / (1024 * 1024 * 1024)).toFixed(2) + " GB");
                    e.preventDefault(); // Stop the crash
                    return false;
                }
            }
        };
    </script>
    <script src="../assets/theme.js"></script>
</body>

</html>