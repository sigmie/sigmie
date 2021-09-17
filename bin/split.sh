#!/usr/bin/env bash

set -e
set -x

TAG="0.8.2"

function split()
{
    SHA1=`./bin/splitsh-lite --prefix=$1`
    git push -f $2 "$SHA1:refs/heads/master" -f
    git push $2 $TAG

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
