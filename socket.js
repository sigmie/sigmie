var app = require('express')();
var http = require('http').createServer(app);
var io = require('socket.io')(http);
var Redis = require("ioredis");
var redis = new Redis(6379, "redis");

io.on('connection', function (socket) {
    console.log('a user connected');
});

http.listen(6001, function () {
    console.log('listening on *:6001');
});
