<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['content_id'])) {
    exit('Unauthorized');
}

$user_id = $_SESSION['user_id'];
$content_id = intval($_POST['content_id']);
$content_type = $_POST['content_type']; // 'movie' or 'series'
$progress = floatval($_POST['progress']);
$total_duration = isset($_POST['duration']) ? floatval($_POST['duration']) : 0;

// Calculate minutes for the profile stats
$watched_minutes = round($progress / 60);

// Check if entry exists
$check = $conn->prepare("SELECT history_id FROM watch_history WHERE user_id=? AND content_id=? AND content_type=?");
$check->bind_param("iis", $user_id, $content_id, $content_type);
$check->execute();
$res = $check->get_result();

if ($res->num_rows > 0) {
    // UPDATE existing record
    $sql = "UPDATE watch_history SET progress_seconds = ?, watched_minutes = ?, last_watched_at = NOW() WHERE user_id=? AND content_id=? AND content_type=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("diiis", $progress, $watched_minutes, $user_id, $content_id, $content_type);
} else {
    // INSERT new record
    $sql = "INSERT INTO watch_history (user_id, content_id, content_type, progress_seconds, watched_minutes, last_watched_at) VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisdi", $user_id, $content_id, $content_type, $progress, $watched_minutes);
}

$stmt->execute();
echo "Success";
?>