# LawFirm ERP — Backend (Laravel 11)

الخلفية (Backend API) لنظام إدارة مكتب المحاماة، مبنية بمعمارية **Modular Monolith** وفق [قرارات المعمارية المعتمدة](../docs/adr/).

## المتطلبات
- PHP 8.3+ · Composer 2 · PostgreSQL 16 · Redis 7 (أو Docker)

## التشغيل عبر Docker (المُوصى به)
```bash
cp backend/.env.example backend/.env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
# التطبيق: http://localhost:8080/api/health
```

## التشغيل المحلي
```bash
cd backend
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan serve   # http://127.0.0.1:8000/api/health
```

## الاختبارات
```bash
php artisan test            # يعمل على SQLite in-memory (سريع)
./vendor/bin/pint --test    # فحص نمط الكود
```

## بنية الوحدات (Modular Monolith)
```
backend/
├── app/Providers/ModuleServiceProvider.php   # يكتشف الوحدات ويحمّل مساراتها وترحيلاتها
├── Modules/
│   ├── Core/        # النواة (Auth/Permissions/Settings/Audit) — Health endpoint جاهز
│   ├── HR/          # الموظفون والموارد البشرية        (Issues #13/#14)
│   ├── Attendance/  # الحضور وتكامل البصمة              (Issues #15/#16)
│   ├── Leave/       # الإجازات                          (Issue #17)
│   └── Dashboard/   # لوحة التحكم                       (Issue #18)
```
كل وحدة تملك `routes/api.php` و`database/migrations/` و`README.md` يوثّق ملكيتها وحدودها. القاعدة: **لا تصل وحدة إلى جداول وحدة أخرى مباشرةً** — راجع [module-boundaries](../docs/module-boundaries.md).

## نقاط النهاية الجاهزة (Foundation)
| المسار | الوصف |
|--------|-------|
| `GET /api/health` | فحص جاهزية النظام + اتصال قاعدة البيانات |
| `GET /api/version` | معلومات الإصدار |
| `GET /up` | مِجسّ صحة إطار Laravel |

## الأمان
- `.env` **غير مُتتبَّع** في Git (`.gitignore`)؛ يُستخدم `.env.example` بقيم غير سرية.
- مسح الأسرار (gitleaks) ضمن CI. الأسرار في الإنتاج عبر Secret Manager/Vault.
