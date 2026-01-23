<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'producer') {
    header("Location: ../auth/auth.php");
    exit;
}

$producer_id = $_SESSION['user_id'];

// Get producer info
$producer_query = "SELECT id, username, email, role FROM users WHERE id = $producer_id LIMIT 1";
$producer_result = mysqli_query($conn, $producer_query);
$producer_info = [];
if ($producer_result && $row = mysqli_fetch_assoc($producer_result)) {
    $producer_info = $row;
}

// Get producer stats
$uploads_query = "SELECT COUNT(*) as total FROM movies WHERE uploaded_by = $producer_id";
$uploads_result = mysqli_query($conn, $uploads_query);
$uploads_count = 0;
if ($uploads_result && $row = mysqli_fetch_assoc($uploads_result)) {
    $uploads_count = intval($row['total']);
}

// Get recently watched
$watched_query = "SELECT DISTINCT m.id, m.title, m.poster_url, m.genre FROM watch_history wh
                  JOIN movies m ON wh.movie_id = m.id
                  WHERE wh.user_id = $producer_id
                  ORDER BY wh.watch_date DESC
                  LIMIT 4";
$watched_result = mysqli_query($conn, $watched_query);
$recently_watched = [];
if ($watched_result) {
    while ($row = mysqli_fetch_assoc($watched_result)) {
        $recently_watched[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile</title>
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
                <li><a href="Series.php" class="hover-glow">Series</a></li>
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

                            <div class="notif-item">
                                <div class="notif-content">
                                    <h4>System Alert</h4>
                                    <p>High server load detected.</p>
                                    <span class="notif-time">3 hours ago</span>
                                </div>
                            </div>

                            <div class="notif-item">
                                <div class="notif-content">
                                    <h4>New Comment</h4>
                                    <p>New comment on <b>Interstellar</b>.</p>
                                    <span class="notif-time">5 hours ago</span>
                                </div>
                            </div>

                            <div class="notif-item">
                                <div class="notif-content">
                                    <h4>Payment Received</h4>
                                    <p>Subscription #9928 processed.</p>
                                    <span class="notif-time">1 day ago</span>
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

        <div class="profile-header-container">
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($producer_info['username'] ?? 'Producer'); ?>&background=0D8ABC&color=fff&size=256" alt="Avatar"
                class="profile-avatar">
            <div class="profile-info">
                <h2><?php echo htmlspecialchars($producer_info['username'] ?? 'Producer'); ?></h2>
                <span class="profile-role-badge">Producer</span>

                <div class="profile-stats-row">
                    <span class="p-stat"><b><?php echo intval($uploads_count); ?></b> Uploads</span>
                    <span class="p-stat"><b>128</b> Hours Watched</span>
                    <span class="p-stat"><b>Active</b> Since Nov 2024</span>
                </div>
            </div>
        </div>

        <section class="section-container blue-border">
            <div class="section-tab">Account Settings</div>

            <div class="panel-form" style="margin-top: 10px;">
                <h3 style="font-size: 16px; margin-bottom: 15px; color:#ccc;">Basic Information</h3>
                <div class="input-row">
                    <input type="text" class="input-field" value="<?php echo htmlspecialchars($producer_info['username'] ?? ''); ?>" readonly
                        style="background:#060b18; cursor: not-allowed;">
                    <input type="email" class="input-field" value="<?php echo htmlspecialchars($producer_info['email'] ?? ''); ?>" readonly
                        style="background:#060b18; cursor: not-allowed;">
                    <input type="text" class="input-field" value="ID: <?php echo intval($producer_id); ?>" readonly
                        style="background:#060b18; cursor: not-allowed;">
                </div>

                <hr style="border: 0; border-top: 1px solid #1f2940; margin: 30px 0;">

                <!-- <h3 style="font-size: 16px; margin-bottom: 15px; color:#ccc;">Change Password</h3>
                <div class="password-grid">
                    <input type="password" class="input-field" placeholder="Current Password">
                    <input type="password" class="input-field" placeholder="New Password">
                    <input type="password" class="input-field" placeholder="Confirm Password">
                </div>
                <button class="btn-save">Update Password</button> -->
            </div>
        </section>

        <section class="section-container blue-border">
            <div class="section-tab">Watching Habits</div>
            <div class="habit-container">
                <div class="habit-item">
                    <div class="habit-label">Action</div>
                    <div class="habit-bar-bg">
                        <div class="habit-bar-fill" style="width: 80%;"></div>
                    </div>
                    <div class="habit-value">80%</div>
                </div>
                <div class="habit-item">
                    <div class="habit-label">Sci-Fi</div>
                    <div class="habit-bar-bg">
                        <div class="habit-bar-fill" style="width: 65%;"></div>
                    </div>
                    <div class="habit-value">65%</div>
                </div>
                <div class="habit-item">
                    <div class="habit-label">Drama</div>
                    <div class="habit-bar-bg">
                        <div class="habit-bar-fill" style="width: 40%;"></div>
                    </div>
                    <div class="habit-value">40%</div>
                </div>
                <div class="habit-item">
                    <div class="habit-label">Comedy</div>
                    <div class="habit-bar-bg">
                        <div class="habit-bar-fill" style="width: 25%;"></div>
                    </div>
                    <div class="habit-value">25%</div>
                </div>
            </div>
        </section>

        <section class="section-container blue-border">
            <div class="section-tab">Recently Watched</div>

            <div class="movie-grid" style="margin-top: 20px;">
                <?php if (!empty($recently_watched)): ?>
                    <?php foreach ($recently_watched as $movie): ?>
                        <a href="MovieDetail.php?id=<?php echo intval($movie['id']); ?>" class="movie-card">
                            <img src="<?php echo htmlspecialchars($movie['poster_url']); ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>"
                                class="movie-poster">
                            <div class="movie-overlay">
                                <h3 class="overlay-title"><?php echo htmlspecialchars($movie['title']); ?></h3>
                                <p class="overlay-genre"><?php echo htmlspecialchars($movie['genre']); ?></p>
                                <p style="color:#228EE5; font-size:12px; margin-top:5px;">Recently Watched</p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #aaa;">No watch history yet.</p>
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

    <script src="../assets/theme.js"></script>
    <script src="../assets/notification.js"></script>

</body>

</html>
