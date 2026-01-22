<?php
session_start();
require_once '../config/db_connect.php';

// --- 1. ADMIN AUTHENTICATION CHECK ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header("Location: ../auth/auth.php");
  exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// --- 2. HANDLE ACTIONS ---

// A. Delete Thread
if (isset($_GET['delete_id'])) {
  $del_id = intval($_GET['delete_id']);
  $stmt = $conn->prepare("DELETE FROM forum_threads WHERE thread_id = ?");
  $stmt->bind_param("i", $del_id);
  $stmt->execute();
  header("Location: adminForum.php");
  exit();
}

// B. Create New Thread
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_post'])) {
  $title = $conn->real_escape_string($_POST['title']);
  $content = $conn->real_escape_string($_POST['description']);

  if (!empty($title) && !empty($content)) {
    $stmt = $conn->prepare("INSERT INTO forum_threads (user_id, title, content, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iss", $user_id, $title, $content);
    $stmt->execute();
    header("Location: adminForum.php");
    exit();
  }
}

// C. Post Comment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_comment'])) {
  $thread_id = intval($_POST['thread_id']);
  $content = $conn->real_escape_string($_POST['comment_text']);

  if (!empty($content)) {
    $stmt = $conn->prepare("INSERT INTO forum_comments (thread_id, user_id, content, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iis", $thread_id, $user_id, $content);
    $stmt->execute();
    header("Location: adminForum.php");
    exit();
  }
}

// --- 3. FETCH DATA ---
$sql = "SELECT t.*, u.username, u.role 
        FROM forum_threads t 
        JOIN users u ON t.user_id = u.user_id 
        ORDER BY t.created_at DESC";
$threads = $conn->query($sql);

// Helper: Avatar Color
function getAvatarColor($name)
{
  $colors = ['#e50914', '#2ecc71', '#3498db', '#9b59b6', '#f1c40f', '#e67e22'];
  return $colors[hexdec(substr(md5($name), 0, 6)) % count($colors)];
}

// Helper: Time Ago (PHP 8.2+ Compatible)
function time_elapsed_string($datetime, $full = false)
{
  $now = new DateTime;
  $ago = new DateTime($datetime);
  $diff = $now->diff($ago);

  $weeks = floor($diff->d / 7);
  $days = $diff->d - ($weeks * 7);

  $string = array(
    'y' => array('val' => $diff->y, 'label' => 'year'),
    'm' => array('val' => $diff->m, 'label' => 'month'),
    'w' => array('val' => $weeks,   'label' => 'week'),
    'd' => array('val' => $days,    'label' => 'day'),
    'h' => array('val' => $diff->h, 'label' => 'hour'),
    'i' => array('val' => $diff->i, 'label' => 'minute'),
    's' => array('val' => $diff->s, 'label' => 'second'),
  );

  $ret = array();
  foreach ($string as $k => $v) {
    if ($v['val'] > 0) {
      $ret[] = $v['val'] . ' ' . $v['label'] . ($v['val'] > 1 ? 's' : '');
    }
  }

  if (!$full) $ret = array_slice($ret, 0, 1);
  return $ret ? implode(', ', $ret) . ' ago' : 'just now';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Community Forum - Admin</title>
  <link rel="stylesheet" href="../assets/Dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="../assets/theme.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>

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
        <li><a href="Wallet.php" class="hover-glow">Wallet</a></li>
        <li><a href="adminForum.php" class="hover-glow" style="color:#228EE5;">Forum</a></li>
        <li><a href="chat.php" class="hover-glow">Chat</a></li>
      </ul>

      <div class="nav-icons">
        <div class="search-container">
          <input type="text" id="navSearch" class="search-input-nav" placeholder="Type to search...">
          <button class="icon-btn hover-glow search-btn-nav" onclick="toggleSearch()">
            <i class="fa-solid fa-magnifying-glass"></i>
          </button>
        </div>

        <div class="notification-wrapper">
          <button class="icon-btn hover-glow"><i class="fa-solid fa-bell"></i></button>
        </div>

        <a href="AdminProfile.php" class="icon-btn hover-glow"><i class="fa-solid fa-user"></i></a>
        <a href="../actions/logout.php" class="icon-btn hover-glow" title="Logout" style="color: #e50914;">
          <i class="fa-solid fa-right-from-bracket"></i>
        </a>
        <button class="icon-btn hover-glow"><i class="fa-solid fa-sun"></i></button>
      </div>
    </div>
  </nav>

  <div class="forum-container">

    <div class="create-post">
      <h3>Admin Announcement / Discussion</h3>
      <form method="POST" action="adminForum.php">
        <input type="text" name="title" class="input-field" placeholder="Title: What's on your mind?" required>
        <textarea name="description" rows="3" class="input-field" placeholder="Description: Share your thoughts..." required></textarea>
        <button type="submit" name="submit_post" class="btn-save">Post Now</button>
      </form>
    </div>

    <div id="forumPostsContainer">
      <?php if ($threads && $threads->num_rows > 0): ?>
        <?php while ($row = $threads->fetch_assoc()):
          $initials = strtoupper(substr($row['username'], 0, 2));
          $avatarColor = getAvatarColor($row['username']);
          $t_id = $row['thread_id'];

          $c_res = $conn->query("SELECT COUNT(*) as cnt FROM forum_comments WHERE thread_id = $t_id");
          $comment_count = $c_res->fetch_assoc()['cnt'];
        ?>
          <div class="post-card">
            <div class="post-header">
              <div class="user-avatar" style="background-color: <?php echo $avatarColor; ?>;">
                <?php echo $initials; ?>
              </div>
              <div>
                <div class="post-info-name">
                  <?php echo htmlspecialchars($row['username']); ?>
                  <?php if ($row['role'] === 'admin') echo '<span class="admin-badge">(Admin)</span>'; ?>
                </div>
                <div class="post-info-time"><?php echo time_elapsed_string($row['created_at']); ?></div>
              </div>
            </div>

            <div class="post-title"><?php echo htmlspecialchars($row['title']); ?></div>
            <p class="post-content"><?php echo nl2br(htmlspecialchars($row['content'])); ?></p>

            <div class="post-actions">
              <span class="action-item" onclick="toggleCommentSection(this)">
                <i class="fa-regular fa-comment"></i> Comments (<?php echo $comment_count; ?>)
              </span>

              <span class="action-item delete-btn" onclick="confirmDelete(<?php echo $row['thread_id']; ?>)">
                <i class="fa-solid fa-trash"></i> Delete Thread
              </span>
            </div>

            <div class="comment-section">
              <form method="POST" action="adminForum.php" class="comment-form">
                <input type="hidden" name="thread_id" value="<?php echo $row['thread_id']; ?>">
                <input type="text" name="comment_text" class="input-field" placeholder="Write a comment..." required>
                <button type="submit" name="submit_comment" class="btn-save sm">Send</button>
              </form>

              <ul class="comments-list">
                <?php
                $sql_c = "SELECT c.*, u.username FROM forum_comments c JOIN users u ON c.user_id = u.user_id WHERE thread_id = $t_id ORDER BY c.created_at ASC";
                $comments = $conn->query($sql_c);
                if ($comments && $comments->num_rows > 0):
                  while ($c = $comments->fetch_assoc()):
                ?>
                    <li class="single-comment">
                      <span class="comment-author"><?php echo htmlspecialchars($c['username']); ?>:</span>
                      <?php echo htmlspecialchars($c['content']); ?>
                    </li>
                <?php endwhile;
                endif; ?>
              </ul>
            </div>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <p class="no-data">No forum posts found.</p>
      <?php endif; ?>
    </div>
  </div>

  <footer class="footer">
    <div class="footer-container">
      <p class="copyright">© 1997-2026 MSP - Movie Streaming Platform, Inc.</p>
    </div>
  </footer>

  <script>
    function toggleCommentSection(element) {
      const card = element.closest('.post-card');
      const section = card.querySelector('.comment-section');
      // Toggle logic using CSS classes is preferred, but simple JS display toggle is standard here
      if (section.style.display === 'none' || section.style.display === '') {
        section.style.display = 'block';
      } else {
        section.style.display = 'none';
      }
    }

    function confirmDelete(id) {
      Swal.fire({
        title: 'Delete Thread?',
        text: "You won't be able to revert this! All comments will be deleted too.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e50914',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
      }).then((result) => {
        if (result.isConfirmed) {
          window.location.href = `adminForum.php?delete_id=${id}`;
        }
      })
    }
  </script>

  <script src="../assets/notification.js"></script>

</body>

</html>