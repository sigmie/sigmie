FROM node:13

WORKDIR /home/root

RUN npm install -g laravel-echo-server

COPY laravel-echo-server.json /home/root/

COPY .env /home/root/

EXPOSE 6001

CMD ["/usr/local/bin/laravel-echo-server", "start", "--force"]
