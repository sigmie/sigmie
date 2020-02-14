FROM node:13

# set work directory
WORKDIR /home/root

# install laravel echo
RUN npm install -g laravel-echo-server

# copy the echo config
COPY echo/laravel-echo-server.json .

# expose default echo port
EXPOSE 6001

CMD ["/usr/local/bin/laravel-echo-server", "start", "--force"]
