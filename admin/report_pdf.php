<?php
require_once '../config/db_connect.php';

$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Fetch Revenue Data for Report
$sql = "SELECT MONTH(created_at) as month, SUM(amount) as revenue 
        FROM transactions 
        WHERE YEAR(created_at) = ? AND status = 'success'
        GROUP BY month ORDER BY month";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $year);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
$total_annual = 0;
while($row = $result->fetch_assoc()) {
    $data[$row['month']] = $row['revenue'];
    $total_annual += $row['revenue'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Financial Report <?php echo $year; ?></title>
    <style>
    body {
        font-family: Arial, sans-serif;
        padding: 40px;
    }

    .header {
        text-align: center;
        margin-bottom: 40px;
        border-bottom: 2px solid #333;
        padding-bottom: 20px;
    }

    .logo {
        font-size: 24px;
        font-weight: bold;
        color: #e50914;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }

    th,
    td {
        border: 1px solid #ddd;
        padding: 12px;
        text-align: left;
    }

    th {
        background-color: #f2f2f2;
    }

    .total-row {
        font-weight: bold;
        background-color: #e5e5e5;
    }

    .print-btn {
        display: none;
    }

    /* Hidden in print view */

    @media print {
        .no-print {
            display: none;
        }
    }
    </style>
</head>

<body onload="window.print()">

    <div class="no-print" style="margin-bottom: 20px;">
        <button onclick="window.print()">Download/Print PDF</button>
        <a href="Dashboard.php">Back to Dashboard</a>
    </div>

    <div class="header">
        <div class="logo">MSP Admin Report</div>
        <h2>Annual Financial Report - <?php echo $year; ?></h2>
        <p>Generated on: <?php echo date("F j, Y, g:i a"); ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Month</th>
                <th>Revenue (BDT)</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            for ($m=1; $m<=12; $m++) {
                $monthName = date('F', mktime(0, 0, 0, $m, 10));
                $amount = isset($data[$m]) ? $data[$m] : 0.00;
                echo "<tr>";
                echo "<td>$monthName</td>";
                echo "<td>" . number_format($amount, 2) . "</td>";
                echo "<td>" . ($amount > 0 ? 'Active' : '-') . "</td>";
                echo "</tr>";
            }
            ?>
            <tr class="total-row">
                <td>Total Annual Revenue</td>
                <td colspan="2"><?php echo number_format($total_annual, 2); ?> BDT</td>
            </tr>
        </tbody>
    </table>

    <div style="margin-top: 50px; text-align: center; font-size: 12px; color: #666;">
        <p>Confidential Report. Only for authorized admin use.</p>
    </div>

</body>

</html>