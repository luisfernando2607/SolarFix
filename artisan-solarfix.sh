#!/bin/bash
exec php -d "extension=/tmp/pdo_sqlite_ext/usr/lib/php/20230831/pdo_sqlite.so" "$@"
