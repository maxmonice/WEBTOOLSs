<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['is_admin']) && empty($_SESSION['is_staff'])) {
    header('Location: login.php');
    exit;
}
require_once '../Db.php';
$pdo = getDB();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Support — Luke's Seafood</title>
    <link href="https://fonts.googleapis.com/css2?family=Aclonica&family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin.css">
    <style>
        .chat-layout { display: grid; grid-template-columns: 350px 1fr; height: calc(100vh - 100px); margin: 20px; background: #1a1a1a; border-radius: 20px; border: 1px solid rgba(255,255,255,0.05); overflow: hidden; }
        .thread-list { background: #161616; border-right: 1px solid rgba(255,255,255,0.05); display: flex; flex-direction: column; }
        .thread-header { padding: 25px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .thread-header h2 { font-family: 'Aclonica', sans-serif; font-size: 1.2rem; color: #fff; }
        
        .threads-container { flex: 1; overflow-y: auto; }
        .thread-item { padding: 20px 25px; border-bottom: 1px solid rgba(255,255,255,0.02); cursor: pointer; transition: background 0.2s; display: flex; align-items: center; gap: 15px; }
        .thread-item:hover { background: rgba(255,255,255,0.02); }
        .thread-item.active { background: rgba(194,38,38,0.1); border-left: 4px solid #C22626; }
        .thread-avatar { width: 44px; height: 44px; border-radius: 12px; background: #222; display: flex; align-items: center; justify-content: center; font-family: 'Aclonica', sans-serif; color: #C22626; border: 1px solid rgba(255,255,255,0.05); }
        .thread-details { flex: 1; min-width: 0; }
        .thread-name { font-weight: 700; color: #fff; display: block; }
        .thread-type { font-size: 0.75rem; color: #666; text-transform: uppercase; letter-spacing: 1px; }

        .chat-main { display: flex; flex-direction: column; background: #1a1a1a; }
        .chat-main-header { padding: 20px 30px; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: space-between; }
        .chat-messages { flex: 1; overflow-y: auto; padding: 30px; display: flex; flex-direction: column; gap: 20px; }
        .message { max-width: 60%; padding: 12px 18px; border-radius: 16px; font-size: 0.9rem; line-height: 1.5; }
        .message.admin { align-self: flex-end; background: #C22626; color: #fff; border-bottom-right-radius: 4px; }
        .message.customer, .message.rider { align-self: flex-start; background: #2a2a2a; color: #fff; border-bottom-left-radius: 4px; }
        .message-time { display: block; font-size: 0.7rem; opacity: 0.6; margin-top: 5px; text-align: right; }

        .chat-input-area { padding: 25px 30px; border-top: 1px solid rgba(255,255,255,0.05); display: flex; gap: 15px; }
        .chat-input { flex: 1; background: #222; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 12px 20px; color: #fff; outline: none; transition: border-color 0.2s; }
        .chat-input:focus { border-color: #C22626; }
        .send-btn { background: #C22626; color: #fff; border: none; padding: 0 25px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: background 0.2s; }
        .send-btn:hover { background: #8B0A1E; }
        
        .empty-chat { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #444; }
        .empty-chat i { font-size: 4rem; margin-bottom: 20px; }
    </style>
</head>
<body class="dark-theme">
    <div class="admin-wrapper">
        <header class="admin-header">
            <div class="header-left">
                <a href="admin-dashboard.php" class="logo">Luke's Seafood</a>
                <span class="page-title">Support Messages</span>
            </div>
        </header>

        <div class="chat-layout">
            <div class="thread-list">
                <div class="thread-header">
                    <h2>Active Conversations</h2>
                </div>
                <div class="threads-container" id="threadsContainer">
                    <!-- Threads here -->
                </div>
            </div>

            <div class="chat-main" id="chatMain">
                <div class="empty-chat" id="emptyChat">
                    <i class="fas fa-comments"></i>
                    <p>Select a conversation to start chatting</p>
                </div>
                
                <div id="chatContent" style="display: none; flex-direction: column; height: 100%;">
                    <div class="chat-main-header">
                        <div class="thread-info">
                            <span class="thread-name" id="activeName">Customer Name</span>
                            <span class="thread-type" id="activeType">Customer</span>
                        </div>
                    </div>
                    <div class="chat-messages" id="chatMessages">
                        <!-- Messages -->
                    </div>
                    <div class="chat-input-area">
                        <input type="text" id="chatInput" class="chat-input" placeholder="Type a message...">
                        <button id="sendBtn" class="send-btn">Send</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
    <script>
        let currentPeer = null;

        async function loadThreads() {
            try {
                const res = await fetch('../chat-api.php?action=get_active_threads');
                const data = await res.json();
                const container = document.getElementById('threadsContainer');
                
                if (data.success) {
                    container.innerHTML = data.threads.map(t => `
                        <div class="thread-item ${currentPeer && currentPeer.id == t.peer_id && currentPeer.type == t.peer_type ? 'active' : ''}" 
                             onclick="selectThread('${t.peer_id}', '${t.peer_type}', '${t.name}')">
                            <div class="thread-avatar">${t.name.charAt(0)}</div>
                            <div class="thread-details">
                                <span class="thread-name">${t.name}</span>
                                <span class="thread-type">${t.peer_type}</span>
                            </div>
                        </div>
                    `).join('');
                }
            } catch (e) { console.error(e); }
        }

        async function selectThread(id, type, name) {
            currentPeer = { id, type, name };
            document.getElementById('emptyChat').style.display = 'none';
            document.getElementById('chatContent').style.display = 'flex';
            document.getElementById('activeName').textContent = name;
            document.getElementById('activeType').textContent = type;
            
            loadThreads(); // Refresh active state
            loadMessages();
        }

        async function loadMessages() {
            if (!currentPeer) return;
            try {
                const res = await fetch(`../chat-api.php?action=get_history&other_id=${currentPeer.id}&other_type=${currentPeer.type}`);
                const data = await res.json();
                const container = document.getElementById('chatMessages');
                
                if (data.success) {
                    container.innerHTML = data.messages.map(m => `
                        <div class="message ${m.sender_type}">
                            ${m.message}
                            <span class="message-time">${m.timestamp}</span>
                        </div>
                    `).join('');
                    container.scrollTop = container.scrollHeight;
                }
            } catch (e) { console.error(e); }
        }

        async function sendMessage() {
            const input = document.getElementById('chatInput');
            const message = input.value.trim();
            if (!message || !currentPeer) return;

            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('message', message);
            formData.append('receiver_id', currentPeer.id);
            formData.append('receiver_type', currentPeer.type);

            input.value = '';
            
            try {
                const res = await fetch('../chat-api.php', { method: 'POST', body: formData });
                loadMessages();
            } catch (e) { console.error(e); }
        }

        document.getElementById('sendBtn').onclick = sendMessage;
        document.getElementById('chatInput').onkeypress = (e) => { if(e.key === 'Enter') sendMessage(); };

        // Real-time via socket
        const socket = io('http://localhost:3000');
        socket.on('new-message', (data) => {
            if (currentPeer && data.senderId == currentPeer.id && data.sender == currentPeer.type) {
                loadMessages();
            } else {
                loadThreads();
            }
        });

        loadThreads();
        setInterval(loadThreads, 10000);
    </script>
</body>
</html>
