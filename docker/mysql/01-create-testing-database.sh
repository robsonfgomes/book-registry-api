#!/bin/bash
# Runs only when the MySQL volume is created for the first time.
set -e

mysql --user=root --password="${MYSQL_ROOT_PASSWORD}" <<-SQL
    CREATE DATABASE IF NOT EXISTS \`${MYSQL_DATABASE}_testing\`;
    GRANT ALL PRIVILEGES ON \`${MYSQL_DATABASE}_testing\`.* TO '${MYSQL_USER}'@'%';
SQL
