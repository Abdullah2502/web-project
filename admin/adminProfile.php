<?php
session_start();
require_once '../config/db_connect.php';

// 1. SECURITY CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/auth.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = "";

// 2. HANDLE PASSWORD UPDATE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_password'])) {
    $current_pass = $_POST['current_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    if ($new_pass !== $confirm_pass) {
        $msg = "<script>alert('New passwords do not match!');</script>";
    } else {
        // Verify old password
        $sql = "SELECT password_hash FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res->fetch_assoc();

        if (password_verify($current_pass, $user['password_hash'])) {
            // Update to new password
            $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $upd = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            $upd->bind_param("si", $new_hash, $user_id);
            if ($upd->execute()) {
                $msg = "<script>alert('Password updated successfully!');</script>";
            } else {
                $msg = "<script>alert('Error updating password.');</script>";
            }
        } else {
            $msg = "<script>alert('Current password is incorrect.');</script>";
        }
    }
}

// 3. FETCH BASIC PROFILE DATA
$sql_user = "SELECT username, email, created_at FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql_user);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();

// 4. FETCH STATS
// A. Count Uploads (Movies + Series)
$sql_uploads = "SELECT 
    (SELECT COUNT(*) FROM movies WHERE uploaded_by = ?) + 
    (SELECT COUNT(*) FROM series WHERE uploaded_by = ?) as total_uploads";
$stmt_up = $conn->prepare($sql_uploads);
$stmt_up->bind_param("ii", $user_id, $user_id);
$stmt_up->execute();
$stats_uploads = $stmt_up->get_result()->fetch_assoc()['total_uploads'];

// B. Watch Time (Sum minutes / 60)
$sql_watch = "SELECT SUM(watched_minutes) as total_mins FROM watch_history WHERE user_id = ?";
$stmt_w = $conn->prepare($sql_watch);
$stmt_w->bind_param("i", $user_id);
$stmt_w->execute();
$total_mins = $stmt_w->get_result()->fetch_assoc()['total_mins'];
$hours_watched = round(($total_mins ?? 0) / 60, 1);

// 5. FETCH GENRE HABITS
$genres = [];
$sql_genres = "
    SELECT m.genre, COUNT(*) as count 
    FROM watch_history w
    JOIN movies m ON w.content_id = m.movie_id AND w.content_type = 'movie'
    WHERE w.user_id = ?
    GROUP BY m.genre
    UNION ALL
    SELECT s.genre, COUNT(*) as count 
    FROM watch_history w
    JOIN series s ON w.content_id = s.series_id AND w.content_type = 'series' -- fixed join on series_id
    WHERE w.user_id = ?
    GROUP BY s.genre
";
$stmt_g = $conn->prepare($sql_genres);
$stmt_g->bind_param("ii", $user_id, $user_id);
$stmt_g->execute();
$res_g = $stmt_g->get_result();

$total_genre_count = 0;
$genre_data = [];
while($row = $res_g->fetch_assoc()) {
    // Handle comma-separated genres if necessary, assuming single genre for simplicity or primary one
    $g_name = trim(explode(',', $row['genre'])[0]); 
    if(!isset($genre_data[$g_name])) $genre_data[$g_name] = 0;
    $genre_data[$g_name] += $row['count'];
    $total_genre_count += $row['count'];
}
arsort($genre_data); // Sort mostly watched first

// 6. FETCH RECENTLY WATCHED
$sql_recent = "
    SELECT 'Movie' as type, m.title, m.genre, m.poster_url, w.last_watched_at
    FROM watch_history w
    JOIN movies m ON w.content_id = m.movie_id
    WHERE w.user_id = ? AND w.content_type = 'movie'
    UNION
    SELECT 'Series' as type, s.title, s.genre, s.poster_url, w.last_watched_at
    FROM watch_history w
    JOIN series s ON w.content_id = s.series_id
    WHERE w.user_id = ? AND w.content_type = 'series' -- fixed join
    ORDER BY last_watched_at DESC LIMIT 4
";
$stmt_r = $conn->prepare($sql_recent);
$stmt_r->bind_param("ii", $user_id, $user_id);
$stmt_r->execute();
$recent_watched = $stmt_r->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile</title>
    <link rel="stylesheet" href="../assets/Dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="../assets/theme.js"></script>
</head>

<body>
    <?php echo $msg; ?>

    <nav class="navbar">
        <div class="nav-container">
            <a href="Dashboard.php" class="logo-btn hover-glow">
                <img src="../assets/logo.png" alt="Logo" class="logo-img">
            </a>
            <ul class="nav-links">
                <li><a href="Dashboard.php" class="hover-glow">Dashboard</a></li>
                <li><a href="Users.php" class="hover-glow">Users</a></li>
                <li><a href="Movies.php" class="hover-glow">Movies</a></li>
                <li><a href="Series.php" class="hover-glow">Series</a></li>
                <li><a href="Wallet.html" class="hover-glow">Wallet</a></li>
                <li><a href="adminForum.php" class="hover-glow">Forum</a></li>
                <li><a href="chat.html" class="hover-glow">Chat</a></li>
            </ul>
            <div class="nav-icons">
                <a href="adminProfile.php" class="icon-btn hover-glow" style="color:#e50914;"><i class="fa-solid fa-user"></i></a>
                <button class="icon-btn hover-glow"><i class="fa-solid fa-sun"></i></button>
            </div>
        </div>
    </nav>

    <div class="main-content">

        <div class="profile-header-container">
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($profile['username']); ?>&background=0D8ABC&color=fff&size=256" alt="Avatar" class="profile-avatar">
            <div class="profile-info">
                <h2><?php echo htmlspecialchars($profile['username']); ?></h2>
                <span class="profile-role-badge">Admin</span>

                <div class="profile-stats-row">
                    <span class="p-stat"><b><?php echo $stats_uploads; ?></b> Uploads</span>
                    <span class="p-stat"><b><?php echo $hours_watched; ?></b> Hours Watched</span>
                    <span class="p-stat"><b>Active</b> Since <?php echo date("M Y", strtotime($profile['created_at'])); ?></span>
                </div>
            </div>
        </div>

        <section class="section-container blue-border">
            <div class="section-tab">Account Settings</div>

            <div class="panel-form" style="margin-top: 10px;">
                <h3 style="font-size: 16px; margin-bottom: 15px; color:#ccc;">Basic Information</h3>
                <div class="input-row">
                    <input type="text" class="input-field" value="<?php echo htmlspecialchars($profile['username']); ?>" readonly style="background:#060b18; cursor: not-allowed;">
                    <input type="email" class="input-field" value="<?php echo htmlspecialchars($profile['email']); ?>" readonly style="background:#060b18; cursor: not-allowed;">
                    <input type="text" class="input-field" value="ID: <?php echo $user_id; ?>" readonly style="background:#060b18; cursor: not-allowed;">
                </div>

                <hr style="border: 0; border-top: 1px solid #1f2940; margin: 30px 0;">

                <h3 style="font-size: 16px; margin-bottom: 15px; color:#ccc;">Change Password</h3>
                <form method="POST">
                    <input type="hidden" name="update_password" value="1">
                    <div class="password-grid">
                        <input type="password" name="current_password" class="input-field" placeholder="Current Password" required>
                        <input type="password" name="new_password" class="input-field" placeholder="New Password" required>
                        <input type="password" name="confirm_password" class="input-field" placeholder="Confirm Password" required>
                    </div>
                    <button type="submit" class="btn-save">Update Password</button>
                </form>
            </div>
        </section>

        <section class="section-container blue-border">
            <div class="section-tab">Watching Habits</div>
            <div class="habit-container">
                <?php if($total_genre_count > 0): ?>
                    <?php 
                    $count = 0;
                    foreach($genre_data as $genre => $val): 
                        if($count >= 4) break; // Limit to top 4 genres
                        $percent = round(($val / $total_genre_count) * 100);
                        $count++;
                    ?>
                    <div class="habit-item">
                        <div class="habit-label"><?php echo htmlspecialchars($genre); ?></div>
                        <div class="habit-bar-bg">
                            <div class="habit-bar-fill" style="width: <?php echo $percent; ?>%;"></div>
                        </div>
                        <div class="habit-value"><?php echo $percent; ?>%</div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color:#666; padding:20px;">No watch history data available yet.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="section-container blue-border">
            <div class="section-tab">Recently Watched</div>

            <div class="movie-grid" style="margin-top: 20px;">
                <?php if($recent_watched->num_rows > 0): ?>
                    <?php while($item = $recent_watched->fetch_assoc()): ?>
                        <?php 
                            $poster = !empty($item['poster_url']) ? "../".$item['poster_url'] : '../assets/logo.png';
                            $dateStr = date("M d", strtotime($item['last_watched_at']));
                        ?>
                        <div class="movie-card">
                            <img src="<?php echo $poster; ?>" alt="Poster" class="movie-poster">
                            <div class="movie-overlay">
                                <h3 class="overlay-title"><?php echo htmlspecialchars($item['title']); ?></h3>
                                <p class="overlay-genre"><?php echo htmlspecialchars($item['genre']); ?></p>
                                <p style="color:#228EE5; font-size:12px; margin-top:5px;">Watched: <?php echo $dateStr; ?></p>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="color:#666; width:100%; padding:20px;">You haven't watched anything yet.</p>
                <?php endif; ?>
            </div>
        </section>

    </div>

    <footer class="footer">
        <div class="footer-container">
            <p class="copyright">© 1997-<?php echo date("Y"); ?> MSP - Movie Streaming Platform, Inc.</p>
        </div>
    </footer>
    <script src="../assets/notification.js"></script>
</body>
</html>