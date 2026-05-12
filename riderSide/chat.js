// riderSide/chat.js
let socket;
let currentOrderId = null;
let activeChats = [];

document.addEventListener('DOMContentLoaded', () => {
    initSocket();
    fetchActiveChats();
    
    const messageInput = document.getElementById('message-input');
    const sendBtn = document.getElementById('send-btn');

    sendBtn.addEventListener('click', sendMessage);
    messageInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    // Auto-resize textarea
    messageInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });
});

function initSocket() {
    socket = io('http://localhost:3000');

    socket.on('connect', () => {
        console.log('Connected to socket server');
    });

    socket.on('new-message', (data) => {
        if (data.orderId === currentOrderId) {
            appendMessage(data);
            scrollToBottom();
        } else {
            // Show red dot on chat badge
            const badge = document.getElementById('chat-badge');
            if (badge) {
                badge.style.display = 'block';
            }
            // Update unread count in thread list
            updateThreadPreview(data);
        }
    });
}

async function fetchActiveChats() {
    try {
        const response = await fetch('rider-orders-api.php?action=get_my_active_chats');
        const data = await response.json();
        
        if (data.success) {
            renderThreads(data.orders);
        }
    } catch (err) {
        console.error('Error fetching chats:', err);
    }
}

function renderThreads(threads) {
    const container = document.getElementById('chat-threads');
    const emptyState = document.getElementById('empty-chats');
    
    // Always include Admin Support thread
    let html = `
        <div class="chat-thread-item admin-thread" onclick="openChat(null, 'Admin Support', 'Support', '—', 'admin', 1)">
            <div class="thread-avatar admin"><i class="fas fa-headset"></i></div>
            <div class="thread-info">
                <div class="thread-header">
                    <span class="thread-name">Luke's Admin Support</span>
                    <span class="thread-time">Always available</span>
                </div>
                <div class="thread-preview">Chat with our support team.</div>
            </div>
        </div>
    `;

    if (threads.length > 0) {
        html += threads.map(thread => `
            <div class="chat-thread-item" onclick="openChat('${thread.id}', '${thread.customer_name}', '${thread.order_num}', '${thread.customer_phone}', 'customer', '${thread.user_id || 0}')">
                <div class="thread-avatar">${thread.customer_name.charAt(0)}</div>
                <div class="thread-info">
                    <div class="thread-header">
                        <span class="thread-name">${thread.customer_name}</span>
                        <span class="thread-time">Active</span>
                    </div>
                    <div class="thread-preview">
                        <span>Order ${thread.order_num}</span>
                    </div>
                </div>
            </div>
        `).join('');
    }

    container.style.display = 'block';
    emptyState.style.display = 'none';
    container.innerHTML = html;
}

let currentTargetType = 'customer';
let currentTargetId = null;

function openChat(orderId, customerName, orderNum, phone, targetType = 'customer', targetId = null) {
    currentOrderId = orderId;
    currentTargetType = targetType;
    currentTargetId = targetId;

    document.getElementById('chat-customer-name').textContent = customerName;
    document.getElementById('chat-order-id').textContent = orderNum;
    document.getElementById('chat-customer-phone').textContent = phone || '—';
    document.getElementById('call-btn').style.display = phone && phone !== '—' ? 'flex' : 'none';
    if (phone) document.getElementById('call-btn').href = `tel:${phone}`;
    
    // Hide badge
    const badge = document.getElementById('chat-badge');
    if (badge) badge.style.display = 'none';

    const chatWindow = document.getElementById('chat-window');
    chatWindow.classList.add('active');
    
    // Clear previous messages
    const msgContainer = document.getElementById('chat-messages');
    msgContainer.innerHTML = '<div class="loading-messages"><i class="fas fa-spinner fa-spin"></i></div>';
    
    // Join socket room
    if (orderId) socket.emit('join-chat', orderId);
    
    // Load chat history
    loadChatHistory(orderId, targetId, targetType);
}

function closeChat() {
    currentOrderId = null;
    document.getElementById('chat-window').classList.remove('active');
}

async function sendMessage() {
    const input = document.getElementById('message-input');
    const message = input.value.trim();
    
    if (!message) return;
    
    const formData = new FormData();
    formData.append('action', 'send_message');
    formData.append('message', message);
    if (currentOrderId && currentOrderId !== 'null') formData.append('order_id', currentOrderId);
    formData.append('receiver_id', currentTargetId);
    formData.append('receiver_type', currentTargetType);

    try {
        const response = await fetch('../chat-api.php', { method: 'POST', body: formData });
        const data = await response.json();
        
        if (data.success) {
            appendMessage({
                sender_type: 'rider',
                message: message,
                timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
            });
            scrollToBottom();
            input.value = '';
            input.style.height = 'auto';
        }
    } catch (err) {
        console.error('Error sending message:', err);
    }
}

function appendMessage(data) {
    const container = document.getElementById('chat-messages');
    const div = document.createElement('div');
    const isMe = data.sender_type === 'rider';
    div.className = `message ${isMe ? 'rider' : data.sender_type}`;
    div.innerHTML = `
        ${data.message}
        <span class="message-time">${data.timestamp}</span>
    `;
    container.appendChild(div);
}

function scrollToBottom() {
    const container = document.getElementById('chat-messages');
    container.scrollTop = container.scrollHeight;
}

async function loadChatHistory(orderId, targetId, targetType) {
    try {
        const url = `../chat-api.php?action=get_history&order_id=${orderId || ''}&other_id=${targetId || ''}&other_type=${targetType}`;
        const response = await fetch(url);
        const data = await response.json();
        
        const container = document.getElementById('chat-messages');
        container.innerHTML = '';
        
        if (data.success && data.messages.length > 0) {
            data.messages.forEach(msg => appendMessage(msg));
        } else {
            container.innerHTML = '<div class="empty-chat-hint">No messages yet. Start a conversation!</div>';
        }
        scrollToBottom();
    } catch (err) {
        console.error('Error loading history:', err);
    }
}

function updateThreadPreview(data) {
    // This would find the thread in the list and update the preview text
    console.log('Update thread preview for', data.orderId);
}
