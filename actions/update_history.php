<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['content_id'])) {
    exit;
}

$user_id = $_SESSION['user_id'];
$content_type = $_POST['content_type']; // 'movie' or 'series' (or 'episode')
$content_id = intval($_POST['content_id']);
$time = floatval($_POST['time']);
$duration = floatval($_POST['duration']);
// Calculate minutes watched roughly for stats
$minutes_watched = round($time / 60);

// Check if record exists
$check = $conn->prepare("SELECT history_id FROM watch_history WHERE user_id = ? AND content_type = ? AND content_id = ?");
$check->bind_param("isi", $user_id, $content_type, $content_id);
$check->execute();
$res = $check->get_result();

if ($res->num_rows > 0) {
    // UPDATE existing record
    $update = $conn->prepare("UPDATE watch_history SET progress_seconds = ?, watched_minutes = ?, last_watched_at = NOW() WHERE user_id = ? AND content_type = ? AND content_id = ?");
    $update->bind_param("diisi", $time, $minutes_watched, $user_id, $content_type, $content_id);
    $update->execute();
} else {
    // INSERT new record
    $insert = $conn->prepare("INSERT INTO watch_history (user_id, content_type, content_id, progress_seconds, watched_minutes, last_watched_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $insert->bind_param("isidi", $user_id, $content_type, $content_id, $time, $minutes_watched);
    $insert->execute();
}
