FROM php:7.4-cli
COPY . /usr/src/builder
COPY php.ini /usr/local/etc/php
COPY phnet /bin
WORKDIR /usr/src/builder
RUN php build.php
