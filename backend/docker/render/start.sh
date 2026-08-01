#!/bin/sh
# بدء الإنتاج (Render): يخدم HTTP على $PORT عبر nginx أمام php-fpm في نفس الحاوية.
# لا يشغّل الهجرات هنا (تُنفَّذ في preDeployCommand لتجنّب تكرارها على كل نسخة).
set -e

: "${PORT:=8080}"
export PORT

# استبدال المنفذ فقط في قالب nginx (بقيّة متغيّرات nginx تبقى حرفية).
envsubst '${PORT}' < /var/www/html/docker/render/nginx.conf.template > /etc/nginx/nginx.conf

# php-fpm في الخلفية (يستمع 127.0.0.1:9000 افتراضياً)، ثم nginx في المقدّمة.
php-fpm -D
exec nginx
