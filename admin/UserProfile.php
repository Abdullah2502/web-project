<?php
session_start();
require_once '../config/db_connect.php';

// --- 1. ADMIN AUTH CHECK ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/auth.php");
    exit();
}

// --- 2. GET TARGET USER ID ---
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid User ID");
}
$target_user_id = intval($_GET['id']);

// --- 3. FETCH BASIC USER INFO ---
$sql_user = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql_user);
$stmt->bind_param("i", $target_user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();

if (!$user) {
    die("User not found.");
}

// --- 4. FETCH ROLE-SPECIFIC PROFILE ---
$profile_data = [];
$role = $user['role'];
$stats = [
    'uploads' => 0,
    'hours_watched' => 0,
    'active_since' => date("M Y", strtotime($user['created_at']))
];

if ($role === 'producer') {
    // Get Producer Details
    $p_sql = "SELECT * FROM producers WHERE user_id = ?";
    $stmt = $conn->prepare($p_sql);
    $stmt->bind_param("i", $target_user_id);
    $stmt->execute();
    $profile_data = $stmt->get_result()->fetch_assoc();

    // Calculate Uploads
    $up_sql = "SELECT 
        (SELECT COUNT(*) FROM movies WHERE uploaded_by = ?) + 
        (SELECT COUNT(*) FROM series WHERE uploaded_by = ?) as total_uploads";
    $stmt = $conn->prepare($up_sql);
    $stmt->bind_param("ii", $target_user_id, $target_user_id);
    $stmt->execute();
    $stats['uploads'] = $stmt->get_result()->fetch_assoc()['total_uploads'];
} elseif ($role === 'viewer') {
    // Get Viewer Details
    $v_sql = "SELECT * FROM viewers WHERE user_id = ?";
    $stmt = $conn->prepare($v_sql);
    $stmt->bind_param("i", $target_user_id);
    $stmt->execute();
    $profile_data = $stmt->get_result()->fetch_assoc();
}

// Calculate Total Watch Time (for everyone)
$w_sql = "SELECT SUM(watched_minutes) as total_mins FROM watch_history WHERE user_id = ?";
$stmt = $conn->prepare($w_sql);
$stmt->bind_param("i", $target_user_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stats['hours_watched'] = $row['total_mins'] ? round($row['total_mins'] / 60, 1) : 0;


// --- 5. FETCH WATCHING HABITS (Combined Movies & Series) ---
$habit_sql = "
    SELECT genre, COUNT(*) as count FROM (
        SELECT m.genre FROM watch_history wh 
        JOIN movies m ON wh.content_id = m.movie_id 
        WHERE wh.user_id = ? AND wh.content_type = 'movie'
        UNION ALL
        SELECT s.genre FROM watch_history wh 
        JOIN episodes e ON wh.content_id = e.episode_id 
        JOIN series s ON e.series_id = s.series_id 
        WHERE wh.user_id = ? AND wh.content_type = 'episode'
        UNION ALL
        SELECT s.genre FROM watch_history wh
        JOIN series s ON wh.content_id = s.series_id
        WHERE wh.user_id = ? AND wh.content_type = 'series'
    ) as combined_genres 
    GROUP BY genre 
    ORDER BY count DESC 
    LIMIT 4";

$stmt = $conn->prepare($habit_sql);
$stmt->bind_param("iii", $target_user_id, $target_user_id, $target_user_id);
$stmt->execute();
$habits_result = $stmt->get_result();

$habits = [];
$total_plays = 0;
while ($h = $habits_result->fetch_assoc()) {
    $habits[] = $h;
    $total_plays += $h['count'];
}

// --- 6. FETCH RECENTLY WATCHED (Robust Join for Movies, Series & Episodes) ---
$history_sql = "
    SELECT wh.*, 
           -- Get Title from Movies OR Series (via episode) OR Series (direct)
           COALESCE(m.title, s_via_ep.title, s_direct.title) as title,
           -- Get Poster
           COALESCE(m.poster_url, s_via_ep.poster_url, s_direct.poster_url) as poster,
           -- Get Genre
           COALESCE(m.genre, s_via_ep.genre, s_direct.genre) as genre,
           -- Get IDs for linking
           m.movie_id,
           COALESCE(s_via_ep.series_id, s_direct.series_id) as final_series_id
    FROM watch_history wh
    LEFT JOIN movies m ON wh.content_type = 'movie' AND wh.content_id = m.movie_id
    LEFT JOIN episodes e ON wh.content_type = 'episode' AND wh.content_id = e.episode_id
    LEFT JOIN series s_via_ep ON e.series_id = s_via_ep.series_id
    LEFT JOIN series s_direct ON wh.content_type = 'series' AND wh.content_id = s_direct.series_id
    WHERE wh.user_id = ?
    ORDER BY wh.last_watched_at DESC
    LIMIT 4";

$stmt = $conn->prepare($history_sql);
$stmt->bind_param("i", $target_user_id);
$stmt->execute();
$history_result = $stmt->get_result();

// --- HELPER: Avatar ---
$display_name = !empty($profile_data['full_name']) ? $profile_data['full_name'] : $user['username'];
if ($role === 'producer' && !empty($profile_data['company_name'])) $display_name = $profile_data['company_name'];
$avatar_url = "https://ui-avatars.com/api/?name=" . urlencode($display_name) . "&background=0D8ABC&color=fff&size=256";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - <?php echo htmlspecialchars($display_name); ?></title>
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
                <li><a href="Users.php" class="hover-glow" style="color:#228EE5;">Users</a></li>
                <li><a href="Movies.php" class="hover-glow">Movies</a></li>
                <li><a href="Series.php" class="hover-glow">Series</a></li>
                <li><a href="Wallet.php" class="hover-glow">Wallet</a></li>
                <li><a href="adminForum.php" class="hover-glow">Forum</a></li>
                <li><a href="chat.php" class="hover-glow">Chat</a></li>
            </ul>

            <div class="nav-icons">
                <div class="notification-wrapper">
                    <button class="icon-btn hover-glow"><i class="fa-solid fa-bell"></i></button>
                </div>
                <a href="AdminProfile.php" class="icon-btn hover-glow"><i class="fa-solid fa-user"></i></a>
                <a href="../actions/logout.php" class="icon-btn hover-glow" style="color: #e50914;"><i class="fa-solid fa-right-from-bracket"></i></a>
            </div>
        </div>
    </nav>

    <div class="main-content">

        <div class="profile-header-container">
            <img src="<?php echo $avatar_url; ?>" alt="Avatar" class="profile-avatar">
            <div class="profile-info">
                <h2><?php echo htmlspecialchars($display_name); ?></h2>
                <span class="profile-role-badge" style="text-transform: capitalize;"><?php echo htmlspecialchars($role); ?></span>

                <div class="profile-stats-row">
                    <?php if ($role === 'producer'): ?>
                        <span class="p-stat"><b><?php echo $stats['uploads']; ?></b> Uploads</span>
                    <?php endif; ?>

                    <span class="p-stat"><b><?php echo $stats['hours_watched']; ?></b> Hours Watched</span>
                    <span class="p-stat"><b>Active</b> Since <?php echo $stats['active_since']; ?></span>
                </div>
            </div>
        </div>

        <section class="section-container blue-border">
            <div class="section-tab">Account Details</div>

            <div class="panel-form" style="margin-top: 10px;">
                <h3 style="font-size: 16px; margin-bottom: 15px; color:#ccc;">Basic Information</h3>

                <div class="input-row">
                    <input type="text" class="input-field" value="<?php echo htmlspecialchars($user['username']); ?>" readonly style="background:#060b18; cursor: not-allowed;">
                    <input type="email" class="input-field" value="<?php echo htmlspecialchars($user['email']); ?>" readonly style="background:#060b18; cursor: not-allowed;">
                    <input type="text" class="input-field" value="ID: <?php echo $user['user_id']; ?>" readonly style="background:#060b18; cursor: not-allowed;">
                </div>

                <?php if ($role === 'producer'): ?>
                    <hr style="border: 0; border-top: 1px solid #1f2940; margin: 30px 0;">
                    <h3 style="font-size: 16px; margin-bottom: 15px; color:#ccc;">Company Information</h3>

                    <div class="input-row">
                        <input type="text" class="input-field" value="<?php echo htmlspecialchars($profile_data['company_name'] ?? 'N/A'); ?>" readonly style="background:#060b18;" placeholder="Company Name">
                        <input type="text" class="input-field" value="<?php echo htmlspecialchars($profile_data['license_number'] ?? 'N/A'); ?>" readonly style="background:#060b18;" placeholder="License Number">
                        <input type="text" class="input-field" value="<?php echo htmlspecialchars($profile_data['website'] ?? 'N/A'); ?>" readonly style="background:#060b18;" placeholder="Website">
                    </div>

                    <div style="margin-top: 20px; padding: 15px; background: rgba(34, 142, 229, 0.05); border: 1px solid #1f2940; border-radius: 8px;">
                        <h4 style="font-size: 14px; margin-bottom: 10px; color:#ccc;">Verification Document</h4>
                        <?php if (!empty($profile_data['document_path'])): ?>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <i class="fa-solid fa-file-contract" style="color: #228EE5; font-size: 24px;"></i>
                                <div>
                                    <p style="color: #fff; margin: 0 0 5px 0; font-size: 13px;">License/Registration File</p>
                                    <a href="../<?php echo htmlspecialchars($profile_data['document_path']); ?>" target="_blank" style="color: #228EE5; font-size: 13px; text-decoration: none; border-bottom: 1px dashed #228EE5;" class="hover-glow">
                                        View Document <i class="fa-solid fa-external-link-alt" style="font-size: 10px; margin-left: 3px;"></i>
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <p style="color: #777; font-size: 13px; margin: 0;"><i class="fa-solid fa-circle-exclamation"></i> No verification document uploaded.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($role === 'viewer'): ?>
                    <hr style="border: 0; border-top: 1px solid #1f2940; margin: 30px 0;">
                    <h3 style="font-size: 16px; margin-bottom: 15px; color:#ccc;">Subscription</h3>
                    <div class="input-row">
                        <input type="text" class="input-field" value="Plan: <?php echo ucfirst($profile_data['subscription_plan'] ?? 'Free'); ?>" readonly style="background:#060b18;">
                        <input type="text" class="input-field" value="Expiry: <?php echo $profile_data['subscription_expiry'] ?? 'Never'; ?>" readonly style="background:#060b18;">
                    </div>
                <?php endif; ?>

            </div>
        </section>

        <?php if (!empty($habits)): ?>
            <section class="section-container blue-border">
                <div class="section-tab">Watching Habits</div>
                <div class="habit-container">
                    <?php foreach ($habits as $h):
                        $percent = $total_plays > 0 ? round(($h['count'] / $total_plays) * 100) : 0;
                    ?>
                        <div class="habit-item">
                            <div class="habit-label"><?php echo htmlspecialchars($h['genre']); ?></div>
                            <div class="habit-bar-bg">
                                <div class="habit-bar-fill" style="width: <?php echo $percent; ?>%;"></div>
                            </div>
                            <div class="habit-value"><?php echo $percent; ?>%</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="section-container blue-border">
            <div class="section-tab">Recently Watched</div>

            <div class="movie-grid" style="margin-top: 20px;">
                <?php
                if ($history_result && $history_result->num_rows > 0) {
                    while ($h_row = $history_result->fetch_assoc()) {
                        // Poster Path
                        $poster = $h_row['poster'];
                        if (!filter_var($poster, FILTER_VALIDATE_URL)) {
                            $poster = '../' . $poster;
                        }

                        $timeAgo = humanTiming(strtotime($h_row['last_watched_at']));

                        // Determine Type and Link
                        $isMovie = ($h_row['content_type'] == 'movie');
                        $typeLabel = $isMovie ? "Movie" : "Series";
                        $link = "#";

                        if ($isMovie) {
                            $link = "../viewer/watchTrailer.php?id=" . $h_row['movie_id'];
                        } elseif ($h_row['final_series_id']) {
                            $link = "../viewer/watchTrailer1.php?id=" . $h_row['final_series_id'];
                        }
                ?>
                        <a href="<?php echo $link; ?>" class="movie-card" target="_blank">
                            <img src="<?php echo htmlspecialchars($poster); ?>" alt="Poster" class="movie-poster" onerror="this.src='../assets/logo.png'">
                            <div class="movie-overlay">
                                <h3 class="overlay-title"><?php echo htmlspecialchars($h_row['title']); ?></h3>
                                <p class="overlay-genre">
                                    <span style="background:#e50914; padding:2px 6px; border-radius:4px; font-size:10px; margin-right:5px;"><?php echo $typeLabel; ?></span>
                                    <?php echo htmlspecialchars($h_row['genre']); ?>
                                </p>
                                <p style="color:#228EE5; font-size:12px; margin-top:5px;"><?php echo $timeAgo; ?> ago</p>
                            </div>
                        </a>
                <?php
                    }
                } else {
                    echo "<p style='color:#777; padding:20px; width:100%; text-align:center;'>No watch history records found for this user.</p>";
                }
                ?>
            </div>
        </section>

    </div>

    <footer class="footer">
        <div class="footer-container">
            <p class="copyright">© 1997-2026 MSP - Movie Streaming Platform, Inc.</p>
        </div>
    </footer>

    <script src="../assets/theme.js"></script>
    <script src="../assets/notification.js"></script>

</body>

</html>

<?php
// Helper Function for "Time Ago"
function humanTiming($time)
{
    $time = time() - $time;
    $time = ($time < 1) ? 1 : $time;
    $tokens = array(
        31536000 => 'year',
        2592000 => 'month',
        604800 => 'week',
        86400 => 'day',
        3600 => 'hour',
        60 => 'minute',
        1 => 'second'
    );
    foreach ($tokens as $unit => $text) {
        if ($time < $unit) continue;
        $numberOfUnits = floor($time / $unit);
        return $numberOfUnits . ' ' . $text . (($numberOfUnits > 1) ? 's' : '');
    }
}
?>