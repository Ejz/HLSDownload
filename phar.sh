#!/usr/bin/env bash

this=`readlink -fe "$0"`
this_dir=`dirname "$this"`
cd "$this_dir"

echo $PATH
which phar-composer || { echo "phar-composer is not found in PATH"; exit 1; }
bin="hlsdownload.phar"

rm -rf build
rm -f vendor/ejz/functions/fonts/*
phar-composer build .
mkdir -p build
chmod a+x "$bin"
mv "$bin" build/"$bin"
