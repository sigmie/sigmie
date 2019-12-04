FROM ubuntu:18.04

WORKDIR /var/www/app

ENV TZ=Europe/Berlin

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN apt-get update

RUN apt-get install -y git unzip zip vim wget curl sudo zsh net-tools

RUN apt-get install -y software-properties-common

RUN apt-get install -y default-mysql-client

RUN add-apt-repository ppa:ondrej/php

RUN apt-get install -y php7.1 php7.1-zip php7.1-dom php7.1-intl php7.1-mbstring php7.1-simplexml php7.1-xml php7.1-common php7.1-opcache php7.1-mcrypt php7.1-cli php7.1-gd php7.1-curl php7.1-mysql php7.1-redis php7.1-fpm php7.1-bcmath

RUN apt-get update --fix-missing

RUN apt-get install -y npm

RUN apt-get install -y gnupg2 ca-certificates lsb-release apt-transport-https

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN apt-get -y install dirmngr apt-transport-https lsb-release ca-certificates

RUN curl -sL https://deb.nodesource.com/setup_10.x | bash

RUN chown -R www-data:www-data /var/www

RUN usermod -s /bin/zsh www-data

RUN usermod -a -G sudo www-data

RUN echo '%sudo ALL=(ALL) NOPASSWD:ALL' >> /etc/sudoers

USER www-data

RUN set -uex; \
    wget https://raw.githubusercontent.com/robbyrussell/oh-my-zsh/master/tools/install.sh; \
    sh ./install.sh; \
    rm ./install.sh

RUN cp ~/.oh-my-zsh/templates/zshrc.zsh-template ~/.zshrc

RUN sudo chsh -s $(which zsh)

CMD ["sleep","infinity"]
