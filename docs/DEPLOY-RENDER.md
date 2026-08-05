# النشر على Render — LawFirm ERP (v1.0)

نشر تشغيلي فقط — لا ميزات ولا تعديل منطق. الملفات: `render.yaml` (Blueprint) ·
`backend/Dockerfile` (nginx+php-fpm) · `backend/docker/render/{start.sh,nginx.conf.template}` ·
`frontend/public/_redirects` · `backend/config/cors.php`.

## قرار المعمارية (لماذا nginx + php-fpm)
المشروع **يملك أصلاً** صورة `php-fpm` وإعداد `nginx` يعرف كيف يخدم Laravel
(`docker/nginx/default.conf`). لذا الحلّ الأبسط والأدنى مخاطرةً هو تشغيلهما معاً في
**صورة واحدة**: nginx يستمع على `$PORT` (تحقنه Render) ويمرّر PHP إلى php-fpm على
`127.0.0.1:9000`. رُفض FrankenPHP (تقنية جديدة غير مستخدمة في المشروع) و`php artisan serve`
(للتطوير فقط). صورة التطوير عبر `docker-compose` تبقى كما هي (CMD `php-fpm` + nginx منفصل).

## بنية النشر
| الخدمة | النوع | يخدم |
|---|---|---|
| `law-api` | Web (Docker) | Laravel API — `start.sh` → nginx على `$PORT` + php-fpm |
| `law-scheduler` | Cron (Docker) | مُجدوِل Laravel — `php artisan schedule:run` كل دقيقة (B3) |
| `law-web` | Static Site | React/Vite (`frontend/dist`) + تحويل SPA |
| `law-postgres` | PostgreSQL | قاعدة الإنتاج |

Health check: `/up`. الهجرات + بذر الأدوار تُنفَّذ عند إقلاع الحاوية داخل `start.sh` (idempotent) — لأن الخطة المجانية لا تدعم `preDeployCommand`.

### المُجدوِل الموحّد (`law-scheduler`) — B3

خدمة `type: cron` واحدة تشغّل **مُجدوِل Laravel** على **نفس صورة الـ backend** بأمر مخصّص
`php artisan schedule:run` (يتخطّى `start.sh` فلا هجرات ولا nginx)، على جدول `* * * * *` (كل دقيقة).
كل المواعيد مُعرَّفة في **`backend/routes/console.php` (مصدر واحد)**، وLaravel يقرّر في كل دقيقة ما
يستحقّ التشغيل. إضافة مهمة مستقبلاً = **سطر واحد في `console.php`** بلا تعديل `render.yaml`.

| المهمة | الجدول | تفعل |
|---|---|---|
| `notifications:remind` | يوميّاً 06:00 UTC | تذكيرات الجلسات/الفواتير **داخل النظام** + **بريد** للأنواع بالقائمة البيضاء (B2) |
| `backup:run` | يوميّاً 03:00 UTC | نسخة احتياطية `pg_dump` → دلو R2 منفصل + تقليم GFS |
| `biometric:sync` (Pull) | **معطّلة** | لا تُجدوَل على السحابة — انظر أدناه |

كل الأوامر **idempotent ومانعة للتكرار** (`withoutOverlapping` + فحوص داخلية)، فأي تشغيل يدوي أو
مكرّر آمن بلا ازدواج. لذا تحمل الخدمة أسرار **`MAIL_*`** (بريد التذكيرات) و**`R2_BACKUP_*`** (النسخ) معاً.

**مزامنة البصمة (Pull) — معطّلة على Render عمداً:** أجهزة البصمة على شبكة المكتب (LAN) لا يصل
إليها المضيف السحابي، والمسار الحيّ هو **Push** (الأجهزة → API) وهو يعمل بالكامل. لتفعيل شبكة أمان
الـ Pull مستقبلاً يلزم مُشغّل `schedule` **داخل شبكة الشركة** (أو VPN يتيح الوصول) يضبط المتغيّر
`BIOMETRIC_PULL_SCHEDULE_ENABLED=true`؛ عندها فقط تُجدوَل كل دقيقة (وتقتصر على أجهزة `api_mode=pull`).

**ملاحظة خطة:** خدمات cron في Render تتطلّب خطة مدفوعة (ليست `free`). للتحقّق بعد النشر: من لوحة
Render شغّل `law-scheduler` يدويّاً (**Trigger Run**) وراقب سجلّها، أو نفّذ الأمر مباشرةً من Shell
(`php artisan notifications:remind` / `php artisan backup:run`) وتحقّق من النتيجة. **بديل تشغيلي:** إن
أزعج إقلاعُ حاوية كل دقيقة، بدّل الخدمة إلى `type: worker` تشغّل `php artisan schedule:work` (عملية
دائمة تستدعي المُجدوِل كل دقيقة بلا إقلاع متكرّر) — نفس `console.php`، نفس البيئة.

## قائمة متغيّرات البيئة

### الخلفية (`law-api`)
| المتغيّر | مطلوب؟ | قيمة الإنتاج | ملاحظة |
|---|---|---|---|
| `APP_KEY` | **مطلوب** | `php artisan key:generate --show` ثم الصقه | لا تُولّده Render (يحتاج base64:) |
| `APP_ENV` | **مطلوب** | `production` | مضبوط في render.yaml |
| `APP_DEBUG` | **مطلوب** | `false` | مضبوط — لا تسريب Stack-trace |
| `APP_URL` | **مطلوب** | `https://law-api-xxx.onrender.com` | يُضبط يدويًا بعد الإنشاء |
| `INITIAL_ADMIN_EMAIL` / `INITIAL_ADMIN_PASSWORD` | **مطلوب** | بريد + كلمة قويّة | أوّل مدير — يُنشأ تلقائيّاً عند الإقلاع (B4). دوِّر الكلمة بعد أوّل دخول |
| `DB_*` | **مطلوب** | من `law-postgres` | مربوط تلقائيًا في render.yaml |
| `CACHE_STORE` | مطلوب | `database` | يعمل عبر جدول cache (لا Redis) |
| `SESSION_DRIVER` | مطلوب | `database` | المصادقة توكن؛ الجلسات نادرة |
| `QUEUE_CONNECTION` | مطلوب | `sync` | تشغيل المهام ضمن الطلب (لا عامل) |
| `LOG_CHANNEL` | مُوصى | `stderr` | سجلّات إلى Render (القرص مؤقّت) |
| `FRONTEND_URL` | **مطلوب** | عنوان `law-web` | CORS الآن fail-closed — فارغ ⇒ رفض كل الأصول العابرة (B5) |
| `SESSION_SECURE_COOKIE` | مطلوب | `true` | كوكيز الجلسة على HTTPS فقط (مضبوط في render.yaml) |
| `MAIL_*` (SMTP) | لتفعيل البريد | `smtp` + بيانات المزوّد | استعادة كلمة المرور — انظر أدناه (وإلّا `log`) |
| `FILESYSTEM_DISK` | افتراضي | `local` | لا رفع ملفات فعلي بعد (بيانات وصفية) |

### تفعيل البريد (SMTP) — استعادة كلمة المرور + إشعارات (B2)

مساران يعتمدان على SMTP: (1) «نسيت كلمة المرور» طرفاً لطرف (خلفية + واجهة)، و(2) **قناة بريد
الإشعارات** — أنواع مُدرَجة في `config/notifications.php` (`email_types`: تذكيرات الجلسات/الفواتير +
قرارات الإجازة) تُرسِل نسخة بريدية للمستخدم النشط بجوار الإشعار داخل النظام. لإرسال البريد **فعليّاً**
اضبط الأسرار التالية في لوحة Render لخدمة `law-api` (كلها `sync:false` في `render.yaml`):

| المتغيّر | مثال/ملاحظة |
|---|---|
| `MAIL_MAILER` | `smtp` (فارغ ⇒ يعود إلى `log`، لا إرسال — آمن قبل الضبط) |
| `MAIL_HOST` | خادم SMTP للمزوّد (المكتب يختاره) |
| `MAIL_PORT` | `587` غالباً (أو `465`) |
| `MAIL_USERNAME` / `MAIL_PASSWORD` | بيانات SMTP |
| `MAIL_SCHEME` | `smtp` أو `smtps` حسب المنفذ/المزوّد |
| `MAIL_FROM_ADDRESS` | عنوان المُرسِل (مثل `no-reply@yourfirm.com`) |
| `MAIL_FROM_NAME` | اسم المُرسِل (اسم المكتب) |

> لا قفل على مزوّد محدّد — أي خادم SMTP يعمل. الإرسال متزامن (`QUEUE_CONNECTION=sync`) فلا حاجة
> لعامل طابور. بعد الضبط، جرّب «نسيت كلمة المرور؟» وتأكّد من وصول الرابط إلى `/reset-password`.
> بريد الإشعارات best-effort: فشل الإرسال يُسجَّل تحذيراً فقط ولا يعطّل العملية، والإشعار داخل
> النظام يُنشأ دائماً. للتحكّم بأنواع البريد عدّل `config/notifications.php` (لا يحتاج تغيير كود آخر).

### الواجهة (`law-web`)
| المتغيّر | مطلوب؟ | قيمة الإنتاج | ملاحظة |
|---|---|---|---|
| `VITE_API_URL` | **مطلوب** | `https://<law-api-domain>/api` | **وقت البناء** — لا يتغيّر بعد البناء |

## خطوات النشر من الصفر
1. ادفع الكود إلى GitHub (تمّ). في Render: **New → Blueprint** واختر المستودع؛ يقرأ `render.yaml`.
2. Render ينشئ: قاعدة `law-postgres`، خدمة `law-api`، موقع `law-web`.
3. اضبط الأسرار (`sync:false`):
   - `law-api`: `APP_KEY` (ولّده محليًا: `cd backend && php artisan key:generate --show`)، `APP_URL` (عنوان law-api)، **`INITIAL_ADMIN_EMAIL` و`INITIAL_ADMIN_PASSWORD`** (أوّل مدير)، أسرار `R2_*`، اختياريًا `FRONTEND_URL` و`MAIL_*`.
   - `law-scheduler`: `APP_KEY` (نفس قيمة law-api)، `MAIL_*` (بريد التذكيرات)، `R2_BACKUP_*` (النسخ).
   - `law-web`: `VITE_API_URL = https://<law-api>/api` ثم **أعد البناء** (تُحقن وقت البناء).
4. أوّل نشر (وكل إقلاع) يشغّل `start.sh`: `migrate --force` + بذر أساسي (أدوار + هيكل تنظيمي + **أوّل مدير من `INITIAL_ADMIN_*`**، كلها idempotent) ثم يقلع الخادم.
5. **أوّل مالك منصّة — تلقائيّاً (المسار الرسمي):** إن ضُبط `INITIAL_ADMIN_EMAIL`/`INITIAL_ADMIN_PASSWORD` (خطوة 3)، يُنشئه `DatabaseSeeder` عند الإقلاع ويُسنِد له دور `admin` (idempotent — مرّة واحدة). **دوِّر كلمة المرور بعد أوّل دخول.** لا حاجة لـ `tinker`. (بديل يدوي عند الحاجة فقط: `php artisan tinker` من Shell الخدمة لإنشاء المستخدم وإسناد الدور.)
6. تحقّق: `https://<law-api>/up` = 200 · الدخول من `law-web` بحساب المدير أعلاه. راجع [GO-LIVE §⑤](GO-LIVE.md) لقائمة Smoke Tests الكاملة.
7. لعرض تجريبي فقط: شغّل بذرة الديمو مرّة (Shell الخدمة): `php artisan db:seed --class="Database\Seeders\DemoSeeder" --force` (حسابات `*@demo.law` كلمة المرور `Passw0rd!`). **لا تشغّلها في إنتاج حقيقي.**

## سلامة الإنتاج (مُتحقَّق)
- ✅ `migrate --force` فقط (في `start.sh` عند الإقلاع) — لا هجرة تفاعلية.
- ✅ لا بذر بيانات ديمو تلقائيًا — `DatabaseSeeder` يبذر الأدوار فقط وينشئ الحساب التجريبي في `local/testing` حصراً؛ `DemoSeeder` يدوي صريح.
- ✅ `APP_DEBUG=false` / `APP_ENV=production` في render.yaml.
- ✅ لا أسرار في Git — `.env` مُتجاهَل و مستبعَد من الصورة (`.dockerignore`).
- ✅ سجلّات مناسبة — `LOG_CHANNEL=stderr`.
- ✅ رؤوس أمان (B5 · PR-2): **law-api (nginx):** `X-Frame-Options` · `X-Content-Type-Options: nosniff` · `Referrer-Policy` · `HSTS` (سنة + `preload`) · **CSP صارم** (`default-src 'none'` — واجهة JSON/تنزيلات). **law-web (SPA، رؤوس Render):** نفس الرؤوس الصارمة مفروضة + **CSP بوضع Report-Only** (يبدأ بلا كسر لأن الواجهة تستخدم أنماطاً سطريّة وخطوط Google؛ بدّله إلى `Content-Security-Policy` بعد التحقّق من عدم وجود انتهاكات في وحدة تحكّم المتصفّح). تُتحقَّق فعليًا بأول نشر على Render.
- ✅ **CORS fail-closed** (B5 · PR-2): `config/cors.php` يرفض كل الأصول العابرة إن لم يُضبط `FRONTEND_URL` (بدل `*`) — لذا **`FRONTEND_URL` مطلوب في الإنتاج**.
- ✅ **كوكيز الجلسة آمنة:** `SESSION_SECURE_COOKIE=true` في `render.yaml` (HTTPS فقط).

## قرارات تحتاج موافقتك
1. **الخطط (plan):** `render.yaml` يستخدم `free` — للإنتاج الفعلي ارفع الخدمة والقاعدة إلى `starter`+ (الـfree تنام وتفقد بيانات القاعدة بعد 90 يومًا).
2. **البريد:** المسار جاهز والأسرار مُعرّفة في `render.yaml` (`sync:false`). لتفعيل الإرسال الفعلي اضبط `MAIL_MAILER=smtp` + بيانات SMTP في لوحة Render (انظر «تفعيل البريد (SMTP)» أعلاه). قبل الضبط يبقى `log` (لا إرسال) دون تعطّل.
3. **الطابور/العامل:** `QUEUE_CONNECTION=sync` (لا عامل). إذا فُعّل استيراد أجهزة البصمة (Push/Webhook) بحجم كبير، أضف Render **Background Worker** (`php artisan queue:work`) + **Cron** (`php artisan schedule:run`) وبدّل `QUEUE_CONNECTION=database` أو `redis`. غير مطلوب لبوابات v1.0 الأربع.
5. **المُجدوِل (`law-scheduler`):** خدمة cron واحدة مُعرَّفة في `render.yaml` (خطة مدفوعة) تشغّل `php artisan schedule:run` كل دقيقة — تغطّي التذكيرات والنسخ الاحتياطي معاً (B3). عند تطبيق الـ Blueprint اضبط `APP_KEY` لها (نفس قيمة `law-api`) وأسرار `MAIL_*` و`R2_BACKUP_*`؛ الـ DB مربوط تلقائيّاً. للتحقّق: شغّلها يدويّاً (**Trigger Run**) أو نفّذ الأمر من Shell وتحقّق من صفوف `user_notifications`/نسخة جديدة. تُضاف أي مهمة مستقبليّة في `routes/console.php` وحده. (راجع «المُجدوِل الموحّد» أعلاه.)
4. **Redis:** غير مستخدم (اخترنا database drivers للتبسيط). أضِفه فقط عند الحاجة للأداء العالي.

## قيود التحقّق في هذه الجولة
تعذّر بناء صورة Docker داخل بيئة التطوير هذه (وكيل الشبكة يحجب Docker Hub — 403)،
لذا لم تُبنَ الصورة هنا. إعداد nginx/الصورة **قياسي ومستنِد إلى ملفات المشروع القائمة**،
واستبدال `${PORT}` مُتحقَّق منطقيًا. يتبقّى بناء واحد فعلي على Render للتأكيد النهائي.
