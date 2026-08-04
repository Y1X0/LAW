# حالة الإصدار — Release Status

سجلّ موجز لحالة جاهزية الوحدات. لا يحتوي أي تغيير برمجي — توثيق فقط.

## الوحدات

| الوحدة | الحالة |
|-------|--------|
| Payroll | ✅ Production Ready (Frontend/UI Layer) |
| Legal Management | ✅ Production Ready (Frontend/UI Layer) |
| Client Management | ✅ Production Ready (Frontend/UI Layer) |
| **Document Management** | ✅ **Production Ready (Frontend + Backend)** — تحقّق حيّ على Cloudflare R2 نجح |

> **Phase 5 (Document Storage & Upload) — مُغلَقة رسميّاً.**

---

## Document Management — إثبات التحقّق الحيّ (Phase 5 مُغلَقة)

العمل البرمجي مكتمل ومُتحقَّق منه في CI (الطبقتان Frontend + Backend، الأمان، معالجة
الفشل، الاختبارات خضراء، Backlog نظيف). اختبارات CI الرئيسية تستخدم `Storage::fake('r2')`،
وهي وحدها **لا تُثبت** الاتصال الفعلي بـ R2. لذا أُجري تحقّق حيّ مخصّص على **Cloudflare R2 الحقيقي**:

- **السير:** `R2 Integration` · التشغيل رقم 2 (`workflow_dispatch`) على `main` — **نجح**.
- **النتيجة:** `OK (1 test, 6 assertions)` — الاختبار **نُفِّذ** (لم يُتخطَّ)، والأسرار الخمسة حُقنت من GitHub Secrets.
- **ما أُثبت فعليّاً:** رفع ملف 256KB إلى R2 → تأكيد الوجود → مطابقة الحجم → تنزيل → **مطابقة SHA-256 مع الأصل** → حذف → تأكيد الحذف.

بهذا تحقّق الاتصال الفعلي، وصلاحية المفاتيح، وسياسات الـ Bucket، وسلامة الجولة عبر الشبكة —
وهي بالضبط النقاط التي لم يكن CI الرئيسي يستطيع إثباتها. الحالة الآن **Production Ready**.

---

## المتطلّبات قبل التحقّق الحيّ

1. إنشاء دلو (Bucket) في Cloudflare R2 ومفاتيح API له.
2. ضبط أسرار البيئة في Render (Environment):
   - `R2_ACCESS_KEY_ID`
   - `R2_SECRET_ACCESS_KEY`
   - `R2_BUCKET`
   - `R2_ENDPOINT`
   - `R2_DEFAULT_REGION=auto`
   - `LEGAL_DOCUMENTS_DISK=r2` (مضبوط أصلاً في `render.yaml`)
3. القرص `r2` معرّف في `backend/config/filesystems.php` (private · path-style · throw).

> الأسماء موثّقة في `backend/.env.example`. لا تُوضع القيم الحسّاسة في المستودع.

---

## التحقّق الآلي عبر CI (R2 Integration workflow)

بدل الاعتماد على جولة يدوية فقط، يوجد الآن سير عمل مخصّص يختبر R2 **الحقيقي** آليّاً:

- **الملف:** `.github/workflows/r2-integration.yml` · **الاختبار:** `backend/tests/Integration/R2LiveTest.php`.
- **يغطّي:** رفع ملف 256KB → تأكيد الوجود والحجم → تنزيل → **مطابقة SHA-256** → حذف → تأكيد الحذف. أي فشل في أي خطوة يُفشل السير.
- **يُشغَّل:** يدويّاً (`workflow_dispatch`)، وعند دفع تغييرات تمسّ التخزين إلى `main`.
- **معزول عن CI الرئيسي:** الاختبار ليس ضمن مجموعتَي Unit/Feature، فلا يعمل في `php artisan test` ولا يتطلّب أسراراً هناك.
- **بدون أسرار:** يتخطّى الاختبار برسالة واضحة (السير يبقى أخضر) — لا يُخزَّن أي مفتاح في المستودع.

### GitHub Secrets المطلوبة (على مالك المستودع)

في **Settings → Secrets and variables → Actions → New repository secret** أضِف:

| Secret | مثال/ملاحظة |
|--------|-------------|
| `R2_ACCESS_KEY_ID` | من لوحة Cloudflare R2 API Tokens |
| `R2_SECRET_ACCESS_KEY` | السرّ المقابل |
| `R2_BUCKET` | اسم الدلو |
| `R2_ENDPOINT` | `https://<account_id>.r2.cloudflarestorage.com` |
| `R2_DEFAULT_REGION` | `auto` |

> هذه أسرار CI منفصلة عن أسرار Render (المذكورة أعلاه)، لكن قيمها نفسها.

بعد ضبط الأسرار، شغّل السير من تبويب **Actions → R2 Integration → Run workflow**. نجاحه = إثبات التكامل الحيّ.

---

## Checklist التحقّق الحيّ اليدوي (اختياري — تأكيد بشري إضافي)

| # | الخطوة | معيار النجاح |
|---|-------|--------------|
| 1 | **رفع** ملف PDF من شاشة القضية («إضافة وثيقة» → اختيار الملف → «رفع») | يُنشأ بنجاح. احسب مسبقاً `sha256sum original.pdf` |
| 2 | **الظهور** في قائمة وثائق القضية | يظهر بالاسم الأصلي + الحجم + تاريخ الرفع |
| 3 | **تنزيل ومطابقة SHA-256** («تنزيل» ثم `sha256sum downloaded.pdf`) | القيمة تطابق الخطوة 1 — يُثبت سلامة الجولة عبر R2 |
| 4 | **حذف** («حذف» → «تأكيد») | يختفي من الواجهة، ويختفي من دلو R2 (لوحة Cloudflare) |
| 5 | **لا يتامى** (`php artisan legal:documents:prune-orphans` بدون `--force`) | يطبع «لا توجد ملفات يتيمة.» |

---

## الترقية إلى Production Ready — تمّت ✅

تحقّق المعيار: **سير عمل `R2 Integration` نجح** (التشغيل رقم 2، `workflow_dispatch` على `main`)
بعد ضبط أسرار GitHub الخمسة. بناءً عليه:

- رُقّيت حالة Document Management إلى **✅ Production Ready (Frontend + Backend)**.
- أُغلقت **Phase 5 (Document Storage & Upload)** رسميّاً.

> ملاحظة: CI الرئيسي يستخدم `Storage::fake` (لا يُثبت R2 الحقيقي)؛ الإثبات الحيّ تحقّق
> آليّاً عبر سير `R2 Integration` المخصّص بأسرار GitHub. لإعادة التحقّق مستقبلاً: شغّل السير مجدّداً
> من تبويب **Actions → R2 Integration → Run workflow**.

---

## المؤجَّل (Backlog — ليس أخطاء)

فحص فيروسات (Antivirus — لا worker على Render)، معاينة داخل المتصفح (Preview)،
Versioning، فهرسة/OCR، وتعديل الوثيقة (لا endpoint). التفاصيل في `docs/BACKLOG.md`.
