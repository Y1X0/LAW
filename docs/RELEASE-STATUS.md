# حالة الإصدار — Release Status

سجلّ موجز لحالة جاهزية الوحدات. لا يحتوي أي تغيير برمجي — توثيق فقط.

## الوحدات

| الوحدة | الحالة |
|-------|--------|
| Payroll | ✅ Production Ready (Frontend/UI Layer) |
| Legal Management | ✅ Production Ready (Frontend/UI Layer) |
| Client Management | ✅ Production Ready (Frontend/UI Layer) |
| **Document Management** | 🟡 **Release Candidate** — بانتظار تحقّق حيّ واحد على Cloudflare R2 |

---

## Document Management — لماذا Release Candidate وليس Production Ready بعد؟

العمل البرمجي مكتمل ومُتحقَّق منه في CI (الطبقتان Frontend + Backend، الأمان، معالجة
الفشل، الاختبارات خضراء، Backlog نظيف). لكن اختبارات CI تستخدم `Storage::fake('r2')`،
وهي **لا تُثبت** ما يلي، لأنه خارج نطاق ما يستطيع الـCI التحقق منه:

- الاتصال الفعلي بـ Cloudflare R2.
- صلاحية مفاتيح R2.
- سياسات الـ Bucket (الخصوصية/الأذونات).
- سلوك الشبكة والأخطاء الحقيقية.

لذلك تبقى الحالة **Release Candidate** حتى تُجرى جولة تحقّق حيّة واحدة على R2 الحقيقي.

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

## Checklist التحقّق الحيّ (رفع → تنزيل → حذف)

| # | الخطوة | معيار النجاح |
|---|-------|--------------|
| 1 | **رفع** ملف PDF من شاشة القضية («إضافة وثيقة» → اختيار الملف → «رفع») | يُنشأ بنجاح. احسب مسبقاً `sha256sum original.pdf` |
| 2 | **الظهور** في قائمة وثائق القضية | يظهر بالاسم الأصلي + الحجم + تاريخ الرفع |
| 3 | **تنزيل ومطابقة SHA-256** («تنزيل» ثم `sha256sum downloaded.pdf`) | القيمة تطابق الخطوة 1 — يُثبت سلامة الجولة عبر R2 |
| 4 | **حذف** («حذف» → «تأكيد») | يختفي من الواجهة، ويختفي من دلو R2 (لوحة Cloudflare) |
| 5 | **لا يتامى** (`php artisan legal:documents:prune-orphans` بدون `--force`) | يطبع «لا توجد ملفات يتيمة.» |

---

## معايير الترقية إلى Production Ready

عند اجتياز الخطوات الخمس أعلاه على R2 الحقيقي:

- تُرقّى حالة Document Management إلى **✅ Production Ready (Frontend + Backend)**.
- تُغلَق **Phase 5 (Document Storage & Upload)** رسميّاً.

---

## المؤجَّل (Backlog — ليس أخطاء)

فحص فيروسات (Antivirus — لا worker على Render)، معاينة داخل المتصفح (Preview)،
Versioning، فهرسة/OCR، وتعديل الوثيقة (لا endpoint). التفاصيل في `docs/BACKLOG.md`.
