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

git pull origin $CURRENT_BRANCH

remote auth git@github.com:sigmie/auth.git
remote cli git@github.com:sigmie/cli.git
remote contracts git@github.com:sigmie/contracts.git
remote exceptions git@github.com:sigmie/exceptions.git
remote http git@github.com:sigmie/http.git
remote mappings git@github.com:sigmie/mappings.git
remote search  git@github.com:sigmie/search.git
remote support git@github.com:sigmie/support.git
remote testing git@github.com:sigmie/testing.git
remote apis git@github.com:sigmie/apis.git

split 'packages/APIs' apis
split 'packages/Auth' auth
split 'packages/Cli' cli
split 'packages/Contracts' contracts
split 'packages/Exceptions' exceptions
split 'packages/Http' http
split 'packages/Mappings' mappings
split 'packages/Search' search
split 'packages/Support' support
split 'packages/Testing' testing