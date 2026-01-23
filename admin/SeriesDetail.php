<?php
session_start();
require_once '../config/db_connect.php';

// 1. CHECK ID
if (!isset($_GET['id'])) {
    echo "No Series ID specified.";
    exit();
}
$series_id = intval($_GET['id']);
$msg = "";

// --- 2. HANDLE ACTIONS ---

// A. UPDATE MONETIZATION
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_monetization'])) {
    $price = $_POST['price'];
    $is_premium = isset($_POST['is_premium']) ? 1 : 0;
    if (!$is_premium) $price = 0.00;

    $sql = "UPDATE series SET price = ?, is_premium = ? WHERE series_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("dii", $price, $is_premium, $series_id);

    if ($stmt->execute()) {
        $msg = "<script>alert('Monetization settings updated!');</script>";
    } else {
        $msg = "<script>alert('Error updating settings: " . $conn->error . "');</script>";
    }
}

// B. DELETE SERIES
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_series'])) {
    $sql_eps = "SELECT video_url FROM episodes WHERE series_id = ?";
    $stmt_eps = $conn->prepare($sql_eps);
    $stmt_eps->bind_param("i", $series_id);
    $stmt_eps->execute();
    $res_eps = $stmt_eps->get_result();
    while ($row = $res_eps->fetch_assoc()) {
        if (!empty($row['video_url']) && file_exists("../" . $row['video_url'])) unlink("../" . $row['video_url']);
    }
    $sql_poster = "SELECT poster_url FROM series WHERE series_id = ?";
    $stmt = $conn->prepare($sql_poster);
    $stmt->bind_param("i", $series_id);
    $stmt->execute();
    $poster = $stmt->get_result()->fetch_assoc();
    if ($poster && !empty($poster['poster_url']) && file_exists("../" . $poster['poster_url'])) unlink("../" . $poster['poster_url']);

    $sql_del = "DELETE FROM series WHERE series_id = ?";
    $stmt = $conn->prepare($sql_del);
    $stmt->bind_param("i", $series_id);
    if ($stmt->execute()) {
        echo "<script>alert('Series deleted!'); window.location.href='Series.php';</script>";
        exit();
    }
}

// C. DELETE EPISODE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_episode'])) {
    $ep_id = intval($_POST['episode_id']);
    $sql_file = "SELECT video_url FROM episodes WHERE episode_id = ?";
    $stmt = $conn->prepare($sql_file);
    $stmt->bind_param("i", $ep_id);
    $stmt->execute();
    $file = $stmt->get_result()->fetch_assoc();
    if ($file && file_exists("../" . $file['video_url'])) unlink("../" . $file['video_url']);

    $conn->query("DELETE FROM episodes WHERE episode_id = $ep_id");
    $msg = "<script>alert('Episode deleted!');</script>";
}

// D. UPDATE DETAILS
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_details'])) {
    $title = $_POST['title'];
    $desc = $_POST['description'];
    $year = $_POST['release_year'];
    $genre = $_POST['genre'];

    $sql = "UPDATE series SET title = ?, description = ?, release_year = ?, genre = ? WHERE series_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssisi", $title, $desc, $year, $genre, $series_id);
    if ($stmt->execute()) $msg = "<script>alert('Details updated!');</script>";
}

// E. UPLOAD EPISODE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_episode'])) {
    $ep_title = $_POST['ep_title'];
    $season = $_POST['season'];
    $episode_num = $_POST['episode_num'];
    $duration = $_POST['duration'];

    if (!empty($_FILES['video_file']['name'])) {
        $target_dir = "../uploads/episodes/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $ext = strtolower(pathinfo($_FILES['video_file']['name'], PATHINFO_EXTENSION));
        $new_name = time() . "_S" . $season . "E" . $episode_num . "." . $ext;
        if (move_uploaded_file($_FILES['video_file']['tmp_name'], $target_dir . $new_name)) {
            $path = "uploads/episodes/" . $new_name;
            $stmt = $conn->prepare("INSERT INTO episodes (series_id, season_number, episode_number, title, video_url, duration_minutes) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param("iiisss", $series_id, $season, $episode_num, $ep_title, $path, $duration);
            $stmt->execute();
            $msg = "<script>alert('Episode uploaded!');</script>";
        }
    }
}

// --- 3. FETCH DATA (Updated to get Uploader Name) ---
$sql = "SELECT s.*, u.username as uploader 
        FROM series s 
        LEFT JOIN users u ON s.uploaded_by = u.user_id 
        WHERE s.series_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $series_id);
$stmt->execute();
$series = $stmt->get_result()->fetch_assoc();
if (!$series) die("Series not found.");

if (!isset($series['price'])) $series['price'] = 0.00;

$sql_eps = "SELECT * FROM episodes WHERE series_id = ? ORDER BY season_number ASC, episode_number ASC";
$stmt = $conn->prepare($sql_eps);
$stmt->bind_param("i", $series_id);
$stmt->execute();
$result_eps = $stmt->get_result();

$poster_url = !empty($series['poster_url']) ? "../" . $series['poster_url'] : '../assets/logo.png';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($series['title']); ?></title>
    <link rel="stylesheet" href="../assets/Dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="../assets/theme.js"></script>
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
        }

        .modal-content {
            background-color: #1a1a1a;
            margin: 5% auto;
            padding: 25px;
            border: 1px solid #333;
            width: 50%;
            max-width: 600px;
            border-radius: 10px;
            color: #fff;
            position: relative;
        }

        .close {
            position: absolute;
            right: 20px;
            top: 15px;
            font-size: 28px;
            cursor: pointer;
            color: #aaa;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #ccc;
            font-size: 14px;
        }

        .form-input {
            width: 100%;
            padding: 10px;
            background: #333;
            border: 1px solid #444;
            color: #fff;
            border-radius: 4px;
        }

        .btn-submit {
            background: #e50914;
            color: white;
            padding: 12px;
            width: 100%;
            border: none;
            cursor: pointer;
            border-radius: 4px;
            font-size: 16px;
            margin-top: 10px;
        }

        .upload-box {
            background: #1f2940;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border: 1px solid #333;
        }

        .episode-item {
            background: #161d2f;
            padding: 15px;
            border-radius: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 4px solid #e50914;
            margin-bottom: 10px;
        }

        .form-row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }

        .input-dark {
            background: #0b1326;
            border: 1px solid #444;
            color: white;
            padding: 10px;
            border-radius: 4px;
            width: 100%;
        }

        .cast-grid {
            display: flex;
            gap: 15px;
            overflow-x: auto;
            padding-bottom: 10px;
        }

        .cast-grid::-webkit-scrollbar {
            height: 8px;
            background: #222;
        }

        .cast-grid::-webkit-scrollbar-thumb {
            background: #555;
            border-radius: 4px;
        }

        .cast-card {
            min-width: 100px;
            text-align: center;
        }

        .cast-img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 5px;
            border: 2px solid #e50914;
        }

        .btn-edit {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            padding: 5px 10px;
            cursor: pointer;
            border-radius: 4px;
            margin-left: 10px;
            font-size: 14px;
        }

        .btn-delete {
            background: #dc3545;
            border: 1px solid #b02a37;
            color: white;
            padding: 5px 10px;
            cursor: pointer;
            border-radius: 4px;
            margin-left: 5px;
            font-size: 14px;
        }

        .btn-del-sm {
            background: transparent;
            color: #dc3545;
            border: none;
            cursor: pointer;
            font-size: 16px;
            margin-left: 10px;
        }

        .api-tools {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .btn-small {
            background: #333;
            color: white;
            border: 1px solid #555;
            padding: 5px 10px;
            cursor: pointer;
            font-size: 12px;
            border-radius: 4px;
        }

        .price-input-container {
            display: none;
            margin-top: 15px;
        }

        .price-input-container.active {
            display: block;
        }
    </style>
</head>

<body>
    <?php echo $msg; ?>

    <div id="videoModal" class="video-modal-overlay">
        <div class="video-player-container">
            <button class="video-close-btn" onclick="closePlayer()"><i class="fa-solid fa-xmark"></i> Close Player</button>
            <video id="localPlayer" controls style="width:100%; height:100%; display:none; background:black;"></video>
        </div>
    </div>

    <nav class="navbar">
        <div class="nav-container">
            <a href="Dashboard.php" class="logo-btn hover-glow"><img src="../assets/logo.png" alt="Logo" class="logo-img"></a>
            <ul class="nav-links">
                <li><a href="Dashboard.php">Dashboard</a></li>
                <li><a href="Users.html">Users</a></li>
                <li><a href="Movies.php">Movies</a></li>
                <li><a href="Series.php" style="color:#228EE5;">Series</a></li>
            </ul>
            <div class="nav-icons"><a href="AdminProfile.html" class="icon-btn hover-glow"><i class="fa-solid fa-user"></i></a></div>
        </div>
    </nav>

    <div id="hero" class="hero-banner" style="background-image: linear-gradient(to bottom, rgba(0,0,0,0.3), #020b1f), url('<?php echo $poster_url; ?>'); background-size: cover; background-position: center;">
        <div class="hero-content">
            <input type="hidden" id="phpSeriesTitle" value="<?php echo htmlspecialchars($series['title']); ?>">
            <input type="hidden" id="phpSeriesYear" value="<?php echo $series['release_year']; ?>">

            <div style="display:flex; align-items:center;">
                <h1 id="sTitle" style="font-size: 48px; margin-bottom: 10px;"><?php echo htmlspecialchars($series['title']); ?></h1>
                <button class="btn-edit" onclick="openEditModal()"><i class="fa-solid fa-pen"></i> Edit</button>
                <form method="POST" onsubmit="return confirm('Delete entire series?');" style="display:inline;">
                    <input type="hidden" name="delete_series" value="1">
                    <button type="submit" class="btn-delete"><i class="fa-solid fa-trash"></i> Delete Series</button>
                </form>
            </div>

            <div style="display: flex; gap: 15px; font-size: 14px; color: #ccc; margin-bottom: 20px;">
                <span><?php echo $series['release_year']; ?></span> |
                <span><?php echo htmlspecialchars($series['genre']); ?></span> |
                <span><i class="fa-solid fa-user"></i> Uploaded by: <b style="color:white;"><?php echo htmlspecialchars($series['uploader'] ?? 'Unknown'); ?></b></span> |
                <span style="color:<?php echo $series['is_premium'] ? '#ffd700' : '#2ecc71'; ?>">
                    <i class="fa-solid fa-star"></i> <?php echo $series['is_premium'] ? 'Premium' : 'Free'; ?>
                </span>
            </div>

            <div style="display: flex; gap: 15px;">
                <button class="btn-watch" onclick="document.querySelector('.episode-item .btn-play')?.click()">
                    <i class="fa-solid fa-play"></i> Watch S1 E1
                </button>
            </div>
        </div>
    </div>

    <div class="detail-container">
        <h2>About</h2>
        <p style="color:var(--text-muted); line-height:1.6; margin-bottom: 30px;">
            <?php echo htmlspecialchars($series['description']); ?>
        </p>

        <div class="monetization-panel">
            <form method="POST">
                <input type="hidden" name="update_monetization" value="1">
                <div class="monetization-header">
                    <h3><i class="fa-solid fa-coins" style="color:#ffd700; margin-right:10px;"></i> Monetization</h3>
                    <div class="toggle-container">
                        <span class="toggle-label">Make Premium</span>
                        <label class="switch">
                            <input type="checkbox" name="is_premium" id="premiumToggle" onclick="togglePriceField()" <?php echo $series['is_premium'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
                <p id="statusText" style="font-size:13px; color:<?php echo $series['is_premium'] ? '#ffd700' : '#2ecc71'; ?>;">
                    <?php echo $series['is_premium'] ? 'This content is <b>PREMIUM</b>.' : 'This content is currently <b>FREE</b>.'; ?>
                </p>

                <div id="priceSection" class="price-input-container <?php echo $series['is_premium'] ? 'active' : ''; ?>">
                    <label style="color:var(--text-muted); font-size:14px;">Set Price ($):</label>
                    <input type="number" step="0.01" name="price" class="input-field" value="<?php echo $series['price']; ?>" style="width: 100px;">
                </div>

                <div style="margin-top: 15px;">
                    <button type="submit" class="btn-save" style="padding:10px 25px;">Save Settings</button>
                </div>
            </form>
        </div>

        <div class="upload-box">
            <h3 style="margin-bottom: 15px; border-bottom: 1px solid #444; padding-bottom: 10px;">
                <i class="fa-solid fa-cloud-arrow-up"></i> Upload New Episode
            </h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="upload_episode" value="1">
                <div class="form-row">
                    <input type="number" name="season" placeholder="Season" class="input-dark" required style="flex:1;">
                    <input type="number" name="episode_num" placeholder="Episode" class="input-dark" required style="flex:1;">
                    <input type="text" name="duration" placeholder="Min" class="input-dark" style="flex:1;">
                </div>
                <div class="form-row"><input type="text" name="ep_title" placeholder="Episode Title" class="input-dark" required></div>
                <div class="form-row"><input type="file" name="video_file" accept="video/*, .mkv" class="input-dark" required style="flex:1;"></div>
                <button type="submit" class="btn-save" style="width:100%; background:#e50914;">Upload Episode</button>
            </form>
        </div>

        <h2>Episodes</h2>
        <div class="episode-list">
            <?php if ($result_eps->num_rows > 0): ?>
                <?php while ($ep = $result_eps->fetch_assoc()): ?>
                    <div class="episode-item">
                        <div>
                            <h4 style="margin:0;">S<?php echo $ep['season_number']; ?> E<?php echo $ep['episode_number']; ?> - <?php echo htmlspecialchars($ep['title']); ?></h4>
                            <small style="color:#888;">Duration: <?php echo $ep['duration_minutes']; ?> min</small>
                        </div>
                        <div style="display:flex; align-items:center;">
                            <button class="btn-play" onclick="playEpisode('<?php echo '../' . $ep['video_url']; ?>')" style="background:transparent; border:none; color:#2ecc71; cursor:pointer; font-size:18px; margin-right:15px;">
                                <i class="fa-solid fa-circle-play"></i>
                            </button>
                            <form method="POST" onsubmit="return confirm('Delete episode?');" style="margin:0;">
                                <input type="hidden" name="delete_episode" value="1">
                                <input type="hidden" name="episode_id" value="<?php echo $ep['episode_id']; ?>">
                                <button type="submit" class="btn-del-sm"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="color:#666;">No episodes uploaded yet.</p>
            <?php endif; ?>
        </div>

        <h2 style="display:flex; justify-content:space-between; align-items:center;">
            Cast (TMDB)
            <div class="api-tools">
                <input type="text" id="manualTmdbId" placeholder="TMDB ID" style="background:#222; border:1px solid #444; color:white; padding:5px; width:80px; font-size:12px;">
                <button onclick="fetchCast(true)" class="btn-small">Link</button>
            </div>
        </h2>
        <div id="sCast" class="cast-grid">
            <p style="color:#666; font-size:12px;">Searching...</p>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2>Edit Series</h2>
            <form method="POST">
                <input type="hidden" name="update_details" value="1">
                <div class="form-group"><label>Title</label><input type="text" name="title" class="form-input" value="<?php echo htmlspecialchars($series['title']); ?>" required></div>
                <div style="display:flex; gap:10px;">
                    <div class="form-group" style="flex:1;"><label>Genre</label><input type="text" name="genre" class="form-input" value="<?php echo htmlspecialchars($series['genre']); ?>"></div>
                    <div class="form-group" style="flex:1;"><label>Year</label><input type="number" name="release_year" class="form-input" value="<?php echo $series['release_year']; ?>"></div>
                </div>
                <div class="form-group"><label>Description</label><textarea name="description" rows="4" class="form-input"><?php echo htmlspecialchars($series['description']); ?></textarea></div>
                <button type="submit" class="btn-submit">Save Changes</button>
            </form>
        </div>
    </div>

    <footer class="footer">
        <div class="footer-container">
            <p class="copyright">© 1997-2026 MSP - Movie Streaming Platform, Inc.</p>
        </div>
    </footer>

    <script src="../assets/notification.js"></script>
    <script>
        function openEditModal() {
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        window.onclick = function(e) {
            if (e.target == document.getElementById('editModal')) closeEditModal();
        }

        function togglePriceField() {
            const checkBox = document.getElementById('premiumToggle');
            const priceDiv = document.getElementById('priceSection');
            const statusText = document.getElementById('statusText');
            if (checkBox.checked) {
                priceDiv.classList.add('active');
                statusText.innerHTML = "This content is <b>PREMIUM</b>. Users must pay to watch.";
                statusText.style.color = "#ffd700";
            } else {
                priceDiv.classList.remove('active');
                statusText.innerHTML = "This content is currently <b>FREE</b> for all users.";
                statusText.style.color = "#2ecc71";
            }
        }

        function playEpisode(url) {
            document.getElementById('videoModal').style.display = 'flex';
            const video = document.getElementById('localPlayer');
            video.src = url;
            video.style.display = 'block';
            video.play();
        }

        function closePlayer() {
            const modal = document.getElementById('videoModal');
            modal.style.display = 'none';
            const video = document.getElementById('localPlayer');
            video.pause();
            video.currentTime = 0;
            video.src = "";
        }
        const API_KEY = '96878691f0272aade53fca27ac2a739f';
        const IMG_POSTER = 'https://image.tmdb.org/t/p/w200';
        async function fetchCast(isManual = false) {
            const castContainer = document.getElementById('sCast');
            let tmdbId = null;
            if (isManual) {
                const manualId = document.getElementById('manualTmdbId').value;
                if (!manualId) {
                    alert("Enter TMDB ID");
                    return;
                }
                tmdbId = manualId;
            } else {
                const title = document.getElementById('phpSeriesTitle').value;
                const year = document.getElementById('phpSeriesYear').value;
                if (!title) return;
                try {
                    let res = await fetch(`https://api.themoviedb.org/3/search/tv?api_key=${API_KEY}&query=${encodeURIComponent(title)}&first_air_date_year=${year}`);
                    let data = await res.json();
                    if (!data.results || data.results.length === 0) {
                        res = await fetch(`https://api.themoviedb.org/3/search/tv?api_key=${API_KEY}&query=${encodeURIComponent(title)}`);
                        data = await res.json();
                    }
                    if (data.results && data.results.length > 0) tmdbId = data.results[0].id;
                    else {
                        castContainer.innerHTML = '<p style="color:#666;">Not found in API.</p>';
                        return;
                    }
                } catch (e) {}
            }
            if (!tmdbId) return;
            try {
                const resCast = await fetch(`https://api.themoviedb.org/3/tv/${tmdbId}/credits?api_key=${API_KEY}`);
                const castData = await resCast.json();
                castContainer.innerHTML = '';
                if (castData.cast) {
                    castData.cast.slice(0, 10).forEach(p => {
                        if (p.profile_path) {
                            castContainer.innerHTML += `<div class="cast-card"><img src="${IMG_POSTER + p.profile_path}" class="cast-img"><p style="font-size:12px; font-weight:bold; color:#ccc;">${p.name}</p></div>`;
                        }
                    });
                }
            } catch (e) {}
        }
        fetchCast(false);
    </script>
</body>

</html>