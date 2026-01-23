<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'producer') {
    header("Location: ../auth/auth.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Unknown';

// Handle new thread post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_post'])) {
    $title = $conn->real_escape_string($_POST['title'] ?? '');
    $content = $conn->real_escape_string($_POST['description'] ?? '');
    
    if ($title && $content) {
        $stmt = $conn->prepare("INSERT INTO forum_threads (user_id, title, content) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("iss", $user_id, $title, $content);
            $stmt->execute();
            $stmt->close();
            header("Location: Forum.php");
            exit();
        }
    }
}

// Handle new comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_comment'])) {
    $thread_id = intval($_POST['thread_id'] ?? 0);
    $comment_text = $conn->real_escape_string($_POST['comment_text'] ?? '');
    
    if ($thread_id > 0 && $comment_text) {
        $stmt = $conn->prepare("INSERT INTO forum_comments (thread_id, user_id, content) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("iis", $thread_id, $user_id, $comment_text);
            $stmt->execute();
            $stmt->close();
            header("Location: Forum.php");
            exit();
        }
    }
}

// Get forum threads with proper JOIN
$threads_query = "SELECT t.thread_id, t.title, t.content, t.created_at, u.username 
                  FROM forum_threads t 
                  JOIN users u ON t.user_id = u.user_id 
                  ORDER BY t.created_at DESC";
$threads_result = $conn->query($threads_query);

// Fetch all threads and convert to array for rendering
$posts = [];
if ($threads_result && $threads_result->num_rows > 0) {
    while ($row = $threads_result->fetch_assoc()) {
        // Get comment count for this thread
        $comment_count_query = "SELECT COUNT(*) as count FROM forum_comments WHERE thread_id = " . $row['thread_id'];
        $comment_result = $conn->query($comment_count_query);
        $comment_count = $comment_result->fetch_assoc()['count'] ?? 0;
        
        // Get comments
        $comments = [];
        $comments_query = "SELECT c.*, u.username FROM forum_comments c 
                          JOIN users u ON c.user_id = u.user_id 
                          WHERE c.thread_id = " . $row['thread_id'] . " 
                          ORDER BY c.created_at ASC";
        $comments_result = $conn->query($comments_query);
        if ($comments_result) {
            while ($comment_row = $comments_result->fetch_assoc()) {
                $comments[] = [
                    'username' => htmlspecialchars($comment_row['username']),
                    'content' => htmlspecialchars($comment_row['content'])
                ];
            }
        }
        
        $posts[] = [
            'id' => intval($row['thread_id']),
            'user' => strtoupper(substr($row['username'], 0, 2)),
            'name' => htmlspecialchars($row['username']),
            'title' => htmlspecialchars($row['title']),
            'content' => htmlspecialchars($row['content']),
            'likes' => 0,
            'likedByMe' => false,
            'time' => $row['created_at'],
            'color' => getAvatarColor($row['username']),
            'comments' => $comments,
            'thread_id' => intval($row['thread_id'])
        ];
    }
}

function getAvatarColor($name)
{
    $colors = ['#e50914', '#2ecc71', '#3498db', '#9b59b6', '#f1c40f', '#e67e22'];
    $index = hexdec(substr(md5($name), 0, 6)) % count($colors);
    return $colors[$index];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Community Forum</title>
  <link rel="stylesheet" href="../assets/Dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="../assets/theme.js"></script>
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
        <li><a href="Forum.php" class="hover-glow" style="color:#228EE5;">Forum</a></li>
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
                  <h4>New Request</h4>
                  <p>Producer uploaded a new discussion.</p>
                  <span class="notif-time">Recently</span>
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

  <div class="forum-container">

    <div class="create-post">
      <h3 style="margin-bottom: 15px;">Start a Conversation</h3>
      <form method="POST" action="Forum.php">
        <input type="text" name="title" id="postTitle" placeholder="Title: What's on your mind?" required>
        <textarea name="description" id="postDesc" rows="3" placeholder="Description: Share your thoughts..." required></textarea>
        <button type="submit" name="submit_post" class="btn-post">Post Now</button>
      </form>
    </div>

    <div class="filter-bar">
      <div class="tab active" onclick="filterPosts('all', this)">All Posts</div>
      <div class="tab" onclick="filterPosts('recent', this)">Recent</div>
      <div class="tab" onclick="filterPosts('popular', this)">Most Liked</div>
    </div>

    <div id="forumPostsContainer"></div>
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

  <script>
    let posts = <?php echo json_encode($posts); ?>;

    document.addEventListener('DOMContentLoaded', function () {
      renderPosts(posts);
    });

    function renderPosts(data) {
      const container = document.getElementById('forumPostsContainer');
      container.innerHTML = '';

      if (data.length === 0) {
        container.innerHTML = '<p style="text-align:center; color: var(--text-sub); padding: 40px;">No discussions yet. Be the first to post!</p>';
        return;
      }

      data.forEach(post => {
        const commentHTML = post.comments.map(c => `
                <li class="single-comment">
                    <span>${c.username}:</span> ${c.content}
                </li>`).join('');

        const card = `
                <div class="post-card">
                    <div class="post-header">
                        <div class="user-avatar" style="background: ${post.color}">${post.user}</div>
                        <div>
                            <div class="post-info-name">${post.name}</div>
                            <div class="post-info-time">${new Date(post.time).toLocaleDateString()}</div>
                        </div>
                    </div>
                    <div class="post-title">${post.title}</div>
                    <p class="post-content">${post.content}</p>
                    <div class="post-actions">
                        <span class="action-item" onclick="toggleCommentSection(this)">
                            <i class="fa-regular fa-comment"></i> Comment (${post.comments.length})
                        </span>
                        <span class="action-item"><i class="fa-solid fa-share-nodes"></i> Share</span>
                    </div>
                    <div class="comment-section">
                        <div class="comment-input-area">
                            <form method="POST" action="Forum.php" style="display: flex; gap: 10px;">
                                <input type="hidden" name="thread_id" value="${post.thread_id}">
                                <input type="text" name="comment_text" placeholder="Write a comment..." required style="flex: 1;">
                                <button type="submit" name="submit_comment" class="btn-post" style="padding: 8px 15px;">Post</button>
                            </form>
                        </div>
                        <ul class="comments-list">${commentHTML}</ul>
                    </div>
                </div>
            `;
        container.innerHTML += card;
      });

      // Add comment section toggle listeners
      document.querySelectorAll('.post-card').forEach(card => {
        const commentBtn = card.querySelector('.post-actions .action-item');
        if (commentBtn) {
          commentBtn.addEventListener('click', function() {
            toggleCommentSection(this);
          });
        }
      });
    }

    function toggleCommentSection(element) {
      const section = element.closest('.post-card').querySelector('.comment-section');
      section.style.display = section.style.display === 'block' ? 'none' : 'block';
    }

    function filterPosts(type, btn) {
      document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
      btn.classList.add('active');
      let filtered = [...posts];
      if (type === 'recent') filtered.sort((a, b) => new Date(b.time) - new Date(a.time));
      else if (type === 'popular') filtered.sort((a, b) => b.likes - a.likes);
      renderPosts(filtered);
    }
  </script>

  <script src="../assets/notification.js"></script>
</body>

</html>
