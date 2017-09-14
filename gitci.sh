#!/usr/bin/env bash

message="$TRAVIS_COMMIT_MESSAGE"
branch="$TRAVIS_BRANCH"

cd "`dirname "$0"`"

function ver_inc() {
    script='NF==1{print ++$NF};NF>1{if(length($NF+1)>length($NF))$(NF-1)++;$NF=sprintf("%0*d",length($NF),($NF+1)%(10^length($NF)));print}'
    echo "$1" | awk -F. -v OFS=. "$script"
}

function ver_inc_rc() {
    echo "$1" | perl -pe 's/RC(\d+)$/RC.($1+1)/e'
}

function ver_rm_rc() {
    echo "$1" | perl -pe 's/-RC(\d+)$//e'
}

# Pull requests and commits to not master branch just build, no deploy!!!
if [ "$TRAVIS_PULL_REQUEST" != "false" -o "$branch" != "master" ]; then
    echo "SKIP!"
    exit 0
fi

git checkout "$branch"
t="https://${GH_TOKEN}@github.com/${TRAVIS_REPO_SLUG}.git"
git remote set-url origin "$t"
git status
if [ "$1" -a "$2" ]; then
    git add "$1"
fi
if ! git diff --cached --quiet && [ "$1" -a "$2" ]; then
    git add "$1"
    git commit -m "$2"
    git push origin "$branch"
fi
tag=`git tag | grep -P '^v1\.\d+\.\d+' | tail -n 1`
if [ -z "$tag" ]; then
    ntag="v1.0.0"
elif echo "$tag" | grep -qP -- '-RC\d+$'; then
    if echo "$message" | grep -qP '\[release\]'; then
        ntag=`ver_rm_rc "$tag"`
    else
        ntag=`ver_inc_rc "$tag"`
    fi
else
    if echo "$message" | grep -qP '\[release\]'; then
        ntag=`ver_inc "$tag"`
    else
        ntag=`ver_inc "$tag"`
        ntag="${ntag}-RC1"
    fi
fi
echo "Commit message : ${message}"
echo "Last tag : ${tag}"
echo "Next tag : ${ntag}"
if git tag | grep -q "$ntag"'$'; then
    ntag=`ver_inc "$ntag"`
    echo "Next tag : ${ntag}"
fi
git tag -a "$ntag" -m "$ntag"
git push --tags
