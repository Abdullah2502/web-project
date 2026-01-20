<?php
session_start();
require_once '../config/db_connect.php';

// --- 1. GET SERIES ID ---
if (!isset($_GET['id'])) {
    echo "No series specified.";
    exit();
}
$series_id = intval($_GET['id']);

// --- 2. AUTH & MEMBERSHIP ---
$isLoggedIn = isset($_SESSION['user_id']);
$user_id = $isLoggedIn ? $_SESSION['user_id'] : 0;
$username = $isLoggedIn ? $_SESSION['username'] : 'Guest';
$membership = 'free';

if ($isLoggedIn) {
    $stmt = $conn->prepare("SELECT subscription_plan FROM viewers WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) $membership = $row['subscription_plan'];
    $stmt->close();
}

// --- 3. FETCH SERIES DETAILS ---
$sql = "SELECT * FROM series WHERE series_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $series_id);
$stmt->execute();
$series = $stmt->get_result()->fetch_assoc();

if (!$series) {
    echo "Series not found.";
    exit();
}

$seriesTitle = $series['title'];
$isPremiumContent = ($series['is_premium'] == 1);
$type = $isPremiumContent ? 'Premium' : 'Free';

// --- 4. HANDLE REVIEWS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if ($membership === 'premium') {
        $rating = intval($_POST['rating']);
        $comment = trim($_POST['comment']);
        $ins = $conn->prepare("INSERT INTO series_reviews (series_id, user_id, username, rating, comment) VALUES (?, ?, ?, ?, ?)");
        $ins->bind_param("iisis", $series_id, $user_id, $username, $rating, $comment);
        $ins->execute();
        header("Location: watchTrailer1.php?id=" . $series_id);
        exit();
    }
}

// Fetch Reviews
$reviews = [];
$totalStars = 0;
$revStmt = $conn->prepare("SELECT username, rating, comment FROM series_reviews WHERE series_id = ? ORDER BY created_at DESC");
$revStmt->bind_param("i", $series_id);
$revStmt->execute();
$revRes = $revStmt->get_result();
while ($row = $revRes->fetch_assoc()) {
    $reviews[] = $row;
    $totalStars += $row['rating'];
}
$avgRating = (count($reviews) > 0) ? round($totalStars / count($reviews), 1) : "N/A";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($seriesTitle); ?> - Details</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Reusing styles for consistency */
        :root {
            --bg-body: #020b1f;
            --bg-nav: rgba(2, 11, 31, 0.95);
            --bg-card: #1f2940;
            --text-main: white;
            --text-sub: #ccc;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
        }

        .navbar {
            padding: 15px 5%;
            background: var(--bg-nav);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px 5% 50px;
        }

        .back-link {
            color: #e50914;
            text-decoration: none;
            margin-bottom: 15px;
            display: inline-block;
            font-size: 14px;
        }

        .status-badge {
            padding: 5px 15px;
            border-radius: 4px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 15px;
            display: inline-block;
        }

        .badge-Free {
            background: #2ecc71;
            color: white;
        }

        .badge-Premium {
            background: #ffd700;
            color: black;
        }

        .player-section {
            width: 100%;
            height: 500px;
            background: #000;
            border-radius: 15px;
            overflow: hidden;
            margin-bottom: 30px;
        }

        iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        .actions {
            margin-bottom: 40px;
        }

        .btn-main {
            background: #e50914;
            color: white;
            padding: 14px 30px;
            border-radius: 6px;
            border: none;
            font-weight: bold;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 16px;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 40px;
        }

        .info-card h3 {
            color: #e50914;
        }

        .info-card p {
            color: #ccc;
            line-height: 1.6;
        }

        .meta-info {
            background: rgba(255, 255, 255, 0.03);
            padding: 20px;
            border-radius: 10px;
        }

        .person-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .person-item img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .comment-section {
            background: var(--bg-card);
            padding: 20px;
            border-radius: 12px;
            margin-top: 30px;
        }

        .comment-item {
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        textarea {
            width: 100%;
            height: 80px;
            background: #0b1326;
            border: 1px solid #333;
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }

        .rating-box i {
            color: #f1c40f;
            cursor: pointer;
        }
    </style>
</head>

<body>

    <nav class="navbar">
        <img src="../assets/logo.png" style="height:35px; cursor:pointer;" onclick="location.href='../index.php'">
    </nav>

    <div class="container">
        <a href="series.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Series</a>
        <div><span class="status-badge badge-<?php echo $type; ?>"><?php echo $type; ?></span></div>
        <h1><?php echo htmlspecialchars($seriesTitle); ?></h1>

        <div class="player-section" id="trailerContainer">
            <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:#666;">Loading Trailer...</div>
        </div>

        <div class="actions">
            <button class="btn-main" onclick="handleWatch()">
                <i class="fa-solid fa-play"></i> Watch Full Series
            </button>
        </div>

        <div class="content-grid">
            <div class="main-content">
                <div class="info-card">
                    <h3>Overview</h3>
                    <p id="seriesDesc"><?php echo htmlspecialchars($series['description']); ?></p>
                </div>

                <div class="comment-section">
                    <h3>Reviews (<?php echo $avgRating; ?> <i class="fa-solid fa-star" style="color:#f1c40f;"></i>)</h3>

                    <?php if ($membership === 'premium'): ?>
                        <form method="POST" id="reviewForm">
                            <div class="rating-box" id="starInput">
                                <span>Rate: </span>
                                <i class="fa-regular fa-star" data-val="1"></i>
                                <i class="fa-regular fa-star" data-val="2"></i>
                                <i class="fa-regular fa-star" data-val="3"></i>
                                <i class="fa-regular fa-star" data-val="4"></i>
                                <i class="fa-regular fa-star" data-val="5"></i>
                                <input type="hidden" name="rating" id="ratingValue" value="0">
                            </div>
                            <textarea name="comment" placeholder="Write a review..." required></textarea>
                            <button type="submit" name="submit_review" class="btn-main" style="padding: 8px 20px; font-size: 14px;">Post</button>
                        </form>
                    <?php else: ?>
                        <p style="color:#e50914; font-size:13px; margin-top:10px;">* Only Premium members can post reviews.</p>
                    <?php endif; ?>

                    <div class="comment-list" style="margin-top:20px;">
                        <?php foreach ($reviews as $rev): ?>
                            <div class="comment-item">
                                <b style="color:#e50914;"><?php echo htmlspecialchars($rev['username']); ?></b>
                                <span style="color:#f1c40f;">(<?php echo $rev['rating']; ?>/5)</span>
                                <p style="font-size:14px; margin-top:5px;"><?php echo htmlspecialchars($rev['comment']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="meta-info">
                <div class="meta-item"><span>Director/Creator</span>
                    <div id="seriesCreator">Loading...</div>
                </div>
                <div class="meta-item" style="margin-top:20px;"><span>Main Cast</span>
                    <div id="seriesCast">Loading...</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const apiKey = '96878691f0272aade53fca27ac2a739f';
        const title = <?php echo json_encode($seriesTitle); ?>;
        const seriesId = <?php echo $series_id; ?>;
        const isPremium = <?php echo json_encode($isPremiumContent); ?>;
        const userPlan = <?php echo json_encode($membership); ?>;
        const isLoggedIn = <?php echo json_encode($isLoggedIn); ?>;

        function handleWatch() {
            if (!isLoggedIn) {
                window.location.href = '../auth/auth.php';
                return;
            }
            if (isPremium && userPlan !== 'premium') {
                Swal.fire({
                    icon: 'lock',
                    title: 'Premium Only',
                    text: 'Upgrade your plan to watch this series.',
                    confirmButtonText: 'Upgrade',
                    confirmButtonColor: '#e50914'
                }).then((r) => {
                    if (r.isConfirmed) window.location.href = 'subscription.php';
                });
            } else {
                window.location.href = `watchSeries.php?id=${seriesId}`;
            }
        }

        // Fetch API Data
        fetch(`https://api.themoviedb.org/3/search/tv?api_key=${apiKey}&query=${encodeURIComponent(title)}`)
            .then(res => res.json())
            .then(data => {
                if (data.results.length > 0) {
                    const tmdbId = data.results[0].id;
                    return fetch(`https://api.themoviedb.org/3/tv/${tmdbId}?api_key=${apiKey}&append_to_response=credits,videos`);
                }
            })
            .then(res => res ? res.json() : null)
            .then(data => {
                if (data) {
                    // Trailer
                    const trailer = data.videos.results.find(v => v.type === 'Trailer' && v.site === 'YouTube');
                    if (trailer) {
                        document.getElementById('trailerContainer').innerHTML = `<iframe src="https://www.youtube.com/embed/${trailer.key}?autoplay=1&mute=1" allowfullscreen></iframe>`;
                    }
                    // Cast
                    const castDiv = document.getElementById('seriesCast');
                    castDiv.innerHTML = '';
                    data.credits.cast.slice(0, 3).forEach(p => {
                        const img = p.profile_path ? `https://image.tmdb.org/t/p/w200${p.profile_path}` : '../assets/user.png';
                        castDiv.innerHTML += `<div class="person-item"><img src="${img}"><b>${p.name}</b></div>`;
                    });
                    // Creator
                    if (data.created_by.length > 0) {
                        const c = data.created_by[0];
                        const img = c.profile_path ? `https://image.tmdb.org/t/p/w200${c.profile_path}` : '../assets/user.png';
                        document.getElementById('seriesCreator').innerHTML = `<div class="person-item"><img src="${img}"><b>${c.name}</b></div>`;
                    }
                }
            });

        // Stars Logic
        const stars = document.querySelectorAll('#starInput i');
        stars.forEach(star => {
            star.onclick = () => {
                const val = star.getAttribute('data-val');
                document.getElementById('ratingValue').value = val;
                stars.forEach(s => {
                    s.className = s.getAttribute('data-val') <= val ? 'fa-solid fa-star' : 'fa-regular fa-star';
                });
            }
        });
    </script>
</body>

</html>