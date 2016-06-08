#!/bin/sh -e
#
# install mongodb

pecl install -f mongodb

echo "extension = mongodb.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini

echo "MongoDB Server version:"
mongod --version

echo "MongoDB PHP Extension version:"
php -i |grep mongodb -4 |grep -2 Version

cat /etc/mongodb.conf
