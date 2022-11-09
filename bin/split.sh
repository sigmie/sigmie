#!/usr/bin/env bash

set -e
set -x

CURRENT_BRANCH="master"

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

remote base git@github.com:sigmie/base.git
remote document git@github.com:sigmie/document.git
remote http git@github.com:sigmie/http.git
remote index git@github.com:sigmie/index.git
remote mappings git@github.com:sigmie/mappings.git
remote parse git@github.com:sigmie/parse.git
remote query git@github.com:sigmie/query.git
remote search git@github.com:sigmie/search.git
remote shared git@github.com:sigmie/shared.git
remote testing git@github.com:sigmie/testing.git

remote english git@github.com:sigmie/english.git
remote german git@github.com:sigmie/german.git
remote greek git@github.com:sigmie/greek.git

split 'packages/Base' base
split 'packages/Document' document
split 'packages/Http' http
split 'packages/Index' index
split 'packages/Mappings' mappings
split 'packages/Parse' parse
split 'packages/Query' query
split 'packages/Search' search
split 'packages/Shared' shared
split 'packages/Testing' testing

split 'packages/English' english
split 'packages/German' german
split 'packages/Greek' greek
