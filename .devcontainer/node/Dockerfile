FROM node:13

WORKDIR /home/root

RUN npm install -g laravel-echo-server

COPY node/laravel-echo-server.json . 

EXPOSE 6001

CMD ["/usr/local/bin/laravel-echo-server", "start", "--force"]
