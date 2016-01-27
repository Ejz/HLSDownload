#!/usr/bin/env bash

NAME="hls-downloader"
TTT=$(mktemp -d) && cd "${TTT}"
curl -sS 'https://getcomposer.org/installer' | php
php composer.phar require "ejz/${NAME}:~1.0"
cd "vendor/ejz/${NAME}/"
curl -sS 'https://getcomposer.org/installer' | php
php composer.phar install
chmod a+x install.sh
./install.sh "${1}"
cd - && rm -rf "${TTT}"
