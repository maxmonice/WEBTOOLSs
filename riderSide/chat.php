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
<title>Luke's Seafood — Chat</title>
<link href="https://fonts.googleapis.com/css2?family=Aclonica&family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="shared.css">
<link rel="stylesheet" href="chat.css">
</head>
<body>
<div class="phone-shell">

  <div class="app-header">
    <div class="header-left">
      <div class="app-logo">Messages</div>
      <div class="app-subtitle">Active Chats</div>
    </div>
    <div class="rider-badge"><div class="dot"></div>Online</div>
  </div>

  <div class="page-content" id="chat-list-page">
    <div class="chat-search">
      <i class="fas fa-search"></i>
      <input type="text" placeholder="Search orders or customers...">
    </div>
    
    <div class="threads-container">
      <div id="chat-threads" class="chat-threads">
        <!-- Populated by JS -->
        <div class="loading-chats">
          <i class="fas fa-spinner fa-spin"></i>
          <span>Loading messages...</span>
        </div>
      </div>

      <div class="empty-chats" id="empty-chats" style="display:none;">
        <div class="empty-icon"><i class="fas fa-comments"></i></div>
        <h3>No active chats</h3>
        <p>Accept an order to start chatting with customers.</p>
      </div>
    </div>
  </div>

  <!-- CHAT WINDOW (Hidden by default, slides in) -->
  <div class="chat-window" id="chat-window">
    <div class="chat-window-header">
      <button class="back-btn" onclick="closeChat()"><i class="fas fa-chevron-left"></i></button>
      <div class="chat-user-info">
        <div class="chat-user-name" id="chat-customer-name">Customer Name</div>
        <div class="chat-meta">
          <span class="chat-order-id" id="chat-order-id">#ORD-0000</span>
          <span class="chat-divider">•</span>
          <span class="chat-customer-phone" id="chat-customer-phone">0917...</span>
        </div>
      </div>
      <div class="chat-actions">
        <a href="tel:#" id="call-btn" class="chat-action-btn"><i class="fas fa-phone"></i></a>
      </div>
    </div>
    
    <div class="chat-messages" id="chat-messages">
      <!-- Messages will appear here -->
    </div>

    <div class="chat-input-area">
      <div class="input-wrapper">
        <textarea id="message-input" placeholder="Type a message..." rows="1"></textarea>
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
    <button class="nav-item active">
      <div class="nav-dot" id="chat-badge"></div>
      <i class="fas fa-comment-dots"></i><span>Chat</span>
    </button>
    <button class="nav-item" onclick="window.location.href='history.php'">
      <i class="fas fa-history"></i><span>History</span>
    </button>
    <button class="nav-item" onclick="window.location.href='account.php'">
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
<script src="chat.js?v=<?= time() ?>"></script>
</body>
</html>
