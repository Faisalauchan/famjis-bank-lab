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

exec apache2-foreground
