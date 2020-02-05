FROM nginx:1.17

RUN apt-get update

RUN apt-get install -y openssl

RUN touch /etc/nginx/conf.d/csr.txt

RUN mkdir /etc/nginx/ssl

RUN openssl dhparam -out /etc/nginx/ssl/dhparam.pem 512

RUN echo '[ req ]'                                     >> csr.txt
RUN echo 'distinguished_name="req_distinguished_name"' >> csr.txt
RUN echo 'prompt="no"'                                 >> csr.txt
RUN echo ''                                            >> csr.txt
RUN echo '[req_distinguished_name]'                    >> csr.txt
RUN echo 'C="DE"'                                      >> csr.txt
RUN echo 'ST="NRW"'                                    >> csr.txt
RUN echo 'L="Germany"'                                 >> csr.txt
RUN echo 'ST="MOS-Sigma"'                              >> csr.txt
RUN echo 'O="MOS-Sigma"'                               >> csr.txt
RUN echo 'CN="www.mos-sigma.com"'                      >> csr.txt

RUN openssl req -config csr.txt -newkey rsa:2048 -new -nodes -x509 -days 3650 -keyout key.pem -out cert.pem

RUN mv cert.pem /etc/nginx/ssl/fullchain.pem

RUN mv key.pem /etc/nginx/ssl/privkey.pem

COPY .devcontainer/nginx/site.conf /etc/nginx/conf.d/default.conf
