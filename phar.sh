#!/usr/bin/env bash

this=`readlink -fe "$0"`
this_dir=`dirname "$this"`
cd "$this_dir"

which phar-composer || { echo "phar-composer is not found in PATH"; exit 1; }
bin="hlsdownload.phar"

rm -rf build
mkdir build
cp composer.json build/
cp -r src/ build/
cp -r vendor/ build/
cp -r bin/ build/
cd build/

rm -f vendor/ejz/functions/fonts/*
> vendor/ejz/functions/ua.list.txt

if [ "$1" ]; then
    sed -i -e "s/__VERSION__/${1}/" bin/hlsdownload.php
fi
phar-composer build .
chmod a+x "$bin"
mv "$bin" ..
cd ..
rm -rf build/
mkdir build
mv "$bin" build/
