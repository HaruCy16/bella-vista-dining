#!/bin/sh
# Runs once on a fresh db volume (after 01-schema.sql and 02-seed.sql).
# Creates the empty database PHPUnit rebuilds on every run and gives the app user access.
set -e
mysql -uroot -p"$MYSQL_ROOT_PASSWORD" <<SQL
CREATE DATABASE IF NOT EXISTS bella_vista_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON bella_vista_test.* TO '$MYSQL_USER'@'%';
FLUSH PRIVILEGES;
SQL
