#!/usr/bin/env bash

this=`readlink -fe "$0"`
this_dir=`dirname "$this"`
cd "$this_dir"

set -e
SQL_HOST=no ./deploy.sh --defaults --test
./deploy.sh -D -e ./phar.sh
set +e
if ./gitci.sh --is-release; then
    next=`./gitci.sh --next-tag`
    cp build/hlsdownload.phar build/hlsdownload-"$next".phar
    ./gitci.sh "build/hlsdownload.phar build/hlsdownload-{$next}.phar" "Release [skip ci]"
else
    ./gitci.sh
fi
