FROM php:7.4-cli
RUN  apt-get -y install dpkg
COPY . /usr/src/builder
COPY php.ini /usr/local/etc/php
WORKDIR /usr/src/builder
RUN php build.php
WORKDIR /
COPY phnet /phnet/usr/bin
RUN chmod -R 755 /phnet  \
    && dpkg-deb --build phnet  \
    && mv phnet.deb phnet.$(cat /phnet_version)_all.deb  \
    && dpkg -i phnet.$(cat /phnet_version)_all.deb


