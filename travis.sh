#!/usr/bin/env bash

this=`readlink -fe "$0"`
this_dir=`dirname "$this"`
cd "$this_dir"

SQL_HOST=no ./deploy.sh --defaults --test
./phar.sh "hlsdownload.phar"
./gitci.sh "build/hlsdownload.phar" "Update build [skip ci]"
