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

if [ "$1" == "--is-release" ]; then
    echo "$message" | grep -qP '\[release\]'
    exit "$?"
fi

if [ "$1" == "--current-tag" ]; then
    tag=`git tag | grep -P '^v1\.\d+\.\d+' | tail -n 1`
    nrc=`ver_rm_rc "$tag"`
    if git tag | grep -q "$nrc"'$'; then
        tag="$nrc"
    fi
    echo "$tag"
    exit
fi

if [ "$1" == "--next-tag" ]; then
    cur=`"$0" --current-tag`
    if [ ! "$cur" ]; then
        next="v1.0.0"
    elif echo "$cur" | grep -qP -- '-RC\d+$'; then
        if "$0" --is-release; then
            next=`ver_rm_rc "$cur"`
        else
            next=`ver_inc_rc "$cur"`
        fi
    else
        next=`ver_inc "$cur"`
        if ! "$0" --is-release; then
            next="${next}-RC1"
        fi
    fi
    echo "$next"
    exit
fi

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
    git add $1
fi
if ! git diff --cached --quiet && [ "$1" -a "$2" ]; then
    git add $1
    git commit -m "$2"
    git push origin "$branch"
fi

cur=`"$0" --current-tag`
next=`"$0" --next-tag`
echo "Commit message : ${message}"
echo "Current tag : ${cur}"
echo "Next tag : ${next}"
git tag -a "$next" -m "$next"
git push --tags
if "$0" --is-release; then
    for tag in `git tag | grep -P -- '-RC\d+$'`; do
        echo "Remove tag: ${tag}"
        git tag -d "$tag"
        git push origin :refs/tags/"$tag"
    done
fi
