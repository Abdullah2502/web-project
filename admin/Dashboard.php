<?php
// Start Session
session_start();

// Include Database Connection
require_once '../config/db_connect.php';

// ---------------------------------------------------
// 1. DATA FETCHING
// ---------------------------------------------------

// 1.1 Total Users
$sql_users = "SELECT COUNT(*) as total_users FROM users";
$res_users = $conn->query($sql_users);
$total_users = $res_users->fetch_assoc()['total_users'];

// 1.2 Total Movies & Series
$sql_content = "SELECT 
    (SELECT COUNT(*) FROM movies) + (SELECT COUNT(*) FROM series) as total_content";
$res_content = $conn->query($sql_content);
$total_content = $res_content->fetch_assoc()['total_content'];

// 1.3 Pending Approvals
$sql_pending_users = "SELECT COUNT(*) as count FROM producers WHERE verification_status = 'pending'";
$pending_users = $conn->query($sql_pending_users)->fetch_assoc()['count'];

$sql_pending_content = "SELECT 
    (SELECT COUNT(*) FROM movies WHERE approval_status = 'pending') + 
    (SELECT COUNT(*) FROM series WHERE approval_status = 'pending') as count";
$pending_content = $conn->query($sql_pending_content)->fetch_assoc()['count'];

// 1.4 Recent Activity Logs
$sql_activity = "
    SELECT u.username, 'Joined the platform' as activity, u.created_at 
    FROM users u
    UNION ALL
    SELECT u.username, CONCAT('Uploaded movie: ', m.title), m.created_at 
    FROM movies m JOIN users u ON m.uploaded_by = u.user_id
    UNION ALL
    SELECT u.username, CONCAT('Uploaded series: ', s.title), s.created_at 
    FROM series s JOIN users u ON s.uploaded_by = u.user_id
    UNION ALL
    SELECT u.username, 'Transaction/Purchase', t.created_at 
    FROM transactions t JOIN users u ON t.user_id = u.user_id
    ORDER BY created_at DESC 
    LIMIT 6";
$result_activity = $conn->query($sql_activity);

// 1.5 Revenue Report (Bar Chart)
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

$sql_revenue = "SELECT MONTH(created_at) as month, SUM(amount) as revenue 
                FROM transactions 
                WHERE YEAR(created_at) = ? AND (type = 'subscription_pay' OR type = 'movie_purchase') AND status = 'success'
                GROUP BY month";
$stmt = $conn->prepare($sql_revenue);
$stmt->bind_param("i", $selected_year);
$stmt->execute();
$res_revenue = $stmt->get_result();

$monthly_revenue = array_fill(1, 12, 0); 
$max_revenue = 100;

while ($row = $res_revenue->fetch_assoc()) {
    $monthly_revenue[$row['month']] = (float)$row['revenue'];
}
$max_val = max($monthly_revenue);
if($max_val > 100) $max_revenue = $max_val;

$points = "";
for ($m = 1; $m <= 12; $m++) {
    $x = ($m - 1) * 10;
    $height_ratio = $monthly_revenue[$m] / $max_revenue;
    $y = 60 - ($height_ratio * 60);
    $points .= "$x,$y ";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
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
                <li><a href="Dashboard.php" class="hover-glow" style="color:#228EE5;">Dashboard</a></li>
                <li><a href="Users.php" class="hover-glow">Users</a></li>
                <li><a href="Movies.php" class="hover-glow">Movies</a></li>
                <li><a href="Series.php" class="hover-glow">Series</a></li>
                <li><a href="Wallet.php" class="hover-glow">Wallet</a></li>
                <li><a href="adminForum.php" class="hover-glow">Forum</a></li>
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
                    <button class="icon-btn hover-glow" onclick="toggleNotifications()">
                        <i class="fa-solid fa-bell"></i>
                    </button>
                </div>
                
                <a href="AdminProfile.php" class="icon-btn hover-glow" title="Profile">
                    <i class="fa-solid fa-user"></i>
                </a>
                
                <a href="../actions/logout.php" class="icon-btn hover-glow" title="Logout" style="color: #e50914;">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </a>

                <button class="icon-btn hover-glow"><i class="fa-solid fa-sun"></i></button>
            </div>
        </div>
    </nav>

    <div class="main-content">
        <h1 class="welcome-text">Welcome Admin,</h1>

        <section class="stats-container">
            <a href="Users.php" class="stat-card hover-glow-card">
                <h3>Total Users</h3>
                <p><?php echo number_format($total_users); ?></p>
            </a>

            <div class="stat-card hover-glow-card">
                <h3>Total Movies & Series</h3>
                <p><?php echo number_format($total_content); ?></p>
            </div>

            <div class="stat-card hover-glow-card">
                <div class="stat-split-row">
                    <a href="Approvals.php#pending-users" class="stat-split-item">
                        <h3>Pending Users</h3>
                        <p><?php echo $pending_users; ?></p>
                    </a>

                    <div class="stat-divider"></div>

                    <a href="Approvals.php#pending-movies" class="stat-split-item">
                        <h3>Pending Content</h3>
                        <p><?php echo $pending_content; ?></p>
                    </a>
                </div>
            </div>
        </section>

        <section class="section-container">
            <div class="section-tab">Activity Logs</div>
            <div class="table-wrapper">
                <table class="activity-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Activity</th>
                            <th>Date/Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result_activity->num_rows > 0): ?>
                            <?php while($row = $result_activity->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                                    <td><?php echo htmlspecialchars($row['activity']); ?></td>
                                    <td><?php echo date("M d, Y H:i", strtotime($row['created_at'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="3">No recent activity found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="section-container">
            <div class="section-tab">Financial Reports (<?php echo $selected_year; ?>)</div>
            
            <div style="position: absolute; top: 15px; right: 20px; display: flex; gap: 10px;">
                <form method="GET" action="Dashboard.php" style="display:flex; align-items:center;">
                    <select name="year" onchange="this.form.submit()" style="padding: 5px; border-radius: 4px; border: 1px solid #444; background: #222; color: #fff;">
                        <?php 
                        $current_year = date('Y');
                        for($y = $current_year; $y >= $current_year - 4; $y--) {
                            $selected = ($y == $selected_year) ? 'selected' : '';
                            echo "<option value='$y' $selected>$y</option>";
                        }
                        ?>
                    </select>
                </form>

                <a href="report_pdf.php?year=<?php echo $selected_year; ?>" target="_blank" class="btn-save" style="text-decoration: none; padding: 8px 15px; font-size: 13px; margin: 0; display: inline-flex; align-items: center; gap: 8px; color: white; background-color: #e50914; border-radius: 4px;">
                    <i class="fa-solid fa-file-pdf"></i> Generate PDF
                </a>
            </div>

            <div class="chart-container">
                <div class="y-axis">
                    <span><?php echo number_format($max_revenue); ?></span>
                    <span><?php echo number_format($max_revenue * 0.8); ?></span>
                    <span><?php echo number_format($max_revenue * 0.6); ?></span>
                    <span><?php echo number_format($max_revenue * 0.4); ?></span>
                    <span><?php echo number_format($max_revenue * 0.2); ?></span>
                    <span>0</span>
                </div>
                <div class="chart-area">
                    <div class="grid-line"></div>
                    <div class="grid-line"></div>
                    <div class="grid-line"></div>
                    <div class="grid-line"></div>
                    <div class="grid-line"></div>
                    <div class="grid-line"></div>

                    <svg viewBox="0 0 110 60" preserveAspectRatio="none" class="chart-line">
                        <polyline fill="none" stroke="#b64696" stroke-width="2" points="<?php echo $points; ?>" />
                    </svg>
                </div>
            </div>
            <div class="x-axis">
                <span>Jan</span><span>Feb</span><span>Mar</span><span>Apr</span><span>May</span>
                <span>Jun</span><span>Jul</span><span>Aug</span><span>Sep</span><span>Oct</span><span>Nov</span><span>Dec</span>
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