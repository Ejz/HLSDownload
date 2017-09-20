#!/usr/bin/env bash

this=`readlink -fe "$0"`
this_dir=`dirname "$this"`
cd "$this_dir"

set -e
SQL_HOST=no ./deploy.sh --defaults --test
./deploy.sh -D -e ./phar.sh
./gitci.sh "build/hlsdownload.phar" "Update build [skip ci]"
