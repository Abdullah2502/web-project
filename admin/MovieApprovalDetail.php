<?php
session_start();
require_once '../config/db_connect.php';

// 1. Check ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<script>alert('No Movie ID provided.'); window.location.href='Approvals.php';</script>";
    exit();
}

$movie_id = intval($_GET['id']);

// 2. Fetch Movie Details from Local DB
$sql = "SELECT m.*, u.username as uploader 
        FROM movies m 
        JOIN users u ON m.uploaded_by = u.user_id 
        WHERE m.movie_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $movie_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "Movie not found.";
    exit();
}

$movie = $result->fetch_assoc();

// 3. Handle Form Submission (Approve/Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action']; // 'approve' or 'reject'
    $status = ($action === 'approve') ? 'approved' : 'rejected';

    $update_stmt = $conn->prepare("UPDATE movies SET approval_status = ? WHERE movie_id = ?");
    $update_stmt->bind_param("si", $status, $movie_id);
    
    if ($update_stmt->execute()) {
        echo "<script>
            alert('Movie has been " . strtoupper($status) . " successfully.');
            window.location.href = 'Approvals.php';
        </script>";
        exit();
    } else {
        echo "<script>alert('Error updating status.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Movie: <?php echo htmlspecialchars($movie['title']); ?></title>
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
                <li><a href="Dashboard.php" class="hover-glow" style="color:#228EE5;">Dashboard</a></li>
                <li><a href="Users.php" class="hover-glow">Users</a></li>
                <li><a href="Movies.php" class="hover-glow">Movies</a></li>
                <li><a href="Series.php" class="hover-glow">Series</a></li>
                <li><a href="Approvals.php" class="hover-glow">Approvals</a></li>
            </ul>
            <div class="nav-icons">
                <a href="AdminProfile.php" class="icon-btn hover-glow"><i class="fa-solid fa-user"></i></a>
            </div>
        </div>
    </nav>

    <div id="hero" class="hero-section" style="background-image: linear-gradient(to bottom, rgba(13,27,61,0.3), #0d1b3d), url('<?php echo htmlspecialchars($movie['poster_url']); ?>');">
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <span class="status-badge-pending">Status: <?php echo ucfirst($movie['approval_status']); ?></span>

            <h1 class="movie-title-large"><?php echo htmlspecialchars($movie['title']); ?></h1>

            <div class="movie-meta">
                <span><?php echo $movie['release_year']; ?></span>
                <span><?php echo floor($movie['duration_minutes']/60) . 'h ' . ($movie['duration_minutes']%60) . 'm'; ?></span>
                <span><?php echo strtoupper($movie['genre']); ?></span>
            </div>
            
            <p style="margin-top: 10px; color: #aaa;">Uploaded by: <?php echo htmlspecialchars($movie['uploader']); ?></p>
        </div>
    </div>

    <div class="detail-container">

        <div class="detail-section">
            <h2 class="detail-heading">Description (From DB)</h2>
            <p class="movie-description">
                <?php echo nl2br(htmlspecialchars($movie['description'])); ?>
            </p>
        </div>

        <div class="detail-section">
            <h2 class="detail-heading">Cast</h2>
            <div id="castContainer" class="cast-scroller"></div>
        </div>

        <div class="admin-action-section">
            <h3 class="admin-action-title">Admin Actions</h3>
            <p style="color:#888; margin-bottom: 20px; font-size:14px;">
                Review the details above before making a decision.
            </p>

            <form method="POST" class="admin-btn-container">
                <button type="submit" name="action" value="approve" class="btn-approve-large">
                    <i class="fa-solid fa-check"></i> Approve Movie
                </button>
                <button type="submit" name="action" value="reject" class="btn-reject-large" onclick="return confirm('Are you sure you want to REJECT this movie?');">
                    <i class="fa-solid fa-xmark"></i> Reject Movie
                </button>
            </form>
        </div>

    </div>

    <script>
        const API_KEY = '96878691f0272aade53fca27ac2a739f';
        const IMG_BASE = 'https://image.tmdb.org/t/p/original';
        const IMG_POSTER = 'https://image.tmdb.org/t/p/w500';
        
        // Use PHP to inject the title for searching
        const movieTitle = "<?php echo addslashes($movie['title']); ?>";

        // Fetch extra visual data (Backdrop/Cast) from TMDB based on title
        async function fetchVisuals() {
            const searchUrl = `https://api.themoviedb.org/3/search/movie?api_key=${API_KEY}&query=${encodeURIComponent(movieTitle)}`;
            
            try {
                const res = await fetch(searchUrl);
                const data = await res.json();

                if (data.results && data.results.length > 0) {
                    const tmdbMovie = data.results[0];
                    
                    // Update Hero Background if TMDB has a better one
                    if (tmdbMovie.backdrop_path) {
                        document.getElementById('hero').style.backgroundImage = `linear-gradient(to bottom, rgba(13,27,61,0.3), #0d1b3d), url('${IMG_BASE + tmdbMovie.backdrop_path}')`;
                    }

                    // Fetch Cast
                    fetchCredits(tmdbMovie.id);
                } else {
                    document.getElementById('castContainer').innerHTML = "<p style='color:#666;'>No cast info found on TMDB.</p>";
                }
            } catch (err) { console.error(err); }
        }

        async function fetchCredits(tmdbId) {
            const url = `https://api.themoviedb.org/3/movie/${tmdbId}/credits?api_key=${API_KEY}`;
            try {
                const res = await fetch(url);
                const data = await res.json();
                const castDiv = document.getElementById('castContainer');
                
                if(data.cast){
                    data.cast.slice(0, 8).forEach(person => {
                        if (person.profile_path) {
                            const card = document.createElement('div');
                            card.classList.add('cast-card');
                            card.innerHTML = `
                                <img src="${IMG_POSTER + person.profile_path}" class="cast-img">
                                <div class="cast-name">${person.name}</div>
                            `;
                            castDiv.appendChild(card);
                        }
                    });
                }
            } catch (err) { console.error(err); }
        }

        // Run fetch
        fetchVisuals();
    </script>
    <script src="../assets/theme.js"></script>

</body>
</html>