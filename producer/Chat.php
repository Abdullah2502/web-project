<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'producer') {
    header("Location: ../auth/auth.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle AJAX requests separately
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (isset($_POST['send_message'])) {
        $message = mysqli_real_escape_string($conn, $_POST['message'] ?? '');
        $receiver_id = intval($_POST['receiver_id'] ?? 0);
        $reply_to_id = intval($_POST['reply_to_id'] ?? 0);
        
        if ($message && $receiver_id > 0) {
            // If replying to a message, prefix with reply marker
            $final_message = $message;
            if ($reply_to_id > 0) {
                // Store reply_to_id in message with JSON format for easy parsing
                $final_message = json_encode([
                    'type' => 'reply',
                    'reply_to' => $reply_to_id,
                    'content' => $message
                ]);
            }
            
            $insert_query = "INSERT INTO chat_messages (sender_id, receiver_id, message, sent_at, is_read)
                             VALUES ($user_id, $receiver_id, '$final_message', NOW(), 0)";
            if (mysqli_query($conn, $insert_query)) {
                echo json_encode(['success' => true, 'message' => 'Message sent']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error sending message']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid input']);
        }
        exit;
    }
    
    if (isset($_POST['get_messages'])) {
        $contact_id = intval($_POST['contact_id'] ?? 0);
        
        if ($contact_id > 0) {
            $messages_query = "SELECT message_id, sender_id, message, sent_at FROM chat_messages WHERE (sender_id = $user_id AND receiver_id = $contact_id) OR (sender_id = $contact_id AND receiver_id = $user_id) ORDER BY sent_at ASC LIMIT 100";
            $messages_result = mysqli_query($conn, $messages_query);
            $messages = [];
            if ($messages_result) {
                while ($row = mysqli_fetch_assoc($messages_result)) {
                    $msg_content = $row['message'];
                    $reply_to = null;
                    $is_reply = false;
                    
                    // Check if this is a reply message
                    $decoded = json_decode($msg_content, true);
                    if ($decoded && isset($decoded['type']) && $decoded['type'] === 'reply') {
                        $is_reply = true;
                        $reply_to = $decoded['reply_to'];
                        $msg_content = $decoded['content'];
                    }
                    
                    $messages[] = [
                        'message_id' => intval($row['message_id']),
                        'sender_id' => intval($row['sender_id']),
                        'message' => htmlspecialchars($msg_content),
                        'time' => date('h:i A', strtotime($row['sent_at'])),
                        'is_reply' => $is_reply,
                        'reply_to' => $reply_to
                    ];
                }
            }
            
            // Mark messages as read
            $update_read = "UPDATE chat_messages SET is_read = 1 WHERE receiver_id = $user_id AND sender_id = $contact_id AND is_read = 0";
            mysqli_query($conn, $update_read);
            
            echo json_encode(['success' => true, 'messages' => $messages]);
        } else {
            echo json_encode(['success' => false, 'messages' => []]);
        }
        exit;
    }
}

// Get list of contacts (other producers)
$contacts = [];
$contacts_query = "SELECT u.id, u.username FROM users u WHERE u.role = 'producer' AND u.id != $user_id ORDER BY u.username ASC LIMIT 20";
$contacts_result = mysqli_query($conn, $contacts_query);
if ($contacts_result) {
    while ($row = mysqli_fetch_assoc($contacts_result)) {
        $contacts[] = $row;
    }
}

// Get selected contact or use first contact
$selected_contact_id = isset($_GET['contact']) ? intval($_GET['contact']) : (count($contacts) > 0 ? intval($contacts[0]['id']) : 0);

$messages = [];
$contact_name = '';
if ($selected_contact_id > 0) {
    // Get contact name
    $contact_name = '';
    $contact_name_query = "SELECT username FROM users WHERE id = $selected_contact_id LIMIT 1";
    $contact_name_result = mysqli_query($conn, $contact_name_query);
    if ($contact_name_result && $row = mysqli_fetch_assoc($contact_name_result)) {
        $contact_name = htmlspecialchars($row['username']);
    }
    
    // Get chat messages with selected contact
    $messages_query = "SELECT message_id, sender_id, message, sent_at FROM chat_messages WHERE (sender_id = $user_id AND receiver_id = $selected_contact_id) OR (sender_id = $selected_contact_id AND receiver_id = $user_id) ORDER BY sent_at ASC LIMIT 100";
    $messages_result = mysqli_query($conn, $messages_query);
    if ($messages_result) {
        while ($row = mysqli_fetch_assoc($messages_result)) {
            $msg_content = $row['message'];
            $reply_to = null;
            $is_reply = false;
            
            // Check if this is a reply message
            $decoded = json_decode($msg_content, true);
            if ($decoded && isset($decoded['type']) && $decoded['type'] === 'reply') {
                $is_reply = true;
                $reply_to = $decoded['reply_to'];
                $msg_content = $decoded['content'];
            }
            
            $messages[] = [
                'message_id' => intval($row['message_id']),
                'sender_id' => intval($row['sender_id']),
                'message' => htmlspecialchars($msg_content),
                'time' => date('h:i A', strtotime($row['sent_at'])),
                'is_reply' => $is_reply,
                'reply_to' => $reply_to
            ];
        }
    }
    
    // Mark messages as read
    $update_read = "UPDATE chat_messages SET is_read = 1 WHERE receiver_id = $user_id AND sender_id = $selected_contact_id AND is_read = 0";
    mysqli_query($conn, $update_read);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - Messenger</title>
    <link rel="stylesheet" href="../assets/Dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="../assets/theme.js"></script>
    <script src="../assets/notification.js"></script>
</head>

<body>

    <nav class="navbar">
        <div class="nav-container">
            <a href="Dashboard.php" class="logo-btn hover-glow">
                <img src="../assets/logo.png" alt="Logo" class="logo-img">
            </a>

            <ul class="nav-links">
                <li><a href="Dashboard.php" class="hover-glow">Dashboard</a></li>
                <li><a href="Movies.php" class="hover-glow">Movies</a></li>
                <li><a href="Series.php" class="hover-glow">Series</a></li>
                <li><a href="Forum.php" class="hover-glow">Forum</a></li>
                <li><a href="Chat.php" class="hover-glow" style="color:#228EE5;">Chat</a></li>
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
                        <span style="position:absolute; top:0; right:0; width:8px; height:8px; background:red; border-radius:50%;"></span>
                    </button>
                    <div id="notificationDropdown" class="notification-dropdown">
                        <div class="notification-header">
                            <span>Notifications</span>
                            <span class="mark-read">Mark all as read</span>
                        </div>
                        <div class="notification-list">
                            <div class="notif-item">
                                <div class="notif-content">
                                    <h4>New Message</h4>
                                    <p><b>Sarah Connor</b> sent you a message.</p>
                                    <span class="notif-time">Just now</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <a href="UserProfile.php" class="icon-btn hover-glow"><i class="fa-solid fa-user"></i></a>
                <button class="icon-btn hover-glow"><i class="fa-solid fa-sun"></i></button>
            </div>
        </div>
    </nav>

    <div class="main-content">
        <h1 class="welcome-text">Messages</h1>

        <div class="chat-app-container">
            
            <div class="chat-sidebar">
                <div class="chat-sidebar-header">
                    <h3 style="margin:0;">Chats</h3>
                    <button class="icon-btn hover-glow"><i class="fa-regular fa-pen-to-square"></i></button>
                </div>
                
                <div class="chat-search">
                    <input type="text" placeholder="Search contacts...">
                </div>

                <div class="contact-list">
                    <?php foreach ($contacts as $contact): ?>
                        <a href="Chat.php?contact=<?php echo intval($contact['id']); ?>" style="text-decoration: none; color: inherit;">
                            <div class="contact-item <?php echo ($selected_contact_id == $contact['id']) ? 'active' : ''; ?>">
                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($contact['username']); ?>&background=0D8ABC&color=fff" class="contact-avatar">
                                <div class="contact-info">
                                    <h4><?php echo htmlspecialchars($contact['username']); ?></h4>
                                    <p>Last message...</p>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="chat-main">
                <?php if ($selected_contact_id): ?>
                    <div class="chat-header">
                        <div class="chat-user-profile">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($contact_name); ?>&background=0D8ABC&color=fff" class="contact-avatar">
                            <div>
                                <h3 style="margin:0; font-size:16px;"><?php echo $contact_name; ?></h3>
                                <span class="user-status">● Online</span>
                            </div>
                        </div>
                        <div class="nav-icons">
                            <button class="icon-btn hover-glow"><i class="fa-solid fa-phone"></i></button>
                            <button class="icon-btn hover-glow"><i class="fa-solid fa-video"></i></button>
                            <button class="icon-btn hover-glow"><i class="fa-solid fa-circle-info"></i></button>
                        </div>
                    </div>

                    <div class="chat-messages" id="messageContainer">
                        <?php foreach ($messages as $msg): ?>
                            <div class="message <?php echo ($msg['sender_id'] == $user_id) ? 'sent' : 'received'; ?>" data-message-id="<?php echo $msg['message_id']; ?>">
                                <div class="message-bubble">
                                    <?php if ($msg['is_reply']): ?>
                                        <div class="reply-indicator">↳ Replying to message #<?php echo $msg['reply_to']; ?></div>
                                    <?php endif; ?>
                                    <?php echo $msg['message']; ?>
                                    <span class="msg-time"><?php echo $msg['time']; ?></span>
                                </div>
                                <button type="button" class="reply-btn" onclick="setReplyTo(<?php echo $msg['message_id']; ?>)" title="Reply">
                                    <i class="fa-solid fa-reply"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <form class="chat-input-area" onsubmit="sendMessage(event); return false;">
                        <button type="button" class="icon-btn hover-glow"><i class="fa-solid fa-paperclip"></i></button>
                        <div class="chat-input-wrapper">
                            <div id="replyContext" class="reply-context" style="display:none;">
                                <small>Replying to message <span id="replyMsgId"></span></small>
                                <button type="button" onclick="clearReply()" class="clear-reply-btn">✕</button>
                            </div>
                            <input type="text" id="msgInput" class="chat-input-field" placeholder="Type a message..." required autofocus>
                        </div>
                        <input type="hidden" id="receiverId" value="<?php echo intval($selected_contact_id); ?>">
                        <input type="hidden" id="replyToId" value="">
                        <button type="submit" class="chat-btn-send">
                            <i class="fa-solid fa-paper-plane"></i>
                        </button>
                    </form>
                <?php else: ?>
                    <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #aaa;">
                        <p>Select a contact to start messaging</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <script>
        const msgContainer = document.getElementById('messageContainer');
        const receiverId = document.getElementById('receiverId')?.value;
        const userId = <?php echo intval($user_id); ?>;
        let refreshInterval;

        // Auto-scroll to bottom on load
        if (msgContainer) {
            setTimeout(() => {
                msgContainer.scrollTop = msgContainer.scrollHeight;
            }, 100);
        }

        function sendMessage(event) {
            event.preventDefault();
            const input = document.getElementById('msgInput');
            const text = input.value.trim();
            const replyToId = document.getElementById('replyToId').value;

            if (!text || !receiverId) return;

            // Send message via AJAX
            fetch('Chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'send_message=1&message=' + encodeURIComponent(text) + '&receiver_id=' + receiverId + (replyToId ? '&reply_to_id=' + replyToId : '')
            })
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Clear Input & Scroll
                    input.value = "";
                    clearReply();
                    msgContainer.scrollTop = msgContainer.scrollHeight;
                    
                    // Refresh messages from other user
                    refreshMessages();
                } else {
                    alert('Failed to send message: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error sending message');
            });
        }
        
        function setReplyTo(messageId) {
            document.getElementById('replyToId').value = messageId;
            document.getElementById('replyMsgId').textContent = '#' + messageId;
            document.getElementById('replyContext').style.display = 'flex';
            document.getElementById('msgInput').focus();
        }
        
        function clearReply() {
            document.getElementById('replyToId').value = '';
            document.getElementById('replyContext').style.display = 'none';
        }

        function refreshMessages() {
            if (!receiverId) return;
            
            fetch('Chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'get_messages=1&contact_id=' + receiverId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && msgContainer) {
                    const currentMessages = msgContainer.querySelectorAll('.message');
                    // Only refresh if new messages (more than current)
                    if (data.messages.length > currentMessages.length) {
                        // Rebuild message container with new messages
                        msgContainer.innerHTML = '';
                        data.messages.forEach(msg => {
                            const msgDiv = document.createElement('div');
                            msgDiv.classList.add('message', msg.sender_id == userId ? 'sent' : 'received');
                            msgDiv.setAttribute('data-message-id', msg.message_id);
                            
                            const bubble = document.createElement('div');
                            bubble.classList.add('message-bubble');
                            
                            let bubbleHTML = '';
                            if (msg.is_reply) {
                                bubbleHTML += '<div class="reply-indicator">↳ Replying to message #' + msg.reply_to + '</div>';
                            }
                            bubbleHTML += msg.message + ' <span class="msg-time">' + msg.time + '</span>';
                            bubble.innerHTML = bubbleHTML;
                            
                            msgDiv.appendChild(bubble);
                            
                            // Add reply button
                            const replyBtn = document.createElement('button');
                            replyBtn.type = 'button';
                            replyBtn.classList.add('reply-btn');
                            replyBtn.setAttribute('onclick', 'setReplyTo(' + msg.message_id + ')');
                            replyBtn.setAttribute('title', 'Reply');
                            replyBtn.innerHTML = '<i class="fa-solid fa-reply"></i>';
                            msgDiv.appendChild(replyBtn);
                            
                            msgContainer.appendChild(msgDiv);
                        });
                        msgContainer.scrollTop = msgContainer.scrollHeight;
                    }
                }
            })
            .catch(error => console.error('Refresh error:', error));
        }

        // Auto-refresh messages every 2 seconds when chat is open
        if (receiverId) {
            refreshInterval = setInterval(refreshMessages, 2000);
        }

        // Clean up interval when leaving page
        window.addEventListener('beforeunload', () => {
            if (refreshInterval) clearInterval(refreshInterval);
        });
    </script>

</body>

</html>
