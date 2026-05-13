<?php
require_once 'staff-config.php';
requireStaff();

$staffName = htmlspecialchars($_SESSION['user_name'] ?? 'Staff');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Chat - Luke's Staff</title>
    <link rel="stylesheet" href="../adminSide/admin.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
    <link rel="stylesheet" href="staff-dashboard.css?v=<?= time() ?>">
    <style>
        .support-chat-page { height: calc(100vh - 64px); padding: 24px; overflow: hidden; }
        .chat-layout { display: grid; grid-template-columns: minmax(280px, 350px) 1fr; height: 100%; background: #1a1a1a; border-radius: 14px; border: 1px solid rgba(255,255,255,0.07); overflow: hidden; box-shadow: 0 18px 50px rgba(0,0,0,0.25); }
        .thread-list { background: #161616; border-right: 1px solid rgba(255,255,255,0.05); display: flex; flex-direction: column; }
        .thread-header { padding: 25px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .thread-header h2 { font-family: 'Aclonica', sans-serif; font-size: 1.2rem; color: #fff; }
        .threads-container { flex: 1; overflow-y: auto; }
        .thread-item { padding: 20px 25px; border-bottom: 1px solid rgba(255,255,255,0.02); cursor: pointer; transition: background 0.2s; display: flex; align-items: center; gap: 15px; }
        .thread-item:hover { background: rgba(255,255,255,0.02); }
        .thread-item.active { background: rgba(194,38,38,0.1); border-left: 4px solid #C22626; }
        .thread-avatar { width: 44px; height: 44px; border-radius: 12px; background: #222; display: flex; align-items: center; justify-content: center; font-family: 'Aclonica', sans-serif; color: #C22626; border: 1px solid rgba(255,255,255,0.05); flex-shrink: 0; }
        .thread-details { flex: 1; min-width: 0; }
        .thread-name { font-weight: 700; color: #fff; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .thread-type { font-size: 0.75rem; color: #666; text-transform: uppercase; letter-spacing: 1px; }
        .chat-main { display: flex; flex-direction: column; background: #1a1a1a; min-width: 0; }
        .chat-main-header { padding: 20px 30px; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: space-between; }
        .chat-messages { flex: 1; overflow-y: auto; padding: 30px; display: flex; flex-direction: column; gap: 20px; }
        .message { max-width: 60%; padding: 12px 18px; border-radius: 16px; font-size: 0.9rem; line-height: 1.5; word-break: break-word; }
        .message.admin { align-self: flex-end; background: #C22626; color: #fff; border-bottom-right-radius: 4px; }
        .message.customer, .message.rider { align-self: flex-start; background: #2a2a2a; color: #fff; border-bottom-left-radius: 4px; }
        .message-time { display: block; font-size: 0.7rem; opacity: 0.6; margin-top: 5px; text-align: right; }
        .chat-input-area { padding: 25px 30px; border-top: 1px solid rgba(255,255,255,0.05); display: flex; gap: 15px; }
        .chat-input { flex: 1; background: #222; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 12px 20px; color: #fff; outline: none; transition: border-color 0.2s; min-width: 0; }
        .chat-input:focus { border-color: #C22626; }
        .send-btn { background: #C22626; color: #fff; border: none; padding: 0 25px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: background 0.2s; }
        .send-btn:hover { background: #8B0A1E; }
        .empty-chat { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #444; text-align: center; padding: 24px; }
        .empty-chat i { font-size: 4rem; margin-bottom: 20px; }
        @media (max-width: 900px) {
            .support-chat-page { height: auto; min-height: calc(100vh - 58px); padding: 16px; overflow: visible; }
            .chat-layout { grid-template-columns: 1fr; height: auto; min-height: calc(100vh - 90px); }
            .thread-list { min-height: 260px; max-height: 38vh; border-right: none; border-bottom: 1px solid rgba(255,255,255,0.05); }
            .chat-main { min-height: 55vh; }
            .message { max-width: 82%; }
            .chat-input-area { padding: 18px; }
        }
    </style>
</head>
<body>
<div class="bg-dots"></div>
<div class="admin-layout">
    <aside class="sidebar" id="sidebar">
<?php $staffNavActive = 'chat'; require __DIR__ . '/staff-sidebar-nav.php'; ?>
    </aside>

    <div class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
                <div>
                    <div class="topbar-title">Support Chat</div>
                    <div class="topbar-breadcrumb">Staff <span>/</span> Support Messages</div>
                </div>
            </div>
            <div class="topbar-right">
                <div class="admin-avatar" style="background:linear-gradient(135deg,#f39c12,#e67e22);" title="<?= $staffName ?>">
                    <?= strtoupper(substr($_SESSION['user_name'] ?? 'S', 0, 1)) ?>
                </div>
            </div>
        </header>

        <main class="support-chat-page">
            <div class="chat-layout">
                <div class="thread-list">
                    <div class="thread-header">
                        <h2>Active Conversations</h2>
                    </div>
                    <div class="threads-container" id="threadsContainer"></div>
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
                        <div class="chat-messages" id="chatMessages"></div>
                        <div class="chat-input-area">
                            <input type="text" id="chatInput" class="chat-input" placeholder="Type a message...">
                            <button id="sendBtn" class="send-btn">Send</button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    if (sidebar) sidebar.classList.toggle('open');
}

function escHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, ch => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    }[ch]));
}

let currentPeer = null;

async function loadThreads() {
    try {
        const res = await fetch('../chat-api.php?action=get_active_threads');
        const data = await res.json();
        const container = document.getElementById('threadsContainer');

        if (data.success) {
            container.innerHTML = data.threads.map(t => {
                const isActive = currentPeer && currentPeer.id == t.peer_id && currentPeer.type == t.peer_type;
                const name = escHtml(t.name);
                const type = escHtml(t.peer_type);
                return `
                    <div class="thread-item ${isActive ? 'active' : ''}"
                         onclick="selectThread('${escHtml(t.peer_id)}', '${type}', '${name}')">
                        <div class="thread-avatar">${name.charAt(0) || '?'}</div>
                        <div class="thread-details">
                            <span class="thread-name">${name}</span>
                            <span class="thread-type">${type}</span>
                        </div>
                    </div>
                `;
            }).join('');
        }
    } catch (e) {
        console.error(e);
    }
}

async function selectThread(id, type, name) {
    currentPeer = { id, type, name };
    document.getElementById('emptyChat').style.display = 'none';
    document.getElementById('chatContent').style.display = 'flex';
    document.getElementById('activeName').textContent = name;
    document.getElementById('activeType').textContent = type;

    loadThreads();
    loadMessages();
}

async function loadMessages() {
    if (!currentPeer) return;
    try {
        const res = await fetch(`../chat-api.php?action=get_history&other_id=${encodeURIComponent(currentPeer.id)}&other_type=${encodeURIComponent(currentPeer.type)}`);
        const data = await res.json();
        const container = document.getElementById('chatMessages');

        if (data.success) {
            container.innerHTML = data.messages.map(m => `
                <div class="message ${escHtml(m.sender_type)}">
                    ${escHtml(m.message)}
                    <span class="message-time">${escHtml(m.timestamp)}</span>
                </div>
            `).join('');
            container.scrollTop = container.scrollHeight;
        }
    } catch (e) {
        console.error(e);
    }
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
        await fetch('../chat-api.php', { method: 'POST', body: formData });
        loadMessages();
    } catch (e) {
        console.error(e);
    }
}

document.getElementById('sendBtn').onclick = sendMessage;
document.getElementById('chatInput').onkeypress = e => {
    if (e.key === 'Enter') sendMessage();
};

const socket = io('http://localhost:3000');
socket.on('new-message', data => {
    if (currentPeer && data.senderId == currentPeer.id && data.sender == currentPeer.type) {
        loadMessages();
    } else {
        loadThreads();
    }
});

loadThreads();
setInterval(loadThreads, 10000);
</script>
<script src="staff-dashboard.js?v=<?= time() ?>"></script>
</body>
</html>
