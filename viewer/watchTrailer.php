<?php
session_start();
require_once '../config/db_connect.php';

// --- 1. AUTH & DATA FETCHING ---
$isLoggedIn = isset($_SESSION['user_id']);
$user_id = $isLoggedIn ? $_SESSION['user_id'] : 0;
$username = $isLoggedIn ? $_SESSION['username'] : "Guest";
$userMembership = "free";

// Fetch User Membership
if ($isLoggedIn) {
    $stmt = $conn->prepare("SELECT subscription_plan FROM viewers WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if($row = $res->fetch_assoc()){
        $userMembership = $row['subscription_plan'];
    }
    $stmt->close();
}

// Check Movie ID
if (!isset($_GET['id'])) {
    echo "No movie specified.";
    exit();
}
$movie_id = intval($_GET['id']);

// Fetch Movie Details
$sql = "SELECT * FROM movies WHERE movie_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $movie_id);
$stmt->execute();
$movie = $stmt->get_result()->fetch_assoc();

if (!$movie) {
    echo "Movie not found.";
    exit();
}

$movieTitle = $movie['title'];
$isContentPremium = ($movie['is_premium'] == 1);
$type = $isContentPremium ? 'Premium' : 'Free';

// --- 2. HANDLE REVIEWS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if ($userMembership === 'premium') {
        $rating = intval($_POST['rating']);
        $comment = trim($_POST['comment']);
        
        $ins = $conn->prepare("INSERT INTO reviews (movie_id, user_id, username, rating, comment) VALUES (?, ?, ?, ?, ?)");
        $ins->bind_param("iisis", $movie_id, $user_id, $username, $rating, $comment);
        $ins->execute();
        
        // Refresh to show comment
        header("Location: watchTrailer.php?id=" . $movie_id);
        exit();
    }
}

// Fetch Reviews
$reviews = [];
$totalStars = 0;
$revStmt = $conn->prepare("SELECT username, rating, comment FROM reviews WHERE movie_id = ? ORDER BY created_at DESC");
$revStmt->bind_param("i", $movie_id);
$revStmt->execute();
$revRes = $revStmt->get_result();
while($row = $revRes->fetch_assoc()) {
    $reviews[] = $row;
    $totalStars += $row['rating'];
}
$avgRating = (count($reviews) > 0) ? round($totalStars / count($reviews), 1) : "N/A";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($movieTitle); ?> - Trailer</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        /* --- THEME VARIABLES --- */
        :root {
            --bg-body: #020b1f;
            --bg-nav: rgba(2, 11, 31, 0.95);
            --bg-card: #1f2940;
            --bg-meta: rgba(255, 255, 255, 0.03);
            --text-main: white;
            --text-sub: #ccc;
            --border-color: rgba(255, 255, 255, 0.1);
            --input-bg: #020b1f;
        }

        [data-theme="light"] {
            --bg-body: #f0f2f5;
            --bg-nav: rgba(255, 255, 255, 0.95);
            --bg-card: #ffffff;
            --bg-meta: #eef0f3;
            --text-main: #1c1e21;
            --text-sub: #444;
            --border-color: #ddd;
            --input-bg: #ffffff;
        }

        * { margin:0; padding:0; box-sizing:border-box; font-family: 'Segoe UI', sans-serif;}
        body {background-color: var(--bg-body); color: var(--text-main); overflow-x:hidden; transition: background 0.3s, color 0.3s;}
        
        .navbar { display:flex; justify-content: space-between; align-items: center; padding:15px 5%; background: var(--bg-nav); border-bottom: 1px solid var(--border-color); }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px 5% 50px; }
        .back-link { color: #e50914; text-decoration: none; display: inline-block; margin-bottom: 15px; font-size: 14px; }
        #theme-toggle { cursor: pointer; font-size: 18px; transition: 0.3s; }
        #theme-toggle:hover { color: #e50914; }

        .status-badge { display: inline-block; padding: 5px 15px; border-radius: 4px; font-size: 12px; font-weight: bold; text-transform: uppercase; margin-bottom: 15px; }
        .badge-Free { background: #2ecc71; color: white; }
        .badge-Premium { background: #ffd700; color: #000; }

        h1 { font-size: 42px; margin-bottom: 20px; font-weight: 700; }

        /* Video / Trailer Area */
        .player-section { 
            width: 100%; height: 600px; margin-bottom: 35px; background: #000;
            border-radius: 15px; overflow: hidden;
            box-shadow: 0 20px 50px rgba(0,0,0,0.8);
            border: 1px solid var(--border-color);
            position: relative;
        }
        iframe, #mspPlayer { width: 100%; height: 100%; border:none; }

        .actions { display: flex; gap: 15px; margin-bottom: 40px; }
        .btn { padding: 14px 30px; border-radius: 6px; font-weight: bold; cursor: pointer; transition: 0.3s; border: none; display: flex; align-items: center; gap: 12px; font-size: 16px; }
        .btn-main { background: #e50914; color: white; }
        .btn-main:hover { background: #b20710; }
        .btn-secondary { background: rgba(255,255,255,0.1); color: var(--text-main); border: 1px solid var(--border-color); }

        .content-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 40px; margin-bottom: 60px; }
        .info-card h3 { color: #e50914; margin-bottom: 15px; font-size: 22px; text-transform: uppercase; }
        .info-card p { color: var(--text-sub); line-height: 1.8; margin-bottom: 25px; }

        .comment-section { background: var(--bg-card); padding: 30px; border-radius: 12px; border: 1px solid var(--border-color); }
        .rating-box { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; padding: 15px; background: rgba(0,0,0,0.1); border-radius: 8px; }
        .stars-input { color: #f1c40f; font-size: 24px; cursor: pointer; }
        .avg-num { font-size: 24px; font-weight: bold; color: #f1c40f; }
        textarea { width: 100%; height: 100px; background: var(--input-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-main); padding: 15px; margin: 15px 0; resize: none; }
        .comment-list { margin-top: 20px; max-height: 300px; overflow-y: auto; }
        .comment-item { padding: 12px 0; border-bottom: 1px solid var(--border-color); }
        .comment-item b { color: #e50914; font-size: 14px; }
        .comment-item p { color: var(--text-sub); }

        .suggestion-section { margin-top: 50px; border-top: 1px solid var(--border-color); padding-top: 40px; }
        .suggestion-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; }
        .suggest-card { background: var(--bg-card); border-radius: 10px; overflow: hidden; cursor: pointer; transition: 0.3s; border: 1px solid var(--border-color); }
        .suggest-card img { width: 100%; height: 280px; object-fit: cover; }
        .suggest-card-body { padding: 10px; text-align: center; font-weight: 600; color: var(--text-main); font-size: 14px; }

        .meta-info { display: flex; flex-direction: column; gap: 20px; background: var(--bg-meta); padding: 30px; border-radius: 12px; height: fit-content; border: 1px solid var(--border-color);}
        .meta-item span { display: block; color: #888; font-size: 13px; margin-bottom: 10px; }
        .person-item { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
        .person-item img { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid #e50914; background: #333; }
        .person-item b { font-size: 15px; color: var(--text-main); }

        .premium-lock-msg { background: rgba(229, 9, 20, 0.1); border: 1px dashed #e50914; padding: 15px; border-radius: 8px; text-align: center; color: var(--text-main); font-size: 14px; }
        
        /* SweetAlert overrides */
        .swal2-popup { background: #0b1326 !important; color: white !important; border: 1px solid rgba(255,255,255,0.1) !important; }
        .swal2-title, .swal2-html-container { color: white !important; }
        .swal2-confirm { background-color: #e50914 !important; }

        @media(max-width: 992px) { .content-grid { grid-template-columns: 1fr; } .player-section { height: 40vh; } }
    </style>
</head>
<body>

    <nav class="navbar">
        <img src="../assets/logo.png" style="height:35px; cursor:pointer;" alt="MSP" onclick="location.href='movie.php'">
        <i class="fa-solid fa-moon" id="theme-toggle"></i>
    </nav>

    <div class="container">
        <a href="movie.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Movies</a>
        
        <div>
            <span class="status-badge badge-<?php echo $type; ?>">
                <?php echo $type; ?>
            </span>
        </div>
        
        <h1 id="displayTitle"><?php echo htmlspecialchars($movieTitle); ?></h1>

        <div class="player-section" id="trailerContainer">
            <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:#ccc;">
                <i class="fa-solid fa-spinner fa-spin fa-2x"></i> &nbsp; Loading Trailer...
            </div>
        </div>

        <div class="actions">
            <button class="btn btn-main" onclick="handleWatchFull()">
                <i class="fa-solid fa-play"></i> Watch Full Movie
            </button>
            <button class="btn btn-secondary"><i class="fa-solid fa-share-nodes"></i> Share</button>
        </div>

        <div class="content-grid">
            <div class="main-content">
                <div class="info-card">
                    <h3>Description</h3>
                    <p id="movieDesc"><?php echo htmlspecialchars($movie['description']); ?></p>
                </div>

                <div class="comment-section">
                    <h3>Ratings & Community</h3>
                    <div class="rating-box">
                        <div><span>Rating</span><div class="avg-num"><?php echo $avgRating; ?></div></div>
                        
                        <?php if ($userMembership === 'premium'): ?>
                        <div style="margin-left: auto; text-align: right;">
                            <span>Rate:</span>
                            <div class="stars-input" id="starRatingInput">
                                <i class="fa-regular fa-star" data-val="1"></i>
                                <i class="fa-regular fa-star" data-val="2"></i>
                                <i class="fa-regular fa-star" data-val="3"></i>
                                <i class="fa-regular fa-star" data-val="4"></i>
                                <i class="fa-regular fa-star" data-val="5"></i>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($userMembership === 'premium'): ?>
                    <form method="POST" id="reviewForm">
                        <input type="hidden" name="rating" id="ratingValue" value="0">
                        <textarea name="comment" id="commentInput" placeholder="Add a public comment..." required></textarea>
                        <button type="submit" name="submit_review" class="btn btn-main" style="padding: 10px 20px; font-size: 14px;">Post Comment</button>
                    </form>
                    <?php else: ?>
                    <div class="premium-lock-msg">
                        <i class="fa-solid fa-crown" style="color: #f1c40f;"></i> 
                        Rating and commenting are available for <b>Premium Members</b> only.
                    </div>
                    <?php endif; ?>

                    <div class="comment-list">
                        <?php foreach ($reviews as $rev): ?>
                        <div class="comment-item">
                            <b><?php echo htmlspecialchars($rev['username']); ?> (<?php echo $rev['rating']; ?>★)</b>
                            <p><?php echo htmlspecialchars($rev['comment']); ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="meta-info">
                <div class="meta-item">
                    <span>Director</span>
                    <div id="movieDirector">Loading...</div>
                </div>
                <div class="meta-item">
                    <span>Main Cast</span>
                    <div id="movieCast">Loading...</div>
                </div>
                <div class="meta-item"><span>Duration</span><b><?php echo $movie['duration_minutes']; ?> min</b></div>
            </div>
        </div>

        <div class="suggestion-section">
            <h2 style="margin-bottom: 25px;">You Might Also Like</h2>
            <div class="suggestion-grid" id="suggestionGrid"></div>
        </div>
    </div>

    <script>
        const API_KEY = '96878691f0272aade53fca27ac2a739f'; 
        const IMG_POSTER = 'https://image.tmdb.org/t/p/w200';
        
        // PHP Data to JS
        const movieTitle = <?php echo json_encode($movieTitle); ?>;
        const movieId = <?php echo $movie_id; ?>;
        const isContentPremium = <?php echo json_encode($isContentPremium); ?>;
        const userPlan = <?php echo json_encode($userMembership); ?>;
        const isLoggedIn = <?php echo json_encode($isLoggedIn); ?>;

        // --- 1. HANDLE WATCH BUTTON CLICK ---
        function handleWatchFull() {
            if(!isLoggedIn) {
                window.location.href = '../auth/auth.php';
                return;
            }

            if (isContentPremium && userPlan !== 'premium') {
                Swal.fire({
                    icon: 'lock',
                    title: 'Subscription Needed',
                    text: 'This movie is for Premium members only.',
                    showCancelButton: true,
                    confirmButtonText: 'Upgrade',
                    cancelButtonText: 'Close',
                    confirmButtonColor: '#e50914'
                }).then((result) => {
                    if (result.isConfirmed) window.location.href = 'subscription.php';
                });
            } else {
                // Go to Watch Page with ID
                window.location.href = `watchMovie.php?id=${movieId}`;
            }
        }

        // --- 2. FETCH TRAILER & METADATA FROM API ---
        async function fetchApiData() {
            try {
                // Search movie by title
                const searchRes = await fetch(`https://api.themoviedb.org/3/search/movie?api_key=${API_KEY}&query=${encodeURIComponent(movieTitle)}`);
                const searchData = await searchRes.json();

                if(searchData.results && searchData.results.length > 0) {
                    const tmdbId = searchData.results[0].id;

                    // Get Details (Credits + Videos + Similar)
                    const detailRes = await fetch(`https://api.themoviedb.org/3/movie/${tmdbId}?api_key=${API_KEY}&append_to_response=credits,videos,similar`);
                    const data = await detailRes.json();

                    // A. Set Trailer
                    const trailer = data.videos.results.find(v => v.type === 'Trailer' && v.site === 'YouTube');
                    if(trailer) {
                        document.getElementById('trailerContainer').innerHTML = `
                            <iframe src="https://www.youtube.com/embed/${trailer.key}?autoplay=1&mute=1" allow="autoplay; encrypted-media" allowfullscreen></iframe>
                        `;
                    } else {
                        document.getElementById('trailerContainer').innerHTML = '<div style="display:flex;justify-content:center;align-items:center;height:100%;color:#666;">No Trailer Available</div>';
                    }

                    // B. Set Director
                    const director = data.credits.crew.find(p => p.job === 'Director');
                    if(director) {
                        const img = director.profile_path ? IMG_POSTER + director.profile_path : 'https://cdn-icons-png.flaticon.com/512/149/149071.png';
                        document.getElementById('movieDirector').innerHTML = `
                            <div class="person-item"><img src="${img}"><b>${director.name}</b></div>
                        `;
                    }

                    // C. Set Cast
                    const castContainer = document.getElementById('movieCast');
                    castContainer.innerHTML = '';
                    data.credits.cast.slice(0, 3).forEach(p => {
                        const img = p.profile_path ? IMG_POSTER + p.profile_path : 'https://cdn-icons-png.flaticon.com/512/149/149071.png';
                        castContainer.innerHTML += `<div class="person-item"><img src="${img}"><b>${p.name}</b></div>`;
                    });

                    // D. Set Suggestions
                    const suggestGrid = document.getElementById('suggestionGrid');
                    data.similar.results.slice(0, 4).forEach(m => {
                        if(m.poster_path) {
                            suggestGrid.innerHTML += `
                                <div class="suggest-card">
                                    <img src="https://image.tmdb.org/t/p/w400${m.poster_path}">
                                    <div class="suggest-card-body">${m.title}</div>
                                </div>
                            `;
                        }
                    });
                }
            } catch(e) {
                console.error("API Error", e);
            }
        }

        // --- 3. THEME LOGIC ---
        const themeToggle = document.getElementById('theme-toggle');
        const body = document.body;
        const applyTheme = (t) => {
            if (t === 'light') { body.setAttribute('data-theme', 'light'); themeToggle.classList.replace('fa-moon', 'fa-sun'); }
            else { body.removeAttribute('data-theme'); themeToggle.classList.replace('fa-sun', 'fa-moon'); }
        };
        themeToggle.addEventListener('click', () => {
            const nt = body.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
            localStorage.setItem('theme', nt); applyTheme(nt);
        });
        applyTheme(localStorage.getItem('theme'));

        // --- 4. RATING LOGIC ---
        if (userPlan === 'premium') {
            const starIcons = document.querySelectorAll('#starRatingInput i');
            starIcons.forEach(s => s.onclick = () => {
                const val = s.getAttribute('data-val');
                document.getElementById('ratingValue').value = val;
                starIcons.forEach(i => {
                    i.classList.replace('fa-solid', 'fa-regular');
                    if(i.getAttribute('data-val') <= val) i.classList.replace('fa-regular', 'fa-solid');
                });
            });
            document.getElementById('reviewForm').onsubmit = (e) => {
                if (document.getElementById('ratingValue').value == "0") {
                    e.preventDefault();
                    Swal.fire({ icon: 'warning', title: 'Wait!', text: 'Select a star rating first.', confirmButtonColor: '#e50914' });
                }
            };
        }

        // Init
        fetchApiData();
    </script>
</body>
</html>