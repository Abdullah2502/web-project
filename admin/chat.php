<?php
session_start();

// Enable error reporting for debugging (Remove this line in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1. Include Database Connection
// Ensure this path is correct relative to admin/chat.php
$db_path = '../config/db_connect.php';
if (file_exists($db_path)) {
    include $db_path;
} else {
    die("Error: Could not find database connection file at: " . $db_path);
}

// 2. Check Admin Access & Session
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // If not logged in or not admin, redirect to login
    header("Location: ../auth/auth.php");
    exit();
}

// 3. Fetch List of Producers (Contact List)
// We join with the producers table to get company names if available
$sql_contacts = "SELECT u.user_id, u.username, p.company_name 
                 FROM users u 
                 LEFT JOIN producers p ON u.user_id = p.user_id 
                 WHERE u.role = 'producer'";

$contacts = $conn->query($sql_contacts);

// 4. Check for SQL Errors
if (!$contacts) {
    die("Database Query Failed: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Chat</title>
    <link rel="stylesheet" href="../assets/Dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> 
    <style>
        .contact-item { cursor: pointer; transition: background 0.2s; }
        .contact-item:hover { background: rgba(255,255,255,0.05); }
        .contact-item.active { background: rgba(42, 92, 255, 0.2); border-left: 3px solid #2a9bff; }
        .no-chat-selected { display: flex; align-items: center; justify-content: center; height: 100%; color: #aaa; }
    </style>
</head>

<body>

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
                <li><a href="Wallet.php" class="hover-glow">Wallet</a></li>
                <li><a href="adminForum.php" class="hover-glow">Forum</a></li>
                <li><a href="chat.php" class="hover-glow" style="color:#228EE5;">Chat</a></li>
            </ul>
            <div class="nav-icons">
                 <a href="adminProfile.php" class="icon-btn hover-glow"><i class="fa-solid fa-user"></i></a>
                 <a href="../actions/logout.php" class="icon-btn hover-glow"><i class="fa-solid fa-right-from-bracket"></i></a>
            </div>
        </div>
    </nav>

    <div class="main-content">
        <h1 class="welcome-text">Producer Support Chat</h1>

        <div class="chat-app-container">
            
            <div class="chat-sidebar">
                <div class="chat-sidebar-header">
                    <h3 style="margin:0;">Producers</h3>
                </div>
                
                <div class="chat-search">
                    <input type="text" id="contactSearch" placeholder="Search producers...">
                </div>

                <div class="contact-list" id="contactList">
                    <?php if ($contacts->num_rows > 0): ?>
                        <?php while($row = $contacts->fetch_assoc()): ?>
                            <?php $displayName = !empty($row['company_name']) ? $row['company_name'] : $row['username']; ?>
                            
                            <div class="contact-item" 
                                 onclick="selectUser(<?php echo $row['user_id']; ?>, '<?php echo htmlspecialchars($displayName); ?>')"
                                 id="user-<?php echo $row['user_id']; ?>">
                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($displayName); ?>&background=random" class="contact-avatar">
                                <div class="contact-info">
                                    <h4><?php echo htmlspecialchars($displayName); ?></h4>
                                    <p class="small-text">@<?php echo htmlspecialchars($row['username']); ?></p>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="padding: 20px; color: #aaa;">No Producers found.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="chat-main">
                <div class="chat-header" id="chatHeader" style="display:none;">
                    <div class="chat-user-profile">
                        <img src="" id="currentAvatar" class="contact-avatar">
                        <div>
                            <h3 style="margin:0; font-size:16px;" id="currentUserName">User</h3>
                            <span class="user-status">● Active</span>
                        </div>
                    </div>
                </div>

                <div class="chat-messages" id="messageContainer">
                    <div class="no-chat-selected">Select a Producer to start chatting</div>
                </div>

                <div class="chat-input-area" id="inputArea" style="display:none;">
                    <input type="text" id="msgInput" class="chat-input-field" placeholder="Type a message..." onkeypress="handleEnter(event)">
                    <button class="chat-btn-send" onclick="sendMessage()">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentReceiverId = null;
        let fetchInterval = null;

        function selectUser(userId, userName) {
            currentReceiverId = userId;
            
            $('#chatHeader').show();
            $('#inputArea').css('display', 'flex'); 
            $('#currentUserName').text(userName);
            $('#currentAvatar').attr('src', `https://ui-avatars.com/api/?name=${userName}&background=random`);

            $('.contact-item').removeClass('active');
            $(`#user-${userId}`).addClass('active');

            fetchMessages();

            if (fetchInterval) clearInterval(fetchInterval);
            fetchInterval = setInterval(fetchMessages, 3000);
        }

        function fetchMessages() {
            if (!currentReceiverId) return;

            $.post('../actions/chat_server.php', {
                action: 'fetch_conversation',
                partner_id: currentReceiverId
            }, function(response) {
                if (response.status === 'success') {
                    renderMessages(response.messages);
                } else {
                    console.error("Chat Error:", response.message);
                }
            }, 'json').fail(function(jqXHR, textStatus, errorThrown) {
                 console.error("AJAX Error:", textStatus, errorThrown);
            });
        }

        function renderMessages(messages) {
            const container = $('#messageContainer');
            container.empty();

            if (messages.length === 0) {
                container.append('<div style="text-align:center; color:#555; margin-top:20px;">No messages yet.</div>');
                return;
            }

            messages.forEach(msg => {
                const type = msg.is_me ? 'sent' : 'received';
                const html = `
                    <div class="message ${type}">
                        <div class="message-bubble">
                            ${msg.message}
                            <span class="msg-time">${msg.time}</span>
                        </div>
                    </div>
                `;
                container.append(html);
            });
            container.scrollTop(container[0].scrollHeight);
        }

        function sendMessage() {
            const input = $('#msgInput');
            const text = input.val().trim();

            if (text === "" || !currentReceiverId) return;

            $.post('../actions/chat_server.php', {
                action: 'send',
                receiver_id: currentReceiverId,
                message: text
            }, function(response) {
                if (response.status === 'success') {
                    input.val('');
                    fetchMessages();
                } else {
                    alert('Error: ' + response.message);
                }
            }, 'json');
        }

        function handleEnter(event) {
            if (event.key === 'Enter') sendMessage();
        }

        // Search Filter
        document.getElementById('contactSearch').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let items = document.querySelectorAll('.contact-item');
            items.forEach(item => {
                let text = item.innerText.toLowerCase();
                item.style.display = text.includes(filter) ? 'flex' : 'none';
            });
        });
    </script>
</body>
</html>