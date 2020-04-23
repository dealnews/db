#!/bin/bash

DOCKERCMD=`which docker`

if [ "$DOCKERCMD" = "" ]
then
    exit
fi

for file in `ls ./tests/containers/*`
do
    NAME=`basename $file`
    echo "Stopping $NAME"
    docker stop $NAME && \
        docker rm $NAME
done
