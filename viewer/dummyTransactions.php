<?php
session_start();
require_once '../config/db_connect.php'; // Correct path to your DB config

// 1. Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/auth.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$errorMsg = "";
$successMsg = "";

// 2. HANDLE PAYMENT SUBMISSION
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $method = $_POST['payment_method'];
    $acc_num = $_POST['account_number'];
    $trx_id = $_POST['trx_id'];
    $amount = 500.00; // Fixed premium price

    // Basic Validation
    if (empty($acc_num) || empty($trx_id)) {
        $errorMsg = "All fields are required.";
    } elseif (strlen($acc_num) != 11) {
        $errorMsg = "Invalid account number length.";
    } else {
        // Start Transaction
        $conn->begin_transaction();

        try {
            // A. Log the Transaction
            $stmt1 = $conn->prepare("INSERT INTO transactions (user_id, amount, type, status, description) VALUES (?, ?, 'subscription_pay', 'success', ?)");
            $desc = "Premium Subscription via " . $method . " (TrxID: " . $trx_id . ")";
            $stmt1->bind_param("ids", $user_id, $amount, $desc);
            $stmt1->execute();
            $stmt1->close();

            // B. Update Viewer Profile (Grant Premium)
            // Sets plan to 'premium' and expiry to 30 days from now
            $stmt2 = $conn->prepare("UPDATE viewers SET subscription_plan = 'premium', subscription_expiry = DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY) WHERE user_id = ?");
            $stmt2->bind_param("i", $user_id);
            $stmt2->execute();
            $stmt2->close();

            // Commit
            $conn->commit();
            $successMsg = "Payment Successful! You are now a Premium member.";

            // Optional: Refresh session var if you store membership there
            $_SESSION['membership'] = 'premium';
        } catch (Exception $e) {
            $conn->rollback();
            $errorMsg = "Transaction failed: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction - MSP Platform</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        /* --- SHARED THEME --- */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', sans-serif;
        }

        body {
            background-color: #020b1f;
            color: white;
            overflow-x: hidden;
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
            background: rgba(2, 11, 31, 0.95);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .logo-img {
            height: 35px;
            cursor: pointer;
            display: block;
        }

        .payment-container {
            padding: 120px 15% 50px;
            display: flex;
            justify-content: center;
        }

        .payment-card {
            background: #0b1326;
            width: 100%;
            max-width: 600px;
            padding: 40px;
            border-radius: 12px;
            border: 1px solid #1f2940;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }

        .section-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 25px;
            border-left: 4px solid #e50914;
            padding-left: 15px;
        }

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }

        .method-box {
            background: #161d2f;
            border: 2px solid #1f2940;
            padding: 20px;
            text-align: center;
            border-radius: 10px;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .method-box img {
            width: 50px;
            height: auto;
            margin-bottom: 10px;
            border-radius: 5px;
        }

        .method-box:hover {
            border-color: #e50914;
            background: #1c253d;
        }

        .method-box.active {
            border-color: #e50914;
            background: #1c253d;
            box-shadow: 0 0 15px rgba(229, 9, 20, 0.2);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #aaa;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            background: #161d2f;
            border: 1px solid #1f2940;
            color: white;
            padding: 12px 15px;
            border-radius: 6px;
            outline: none;
        }

        .form-group input:focus {
            border-color: #e50914;
        }

        .btn-pay {
            width: 100%;
            background: #e50914;
            color: white;
            border: none;
            padding: 15px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 10px;
        }

        .btn-pay:hover {
            background: #b20710;
            transform: translateY(-2px);
        }

        .order-summary {
            background: #161d2f;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            border: 1px dashed #1f2940;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 14px;
        }

        .total-price {
            color: #e50914;
            font-weight: bold;
            font-size: 18px;
        }

        /* SweetAlert Custom Dark Styling */
        .swal2-popup {
            background: #0b1326 !important;
            color: white !important;
            border: 1px solid #1f2940 !important;
        }

        .swal2-title {
            color: white !important;
        }

        .swal2-html-container {
            color: #aaa !important;
        }

        .swal2-confirm {
            background-color: #e50914 !important;
        }
    </style>
</head>

<body>

    <nav class="navbar">
        <a href="../index.php"><img src="../assets/logo.png" class="logo-img" alt="MSP"></a>
        <div style="color: #666;"><i class="fa-solid fa-shield-halved"></i> Secure Checkout</div>
    </nav>

    <main class="payment-container">
        <div class="payment-card">
            <div class="section-title">Upgrade to Premium</div>

            <div class="order-summary">
                <div class="summary-row"><span>Item:</span> <span>Premium Movie Access</span></div>
                <div class="summary-row"><span>Validity:</span> <span>30 Days</span></div>
                <div class="summary-row total-price"><span>Total:</span> <span>500 BDT</span></div>
            </div>

            <form action="" method="POST" id="paymentForm">
                <label style="display:block; margin-bottom:10px; font-size:14px; color:#aaa;">Select Payment Method:</label>
                <div class="payment-methods">
                    <div class="method-box active" onclick="selectMethod(this, 'bKash')">
                        <i class="fa-solid fa-wallet" style="font-size: 30px; margin-bottom: 10px; color:#e2136e;"></i>
                        <div>bKash</div>
                    </div>
                    <div class="method-box" onclick="selectMethod(this, 'Rocket')">
                        <i class="fa-solid fa-rocket" style="font-size: 30px; margin-bottom: 10px; color:#8e44ad;"></i>
                        <div>Rocket</div>
                    </div>
                    <div class="method-box" onclick="selectMethod(this, 'Bank')">
                        <i class="fa-solid fa-building-columns" style="font-size: 30px; margin-bottom: 10px; color:#aaa;"></i>
                        <div>Bank</div>
                    </div>
                </div>

                <input type="hidden" name="payment_method" id="selectedMethod" value="bKash">

                <div class="form-group">
                    <label id="phoneLabel">bKash Account Number</label>
                    <input type="text" name="account_number" id="accountNumber" placeholder="01XXXXXXXXX" maxlength="11" required>
                </div>

                <div class="form-group">
                    <label>Transaction ID (TrxID)</label>
                    <input type="text" name="trx_id" id="trxId" placeholder="Enter the 6-digit ID" maxlength="6" required>
                </div>

                <button type="submit" class="btn-pay">Confirm Transaction</button>
            </form>

            <p style="text-align:center; font-size: 12px; color:#666; margin-top:20px;">
                This is a simulated environment. No real money will be deducted.
            </p>
        </div>
    </main>

    <script>
        function selectMethod(element, method) {
            document.querySelectorAll('.method-box').forEach(box => box.classList.remove('active'));
            element.classList.add('active');
            document.getElementById('selectedMethod').value = method;

            const label = document.getElementById('phoneLabel');
            label.innerText = (method === 'Bank') ? "Bank Account / Card Number" : method + " Account Number";
        }

        // Check for PHP success/error messages
        <?php if ($successMsg): ?>
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: '<?php echo $successMsg; ?>',
                confirmButtonText: 'Go to Subscription'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'subscription.php';
                }
            });
        <?php elseif ($errorMsg): ?>
            Swal.fire({
                icon: 'error',
                title: 'Oops...',
                text: '<?php echo $errorMsg; ?>'
            });
        <?php endif; ?>

        // Frontend Validation
        document.getElementById('paymentForm').onsubmit = function(e) {
            // Only validate formatting, let PHP handle the logic
            const acc = document.getElementById('accountNumber').value;
            const trx = document.getElementById('trxId').value;

            if (acc.length < 11 || isNaN(acc)) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Number',
                    text: 'Please enter a valid numeric account number.',
                    confirmButtonText: 'Try Again'
                });
                return false;
            }
            if (trx.length < 6) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid TrxID',
                    text: 'Transaction ID must be at least 6 characters!',
                    confirmButtonText: 'Try Again'
                });
                return false;
            }

            // Show loading animation immediately
            let timerInterval;
            Swal.fire({
                title: 'Processing Payment',
                html: 'Verifying transaction...',
                timer: 2000,
                timerProgressBar: true,
                didOpen: () => {
                    Swal.showLoading();
                },
                willClose: () => {
                    // Allow the form to submit naturally after the fake delay visual
                }
            });
            // Note: In a real app, you'd use AJAX here. For this simple setup,
            // we let the form submit naturally after the validation check.
        };
    </script>
</body>

</html>