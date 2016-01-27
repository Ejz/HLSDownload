#!/usr/bin/env bash

NAME="hls-downloader-cli"
PREFIX=${1:-/usr/local}
BIN="${PREFIX}/bin"

if [ ! -d "${PREFIX}" ]; then
    echo 1>&2 "Directory ${PREFIX} does not exist!"
    exit
fi

SOURCE=`pwd`
DESTINATION="${PREFIX}/${NAME}"

echo "Install ${SOURCE} to ${DESTINATION} ... [OK]"
rm -rf "${DESTINATION}"
rm -f "${BIN}/${NAME}"

if [ -d "${DESTINATION}" ]; then
    echo 1>&2 "Destination (${DESTINATION}) already exists!"
    exit
fi

cp -r "${SOURCE}" "${DESTINATION}"
ln -s "${DESTINATION}/${NAME}" "$BIN/${NAME}"

echo "Finalize ... [OK]"

chmod -R 755 "${DESTINATION}/${NAME}"
chmod 755 "${BIN}/${NAME}"
