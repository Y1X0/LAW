#!/bin/sh
# بدء الإنتاج (Render): يخدم HTTP على $PORT عبر nginx أمام php-fpm في نفس الحاوية.
set -e

: "${PORT:=8080}"
export PORT

# الهجرات + بذر الأدوار عند الإقلاع: الخطة المجانية في Render لا تدعم preDeployCommand،
# لذا تُنفَّذ هنا. كلاهما idempotent (migrate يطبّق المعلّق فقط، وRbacSeeder firstOrCreate)،
# وDatabaseSeeder ينشئ الحساب التجريبي في local/testing فقط — آمن للإنتاج.
php artisan migrate --force
php artisan db:seed --class='Database\Seeders\DatabaseSeeder' --force

# استبدال المنفذ فقط في قالب nginx (بقيّة متغيّرات nginx تبقى حرفية).
envsubst '${PORT}' < /var/www/html/docker/render/nginx.conf.template > /etc/nginx/nginx.conf

# php-fpm في الخلفية (يستمع 127.0.0.1:9000 افتراضياً)، ثم nginx في المقدّمة.
php-fpm -D
exec nginx
