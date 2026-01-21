<?php
session_start();
include '../config/db_connect.php';

// Set header to return JSON
header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Unauthorized'];

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode($response);
    exit;
}

$my_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

// --- 1. SEND MESSAGE ---
if ($action === 'send') {
    $receiver_id = intval($_POST['receiver_id']);
    $message = trim($_POST['message']);

    if (!empty($message) && $receiver_id) {
        $stmt = $conn->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $my_id, $receiver_id, $message);

        if ($stmt->execute()) {
            $response = ['status' => 'success'];
        } else {
            $response['message'] = "DB Error: " . $conn->error;
        }
    } else {
        $response['message'] = "Empty message";
    }
}

// --- 2. FETCH MESSAGES (Load Chat History) ---
elseif ($action === 'fetch_conversation') {
    $partner_id = intval($_POST['partner_id']);

    // Fetch messages between Me and Partner, ordered by time
    $sql = "SELECT m.*, u.username 
            FROM chat_messages m 
            JOIN users u ON m.sender_id = u.user_id
            WHERE (m.sender_id = ? AND m.receiver_id = ?) 
               OR (m.sender_id = ? AND m.receiver_id = ?) 
            ORDER BY m.sent_at ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiii", $my_id, $partner_id, $partner_id, $my_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = [
            'id' => $row['message_id'],
            'sender_id' => $row['sender_id'],
            'message' => htmlspecialchars($row['message']), // Prevent XSS
            'time' => date('h:i A', strtotime($row['sent_at'])),
            'is_me' => ($row['sender_id'] == $my_id)
        ];
    }

    // Mark as read (Optional logic)
    $conn->query("UPDATE chat_messages SET is_read = 1 WHERE sender_id = $partner_id AND receiver_id = $my_id");

    $response = ['status' => 'success', 'messages' => $messages];
}

echo json_encode($response);
$conn->close();
