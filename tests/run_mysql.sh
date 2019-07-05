#!/bin/bash

docker run -d -p 33306:3306 \
    --name dealnews-db-mysql-test-instance \
    -e MYSQL_ROOT_PASSWORD=percona-root \
    -e MYSQL_DATABASE=mytestdb \
    -e MYSQL_USER=test \
    -e MYSQL_PASSWORD=test \
    percona:5.7