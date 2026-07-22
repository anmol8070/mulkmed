const express = require('express');
const app = express();
const http = require('http').createServer(app);

const io = require('socket.io')(http,{
    cors:{
        origin:"*"
    }
});

io.on('connection', (socket) => {
    console.log('User connected:', socket.id);

    socket.on('send_message', (data) => {
        console.log(data);

        io.emit('receive_message', data);
    });

    socket.on('disconnect', () => {
        console.log('User disconnected');
    });
});

http.listen(3001, () => {
    console.log('Socket running on port 3001');
});