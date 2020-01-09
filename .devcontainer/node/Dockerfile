FROM node:13

RUN mkdir /app

COPY . /app/

RUN cd /app && npm install

EXPOSE 3000

CMD ["node","/app/serve.js"]
