var app = require('express')()
var http = require('http').createServer(app)
var io = require('socket.io')(http)
var Redis = require('ioredis')
var redis = new Redis(6379, 'redis')
var publisher = new Redis(6379, 'redis')

redis.psubscribe('*', function (err, count) {
  console.log('Message recieved')

  publisher.publish('test-channel', 'Hello world!')
})

redis.on('pmessage', function (channel, message) {
  console.log(channel)
  console.log(message)

  io.emit(channel, message)
})

redis.on('message', function (channel, message) {
  console.log(message)
  io.emit('test-channel', 'foo:bar')
})

io.on('connection', function (socket) {
  console.log('a user connected')
  socket.broadcast.emit('test-channel', 'lore:ipsum')
})

http.listen(6001, function () {
  console.log('listening on *:6001')
})
