#!/bin/bash

DOCKERCMD=`which docker`

if [ "$DOCKERCMD" = "" ]
then
    exit
fi

function run_container() {

    NAME=$1
    CMD=$2

    RUNNING=`docker ps | fgrep $NAME`

    if [ "$RUNNING" = "" ]
    then

        CREATED=`docker ps --all | fgrep $NAME`

        if [ "$CREATED" = "" ]
        then
            echo "Creating $NAME"
            $CMD
        else
            echo "Starting $NAME"
            docker start $NAME
        fi
        echo "Waiting for $NAME sandbox to start"
        sleep 10
    fi
}

for file in `ls ./tests/containers/*`
do
    NAME=`basename $file`
    run_container $NAME ./tests/containers/$NAME
done