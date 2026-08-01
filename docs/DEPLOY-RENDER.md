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
| `law-web` | Static Site | React/Vite (`frontend/dist`) + تحويل SPA |
| `law-postgres` | PostgreSQL | قاعدة الإنتاج |

Health check: `/up`. الهجرات + بذر الأدوار: `preDeployCommand` (مرّة، لا لكل نسخة).

## قائمة متغيّرات البيئة

### الخلفية (`law-api`)
| المتغيّر | مطلوب؟ | قيمة الإنتاج | ملاحظة |
|---|---|---|---|
| `APP_KEY` | **مطلوب** | `php artisan key:generate --show` ثم الصقه | لا تُولّده Render (يحتاج base64:) |
| `APP_ENV` | **مطلوب** | `production` | مضبوط في render.yaml |
| `APP_DEBUG` | **مطلوب** | `false` | مضبوط — لا تسريب Stack-trace |
| `APP_URL` | **مطلوب** | `https://law-api-xxx.onrender.com` | يُضبط يدويًا بعد الإنشاء |
| `DB_*` | **مطلوب** | من `law-postgres` | مربوط تلقائيًا في render.yaml |
| `CACHE_STORE` | مطلوب | `database` | يعمل عبر جدول cache (لا Redis) |
| `SESSION_DRIVER` | مطلوب | `database` | المصادقة توكن؛ الجلسات نادرة |
| `QUEUE_CONNECTION` | مطلوب | `sync` | تشغيل المهام ضمن الطلب (لا عامل) |
| `LOG_CHANNEL` | مُوصى | `stderr` | سجلّات إلى Render (القرص مؤقّت) |
| `FRONTEND_URL` | اختياري | عنوان `law-web` | يحصر CORS بالواجهة فقط |
| `MAIL_MAILER` (+`MAIL_*`) | اختياري | `smtp` + بيانات | لتفعيل بريد إعادة كلمة المرور (وإلّا `log`) |
| `FILESYSTEM_DISK` | افتراضي | `local` | لا رفع ملفات فعلي بعد (بيانات وصفية) |

### الواجهة (`law-web`)
| المتغيّر | مطلوب؟ | قيمة الإنتاج | ملاحظة |
|---|---|---|---|
| `VITE_API_URL` | **مطلوب** | `https://<law-api-domain>/api` | **وقت البناء** — لا يتغيّر بعد البناء |

## خطوات النشر من الصفر
1. ادفع الكود إلى GitHub (تمّ). في Render: **New → Blueprint** واختر المستودع؛ يقرأ `render.yaml`.
2. Render ينشئ: قاعدة `law-postgres`، خدمة `law-api`، موقع `law-web`.
3. اضبط الأسرار (`sync:false`):
   - `law-api`: `APP_KEY` (ولّده محليًا: `cd backend && php artisan key:generate --show`)، `APP_URL` (عنوان law-api)، اختياريًا `FRONTEND_URL` و`MAIL_*`.
   - `law-web`: `VITE_API_URL = https://<law-api>/api` ثم **أعد البناء** (تُحقن وقت البناء).
4. أوّل نشر يشغّل `preDeployCommand` (migrate + بذر الأدوار) ثم يقلع الخادم.
5. **إنشاء أوّل مالك منصّة** (قاعدة الإنتاج فيها أدوار بلا مستخدمين — أنشئ حساب Super Admin مرّة من Shell خدمة `law-api`):
   ```sh
   php artisan tinker --execute="\$u=App\Models\User::firstOrCreate(['email'=>'owner@your-firm.com'],['name'=>'مالك المكتب','password'=>bcrypt('ضع-كلمة-قوية'),'status'=>'active','email_verified_at'=>now()]); \$u->assignRole('admin'); echo 'admin ready';"
   ```
6. تحقّق: `https://<law-api>/up` = 200 · الدخول من `law-web` بحساب المالك أعلاه.
7. لعرض تجريبي فقط: شغّل بذرة الديمو مرّة (Shell الخدمة): `php artisan db:seed --class="Database\Seeders\DemoSeeder" --force` (حسابات `*@demo.law` كلمة المرور `Passw0rd!`). **لا تشغّلها في إنتاج حقيقي.**

## سلامة الإنتاج (مُتحقَّق)
- ✅ `migrate --force` فقط (في `preDeployCommand`) — لا هجرة تفاعلية.
- ✅ لا بذر بيانات ديمو تلقائيًا — `DatabaseSeeder` يبذر الأدوار فقط وينشئ الحساب التجريبي في `local/testing` حصراً؛ `DemoSeeder` يدوي صريح.
- ✅ `APP_DEBUG=false` / `APP_ENV=production` في render.yaml.
- ✅ لا أسرار في Git — `.env` مُتجاهَل و مستبعَد من الصورة (`.dockerignore`).
- ✅ سجلّات مناسبة — `LOG_CHANNEL=stderr`.

## قرارات تحتاج موافقتك
1. **الخطط (plan):** `render.yaml` يستخدم `free` — للإنتاج الفعلي ارفع الخدمة والقاعدة إلى `starter`+ (الـfree تنام وتفقد بيانات القاعدة بعد 90 يومًا).
2. **البريد:** حاليًا `log`. إن كانت إعادة تعيين كلمة المرور مطلوبة للمستخدمين، زوّد SMTP.
3. **الطابور/العامل:** `QUEUE_CONNECTION=sync` (لا عامل). إذا فُعّل استيراد أجهزة البصمة (Push/Webhook) بحجم كبير، أضف Render **Background Worker** (`php artisan queue:work`) + **Cron** (`php artisan schedule:run`) وبدّل `QUEUE_CONNECTION=database` أو `redis`. غير مطلوب لبوابات v1.0 الأربع.
4. **Redis:** غير مستخدم (اخترنا database drivers للتبسيط). أضِفه فقط عند الحاجة للأداء العالي.

## قيود التحقّق في هذه الجولة
تعذّر بناء صورة Docker داخل بيئة التطوير هذه (وكيل الشبكة يحجب Docker Hub — 403)،
لذا لم تُبنَ الصورة هنا. إعداد nginx/الصورة **قياسي ومستنِد إلى ملفات المشروع القائمة**،
واستبدال `${PORT}` مُتحقَّق منطقيًا. يتبقّى بناء واحد فعلي على Render للتأكيد النهائي.
