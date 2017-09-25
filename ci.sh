#!/usr/bin/env bash

this=`readlink -fe "$0"`
this_dir=`dirname "$this"`
cd "$this_dir"

SQL_HOST=no ./deploy.sh --defaults --test || exit 1
./deploy.sh -D -e ./phar.sh
if ./gitci.sh --is-release; then
    next=`./gitci.sh --next-tag`
    ./deploy.sh -D -e ./phar.sh "$next" || exit 1
    cp build/hlsdownload.phar build/hlsdownload-"$next".phar
    ./gitci.sh "build/hlsdownload.phar build/hlsdownload-${next}.phar" "Release [skip ci]"
else
    ./deploy.sh -D -e ./phar.sh || exit 1
    ./gitci.sh
fi
