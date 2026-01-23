<?php
session_start();
require_once '../config/db_connect.php';

// 1. CHECK ID
if (!isset($_GET['id'])) {
    echo "No Movie ID specified.";
    exit();
}
$movie_id = intval($_GET['id']);

// 2. HANDLE DELETE MOVIE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_movie'])) {
    $sql_files = "SELECT video_url, poster_url FROM movies WHERE movie_id = ?";
    $stmt_files = $conn->prepare($sql_files);
    $stmt_files->bind_param("i", $movie_id);
    $stmt_files->execute();
    $files = $stmt_files->get_result()->fetch_assoc();

    if ($files) {
        if (file_exists("../" . $files['video_url'])) unlink("../" . $files['video_url']);
        if (file_exists("../" . $files['poster_url'])) unlink("../" . $files['poster_url']);
    }

    $sql_del = "DELETE FROM movies WHERE movie_id = ?";
    $stmt_del = $conn->prepare($sql_del);
    $stmt_del->bind_param("i", $movie_id);

    if ($stmt_del->execute()) {
        echo "<script>alert('Movie deleted successfully!'); window.location.href='Movies.php';</script>";
        exit();
    } else {
        echo "<script>alert('Error deleting movie: " . $conn->error . "');</script>";
    }
}

// 3. HANDLE MONETIZATION UPDATE
$msg = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_monetization'])) {
    $is_premium = isset($_POST['is_premium']) ? 1 : 0;
    $price = $is_premium ? floatval($_POST['price']) : 0.00;

    $sql = "UPDATE movies SET price = ?, is_premium = ? WHERE movie_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("dii", $price, $is_premium, $movie_id);

    if ($stmt->execute()) {
        $msg = "<script>alert('Monetization settings saved!');</script>";
    } else {
        $msg = "<script>alert('Error updating settings.');</script>";
    }
}

// 4. HANDLE DETAILS UPDATE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_details'])) {
    $title = $_POST['title'];
    $desc = $_POST['description'];
    $year = $_POST['release_year'];
    $genre = $_POST['genre'];
    $duration = $_POST['duration'];

    $sql = "UPDATE movies SET title = ?, description = ?, release_year = ?, genre = ?, duration_minutes = ? WHERE movie_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssisii", $title, $desc, $year, $genre, $duration, $movie_id);

    if ($stmt->execute()) {
        $msg = "<script>alert('Movie details updated!');</script>";
    } else {
        $msg = "<script>alert('Error updating details.');</script>";
    }
}

// 5. FETCH MOVIE DATA (Updated to get Uploader Name)
$sql = "SELECT m.*, u.username as uploader 
        FROM movies m 
        LEFT JOIN users u ON m.uploaded_by = u.user_id 
        WHERE m.movie_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $movie_id);
$stmt->execute();
$movie = $stmt->get_result()->fetch_assoc();

if (!$movie) die("Movie not found.");

$poster_url = !empty($movie['poster_url']) ? "../" . $movie['poster_url'] : '../assets/logo.png';
$video_file = !empty($movie['video_url']) ? "../" . $movie['video_url'] : '';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($movie['title']); ?> - Details</title>
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

        .btn-edit:hover {
            background: rgba(255, 255, 255, 0.2);
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

        .btn-delete:hover {
            background: #bb2d3b;
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
            <button class="video-close-btn" onclick="closePlayer()">
                <i class="fa-solid fa-xmark"></i> Close Player
            </button>
            <video id="localPlayer" controls style="width:100%; height:100%; display:none; background:black;">
                <source src="<?php echo $video_file; ?>" type="video/mp4">
            </video>
            <iframe id="trailerFrame" width="100%" height="100%" style="display:none; border:none;" allow="autoplay; encrypted-media" allowfullscreen></iframe>
        </div>
    </div>

    <nav class="navbar">
        <div class="nav-container">
            <a href="Dashboard.php" class="logo-btn hover-glow"><img src="../assets/logo.png" alt="Logo" class="logo-img"></a>
            <ul class="nav-links">
                <li><a href="Dashboard.php" class="hover-glow">Dashboard</a></li>
                <li><a href="Users.php" class="hover-glow">Users</a></li>
                <li><a href="Movies.php" class="hover-glow" style="color:#228EE5;">Movies</a></li>
                <li><a href="Series.php" class="hover-glow">Series</a></li>
                <li><a href="Wallet.html" class="hover-glow">Wallet</a></li>
            </ul>
            <div class="nav-icons"><a href="AdminProfile.html" class="icon-btn hover-glow"><i class="fa-solid fa-user"></i></a></div>
        </div>
    </nav>

    <div id="hero" class="hero-banner" style="background-image: linear-gradient(to bottom, rgba(0,0,0,0.3), #020b1f), url('<?php echo $poster_url; ?>'); background-size: cover; background-position: center;">
        <div class="hero-content">

            <input type="hidden" id="phpTitle" value="<?php echo htmlspecialchars($movie['title']); ?>">
            <input type="hidden" id="phpYear" value="<?php echo $movie['release_year']; ?>">

            <div style="display:flex; align-items:center;">
                <h1 id="mTitle" style="font-size: 48px; margin-bottom: 10px;"><?php echo htmlspecialchars($movie['title']); ?></h1>
                <button class="btn-edit" onclick="openEditModal()"><i class="fa-solid fa-pen"></i> Edit</button>
                <form method="POST" onsubmit="return confirm('⚠️ WARNING: Delete this movie?');" style="display:inline;">
                    <input type="hidden" name="delete_movie" value="1">
                    <button type="submit" class="btn-delete"><i class="fa-solid fa-trash"></i> Delete</button>
                </form>
            </div>

            <div style="display: flex; gap: 15px; font-size: 14px; color: #ccc; margin-bottom: 20px;">
                <span id="mYear"><?php echo $movie['release_year']; ?></span> |
                <span id="mDuration"><?php echo $movie['duration_minutes']; ?> min</span> |
                <span id="mGenre"><?php echo htmlspecialchars($movie['genre']); ?></span> |
                <span><i class="fa-solid fa-user"></i> Uploaded by: <b style="color:white;"><?php echo htmlspecialchars($movie['uploader'] ?? 'Unknown'); ?></b></span> |
                <span id="mRating" style="color:<?php echo $movie['is_premium'] ? '#ffd700' : '#2ecc71'; ?>">
                    <i class="fa-solid fa-star"></i> <?php echo $movie['is_premium'] ? 'Premium' : 'Free'; ?>
                </span>
            </div>

            <div style="display: flex; gap: 15px;">
                <?php if (!empty($movie['video_url'])): ?>
                    <button class="btn-watch" onclick="openLocalPlayer()"><i class="fa-solid fa-play"></i> Watch Movie</button>
                <?php else: ?>
                    <button class="btn-watch" style="opacity:0.5; cursor:not-allowed;">No Video File</button>
                <?php endif; ?>
                <button class="btn-save" style="background: rgba(255,255,255,0.2);" onclick="fetchTrailer()"><i class="fa-brands fa-youtube" style="margin-right:8px;"></i> Watch Trailer</button>
            </div>
        </div>
    </div>

    <div class="detail-container">
        <h2>About</h2>
        <p id="mOverview" style="color:var(--text-muted); line-height:1.6; margin-bottom: 30px;">
            <?php echo htmlspecialchars($movie['description']); ?>
        </p>

        <div class="monetization-panel">
            <form method="POST">
                <input type="hidden" name="update_monetization" value="1">
                <div class="monetization-header">
                    <h3><i class="fa-solid fa-coins" style="color:#ffd700; margin-right:10px;"></i> Monetization</h3>
                    <div class="toggle-container">
                        <span class="toggle-label">Make Premium</span>
                        <label class="switch">
                            <input type="checkbox" name="is_premium" id="premiumToggle" onclick="togglePriceField()" <?php echo $movie['is_premium'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>

                <p id="statusText" style="font-size:13px; color:<?php echo $movie['is_premium'] ? '#ffd700' : '#2ecc71'; ?>;">
                    <?php echo $movie['is_premium'] ? 'This content is <b>PREMIUM</b>. Users must pay to watch.' : 'This content is currently <b>FREE</b> for all users.'; ?>
                </p>

                <div id="priceSection" class="price-input-container <?php echo $movie['is_premium'] ? 'active' : ''; ?>">
                    <label style="color:var(--text-muted); font-size:14px;">Set Price ($):</label>
                    <input type="number" step="0.01" name="price" class="input-field" value="<?php echo $movie['price']; ?>" style="width: 100px;">
                </div>

                <div style="margin-top: 15px;">
                    <button type="submit" class="btn-save" style="padding:10px 25px;">Save Changes</button>
                </div>
            </form>
        </div>

        <h2 style="display:flex; justify-content:space-between; align-items:center;">
            Cast (From TMDB)
            <div class="api-tools">
                <input type="text" id="manualTmdbId" placeholder="TMDB ID (Optional)" style="background:#222; border:1px solid #444; color:white; padding:5px; width:120px; font-size:12px;">
                <button onclick="fetchApiData(true)" class="btn-small">Force Link</button>
            </div>
        </h2>
        <div id="mCast" class="cast-grid">
            <p style="color:#666; font-size:12px;">Searching API...</p>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2>Edit Movie Details</h2>
            <form method="POST">
                <input type="hidden" name="update_details" value="1">
                <div class="form-group"><label>Title</label><input type="text" name="title" class="form-input" value="<?php echo htmlspecialchars($movie['title']); ?>" required></div>
                <div style="display:flex; gap:10px;">
                    <div class="form-group" style="flex:1;"><label>Genre</label><input type="text" name="genre" class="form-input" value="<?php echo htmlspecialchars($movie['genre']); ?>"></div>
                    <div class="form-group" style="flex:1;"><label>Year</label><input type="number" name="release_year" class="form-input" value="<?php echo $movie['release_year']; ?>"></div>
                    <div class="form-group" style="flex:1;"><label>Duration (min)</label><input type="number" name="duration" class="form-input" value="<?php echo $movie['duration_minutes']; ?>"></div>
                </div>
                <div class="form-group"><label>Description</label><textarea name="description" rows="4" class="form-input"><?php echo htmlspecialchars($movie['description']); ?></textarea></div>
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

        function openEditModal() {
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        window.onclick = function(e) {
            if (e.target == document.getElementById('editModal')) closeEditModal();
        }

        function openLocalPlayer() {
            document.getElementById('videoModal').style.display = 'flex';
            document.getElementById('trailerFrame').style.display = 'none';
            document.getElementById('localPlayer').style.display = 'block';
            document.getElementById('localPlayer').play();
        }

        function closePlayer() {
            const modal = document.getElementById('videoModal');
            modal.style.display = 'none';
            const video = document.getElementById('localPlayer');
            video.pause();
            video.currentTime = 0;
            video.style.display = 'none';
            const iframe = document.getElementById('trailerFrame');
            iframe.src = '';
            iframe.style.display = 'none';
        }

        const API_KEY = '96878691f0272aade53fca27ac2a739f';
        const IMG_POSTER = 'https://image.tmdb.org/t/p/w200';
        let foundTmdbId = null;

        async function fetchApiData(isManual = false) {
            const castContainer = document.getElementById('mCast');
            let tmdbId = null;
            if (isManual) {
                const manualId = document.getElementById('manualTmdbId').value;
                if (!manualId) {
                    alert("Enter TMDB ID");
                    return;
                }
                tmdbId = manualId;
            } else {
                const title = document.getElementById('phpTitle').value;
                const year = document.getElementById('phpYear').value;
                if (!title) return;
                try {
                    let res = await fetch(`https://api.themoviedb.org/3/search/movie?api_key=${API_KEY}&query=${encodeURIComponent(title)}&primary_release_year=${year}`);
                    let data = await res.json();
                    if (!data.results || data.results.length === 0) {
                        res = await fetch(`https://api.themoviedb.org/3/search/movie?api_key=${API_KEY}&query=${encodeURIComponent(title)}`);
                        data = await res.json();
                    }
                    if (data.results && data.results.length > 0) tmdbId = data.results[0].id;
                    else {
                        castContainer.innerHTML = '<p style="color:#666;">Not found in API.</p>';
                        return;
                    }
                } catch (e) {
                    console.error(e);
                }
            }
            if (!tmdbId) return;
            foundTmdbId = tmdbId;
            try {
                const resCast = await fetch(`https://api.themoviedb.org/3/movie/${tmdbId}/credits?api_key=${API_KEY}`);
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

        async function fetchTrailer() {
            if (!foundTmdbId) {
                await fetchApiData();
                if (!foundTmdbId) {
                    alert("Could not find trailer in API.");
                    return;
                }
            }
            try {
                const res = await fetch(`https://api.themoviedb.org/3/movie/${foundTmdbId}/videos?api_key=${API_KEY}`);
                const data = await res.json();
                const trailer = data.results.find(v => v.type === 'Trailer' && v.site === 'YouTube');
                if (trailer) {
                    document.getElementById('videoModal').style.display = 'flex';
                    document.getElementById('localPlayer').style.display = 'none';
                    const iframe = document.getElementById('trailerFrame');
                    iframe.style.display = 'block';
                    iframe.src = `https://www.youtube.com/embed/${trailer.key}?autoplay=1`;
                } else {
                    alert("No YouTube trailer found for this movie.");
                }
            } catch (e) {
                alert("API Error");
            }
        }
        fetchApiData(false);
    </script>
</body>

</html>