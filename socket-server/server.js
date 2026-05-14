const express = require('express');
const app = express();
const http = require('http').createServer(app);
const cors = require('cors');

// Enable CORS so the PHP pages can communicate with the Node server.
app.use(cors());

const io = require('socket.io')(http, {
    cors: {
        origin: "*",
        methods: ["GET", "POST"]
    }
});

// HTTP endpoint for PHP to emit socket events.
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

app.get('/emit', (req, res) => {
    const { event, orderId, status, data } = req.query;
    if (!event) {
        res.status(400).json({ error: 'Missing event' });
        return;
    }

    if (data) {
        let payload;
        try {
            payload = JSON.parse(data);
        } catch (err) {
            res.status(400).json({ error: 'Invalid data payload' });
            return;
        }

        const roomId = payload.roomId || payload.orderId;
        if (roomId) {
            io.to(`chat_${roomId}`).emit(event, payload);
        }
        if (payload.orderId) {
            io.to(`order_${payload.orderId}`).emit(event, payload);
        }

        console.log(`[HTTP->Socket] Emitted '${event}' for ${roomId || 'payload'}`);
        res.json({ success: true });
        return;
    }

    if (orderId) {
        const payload = { orderId: parseInt(orderId), status };
        io.to(`order_${orderId}`).emit(event, payload);
        io.to(`chat_${orderId}`).emit(event, payload);
        console.log(`[HTTP->Socket] Emitted '${event}' for order ${orderId} with status: ${status}`);
        res.json({ success: true });
        return;
    }

    res.status(400).json({ error: 'Missing orderId or data' });
});

io.on('connection', (socket) => {
    console.log('A user connected:', socket.id);

    // Customer joins a room specifically for their order ID.
    socket.on('track_order', (orderId) => {
        console.log(`Tracking request for Order: ${orderId}`);
        socket.join(`order_${orderId}`);
    });

    // Compatibility with customer pages using "join-order".
    socket.on('join-order', (orderId) => {
        console.log(`Join-order request for Order: ${orderId}`);
        socket.join(`order_${orderId}`);
    });

    // Rider sends a position update.
    socket.on('rider_update', (data) => {
        console.log(`Rider moved for Order: ${data.order_id} to ${data.latitude}, ${data.longitude}`);
        io.to(`order_${data.order_id}`).emit('location_update', data);
        io.to(`order_${data.order_id}`).emit('receive-location', {
            lat: data.latitude,
            lng: data.longitude
        });
    });

    // Compatibility with rider pages using "send-location".
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

    // Chat System.
    socket.on('join-chat', (roomId) => {
        console.log(`User joined chat: ${roomId}`);
        socket.join(`chat_${roomId}`);
    });

    socket.on('send-message', (data) => {
        const { orderId, roomId, sender, message, timestamp } = data;
        const targetRoom = roomId || orderId;
        if (!targetRoom) return;
        console.log(`New message for chat ${targetRoom} from ${sender}: ${message}`);
        io.to(`chat_${targetRoom}`).emit('new-message', {
            ...data,
            timestamp
        });
    });

    // Order Status Updates (rider accepts, delivers, etc.).
    socket.on('status-update', (data) => {
        const { orderId, status } = data;
        if (!orderId || !status) return;
        console.log(`Order ${orderId} status changed to: ${status}`);
        io.to(`order_${orderId}`).emit('order-status-update', { orderId, status });
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
