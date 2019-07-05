#!/bin/bash

docker run -d -p 55432:5432 \
    --name dealnews-db-postgres-test-instance \
    -e POSTGRES_DB=pgtestdb \
    -e POSTGRES_USER=test \
    -e POSTGRES_PASSWORD=test \
    postgres:latest