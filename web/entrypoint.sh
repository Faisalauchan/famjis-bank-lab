#!/bin/sh
set -e

: "${STUDENT_ID:=DEMO}"
: "${SECRET_SALT:=change_me}"

mkdir -p /var/www/data /var/www/private /var/www/html/uploads

# bake the per-student values in so every request sees them
cat > /var/www/private/generated.php <<PHP
<?php
define('STUDENT_ID', '${STUDENT_ID}');
define('SECRET_SALT', '${SECRET_SALT}');
PHP

chown -R www-data:www-data /var/www/data /var/www/private /var/www/html/uploads

# seed the database (idempotent) and write the upload flag
php /var/www/html/seed.php || true

# seed.php runs as root, so a freshly-created bank.sqlite would be root-owned
# and SQLite would then fail writes with "attempt to write a readonly database".
# Re-chown AFTER seeding so the DB file (and any WAL/journal) is writable by www-data.
chown -R www-data:www-data /var/www/data /var/www/private /var/www/html/uploads

exec apache2-foreground
