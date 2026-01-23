<?php
session_start();
require_once '../config/db_connect.php';

// --- 1. AUTHENTICATION & MEMBERSHIP CHECK ---
$isLoggedIn = isset($_SESSION['user_id']);
$user_id = $isLoggedIn ? $_SESSION['user_id'] : null;
$username = $isLoggedIn ? $_SESSION['username'] : 'Guest';
$membership = "free";

if ($isLoggedIn) {
    // 1. Get Membership Status (from viewers table)
    $stmt = $conn->prepare("SELECT subscription_plan FROM viewers WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $membership = $row['subscription_plan'];
        }
        $stmt->close();
    }
}

// --- 2. HANDLE NEW THREAD SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_post'])) {
    if ($isLoggedIn && $membership === 'premium') {
        $title = $conn->real_escape_string($_POST['title']);
        $content = $conn->real_escape_string($_POST['description']);

        // Insert into 'forum_threads' using user_id
        $stmt = $conn->prepare("INSERT INTO forum_threads (user_id, title, content) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user_id, $title, $content);
        $stmt->execute();
        $stmt->close();

        header("Location: forum.php");
        exit();
    }
}

// --- 3. HANDLE NEW COMMENT SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_comment'])) {
    if ($isLoggedIn) {
        $thread_id = intval($_POST['thread_id']); // Changed from post_id to thread_id
        $content = $conn->real_escape_string($_POST['comment_text']);

        // Insert into 'forum_comments' using user_id
        $stmt = $conn->prepare("INSERT INTO forum_comments (thread_id, user_id, content) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $thread_id, $user_id, $content);
        $stmt->execute();
        $stmt->close();

        header("Location: forum.php");
        exit();
    } else {
        header("Location: ../auth/auth.php");
        exit();
    }
}

// --- 4. FETCH THREADS (With Usernames) ---
// We JOIN 'users' table to get the username for each thread author
$sql = "SELECT t.*, u.username 
        FROM forum_threads t 
        JOIN users u ON t.user_id = u.user_id 
        ORDER BY t.created_at DESC";
$threads = $conn->query($sql);

// --- HELPER: Generate Avatar Color from Name ---
function getAvatarColor($name)
{
    $colors = ['#e50914', '#2ecc71', '#3498db', '#9b59b6', '#f1c40f', '#e67e22'];
    // Use hash to pick a consistent color for the same username
    $index = hexdec(substr(md5($name), 0, 6)) % count($colors);
    return $colors[$index];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forum - MSP Platform</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            if (savedTheme === 'light') {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>

    <style>
        /* --- STYLES (Identical to previous Design) --- */
        :root {
            --bg-body: #020b1f;
            --bg-card: #0b1326;
            --bg-input: #161d2f;
            --bg-navbar: linear-gradient(to bottom, rgba(2, 11, 31, 0.95), transparent);
            --text-main: white;
            --text-sub: #aaa;
            --border: #1f2940;
            --dropdown-bg: #0b1326;
        }

        [data-theme="light"] {
            --bg-body: #f0f2f5;
            --bg-card: #ffffff;
            --bg-input: #e4e6eb;
            --bg-navbar: rgba(255, 255, 255, 0.95);
            --text-main: #1c1e21;
            --text-sub: #65676b;
            --border: #ddd;
            --dropdown-bg: #ffffff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', sans-serif;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            overflow-x: hidden;
            transition: 0.3s;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        .navbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 5%;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            background: var(--bg-navbar);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 25px;
        }

        .nav-links a {
            color: var(--text-sub);
            font-weight: 500;
            font-size: 15px;
            transition: 0.3s;
        }

        .nav-links a.active {
            color: #e50914;
        }

        .nav-icons {
            display: flex;
            gap: 20px;
            font-size: 18px;
            align-items: center;
            color: #ccc;
        }

        .logo-img {
            height: 35px;
        }

        .search-container {
            display: flex;
            align-items: center;
            position: relative;
        }

        .search-input {
            width: 0;
            opacity: 0;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid #444;
            color: var(--text-main);
            padding: 5px 0;
            border-radius: 4px;
            outline: none;
            transition: all 0.4s ease;
            font-size: 14px;
        }

        .search-input.active {
            width: 180px;
            opacity: 1;
            padding: 5px 10px;
            margin-right: 10px;
        }

        .fa-magnifying-glass {
            cursor: pointer;
            transition: 0.3s;
        }

        #user-display-name {
            font-size: 14px;
            color: #e50914;
            font-weight: 600;
            cursor: pointer;
        }

        .user-container {
            position: relative;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .logout-dropdown {
            position: absolute;
            top: 40px;
            right: 0;
            background: var(--dropdown-bg);
            border: 1px solid var(--border);
            border-radius: 4px;
            display: none;
            z-index: 1001;
            overflow: hidden;
            min-width: 140px;
        }

        .logout-dropdown button,
        .logout-dropdown a {
            display: block;
            background: none;
            border: none;
            color: var(--text-main);
            padding: 10px 20px;
            cursor: pointer;
            width: 100%;
            text-align: left;
            font-size: 14px;
            transition: 0.3s;
            text-decoration: none;
        }

        .logout-dropdown button:hover,
        .logout-dropdown a:hover {
            background: #e50914;
            color: white;
        }

        .forum-container {
            padding: 120px 15% 50px;
            min-height: 80vh;
        }

        .filter-bar {
            display: flex;
            gap: 15px;
            margin-bottom: 40px;
        }

        .tab {
            padding: 8px 24px;
            border-radius: 20px;
            background: var(--border);
            cursor: pointer;
            font-size: 14px;
            transition: 0.3s;
            border: 1px solid transparent;
            color: var(--text-sub);
        }

        .tab.active,
        .tab:hover {
            background: #e50914;
            color: white;
            box-shadow: 0 0 15px rgba(229, 9, 20, 0.4);
        }

        .create-post {
            background: var(--bg-card);
            padding: 25px;
            border-radius: 12px;
            border: 1px solid var(--border);
            margin-bottom: 50px;
        }

        .create-post input,
        .create-post textarea {
            width: 100%;
            background: var(--bg-input);
            border: 1px solid var(--border);
            color: var(--text-main);
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            outline: none;
        }

        .btn-post {
            background: #e50914;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }

        .post-card {
            background: var(--bg-card);
            padding: 25px;
            border-radius: 12px;
            border: 1px solid var(--border);
            margin-bottom: 25px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
        }

        .post-title {
            font-size: 19px;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--text-main);
        }

        .post-content {
            color: var(--text-sub);
            font-size: 14px;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .post-actions {
            display: flex;
            gap: 25px;
            border-top: 1px solid var(--border);
            padding-top: 18px;
        }

        .action-item {
            cursor: pointer;
            color: #808080;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .comment-section {
            display: none;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }

        .single-comment {
            background: var(--bg-input);
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 8px;
            font-size: 13px;
            border-left: 2px solid #e50914;
            list-style: none;
            color: var(--text-main);
        }

        .single-comment span {
            font-weight: bold;
            color: #e50914;
            margin-right: 5px;
        }

        #theme-toggle {
            cursor: pointer;
        }

        /* SweetAlert */
        .swal2-popup {
            background-color: var(--bg-card) !important;
            color: var(--text-main) !important;
            border: 1px solid var(--border) !important;
        }

        .swal2-title {
            color: var(--text-main) !important;
        }

        .swal2-html-container {
            color: var(--text-sub) !important;
        }

        .swal2-confirm {
            background-color: #e50914 !important;
        }

        @media(max-width:768px) {
            .nav-links {
                display: none;
            }

            .forum-container {
                padding: 120px 5% 50px;
            }
        }

        .btn-signin {
            padding: 8px 16px;
            background-color: #e50914;
            color: white;
            border-radius: 4px;
            font-weight: 500;
            font-size: 14px;
        }

        .btn-signin:hover {
            background-color: #b20710;
        }
    </style>
</head>

<body>

    <nav class="navbar">
        <div style="font-size: 30px;"><img src="../assets/logo.png" class="logo-img" alt="MSP"></div>
        <ul class="nav-links">
            <li><a href="../index.php">Home</a></li>
            <li><a href="movie.php">Movies</a></li>
            <li><a href="series.php">Series</a></li>
            <li><a href="forum.php" class="active">Forum</a></li>
            <li><a href="subscription.php">Subscribe</a></li>
        </ul>
        <div class="nav-icons">
            <div class="search-container">
                <input type="text" class="search-input" placeholder="Search..." id="searchInput">
                <i class="fa-solid fa-magnifying-glass" id="searchIcon"></i>
            </div>
            <i class="fa-regular fa-bell"></i>

            <div class="user-container" id="userArea">
                <span id="user-display-name">
                    <?php if ($isLoggedIn): ?>
                        <span style="<?php echo ($membership === 'premium') ? 'color: #FFD700;' : ''; ?>">
                            <?php echo htmlspecialchars($username); ?>
                            <?php if ($membership === 'premium') echo ' <i class="fa-solid fa-crown" style="font-size: 10px; margin-left: 4px;"></i>'; ?>
                        </span>
                    <?php endif; ?>
                </span>

                <?php if ($isLoggedIn): ?>
                    <i class="fa-solid fa-circle-user"></i>
                    <div class="logout-dropdown" id="logoutDropdown">
                        <button onclick="window.location.href='userDashboard.php'">Dashboard</button>
                        <a href="../actions/logout.php">Log Out</a>
                    </div>
                <?php else: ?>
                    <a href="../auth/auth.php" class="btn-signin">Sign In</a>
                <?php endif; ?>
            </div>
            <i class="fa-solid fa-moon" id="theme-toggle"></i>
        </div>
    </nav>

    <main class="forum-container">
        <div class="create-post">
            <h3 style="margin-bottom: 15px; color: var(--text-main);">Start a Conversation
                <?php if ($membership !== 'premium'): ?>
                    <span id="premium-note" style="font-size: 12px; color: #f1c40f; font-weight: normal; margin-left: 10px;">(Premium Only)</span>
                <?php endif; ?>
            </h3>
            <form action="forum.php" method="POST" id="mainPostForm">
                <input type="text" name="title" id="postTitle" placeholder="What's on your mind?" required>
                <textarea name="description" id="postDesc" rows="3" placeholder="Write more details..." required></textarea>
                <button type="button" class="btn-post" onclick="handleNewPost()">Post Now</button>
                <input type="hidden" name="submit_post" value="1">
            </form>
        </div>

        <div class="filter-bar">
            <a href="forum.php" class="tab active">All Posts</a>
        </div>

        <div id="forumPostsContainer">
            <?php
            if ($threads && $threads->num_rows > 0):
                while ($row = $threads->fetch_assoc()):
                    // Prepare display variables
                    $authorName = htmlspecialchars($row['username']);
                    $initials = strtoupper(substr($authorName, 0, 2));
                    $avatarColor = getAvatarColor($authorName);
            ?>
                    <div class="post-card">
                        <div class="post-header" style="display:flex; gap:12px; align-items:center; margin-bottom:10px;">
                            <div class="user-avatar" style="background: <?= $avatarColor ?>"><?= $initials ?></div>
                            <div>
                                <div style="font-size: 14px; font-weight: 600; color: var(--text-main);"><?= $authorName ?></div>
                                <div style="font-size: 12px; color: #666;"><?= date("M d, Y", strtotime($row['created_at'])) ?></div>
                            </div>
                        </div>
                        <div class="post-title"><?= htmlspecialchars($row['title']) ?></div>
                        <p class="post-content"><?= nl2br(htmlspecialchars($row['content'])) ?></p>

                        <div class="post-actions">
                            <span class="action-item" onclick="toggleCommentSection(this)"><i class="fa-regular fa-comment"></i> Comment</span>
                        </div>

                        <div class="comment-section">
                            <form action="forum.php" method="POST" style="display:flex; gap:10px; margin-bottom:15px;">
                                <input type="hidden" name="thread_id" value="<?= $row['thread_id'] ?>">
                                <input type="text" name="comment_text" style="flex:1; background:var(--bg-input); border:1px solid var(--border); color:var(--text-main); padding:8px; border-radius:4px;" placeholder="Write a comment..." required>
                                <button type="submit" name="submit_comment" class="btn-post" style="padding: 5px 15px;">Post</button>
                            </form>
                            <ul class="comments-list">
                                <?php
                                $t_id = $row['thread_id'];
                                // Join users table to get comment author names
                                $sql_comments = "SELECT c.*, u.username 
                                             FROM forum_comments c 
                                             JOIN users u ON c.user_id = u.user_id 
                                             WHERE c.thread_id = $t_id 
                                             ORDER BY c.created_at ASC";
                                $comments = $conn->query($sql_comments);

                                if ($comments && $comments->num_rows > 0):
                                    while ($c = $comments->fetch_assoc()): ?>
                                        <li class="single-comment"><span><?= htmlspecialchars($c['username']) ?>:</span> <?= htmlspecialchars($c['content']) ?></li>
                                <?php endwhile;
                                endif; ?>
                            </ul>
                        </div>
                    </div>
            <?php
                endwhile;
            else:
                echo '<p style="text-align:center; color:var(--text-sub);">No discussions yet. Be the first to post!</p>';
            endif;
            ?>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Theme Logic
            const themeToggle = document.getElementById('theme-toggle');
            const htmlEl = document.documentElement;

            if (htmlEl.getAttribute('data-theme') === 'light') {
                themeToggle.classList.replace('fa-moon', 'fa-sun');
            }

            themeToggle.addEventListener('click', () => {
                if (htmlEl.getAttribute('data-theme') === 'light') {
                    htmlEl.removeAttribute('data-theme');
                    themeToggle.classList.replace('fa-sun', 'fa-moon');
                    localStorage.setItem('theme', 'dark');
                } else {
                    htmlEl.setAttribute('data-theme', 'light');
                    themeToggle.classList.replace('fa-moon', 'fa-sun');
                    localStorage.setItem('theme', 'light');
                }
            });

            // Dropdown Logic
            const userArea = document.getElementById('userArea');
            const logoutDropdown = document.getElementById('logoutDropdown');

            if (userArea && logoutDropdown) {
                userArea.addEventListener('click', function(e) {
                    e.stopPropagation();
                    logoutDropdown.style.display = (logoutDropdown.style.display === 'block') ? 'none' : 'block';
                });
                document.addEventListener('click', () => {
                    logoutDropdown.style.display = 'none';
                });
            }

            // Search Icon Logic
            const searchIcon = document.getElementById('searchIcon');
            if (searchIcon) {
                searchIcon.addEventListener('click', (e) => {
                    e.stopPropagation();
                    document.getElementById('searchInput').classList.toggle('active');
                });
            }
        });

        function handleNewPost() {
            const membership = "<?php echo $membership; ?>";
            const isLoggedIn = "<?php echo $isLoggedIn ? '1' : '0'; ?>" === "1";

            if (!isLoggedIn) {
                Swal.fire({
                    title: 'Access Denied',
                    text: 'Please login to join the conversation.',
                    icon: 'warning',
                    confirmButtonText: 'Login Now',
                    showCancelButton: true,
                    cancelButtonColor: '#444'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = '../auth/auth.php';
                    }
                });
                return;
            }

            if (membership !== "premium") {
                Swal.fire({
                    title: 'Premium Required',
                    text: 'Only Premium members can start new conversations. Upgrade now to unlock this feature!',
                    icon: 'info',
                    confirmButtonText: 'View Plans',
                    showCancelButton: true,
                    cancelButtonColor: '#444'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'subscription.php';
                    }
                });
                return;
            }

            // If everything is fine, submit form
            const title = document.getElementById('postTitle').value;
            const desc = document.getElementById('postDesc').value;

            if (title === "" || desc === "") {
                Swal.fire('Error', 'Please fill in both title and description.', 'error');
                return;
            }

            document.getElementById('mainPostForm').submit();
        }

        function toggleCommentSection(element) {
            const section = element.closest('.post-card').querySelector('.comment-section');
            section.style.display = section.style.display === 'block' ? 'none' : 'block';
        }
    </script>
</body>

</html>