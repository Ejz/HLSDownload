#!/usr/bin/env bash

this=`readlink -fe "$0"`
this_dir=`dirname "$this"`
cd "$this_dir"

if [ ! -f "phar-composer.phar" ]; then
    wget "https://github.com/clue/phar-composer/releases/download/v1.0.0/phar-composer.phar"
    chmod a+x phar-composer.phar
    test -f "phar-composer.phar" || exit 1
fi

./phar-composer.phar build .
mkdir -p build
chmod a+x myrepo.phar
mv myrepo.phar build/"$1"
