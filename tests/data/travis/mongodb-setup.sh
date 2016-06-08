#!/bin/sh -e
#
# install mongodb

# MongoDB Server :

sudo apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv EA312927
echo "deb http://repo.mongodb.org/apt/ubuntu precise/mongodb-org/3.2 multiverse" | sudo tee /etc/apt/sources.list.d/mongodb-org-3.2.list
sudo apt-get update -qq
sudo apt-get install -y mongodb-org

sudo service mongod start

echo "MongoDB Server version:"
mongod --version

mongo yii2test --eval 'db.createUser({user: "travis", pwd: "test", roles: ["readWrite", "dbAdmin"]});'

# PHP Extension :

pecl install -f mongodb

echo "extension = mongodb.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini

echo "MongoDB PHP Extension version:"
php -i |grep mongodb -4 |grep -2 Version

cat /etc/mongodb.conf
