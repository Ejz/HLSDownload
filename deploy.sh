#!/usr/bin/env bash

this=`readlink -fe "$0"`
this_dir=`dirname "$this"`
cd "$this_dir"
prefix=`basename "$this_dir"`
prefix=`echo "$prefix" | awk '{print tolower($0)}'`
while test "$#" -gt 0; do
    case "$1" in
        -h|--help)
            echo "-h, --help          show help"
            echo "-D, --defaults      do not ask user"
            echo "-L, --login         login to nginx instance"
            echo "-T, --test          run phpunit with filter"
            echo "-e, --exec [cmd]    exec inside nginx"
            exit 0
            ;;
        -D|--defaults)
            defaults="yes"
            shift
            ;;
        -L|--login)
            login="yes"
            shift
            ;;
        -T|--test)
            test="yes"
            shift
            ;;
        -e|--exec)
            shift
            exec="$@"
            shift 1000
            ;;
        *)
            break
            ;;
    esac
done

sudo=""
[ "$EUID" -ne "0" ] && sudo="sudo"

vars=()
vars+=("HOST ${prefix}.dev")
vars+=("DOCKER_NAME_PREFIX ${prefix}_")
vars+=("DOCKER_IMAGE_PREFIX ejzspb/")
vars+=("ELASTICSEARCH_HOST no")
vars+=("SQL_HOST yes")
vars+=("SQL_PORT 3306")
vars+=("SQL_USER user")
vars+=("SQL_PASS pass")
vars+=("SQL_DB ${prefix}")

for var in "${vars[@]}"; do
    one=`echo "$var" | cut -d" " -f1`
    one_=`echo "$one" | cut -d_ -f1`
    one_host="${one_}_HOST"
    if [ "${!one_host}" == "no" -a "$one_host" != "$one" ]; then
        continue
    fi
    two=`echo "$var" | cut -d" " -f2-`
    temp="${!one}"
    if [ -z "$temp" ] && [ -f "vars/${one}" ]; then
        temp=`cat "vars/${one}"`
    fi
    [ "$temp" ] && two="$temp"
    append=", Docker - [yes], Ignore - [no]"
    echo "$one" | grep -q "_HOST" || append=""
    echo -n "${one}? (${two} - [ENTER]${append}): "
    if [ ! -t 0 ] || [ "$defaults" ]; then
        echo
        input=""
    else
        read input
    fi
    if [ -z "$input" ]; then
        temp="$two"
    else
        temp="$input"
    fi
    mkdir -p vars
    echo "$temp" >"vars/${one}"
    eval export "$one"='$temp'
done

BASE="/var/www/${HOST}"
CGI="$BASE"
[ -d cgi ] && CGI="${BASE}/cgi"
[ -t 0 ] && t="t"
EXEC="$sudo docker exec -i ${DOCKER_NAME_PREFIX}nginx"
EXEC_T="$sudo docker exec -i${t} ${DOCKER_NAME_PREFIX}nginx"
EXEC_GH_TOKEN="$sudo docker exec -i${t} ${DOCKER_NAME_PREFIX}nginx env GH_TOKEN=${GH_TOKEN}"

if test "$test"; then
    exec=($EXEC "phpunit" "-c" "/var/www/${HOST}/phpunit.xml")
elif test "$login"; then
    exec=($EXEC_T "bash")
elif test "${#exec[@]}" -gt 0; then
    exec=($EXEC_T ${exec[@]})
fi

if [ "${#exec[@]}" -gt 0 ] && $sudo docker ps --filter "name=^/${DOCKER_NAME_PREFIX}nginx" | grep -q "$DOCKER_NAME_PREFIX"nginx; then
    "${exec[@]}"
    exit "$?"
fi

$sudo rm -f local.ini cgi/local.ini
$sudo rm -rf vendor cgi/vendor
list=`$sudo docker ps -a --filter "name=^/${DOCKER_NAME_PREFIX}" | awk '{print $1}' | tail -n +2`
if [ "$list" ]; then
    echo "Delete Docker containers with prefix ${DOCKER_NAME_PREFIX}:"
    $sudo docker rm -f -v $list
fi

if [ "$SQL_HOST" == "yes" ]; then
    $sudo docker pull "$DOCKER_IMAGE_PREFIX"mariadb
    $sudo docker run -d --name "$DOCKER_NAME_PREFIX"mariadb \
        -e "MYSQL_RANDOM_ROOT_PASSWORD=yes" -e "MYSQL_DATABASE=${SQL_DB}" \
        -e "MYSQL_USER=${SQL_USER}" -e "MYSQL_PASSWORD=${SQL_PASS}" "$DOCKER_IMAGE_PREFIX"mariadb
    { sleep 2; $sudo docker ps | grep -q "$DOCKER_NAME_PREFIX"mariadb; } || { echo "mariadb failed to run!"; exit 1; }
    ip=`$sudo docker inspect "$DOCKER_NAME_PREFIX"mariadb | grep '"IPAddress"' | cut -d'"' -f4 | head -1`
    SQL_HOST="$ip"
    SQL_PORT=3306
    echo "SQL_HOST=${SQL_HOST}"
    echo "SQL_PORT=${SQL_PORT}"
fi

if [ "$ELASTICSEARCH_HOST" == "yes" ]; then
    $sudo docker pull elasticsearch
    $sudo docker run -d --name "$DOCKER_NAME_PREFIX"elasticsearch -e "ES_JAVA_OPTS=-Xms512m -Xmx512m" elasticsearch
    { sleep 2; $sudo docker ps | grep -q "$DOCKER_NAME_PREFIX"elasticsearch; } || { echo "elasticsearch failed to run!"; exit 1; }
    ip=`$sudo docker inspect "$DOCKER_NAME_PREFIX"elasticsearch | grep '"IPAddress"' | cut -d'"' -f4 | head -1`
    ELASTICSEARCH_HOST="$ip"
    echo "ELASTICSEARCH_HOST=${ELASTICSEARCH_HOST}"
fi

# Start nginx
$sudo docker pull "$DOCKER_IMAGE_PREFIX"nginx
$sudo lsof -i -P -n | grep LISTEN | grep -q ':80' || expose="-p 0.0.0.0:80:80"
$sudo docker run -w "$BASE" --add-host "$HOST":127.0.0.1 -v "`pwd`":/var/www/"$HOST" ${expose} \
    --name "$DOCKER_NAME_PREFIX"nginx -d "$DOCKER_IMAGE_PREFIX"nginx
{ sleep 2; $sudo docker ps | grep -q "$DOCKER_NAME_PREFIX"nginx; } || { echo "nginx failed to run!"; exit 1; }
ip=`$sudo docker inspect "$DOCKER_NAME_PREFIX"nginx | grep '"IPAddress"' | cut -d'"' -f4 | head -1`
echo "HOST=${ip}"

echo
echo "// --------------------- //"
echo "//  Containers started!  //"
echo "// --------------------- //"
echo

set -x
set -e

$EXEC bash -c "echo 'export TERM=xterm' >>/root/.bashrc"
$EXEC bash -c "echo 'cd ${BASE}' >>/root/.bashrc"
$EXEC git config --global user.email "user@email.com"
$EXEC git config --global user.name "Name"
set +x
echo "$EXEC" "$BASE"/install.sh
if [ "$GH_TOKEN" ]; then
    echo "Run install.sh with GH_TOKEN!"
    $EXEC_GH_TOKEN "$BASE"/install.sh
else
    $EXEC "$BASE"/install.sh
fi
set -x
$EXEC php "$CGI"/bootstrap.php ini_file_set LOCAL_INI global.default_host "$HOST"
if [ "$SQL_HOST" != "no" ]; then
    $EXEC php "$CGI"/bootstrap.php ini_file_set LOCAL_INI sql.host "$SQL_HOST"
    $EXEC php "$CGI"/bootstrap.php ini_file_set LOCAL_INI sql.port "$SQL_PORT"
    $EXEC php "$CGI"/bootstrap.php ini_file_set LOCAL_INI sql.user "$SQL_USER"
    $EXEC php "$CGI"/bootstrap.php ini_file_set LOCAL_INI sql.pass "$SQL_PASS"
    $EXEC php "$CGI"/bootstrap.php ini_file_set LOCAL_INI sql.db "$SQL_DB"
fi

if [ "${#exec[@]}" -gt 0 ]; then
    "${exec[@]}"
    exit "$?"
fi
