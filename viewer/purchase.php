<?php
session_start();
require_once '../config/db_connect.php';

// 1. LOGIN CHECK
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/auth.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$errorMsg = "";
$successMsg = "";

// 2. GET CONTENT DETAILS
if (!isset($_GET['id']) || !isset($_GET['type'])) {
    echo "Invalid Request";
    exit();
}

$content_id = intval($_GET['id']);
$content_type = $_GET['type']; // 'movie' or 'series'
$content = null;

if ($content_type === 'movie') {
    // Added 'uploaded_by' to fetch the creator's ID
    $stmt = $conn->prepare("SELECT title, price, poster_url, uploaded_by FROM movies WHERE movie_id = ?");
    $stmt->bind_param("i", $content_id);
    $stmt->execute();
    $content = $stmt->get_result()->fetch_assoc();
} elseif ($content_type === 'series') {
    // Added 'uploaded_by' to fetch the creator's ID
    $stmt = $conn->prepare("SELECT title, price, poster_url, uploaded_by FROM series WHERE series_id = ?");
    $stmt->bind_param("i", $content_id);
    $stmt->execute();
    $content = $stmt->get_result()->fetch_assoc();
}

if (!$content) {
    echo "Content not found.";
    exit();
}

$title = $content['title'];
$price = $content['price'];
$poster = !empty($content['poster_url']) ? "../" . $content['poster_url'] : "../assets/logo.png";
$uploader_id = $content['uploaded_by']; // ID of the Producer/Admin who uploaded it

// 3. CHECK IF ALREADY PURCHASED
$check = $conn->prepare("SELECT purchase_id FROM purchases WHERE user_id = ? AND content_type = ? AND content_id = ?");
$check->bind_param("isi", $user_id, $content_type, $content_id);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    $redirectPage = ($content_type === 'movie') ? "watchMovie.php" : "watchSeries.php";
    header("Location: $redirectPage?id=$content_id");
    exit();
}

// 4. HANDLE PAYMENT
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $method = $_POST['payment_method'];
    $acc_num = $_POST['account_number'];
    $trx_id = $_POST['trx_id'];

    if (empty($acc_num) || empty($trx_id)) {
        $errorMsg = "Please fill in all payment details.";
    } else {
        $conn->begin_transaction();
        try {
            // A. Deduct/Log Payment from Viewer (movie_purchase)
            $descViewer = "Purchased $content_type: $title (TrxID: $trx_id)";
            $stmt1 = $conn->prepare("INSERT INTO transactions (user_id, amount, type, status, description) VALUES (?, ?, 'movie_purchase', 'success', ?)");
            $stmt1->bind_param("ids", $user_id, $price, $descViewer);
            $stmt1->execute();

            // B. Grant Access (Insert into Purchases Table)
            $stmt2 = $conn->prepare("INSERT INTO purchases (user_id, content_type, content_id, price) VALUES (?, ?, ?, ?)");
            $stmt2->bind_param("isid", $user_id, $content_type, $content_id, $price);
            $stmt2->execute();

            // --- C. CREDIT THE UPLOADER (Wallet + Transaction Log) ---

            // 1. Update Wallet Balance
            $stmt3 = $conn->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?");
            $stmt3->bind_param("di", $price, $uploader_id);
            $stmt3->execute();

            // 2. Log Payout Transaction for Producer
            $descProducer = "Earnings from purchase of: $title";
            $stmt4 = $conn->prepare("INSERT INTO transactions (user_id, amount, type, status, description) VALUES (?, ?, 'producer_payout', 'success', ?)");
            $stmt4->bind_param("ids", $uploader_id, $price, $descProducer);
            $stmt4->execute();

            // ---------------------------------------------------------

            $conn->commit();

            $targetPage = ($content_type === 'movie') ? "watchMovie.php" : "watchSeries.php";
            echo "<script>
                alert('Purchase Successful! Redirecting to content...');
                window.location.href = '$targetPage?id=$content_id';
            </script>";
            exit();
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
    <title>Buy <?php echo htmlspecialchars($title); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --bg-dark: #020b1f;
            --bg-card: #0b1326;
            --accent: #e50914;
            --text: #fff;
        }

        body {
            background-color: var(--bg-dark);
            color: var(--text);
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .purchase-wrapper {
            display: flex;
            background: var(--bg-card);
            width: 900px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6);
            border: 1px solid #1f2940;
        }

        /* Left Side: Content Info */
        .content-info {
            flex: 1;
            padding: 40px;
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.8), rgba(0, 0, 0, 0.4)), url('<?php echo $poster; ?>');
            background-size: cover;
            background-position: center;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            position: relative;
        }

        .content-info::before {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(2, 11, 31, 0.7);
        }

        .info-text {
            position: relative;
            z-index: 2;
        }

        .info-text h1 {
            font-size: 32px;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.8);
        }

        .price-tag {
            display: inline-block;
            background: var(--accent);
            color: white;
            padding: 5px 15px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 15px;
        }

        /* Right Side: Payment Form */
        .payment-form {
            flex: 1.2;
            padding: 40px;
            display: flex;
            flex-direction: column;
        }

        .form-header {
            margin-bottom: 30px;
            border-bottom: 1px solid #1f2940;
            padding-bottom: 15px;
        }

        .form-header h2 {
            font-size: 22px;
            color: #ccc;
        }

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 25px;
        }

        .method {
            background: #161d2f;
            border: 2px solid #1f2940;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: 0.3s;
        }

        .method:hover,
        .method.active {
            border-color: var(--accent);
            background: #1c253d;
        }

        .method i {
            font-size: 24px;
            margin-bottom: 5px;
            display: block;
        }

        .input-group {
            margin-bottom: 20px;
        }

        .input-group label {
            display: block;
            color: #aaa;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .input-group input {
            width: 100%;
            padding: 12px;
            background: #161d2f;
            border: 1px solid #1f2940;
            color: white;
            border-radius: 6px;
            outline: none;
        }

        .input-group input:focus {
            border-color: var(--accent);
        }

        .btn-pay {
            width: 100%;
            padding: 15px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
            margin-top: auto;
        }

        .btn-pay:hover {
            background: #b20710;
        }

        .back-btn {
            position: absolute;
            top: 20px;
            left: 20px;
            color: white;
            text-decoration: none;
            z-index: 10;
            font-size: 14px;
            background: rgba(0, 0, 0, 0.5);
            padding: 5px 10px;
            border-radius: 4px;
        }

        @media (max-width: 800px) {
            .purchase-wrapper {
                flex-direction: column;
                width: 95%;
            }

            .content-info {
                height: 200px;
            }
        }
    </style>
</head>

<body>

    <div class="purchase-wrapper">
        <a href="javascript:history.back()" class="back-btn"><i class="fa-solid fa-arrow-left"></i> Cancel</a>

        <div class="content-info">
            <div class="info-text">
                <span class="price-tag"><?php echo $price; ?> BDT</span>
                <h1><?php echo htmlspecialchars($title); ?></h1>
                <p style="color:#ccc;">One-time purchase for lifetime access.</p>
            </div>
        </div>

        <div class="payment-form">
            <div class="form-header">
                <h2>Secure Checkout</h2>
            </div>

            <form method="POST" id="payForm">
                <label style="color:#888; font-size:13px; margin-bottom:10px; display:block;">Select Payment Method</label>
                <div class="payment-methods">
                    <div class="method active" onclick="setMethod('bKash', this)">
                        <i class="fa-solid fa-wallet" style="color:#e2136e;"></i> bKash
                    </div>
                    <div class="method" onclick="setMethod('Rocket', this)">
                        <i class="fa-solid fa-rocket" style="color:#8e44ad;"></i> Rocket
                    </div>
                    <div class="method" onclick="setMethod('Bank', this)">
                        <i class="fa-solid fa-building-columns" style="color:#aaa;"></i> Bank
                    </div>
                </div>
                <input type="hidden" name="payment_method" id="payMethod" value="bKash">

                <div class="input-group">
                    <label>Account Number</label>
                    <input type="text" name="account_number" placeholder="01XXXXXXXXX" required>
                </div>

                <div class="input-group">
                    <label>Transaction ID (TrxID)</label>
                    <input type="text" name="trx_id" placeholder="Enter TrxID" required>
                </div>

                <button type="submit" class="btn-pay">Pay <?php echo $price; ?> BDT</button>
            </form>
        </div>
    </div>

    <script>
        function setMethod(method, elem) {
            document.getElementById('payMethod').value = method;
            document.querySelectorAll('.method').forEach(m => m.classList.remove('active'));
            elem.classList.add('active');
        }

        <?php if ($errorMsg): ?>
            Swal.fire({
                icon: 'error',
                title: 'Payment Failed',
                text: '<?php echo $errorMsg; ?>'
            });
        <?php endif; ?>
    </script>

</body>

</html>