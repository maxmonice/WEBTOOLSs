const express = require('express');
const app = express();
const http = require('http').createServer(app);
const cors = require('cors');

// Enable CORS so the PHP pages can communicate with the Node server
app.use(cors());

const io = require('socket.io')(http, {
    cors: {
        origin: "*", // allow all origins (fine for dev/test)
        methods: ["GET", "POST"]
    }
});

// HTTP endpoint for PHP to emit socket events
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

app.get('/emit', (req, res) => {
    const { event, orderId, status } = req.query;
    if (event && orderId) {
        io.to(`order_${orderId}`).emit(event, { orderId: parseInt(orderId), status });
        io.to(`chat_${orderId}`).emit(event, { orderId: parseInt(orderId), status });
        console.log(`[HTTP→Socket] Emitted '${event}' for order ${orderId} with status: ${status}`);
        res.json({ success: true });
    } else {
        res.status(400).json({ error: 'Missing event or orderId' });
    }
});

io.on('connection', (socket) => {
    console.log('A user connected:', socket.id);

    // Customer joins a room specifically for their Order ID
    socket.on('track_order', (orderId) => {
        console.log(`Tracking request for Order: ${orderId}`);
        socket.join(`order_${orderId}`);
    });
    // Compatibility with customer pages using "join-order"
    socket.on('join-order', (orderId) => {
        console.log(`Join-order request for Order: ${orderId}`);
        socket.join(`order_${orderId}`);
    });

    // Rider sends a position update
    socket.on('rider_update', (data) => {
        console.log(`Rider moved for Order: ${data.order_id} to ${data.latitude}, ${data.longitude}`);
        // Broadcast the update to anyone in that order's room
        io.to(`order_${data.order_id}`).emit('location_update', data);
        io.to(`order_${data.order_id}`).emit('receive-location', {
            lat: data.latitude,
            lng: data.longitude
        });
    });

    // Compatibility with rider pages using "send-location"
    socket.on('send-location', (data) => {
        const orderId = data.orderId ?? data.order_id;
        const lat = data.lat ?? data.latitude;
        const lng = data.lng ?? data.longitude;
        if (!orderId || lat === undefined || lng === undefined) return;
        console.log(`Rider moved for Order: ${orderId} to ${lat}, ${lng}`);
        io.to(`order_${orderId}`).emit('receive-location', { lat, lng });
        io.to(`order_${orderId}`).emit('location_update', {
            order_id: orderId,
            latitude: lat,
            longitude: lng
        });
    });

    // Chat System
    socket.on('join-chat', (orderId) => {
        console.log(`User joined chat for Order: ${orderId}`);
        socket.join(`chat_${orderId}`);
    });

    socket.on('send-message', (data) => {
        const { orderId, sender, message, timestamp } = data;
        console.log(`New message for Order ${orderId} from ${sender}: ${message}`);
        // Emit to everyone in the chat room (including sender if they have multiple tabs)
        io.to(`chat_${orderId}`).emit('new-message', {
            sender,
            message,
            timestamp,
            orderId
        });
    });

    // Order Status Updates (rider accepts, delivers, etc.)
    socket.on('status-update', (data) => {
        const { orderId, status } = data;
        if (!orderId || !status) return;
        console.log(`Order ${orderId} status changed to: ${status}`);
        // Notify customer and any admin/staff in the order room
        io.to(`order_${orderId}`).emit('order-status-update', { orderId, status });
        // Also notify the chat room
        io.to(`chat_${orderId}`).emit('order-status-update', { orderId, status });
    });

    socket.on('disconnect', () => {
        console.log('User disconnected:', socket.id);
    });
});

const PORT = 3000;
http.listen(PORT, () => {
    console.log(`Socket.io server listening on *:${PORT}`);
});
