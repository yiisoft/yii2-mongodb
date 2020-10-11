#!/bin/sh -e
#
# install mongodb

# MongoDB Server :

echo "MongoDB Server version:"
mongod --version

mongo yii2test --eval 'db.createUser({user: "travis", pwd: "test", roles: ["readWrite", "dbAdmin"]});'

# PHP Extension :

if (php --version | grep -i HipHop > /dev/null); then
    echo "skip PHP extension installation on HHVM"
else

    #https://docs.mongodb.com/drivers/php#language-compatibility
    if [ $(php -r "echo version_compare(phpversion(),'5.5','<') ? 1 : 0;") == 1 ]
    then
        pecl install -f mongodb-1.3.4
    elif [ $(php -r "echo version_compare(phpversion(),'5.5','>=') && version_compare(phpversion(),'7.2','<=') ? 1 : 0;") == 1 ]
    then
        pecl install -f mongodb-1.4.4
    elif [ $(php -r "echo version_compare(phpversion(),'7.3','>=') && version_compare(phpversion(),'7.4','<') ? 1 : 0;") == 1 ]
    then
        pecl install -f mongodb-1.5.5
    elif [ $(php -r "echo version_compare(phpversion(),'7.4','>=') ? 1 : 0;") == 1 ]
    then
        pecl install -f mongodb
    fi
    echo "extension = mongodb.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
    echo "MongoDB PHP Extension version:"
    php -i |grep mongodb -4 |grep -2 version
fi

#cat /etc/mongodb.conf
