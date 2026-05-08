const app = require('express')();
const http = require('http').createServer(app);
const io = require('socket.io')(http, {
    cors: { origin: "*" } // This allows your PHP pages to talk to this server
});

io.on('connection', (socket) => {
    console.log('A user connected');

    // When a customer opens their tracking page, they join a "room" for their order
    socket.on('join-order', (orderId) => {
        socket.join(`order_${orderId}`);
        console.log(`User joined room for Order #${orderId}`);
    });

    // When the Rider moves, they send their location to the server
    socket.on('send-location', (data) => {
        // Broadcast that location ONLY to the customer in that order's room
        io.to(`order_${data.orderId}`).emit('receive-location', {
            lat: data.lat,
            lng: data.lng
        });
        console.log(`Rider moved for Order #${data.orderId}: ${data.lat}, ${data.lng}`);
    });

    socket.on('disconnect', () => {
        console.log('User disconnected');
    });
});

http.listen(3000, () => {
    console.log('Tracking Server is running on http://localhost:3000');
});
