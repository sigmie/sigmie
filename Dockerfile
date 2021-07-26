# Install docker engine
FROM debian:buster

# avoid errro message
ENV DEBIAN_FRONTEND noninteractive

# app directory
WORKDIR /var/www/sigmie

# time zone
ENV TZ=UTC

# xdebug mode 
ENV XDEBUG_MODE=coverage

# setup timezone
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# install
RUN apt-get update && \
    apt-get install -y git unzip zip vim wget curl supervisor curl sudo && \
    apt -y install lsb-release apt-transport-https ca-certificates && \
    wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg && \
    echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" | tee /etc/apt/sources.list.d/php.list && \
    apt-get update && \
    apt-get install -y php8.0 php8.0-dev php8.0-fpm php8.0-zip php8.0-dom php8.0-intl php8.0-mbstring php8.0-simplexml php8.0-xml php8.0-common php8.0-opcache php8.0-cli php8.0-gd php8.0-curl php8.0-bcmath && \
    apt-get autoremove -y && apt-get clean -y && rm -rf /var/lib/apt/lists/*

# install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# command
CMD sleep infinity
