#!/usr/bin/env bash

cd "`dirname "$0"`"

# Install composer itself
if [ ! -f "composer.phar" ]; then
    echo "Installing Composer .."
    curl -sS 'https://getcomposer.org/installer' | php
    chmod a+x composer.phar
    if [ "$EUID" -eq "0" ]; then
        echo "Moving to /usr/local/bin .."
        cp composer.phar /usr/local/bin/composer
    fi
fi

# Install PHPUnit
if [ ! -f "phpunit.phar" ]; then
    echo "Installing PHPUnit .."
    ver="5.7"
    wget "https://phar.phpunit.de/phpunit-${ver}.phar" -O phpunit.phar
    chmod a+x phpunit.phar
    if [ "$EUID" -eq "0" ]; then
        echo "Moving to /usr/local/bin .."
        cp phpunit.phar /usr/local/bin/phpunit
    fi
fi

# Composer install/update
if [ -d cgi ]; then
    cd cgi
    ../composer.phar config --global github-protocols https
    [ "$GH_TOKEN" ] && ../composer.phar config -g github-oauth.github.com "$GH_TOKEN"
    ../composer.phar install
    ../composer.phar update
    cd ..
else
    ./composer.phar config --global github-protocols https
    [ "$GH_TOKEN" ] && ./composer.phar config -g github-oauth.github.com "$GH_TOKEN"
    ./composer.phar install
    ./composer.phar update
fi

# Patch NormalizerFormatter.php
p1="cgi/vendor/monolog/monolog/src/Monolog/Formatter/NormalizerFormatter.php"
p2="cgi/patch/normalizer.patch"
if [ -f "$p1" -a -f "$p2" ]; then
    patch "$p1" "$p2"
fi

# Patch ErrorHandler.php
p1="cgi/vendor/monolog/monolog/src/Monolog/ErrorHandler.php"
p2="cgi/patch/errorhandler.patch"
if [ -f "$p1" -a -f "$p2" ]; then
    patch "$p1" "$p2"
fi

# SCSS
if [ -d "www/css" ]; then
    cd ../www/css
    if [ -f sass.pid ] && kill `cat sass.pid` >/dev/null 2>&1; then
        echo "Killed previous sass process .. PID = "`cat sass.pid`
    fi
    rm -f *.css *.css.map
    nohup sass --watch .:. --style compressed >sass.log 2>&1 &
    echo "$!" >sass.pid
    sleep 5
    cd -
fi

[ -x "js.min.sh" ] && ./js.min.sh
[ -x "css.min.sh" ] && ./css.min.sh

if [ -d cgi ]; then
    mkdir -p cgi/logs
    mkdir -p cgi/stat
fi

[ -x "cgi/cron.sh" ] && ./cgi/cron.sh
exit 0
