<?php
session_start();
require_once '../config/db_connect.php';

// --- 0. SERVER ERROR CHECK (File too large) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
    echo "<script>
            alert('❌ SERVER ERROR: The file is larger than the server allows.\\n\\nPlease check your php.ini settings for upload_max_filesize and post_max_size.');
            window.location.href='Series.php';
          </script>";
    exit();
}

// --- 1. HANDLE SERIES CREATE ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_series'])) {
    $title = $_POST['title'];
    $year = $_POST['year'];
    $genre = $_POST['genre'];
    $description = $title; // Default description if not provided
    $uploaded_by = $_SESSION['user_id'] ?? 1;

    // Poster Upload
    $poster_path = "";
    if (!empty($_FILES['poster']['name'])) {
        $target_dir = "../uploads/thumbnails/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $poster_name = time() . "_s_" . basename($_FILES['poster']['name']);
        move_uploaded_file($_FILES['poster']['tmp_name'], $target_dir . $poster_name);
        $poster_path = "uploads/thumbnails/" . $poster_name;
    }

    // Insert into DB (Notice: No video_url needed here)
    // Note: Admin uploads are auto-approved
    $sql = "INSERT INTO series (title, description, release_year, genre, poster_url, uploaded_by, approval_status) 
            VALUES (?, ?, ?, ?, ?, ?, 'approved')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssissi", $title, $description, $year, $genre, $poster_path, $uploaded_by);

    if ($stmt->execute()) {
        echo "<script>alert('Series Profile Created! Now click on it to add episodes.'); window.location.href='Series.php';</script>";
    } else {
        echo "<script>alert('Error: " . $conn->error . "');</script>";
    }
}

// --- 2. SEARCH & FETCH ---
// Base condition: Only show approved series
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

$sql_fetch = "SELECT * FROM series WHERE " . implode(" AND ", $where) . " ORDER BY created_at DESC";
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
    <title>Series & TV Shows</title>
    <link rel="stylesheet" href="../assets/Dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <nav class="navbar">
        <div class="nav-container">
            <a href="Dashboard.html" class="logo-btn hover-glow">
                <img src="../assets/logo.png" alt="Logo" class="logo-img">
            </a>
            <ul class="nav-links">
                <li><a href="Dashboard.php" class="hover-glow">Dashboard</a></li>
                <li><a href="Users.html" class="hover-glow">Users</a></li>
                <li><a href="Movies.php" class="hover-glow">Movies</a></li>
                <li><a href="Series.php" class="hover-glow" style="color:#228EE5;">Series</a></li>
                <li><a href="Wallet.php" class="hover-glow">Wallet</a></li>
                <li><a href="adminForum.php" class="hover-glow">Forum</a></li>
                <li><a href="chat.php" class="hover-glow">Chat</a></li>
            </ul>
            <div class="nav-icons">
                <div class="search-container">
                    <input type="text" id="navSearch" class="search-input-nav" placeholder="Type to search...">
                    <button class="icon-btn hover-glow search-btn-nav"><i class="fa-solid fa-magnifying-glass"></i></button>
                </div>
                <div class="notification-wrapper">
                    <button class="icon-btn hover-glow"><i class="fa-solid fa-bell"></i></button>
                </div>
                <a href="AdminProfile.html" class="icon-btn hover-glow"><i class="fa-solid fa-user"></i></a>
            </div>
        </div>
    </nav>

    <div class="main-content">
        <h1 class="welcome-text">TV Series</h1>

        <section class="movie-control-panel">
            <div class="section-tab">Advance Search</div>
            <div class="panel-content">
                <div class="panel-icon"><i class="fa-solid fa-tv"></i></div>
                <form method="GET" class="panel-form" style="width:100%;">
                    <div class="input-row">
                        <select name="year" id="search-year" class="select-field">
                            <option value="">Select Year</option>
                        </select>
                        <input type="text" class="input-field" placeholder="Country">
                        <input type="text" class="input-field" placeholder="Actor">
                        <input type="text" class="input-field" placeholder="Creator">
                    </div>

                    <div class="search-wrapper">
                        <input type="text" name="search" class="input-field" placeholder="Search for TV shows..." value="<?php echo $_GET['search'] ?? ''; ?>">
                        <button type="submit" class="search-icon-btn"><i class="fa-solid fa-magnifying-glass"></i></button>
                    </div>
                </form>
            </div>
        </section>

        <section class="movie-control-panel">
            <div class="section-tab">Create New Series</div>
            <div class="panel-content">
                <div class="panel-icon"><i class="fa-solid fa-folder-plus"></i></div>
                <form method="POST" enctype="multipart/form-data" class="panel-form" style="width:100%;">
                    <input type="hidden" name="upload_series" value="1">

                    <div class="input-row">
                        <input type="text" name="title" class="input-field" placeholder="Series Title" required>
                        <select name="year" id="upload-year" class="select-field" required>
                            <option value="">Select Year</option>
                        </select>
                        <input type="text" class="input-field" placeholder="Country">
                    </div>

                    <div class="input-row">
                        <input type="text" class="input-field" placeholder="Creator">
                        <input type="text" class="input-field" placeholder="Main Actor">
                        <input type="text" name="genre" class="input-field" placeholder="Genres (e.g. Drama)" required>
                    </div>

                    <input type="file" name="poster" id="posterFileInput" style="display: none;" accept="image/*" required>

                    <div class="input-row" style="margin-top: 15px;">
                        <button type="button" id="posterBtn" class="btn-upload"
                            style="background-color: var(--bg-element); border: 1px solid var(--accent-color); color: var(--accent-color);"
                            onclick="document.getElementById('posterFileInput').click()">
                            <i class="fa-solid fa-image"></i> Select Poster Image
                        </button>
                    </div>

                    <button type="submit" class="btn-upload" style="margin-top: 15px;">
                        <i class="fa-solid fa-plus"></i> Create Series Profile
                    </button>
                </form>
            </div>
        </section>

        <section class="section-container blue-border">
            <div class="section-tab">Library</div>

            <div class="movie-grid" id="series-grid-container">
                <?php while ($row = $result->fetch_assoc()): ?>
                    <?php
                    $badgeClass = $row['is_premium'] ? 'badge-premium' : 'badge-free';
                    $badgeText = $row['is_premium'] ? 'PREMIUM' : 'FREE';
                    $poster = !empty($row['poster_url']) ? "../" . $row['poster_url'] : '../assets/logo.png';
                    ?>
                    <a href="SeriesDetail.php?id=<?php echo $row['series_id']; ?>" class="movie-card">
                        <div class="badge-overlay <?php echo $badgeClass; ?>"><?php echo $badgeText; ?></div>
                        <img src="<?php echo $poster; ?>" alt="<?php echo $row['title']; ?>" class="movie-poster">
                        <div class="movie-overlay">
                            <h3 class="overlay-title"><?php echo htmlspecialchars($row['title']); ?></h3>
                            <p class="overlay-genre"><?php echo htmlspecialchars($row['genre']); ?></p>
                            <p style="color:#ccc; font-size:12px; margin-top:10px;">Click to Manage Episodes</p>
                        </div>
                    </a>
                <?php endwhile; ?>

                <?php if ($result->num_rows == 0): ?>
                    <p style="color:#888; text-align:center; width:100%;">No approved series found.</p>
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

        document.getElementById('posterFileInput').addEventListener('change', function() {
            if (this.files[0]) {
                const btn = document.getElementById('posterBtn');
                btn.innerHTML = '<i class="fa-solid fa-check"></i> ' + this.files[0].name;
                btn.style.borderColor = "#28a745";
                btn.style.color = "#28a745";
            }
        });
    </script>
    <script src="../assets/theme.js"></script>
</body>

</html>