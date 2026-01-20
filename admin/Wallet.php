<?php
session_start();
require_once '../config/db_connect.php';

// --- 1. AUTHENTICATION CHECK ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/auth.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";
$msgType = "";

// --- 2. CALCULATE REAL-TIME BALANCE FROM TRANSACTIONS ---
// We calculate the balance by: (Total Earnings) - (Total Withdrawals)
// Earnings = 'subscription_pay' + 'movie_purchase'
// Withdrawals = 'producer_payout' + 'producer_fee'

$balance_sql = "SELECT 
    SUM(CASE WHEN type IN ('subscription_pay', 'movie_purchase') THEN amount ELSE 0 END) as total_income,
    SUM(CASE WHEN type IN ('producer_payout', 'producer_fee') THEN amount ELSE 0 END) as total_expense
    FROM transactions";

$bal_result = $conn->query($balance_sql);
$bal_row = $bal_result->fetch_assoc();

// Real-time calculated balance
$calculated_balance = floatval($bal_row['total_income']) - floatval($bal_row['total_expense']);

// --- 3. HANDLE WITHDRAWAL SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'withdraw') {
    $amount = floatval($_POST['amount']);
    $method = $_POST['method'];

    if ($amount <= 0) {
        $message = "Invalid amount.";
        $msgType = "error";
    } elseif ($calculated_balance < $amount) {
        // Validate against the real calculated balance
        $message = "Insufficient funds. Available: $" . number_format($calculated_balance, 2);
        $msgType = "error";
    } else {
        // Start Transaction
        $conn->begin_transaction();
        try {
            // A. Log Transaction (This effectively reduces the balance for the next calculation)
            $log = $conn->prepare("INSERT INTO transactions (user_id, amount, type, status, description, created_at) VALUES (?, ?, 'producer_payout', 'success', ?, NOW())");
            $desc = "Withdrawal to " . $method;
            $log->bind_param("ids", $user_id, $amount, $desc);
            $log->execute();
            $log->close();

            // B. (Optional) Sync 'wallets' table if you still use it for other lookups
            $update = $conn->prepare("UPDATE wallets SET balance = balance - ? WHERE user_id = ?");
            $update->bind_param("di", $amount, $user_id);
            $update->execute();
            $update->close();

            $conn->commit();

            // Recalculate balance immediately for display
            $calculated_balance -= $amount;

            $message = "Withdrawal of $" . number_format($amount, 2) . " successful!";
            $msgType = "success";
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Transaction failed: " . $e->getMessage();
            $msgType = "error";
        }
    }
}

// --- 4. FETCH TRANSACTIONS LIST ---
// Join users table to show who paid or who withdrew
$sql_trans = "SELECT t.*, u.username 
              FROM transactions t 
              JOIN users u ON t.user_id = u.user_id 
              ORDER BY t.created_at DESC LIMIT 50";
$transactions = $conn->query($sql_trans);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wallet - Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/Dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="../assets/theme.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .text-success {
            color: #2ecc71;
            font-weight: bold;
        }

        .text-danger {
            color: #e74c3c;
            font-weight: bold;
        }
    </style>
</head>

<body>

    <?php if ($message): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: '<?php echo $msgType; ?>',
                    title: '<?php echo ucfirst($msgType); ?>',
                    text: '<?php echo $message; ?>',
                    confirmButtonColor: '#e50914'
                });
            });
        </script>
    <?php endif; ?>

    <div id="modal-withdraw" class="modal-overlay" onclick="closeModalOnOverlay(event, 'modal-withdraw')">
        <div class="modal-form-container">
            <div class="modal-header">
                <h3>Withdraw Funds</h3>
                <button class="close-icon-btn" onclick="closeModal('modal-withdraw')">&times;</button>
            </div>

            <form method="POST" action="Wallet.php">
                <input type="hidden" name="action" value="withdraw">

                <label class="modal-label">Amount ($)</label>
                <input type="number" name="amount" step="0.01" class="input-field" placeholder="Enter amount" required>

                <label class="modal-label">Withdraw To</label>
                <select name="method" class="select-field" required>
                    <option value="">Select Method</option>
                    <option value="Bank Account">Bank Account (**** 8821)</option>
                    <option value="Visa Card">Visa Card (**** 4242)</option>
                </select>

                <div style="margin-top: 15px; font-size: 12px; color: #888;">
                    Available Balance: <b style="color:var(--accent-color)">$<?php echo number_format($calculated_balance, 2); ?></b>
                </div>

                <button type="submit" class="btn-save modal-btn-action" style="background-color: #00e676;">Confirm Withdrawal</button>
            </form>
        </div>
    </div>

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
                <li><a href="Wallet.php" class="hover-glow" style="color:#228EE5;">Wallet</a></li>
                <li><a href="adminForum.php" class="hover-glow">Forum</a></li>
                <li><a href="chat.php" class="hover-glow">Chat</a></li>
            </ul>

            <div class="nav-icons">
                <div class="notification-wrapper">
                    <button class="icon-btn hover-glow" onclick="toggleNotifications()">
                        <i class="fa-solid fa-bell"></i>
                    </button>
                </div>
                <a href="AdminProfile.php" class="icon-btn hover-glow"><i class="fa-solid fa-user"></i></a>
                <button class="icon-btn hover-glow"><i class="fa-solid fa-sun"></i></button>
                <a href="../actions/logout.php" class="icon-btn hover-glow" style="color: #e50914;"><i class="fa-solid fa-right-from-bracket"></i></a>
            </div>
        </div>
    </nav>

    <div class="main-content">

        <section class="wallet-hero-card">
            <h2 class="wallet-label">Total Balance:</h2>
            <div class="wallet-amount">$<?php echo number_format($calculated_balance, 2); ?></div>

            <div class="wallet-actions">
                <div class="w-action-item" onclick="openModal('modal-withdraw')">
                    <div class="w-icon-box bg-green"><i class="fa-solid fa-arrow-down"></i></div>
                    <span class="w-label">Withdraw</span>
                </div>
                <div class="w-action-item" onclick="dummyAction('Card Added')">
                    <div class="w-icon-box bg-yellow"><i class="fa-solid fa-credit-card"></i></div>
                    <span class="w-label">Add Card</span>
                </div>
                <div class="w-action-item" onclick="dummyAction('Bank Linked')">
                    <div class="w-icon-box bg-cyan"><i class="fa-solid fa-building-columns"></i></div>
                    <span class="w-label">Add Bank Account</span>
                </div>
            </div>
        </section>

        <section class="section-container blue-border">
            <div class="section-tab">Recent Transactions</div>
            <div class="table-wrapper">
                <table class="activity-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Type</th>
                            <th>User</th>
                            <th>Date</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($transactions && $transactions->num_rows > 0) {
                            while ($row = $transactions->fetch_assoc()) {
                                // Map Types
                                $typeMap = [
                                    'subscription_pay' => 'Subscription',
                                    'movie_purchase' => 'Movie Purchase',
                                    'producer_payout' => 'Payout',
                                    'producer_fee' => 'Service Fee'
                                ];
                                $displayType = isset($typeMap[$row['type']]) ? $typeMap[$row['type']] : ucwords(str_replace('_', ' ', $row['type']));

                                // Logic: Red for money OUT, Green for money IN
                                $isExpense = in_array($row['type'], ['producer_payout', 'producer_fee']);
                                $amountClass = $isExpense ? 'text-danger' : 'text-success';
                                $sign = $isExpense ? '-' : '+';

                                $dateDisplay = date("Y-m-d H:i", strtotime($row['created_at']));
                        ?>
                                <tr>
                                    <td>#TXN-<?php echo $row['transaction_id']; ?></td>
                                    <td><?php echo $displayType; ?></td>
                                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                                    <td><?php echo $dateDisplay; ?></td>
                                    <td class="<?php echo $amountClass; ?>">
                                        <?php echo $sign . '$' . number_format($row['amount'], 2); ?>
                                    </td>
                                </tr>
                        <?php
                            }
                        } else {
                            echo "<tr><td colspan='5' style='text-align:center; padding:20px;'>No transactions found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </section>

    </div>

    <footer class="footer">
        <div class="footer-container">
            <p class="copyright">© 1997-2026 MSP - Movie Streaming Platform, Inc.</p>
        </div>
    </footer>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function closeModalOnOverlay(event, modalId) {
            if (event.target.id === modalId) closeModal(modalId);
        }

        function dummyAction(actionName) {
            Swal.fire({
                title: 'Feature Coming Soon',
                text: actionName + ' logic not implemented yet.',
                icon: 'info'
            });
        }
    </script>
    <script src="../assets/notification.js"></script>

</body>

</html>