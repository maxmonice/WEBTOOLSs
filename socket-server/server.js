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

    socket.on('disconnect', () => {
        console.log('User disconnected:', socket.id);
    });
});

const PORT = 3000;
http.listen(PORT, () => {
    console.log(`Socket.io server listening on *:${PORT}`);
});
