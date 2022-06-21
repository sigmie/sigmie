#!/usr/bin/env bash

set -e
set -x

CURRENT_BRANCH="0.x"

function split()
{
    SHA1=`./bin/splitsh-lite --prefix=$1`
    git push $2 "$SHA1:refs/heads/$CURRENT_BRANCH" -f
}

function remote()
{
    git remote add $1 $2 || true
}

git pull origin master

remote cli git@github.com:sigmie/cli.git
remote http git@github.com:sigmie/http.git
remote support git@github.com:sigmie/support.git
remote testing git@github.com:sigmie/testing.git
remote base git@github.com:sigmie/base.git
remote english git@github.com:sigmie/english.git
remote german git@github.com:sigmie/german.git
remote greek git@github.com:sigmie/greek.git

split 'packages/English' english
split 'packages/German' german
split 'packages/Greek' greek
split 'packages/Base' base
split 'packages/Cli' cli
split 'packages/Http' http
split 'packages/Support' support
split 'packages/Testing' testing
