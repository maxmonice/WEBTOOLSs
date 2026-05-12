<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['rider_id'])) {
    header('Location: login.php');
    exit;
}
$riderName = htmlspecialchars($_SESSION['rider_name'] ?? 'Rider');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Luke's Seafood — Admin Support</title>
<link href="https://fonts.googleapis.com/css2?family=Aclonica&family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="shared.css">
<link rel="stylesheet" href="chat.css">
<style>
  .admin-chat-header {
    background: var(--red-deep);
    padding: 15px 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    position: relative;
    z-index: 10;
  }
  .admin-avatar {
    width: 40px; height: 40px; border-radius: 12px;
    background: #fff; display: flex; align-items: center; justify-content: center;
    color: var(--red); font-size: 1.2rem;
  }
  .admin-info h3 { font-size: 0.95rem; font-weight: 700; margin: 0; }
  .admin-info p { font-size: 0.7rem; opacity: 0.7; margin: 0; }
  
  .chat-container {
    flex: 1; display: flex; flex-direction: column;
    overflow: hidden; background: var(--chat-bg);
  }
  
  .light-theme .admin-chat-header { background: var(--red); }
  .light-theme .chat-container { background: #f8f9fa; }
</style>
</head>
<body>
<div class="phone-shell">

  <div class="admin-chat-header">
    <button class="back-btn" onclick="window.location.href='account.php'"><i class="fas fa-chevron-left"></i></button>
    <div class="admin-avatar"><i class="fas fa-user-shield"></i></div>
    <div class="admin-info">
      <h3>Admin Support</h3>
      <p>Always active for riders</p>
    </div>
  </div>

  <div class="chat-container">
    <div class="chat-messages" id="chat-messages">
      <div class="message customer">
        Hello! How can we help you today?
        <span class="message-time">System</span>
      </div>
    </div>

    <div class="chat-input-area">
      <div class="input-wrapper">
        <textarea id="message-input" placeholder="Type your concern..." rows="1"></textarea>
        <button id="send-btn" class="send-btn"><i class="fas fa-paper-plane"></i></button>
      </div>
    </div>
  </div>

  <!-- Bottom Nav -->
  <div class="bottom-nav">
    <button class="nav-item" onclick="window.location.href='orders.php'">
      <i class="fas fa-clipboard-list"></i><span>Orders</span>
    </button>
    <button class="nav-item" onclick="window.location.href='map.php'">
      <i class="fas fa-map-marked-alt"></i><span>Map</span>
    </button>
    <button class="nav-item" onclick="window.location.href='chat.php'">
      <i class="fas fa-comment-dots"></i><span>Chat</span>
    </button>
    <button class="nav-item" onclick="window.location.href='history.php'">
      <i class="fas fa-history"></i><span>History</span>
    </button>
    <button class="nav-item active" onclick="window.location.href='account.php'">
      <i class="fas fa-user-circle"></i><span>Account</span>
    </button>
  </div>

</div>

<script>
  const RIDER_ID = '<?= $_SESSION['rider_id'] ?>';
  const RIDER_NAME = '<?= $riderName ?>';
</script>
<script src="https://cdn.socket.io/4.5.4/socket.io.min.js"></script>
<script src="theme-manager.js"></script>
<script>
  // Simple chat logic for Admin
  const socket = io('https://your-socket-server.com'); // Placeholder
  const messagesContainer = document.getElementById('chat-messages');
  const messageInput = document.getElementById('message-input');
  const sendBtn = document.getElementById('send-btn');

  function appendMessage(text, type) {
    const msg = document.createElement('div');
    msg.className = `message ${type}`;
    msg.innerHTML = `${text}<span class="message-time">${new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>`;
    messagesContainer.appendChild(msg);
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
  }

  sendBtn.onclick = () => {
    const text = messageInput.value.trim();
    if(!text) return;
    appendMessage(text, 'rider');
    messageInput.value = '';
    // socket.emit('admin_message', { riderId: RIDER_ID, text });
  };

  messageInput.onkeydown = (e) => {
    if(e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendBtn.click();
    }
  };
</script>
</body>
</html>
