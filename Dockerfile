FROM php:7.4-cli
COPY . /usr/src/builder
COPY php.ini /usr/local/etc/php
WORKDIR /usr/src/builder
RUN php build.php
COPY phnet /usr/src/builder/bin
RUN link /usr/src/builder/phnet /bin/phnet
RUN chmod +x /bin/phnet


