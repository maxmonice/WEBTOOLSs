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
    
    if (threads.length === 0) {
        container.style.display = 'none';
        emptyState.style.display = 'flex';
        return;
    }

    container.style.display = 'block';
    emptyState.style.display = 'none';
    container.innerHTML = threads.map(thread => `
        <div class="chat-thread-item" onclick="openChat('${thread.id}', '${thread.customer_name}', '${thread.order_num}', '${thread.customer_phone}')">
            <div class="thread-avatar">${thread.customer_name.charAt(0)}</div>
            <div class="thread-info">
                <div class="thread-header">
                    <span class="thread-name">${thread.customer_name}</span>
                    <span class="thread-time">Just now</span>
                </div>
                <div class="thread-preview">
                    <span>Order ${thread.order_num}</span>
                    <!-- <span class="unread-badge">1</span> -->
                </div>
            </div>
        </div>
    `).join('');
}

function openChat(orderId, customerName, orderNum, phone) {
    currentOrderId = orderId;
    document.getElementById('chat-customer-name').textContent = customerName;
    document.getElementById('chat-order-id').textContent = orderNum;
    document.getElementById('chat-customer-phone').textContent = phone || 'No Number';
    document.getElementById('call-btn').href = `tel:${phone}`;
    
    // Hide badge
    const badge = document.getElementById('chat-badge');
    if (badge) badge.style.display = 'none';

    const chatWindow = document.getElementById('chat-window');
    chatWindow.classList.add('active');
    
    // Clear previous messages
    document.getElementById('chat-messages').innerHTML = '';
    
    // Join socket room
    socket.emit('join-chat', orderId);
    
    // Load chat history (if any - simulated for now)
    loadChatHistory(orderId);
}

function closeChat() {
    currentOrderId = null;
    document.getElementById('chat-window').classList.remove('active');
}

function sendMessage() {
    const input = document.getElementById('message-input');
    const message = input.value.trim();
    
    if (!message || !currentOrderId) return;
    
    const data = {
        orderId: currentOrderId,
        sender: 'rider',
        message: message,
        timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
    };
    
    socket.emit('send-message', data);
    
    input.value = '';
    input.style.height = 'auto';
}

function appendMessage(data) {
    const container = document.getElementById('chat-messages');
    const div = document.createElement('div');
    div.className = `message ${data.sender}`;
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

function loadChatHistory(orderId) {
    // In a real app, you'd fetch from a database
    // For this demo, we'll just show a greeting
    setTimeout(() => {
        appendMessage({
            sender: 'customer',
            message: "Hello! I'm waiting for my order.",
            timestamp: "10:00 AM"
        });
        scrollToBottom();
    }, 500);
}

function updateThreadPreview(data) {
    // This would find the thread in the list and update the preview text
    console.log('Update thread preview for', data.orderId);
}
