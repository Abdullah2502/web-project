<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
require_once '../config/db_connect.php';

// --- CHECK AUTHENTICATION & ROLE ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'producer') {
    header("Location: ../auth/auth.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$username = htmlspecialchars($_SESSION['username'] ?? 'Producer');

// --- SET SAFE DEFAULTS ---
$company_name = 'Your Studio';
$verification_status = 'pending';
$current_balance = 0;
$total_movies = 0;
$total_series = 0;
$pending_approvals = 0;
$total_earnings = 0;
$monthly_earnings = 0;
$top_content_title = 'No data';

// --- SIMPLIFIED DATABASE QUERIES ---
try {
    // Producer info
    $sql = "SELECT company_name, verification_status FROM producers WHERE user_id = $user_id LIMIT 1";
    if ($result = $conn->query($sql)) {
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $company_name = htmlspecialchars($row['company_name']);
            $verification_status = htmlspecialchars($row['verification_status']);
        }
        $result->free();
    }
    
    // Wallet balance
    $sql = "SELECT COALESCE(balance, 0) as balance FROM wallets WHERE user_id = $user_id LIMIT 1";
    if ($result = $conn->query($sql)) {
        if ($result->num_rows > 0) {
            $current_balance = floatval($result->fetch_assoc()['balance']);
        }
        $result->free();
    }
    
    // Count movies
    $sql = "SELECT COUNT(*) as cnt FROM movies WHERE uploaded_by = $user_id";
    if ($result = $conn->query($sql)) {
        $total_movies = intval($result->fetch_assoc()['cnt']);
        $result->free();
    }
    
    // Count series
    $sql = "SELECT COUNT(*) as cnt FROM series WHERE uploaded_by = $user_id";
    if ($result = $conn->query($sql)) {
        $total_series = intval($result->fetch_assoc()['cnt']);
        $result->free();
    }
    
    // Count pending
    $sql = "SELECT (SELECT COUNT(*) FROM movies WHERE uploaded_by = $user_id AND approval_status = 'pending') +
            (SELECT COUNT(*) FROM series WHERE uploaded_by = $user_id AND approval_status = 'pending') as cnt";
    if ($result = $conn->query($sql)) {
        $pending_approvals = intval($result->fetch_assoc()['cnt']);
        $result->free();
    }
    
    // Total earnings
    $sql = "SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE user_id = $user_id AND status = 'success'";
    if ($result = $conn->query($sql)) {
        $total_earnings = floatval($result->fetch_assoc()['total']);
        $result->free();
    }
    
    // Monthly earnings
    $sql = "SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE user_id = $user_id AND status = 'success' AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())";
    if ($result = $conn->query($sql)) {
        $monthly_earnings = floatval($result->fetch_assoc()['total']);
        $result->free();
    }
    
} catch (Exception $e) {
    // Silently fail, use defaults
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Producer Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/Dashboard.css">
    <link rel="stylesheet" href="../assets/producerPage.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
</head>

<body>
    <div class="page">
      <!-- NAVBAR -->
      <nav class="navbar">
        <div class="nav-container">
          <a href="Dashboard.php" class="logo-btn hover-glow">
            <img src="../assets/logo.png" alt="Logo" class="logo-img" />
          </a>

          <ul class="nav-links">
            <li>
              <a href="Dashboard.php" class="hover-glow" style="color: #228ee5">Dashboard</a>
            </li>
            <li><a href="Movies.php" class="hover-glow">Movies</a></li>
            <li><a href="Series.php" class="hover-glow">Series</a></li>
            <li><a href="Forum.php" class="hover-glow">Forum</a></li>
            <li><a href="Chat.php" class="hover-glow">Chat</a></li>
          </ul>

          <div class="nav-icons">
            <button class="icon-btn hover-glow">
              <i class="fa-solid fa-magnifying-glass"></i>
            </button>
            <div class="notification-wrapper">
              <button class="icon-btn hover-glow" onclick="toggleNotifications()">
                <i class="fa-solid fa-bell"></i>
                <span style="position: absolute; top: 0; right: 0; width: 8px; height: 8px; background: red; border-radius: 50%;"></span>
              </button>

              <div id="notificationDropdown" class="notification-dropdown">
                <div class="notification-header">
                  <span>Notifications</span>
                  <span class="mark-read">Mark all as read</span>
                </div>
                <div class="notification-list">
                  <?php if ($verification_status === 'verified'): ?>
                    <div class="notif-item">
                      <div class="notif-content">
                        <h4>Account Verified</h4>
                        <p>Your producer account has been verified!</p>
                        <span class="notif-time">verified</span>
                      </div>
                    </div>
                  <?php elseif ($verification_status === 'pending'): ?>
                    <div class="notif-item">
                      <div class="notif-content">
                        <h4>Pending Verification</h4>
                        <p>Your account is awaiting admin verification.</p>
                        <span class="notif-time">pending</span>
                      </div>
                    </div>
                  <?php endif; ?>
                  
                  <?php if ($pending_approvals > 0): ?>
                    <div class="notif-item">
                      <div class="notif-content">
                        <h4>Content Pending</h4>
                        <p>You have <?php echo $pending_approvals; ?> content awaiting approval.</p>
                        <span class="notif-time">in review</span>
                      </div>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <a href="Profile.php" class="icon-btn hover-glow"><i class="fa-solid fa-user"></i></a>
            <a href="../actions/logout.php" class="icon-btn hover-glow" title="Logout" style="color: #e50914;">
              <i class="fa-solid fa-right-from-bracket"></i>
            </a>
            <button class="icon-btn hover-glow" onclick="toggleTheme()">
              <i class="fa-solid fa-sun"></i>
            </button>
          </div>
        </div>
      </nav>

      <div class="main-content">
        <!-- HERO -->
        <section class="hero">
          <div class="prod-hero-left">
            <h1>Welcome back<br /><span><?php echo htmlspecialchars($username); ?></span></h1>

            <div class="hero-buttons">
              <button class="btn primary" id="payFeeBtn" onclick="openModal('payFeeModal')">
                Pay Monthly Fee
              </button>
              <button class="btn primary" id="collectEarningsBtn" onclick="openModal('collectEarningsModal')">
                Collect Earnings
              </button>
            </div>
          </div>

          <div class="hero-right">
            <i class="fa fa-user-o" aria-hidden="true"></i>
          </div>
        </section>

        <!-- STATS -->
        <section class="stats">
          <div class="stat-card">
            <h4>Total Earning</h4>
            <p>Total <?php echo $total_movies + $total_series; ?> content</p>
            <span>$<?php echo number_format($total_earnings, 2); ?></span>
          </div>

          <div class="stat-card">
            <h4>This Month</h4>
            <p>Pending: <?php echo $pending_approvals; ?></p>
            <span>$<?php echo number_format($monthly_earnings, 2); ?></span>
          </div>

          <div class="stat-card">
            <h4>Most Earning</h4>
            <p><?php echo $top_content_title; ?></p>
            <span>$0.00</span>
          </div>
        </section>

        <!-- UPLOADED MOVIES -->
        <section class="movies">
          <div class="movies-header">
            <h2>Uploaded Movies</h2>
            <button class="btn primary small" onclick="window.location.href='Movies.php'">
              Upload New Movie
            </button>
          </div>

          <div class="movies-row">
            <div class="movie-card"><div class="plus">+</div></div>
            <div class="movie-card"><div class="plus">+</div></div>
            <div class="movie-card"><div class="plus">+</div></div>
            <div class="movie-card"><div class="plus">+</div></div>
            <div class="movie-card"><div class="plus">+</div></div>
            <div class="movie-card"><div class="plus">+</div></div>
          </div>
        </section>
      </div>
    </div>

    <!-- MODALS -->
    <div class="modal-overlay" id="payFeeModal">
      <div class="modal">
        <h2>Pay Monthly Fee</h2>
        <p>Your monthly producer subscription fee is <b>$49</b></p>

        <div class="modal-actions">
          <button class="btn primary" onclick="openPayModal()">
            Pay Now
          </button>
          <button class="btn secondary" onclick="closeModal('payFeeModal')">
            Cancel
          </button>
        </div>
      </div>
    </div>

    <div class="modal-overlay" id="collectEarningsModal">
      <div class="modal">
        <h2>Collect Earnings</h2>
        <p>Available balance: <b>$<?php echo number_format($current_balance, 2); ?></b></p>

        <div class="modal-actions">
          <button class="btn primary" onclick="openWithdrawModal()">
            Withdraw
          </button>
          <button class="btn secondary" onclick="closeModal('collectEarningsModal')">
            Cancel
          </button>
        </div>
      </div>
    </div>

    <div id="modal-withdraw" class="modal-overlay" onclick="closeModalOnOverlay(event, 'modal-withdraw')">
      <div class="modal-form-container">
        <div class="modal-header">
          <h3>Withdraw Funds</h3>
          <button class="close-icon-btn" onclick="closeModal('modal-withdraw')">&times;</button>
        </div>

        <label class="modal-label">Amount ($)</label>
        <input type="number" class="input-field" placeholder="Enter amount (e.g. 50.00)" />

        <label class="modal-label">Withdraw To</label>
        <select class="select-field">
          <option>Select Method</option>
          <option>Bank Account</option>
          <option>Visa Card</option>
        </select>

        <div style="margin-top: 15px; font-size: 12px; color: #888">
          Available Balance: <b style="color: var(--accent-color)">$<?php echo number_format($current_balance, 2); ?></b>
        </div>

        <button class="btn-save modal-btn-action" style="background-color: #00e676" onclick="dummyAction('Withdrawal')">
          Confirm Withdrawal
        </button>
      </div>
    </div>

    <div id="modal-add-card" class="modal-overlay" onclick="closeModalOnOverlay(event, 'modal-add-card')">
      <div class="modal-form-container">
        <div class="modal-header">
          <h3>Add New Card</h3>
          <button class="close-icon-btn" onclick="closeModal('modal-add-card')">&times;</button>
        </div>

        <label class="modal-label">Cardholder Name</label>
        <input type="text" class="input-field" placeholder="Name on card" />

        <label class="modal-label">Card Number</label>
        <input type="text" class="input-field" placeholder="0000 0000 0000 0000" />

        <div style="display: flex; gap: 15px">
          <div style="flex: 1">
            <label class="modal-label">Expiry Date</label>
            <input type="text" class="input-field" placeholder="MM/YY" />
          </div>
          <div style="flex: 1">
            <label class="modal-label">CVV</label>
            <input type="password" class="input-field" placeholder="123" />
          </div>
        </div>

        <button class="btn-save modal-btn-action" style="background-color: #ffea00; color: black" onclick="dummyAction('Card')">
          Save Card
        </button>
      </div>
    </div>

    <script>
      function toggleNotifications() {
        const dropdown = document.getElementById("notificationDropdown");
        dropdown.classList.toggle("active");
      }

      window.addEventListener("click", function (e) {
        const dropdown = document.getElementById("notificationDropdown");
        const wrapper = document.querySelector(".notification-wrapper");
        if (!wrapper.contains(e.target)) {
          dropdown.classList.remove("active");
        }
      });

      function openModal(id) {
        document.getElementById(id).classList.add("active");
      }

      function closeModal(id) {
        document.getElementById(id).classList.remove("active");
      }

      function closeModalOnOverlay(e, id) {
        if (e.target.id === id) {
          closeModal(id);
        }
      }

      window.addEventListener("click", (e) => {
        document.querySelectorAll(".modal-overlay").forEach((modal) => {
          if (e.target === modal) modal.classList.remove("active");
        });
      });

      function openWithdrawModal() {
        closeModal("collectEarningsModal");
        openModal("modal-withdraw");
      }
      
      function openPayModal() {
        closeModal("payFeeModal");
        openModal("modal-add-card");
      }

      function dummyAction(action) {
        alert(action + ' processing...');
      }

      function toggleTheme() {
        document.body.classList.toggle('light-mode');
        localStorage.setItem('theme', document.body.classList.contains('light-mode') ? 'light' : 'dark');
      }

      // Load theme preference
      if (localStorage.getItem('theme') === 'light') {
        document.body.classList.add('light-mode');
      }
    </script>

    <script src="../assets/theme.js"></script>
</body>
</html>
