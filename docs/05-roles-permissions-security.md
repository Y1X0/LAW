# 05 — الصلاحيات والأمان (Roles, Permissions & Security)

---

## 1. نموذج الصلاحيات (Authorization Model)

النظام يجمع بين نموذجين لتحقيق دقة عالية:

- **RBAC (Role-Based):** لكل مستخدم دور/أدوار، ولكل دور مجموعة صلاحيات (Permissions).
- **ABAC (Attribute-Based):** قواعد إضافية على مستوى السجل/الحقل حسب سمات (الفرع، الملكية، السرية). مثال: المحامي يرى القضايا التي هو مسؤول عنها فقط، ما لم يملك `cases.view_all`.

```mermaid
flowchart LR
    U["User"] -->|user_role| R["Roles"]
    R -->|role_permission| P["Permissions"]
    U -.->|user_permission (استثناء grant/revoke)| P
    U -->|مقيّد بـ| B["Branch"]
    P --> POL["Policy/Guard\n(فحص وقت التنفيذ)"]
    POL --> DEC{"مسموح؟"}
    DEC -- ownership/branch/confidential --> RES["قرار الوصول"]
```

## 2. الصلاحيات الذرّية (Atomic Permissions)

تُعرّف الصلاحيات بنمط `module.action` وتُخزَّن في جدول `permissions`. أمثلة:

| الوحدة | صلاحيات |
|--------|---------|
| employees | `employees.view` · `employees.view_all` · `employees.create` · `employees.update` · `employees.delete` · `employees.salary.view` |
| attendance | `attendance.view` · `attendance.manual` · `attendance.approve` · `attendance.report` |
| leaves | `leaves.request` · `leaves.approve` · `leaves.view_all` |
| payroll | `payroll.view` · `payroll.create` · `payroll.approve` · `payroll.pay` · `payslip.view_own` |
| cases | `cases.view` · `cases.view_all` · `cases.create` · `cases.update` · `cases.close` · `cases.delete` |
| hearings | `hearings.view` · `hearings.manage` |
| clients | `clients.view` · `clients.create` · `clients.update` · `clients.delete` |
| contracts | `contracts.view` · `contracts.manage` |
| finance | `invoices.view` · `invoices.create` · `invoices.approve` · `payments.create` · `expenses.create` · `journal.post` · `finance.reports` |
| marketing | `leads.view` · `leads.manage` · `campaigns.manage` · `leads.convert` |
| tasks | `tasks.view` · `tasks.create` · `tasks.assign` |
| documents | `documents.view` · `documents.upload` · `documents.view_confidential` · `documents.delete` |
| admin | `users.manage` · `roles.manage` · `settings.manage` · `audit.view` · `backup.manage` |
| reports | `reports.hr` · `reports.finance` · `reports.cases` · `reports.marketing` |

> تصميم الصلاحيات ذرّي ليتيح تركيب أي دور جديد مستقبلاً دون تعديل الكود (Data-Driven).

## 3. الأدوار القياسية ومصفوفة الصلاحيات (Role Matrix)

الرمز: ✅ كامل · 🔵 خاص به فقط (own) · ➖ لا يوجد

| الوحدة / الدور | المدير العام | HR | الإدارة (Admin) | المحامي | المالية | التسويق | السكرتارية | الموظف |
|----------------|:-----------:|:--:|:--------------:|:-------:|:-------:|:-------:|:----------:|:------:|
| لوحة التحكم | ✅ كامل | HR | تشغيلية | قضاياه | مالية | تسويق | تشغيلية | شخصية |
| الموظفون | ✅ | ✅ | ➖ | ➖ | ➖ | ➖ | 🔵 عرض | 🔵 ملفه |
| رواتب/بيانات مالية للموظف | ✅ | ✅ | ➖ | ➖ | عرض | ➖ | ➖ | 🔵 كشفه |
| الحضور | عرض | ✅ | ➖ | 🔵 | ➖ | ➖ | إدخال يدوي | 🔵 |
| الإجازات | اعتماد | ✅ اعتماد | ➖ | طلب+اعتماد فريقه | طلب | طلب | طلب+إدخال | 🔵 طلب |
| الرواتب | اعتماد | ✅ إنشاء | ➖ | ➖ | ✅ اعتماد/صرف | ➖ | ➖ | 🔵 كشفه |
| تقييم الأداء | ✅ | ✅ | ➖ | 🔵 لفريقه | ➖ | ➖ | ➖ | 🔵 عرض |
| القضايا | ✅ view_all | ➖ | ➖ | 🔵 قضاياه | عرض للفوترة | ➖ | ✅ إدخال/جدولة | ➖ |
| الجلسات | عرض | ➖ | ➖ | 🔵 | ➖ | ➖ | ✅ إدارة | ➖ |
| العملاء | ✅ | ➖ | ➖ | عملاؤه | عرض | ✅ | ✅ إدخال | ➖ |
| العقود | ✅ | ➖ | ➖ | عرض | ✅ | ➖ | إدخال | ➖ |
| المالية (فواتير/سندات/قيود) | عرض/اعتماد | ➖ | ➖ | ➖ | ✅ كامل | ➖ | ➖ | ➖ |
| التقارير المالية | ✅ | ➖ | ➖ | ➖ | ✅ | ➖ | ➖ | ➖ |
| التسويق/CRM | ✅ | ➖ | ➖ | ➖ | ➖ | ✅ | عرض | ➖ |
| المهام | ✅ | ✅ | ➖ | 🔵 | 🔵 | 🔵 | ✅ | 🔵 |
| المستندات/الأرشيف | ✅ | موظفين | ➖ | قضاياه | مالية | تسويق | إدخال | 🔵 |
| المستخدمون/الأدوار | ✅ | ➖ | ✅ | ➖ | ➖ | ➖ | ➖ | ➖ |
| الإعدادات/النسخ الاحتياطي | ✅ | ➖ | ✅ | ➖ | ➖ | ➖ | ➖ | ➖ |
| سجل التدقيق (Audit) | ✅ | ➖ | ✅ | ➖ | ➖ | ➖ | ➖ | ➖ |

> المدير العام (Owner) يملك صلاحية عليا. مدير النظام (Admin) يدير المستخدمين والإعدادات دون رؤية البيانات المالية/القضائية الحساسة (فصل الأدوار — Segregation of Duties).

## 4. تطبيق الصلاحيات تقنياً

- **طبقة الـ API (Middleware/Guard):** كل مسار محميّ يتطلب صلاحية محددة قبل الوصول للـ Controller.
- **طبقة السجل (Policy):** فحص الملكية/الفرع/السرية (ABAC) قبل عرض/تعديل سجل بعينه.
- **طبقة الواجهة (UI):** إخفاء/تعطيل الأزرار والقوائم حسب صلاحيات المستخدم (لكن القرار الحاسم دائماً في الخادم — لا يُعتمد على إخفاء الواجهة أمنياً).
- **على مستوى الحقل (Field-Level):** حقول حساسة (الراتب، الحساب البنكي) تُخفى إلا لمن يملك `employees.salary.view`.
- **العزل بالفرع:** تصفية تلقائية بـ `branch_id` حسب فرع المستخدم (مع صلاحية عابرة للفروع للإدارة العليا).

---

## 5. الأمان (Security)

### 5.1 المصادقة (Authentication)
- تسجيل دخول عبر **JWT (Access Token قصير العمر ~15 دقيقة) + Refresh Token** (دوّار/Rotating، مخزّن بشكل آمن).
- **MFA / TOTP:** مصادقة ثنائية (Google Authenticator/Authy) إلزامية للأدوار الحساسة (Owner/Admin/Finance)، اختيارية للبقية. رموز احتياطية (Recovery Codes).
- سياسة كلمات مرور قوية + انتهاء دوري + منع إعادة الاستخدام.
- قفل الحساب بعد محاولات فاشلة (`failed_attempts`, `locked_until`).

### 5.2 تشفير البيانات (Encryption)
- **كلمات المرور:** تجزئة **bcrypt/argon2** (لا تُخزَّن نصاً أبداً).
- **عند النقل (In-Transit):** TLS 1.2+ لكل الاتصالات (HTTPS/WSS).
- **عند الراحة (At-Rest):** تشفير قرص قاعدة البيانات + تشفير الحقول فائقة الحساسية (mfa_secret, tokens, أرقام بنكية) على مستوى التطبيق (AES-256).
- **الملفات:** تخزين S3 مع تشفير جانب الخادم + روابط موقّعة مؤقتة (Signed URLs) للتنزيل.

### 5.3 إدارة الجلسات (Session Management)
- جدول `sessions`: عرض الأجهزة/الجلسات النشطة، إنهاء جلسة، "تسجيل خروج من كل الأجهزة".
- انتهاء تلقائي للخمول (Idle Timeout) وإبطال الـ Refresh Token عند تغيير كلمة المرور.

### 5.4 السجلات والتدقيق (Logs & Audit)
- **Audit Logs:** كل عملية create/update/delete/approve/export/login تُسجَّل (من، ماذا، القيم قبل/بعد، IP، الوقت) في `audit_logs`.
- **Security Logs:** محاولات الدخول الفاشلة، تغييرات الصلاحيات، الوصول للبيانات السرية.
- لا يمكن للمستخدمين حذف سجلات التدقيق (Append-Only).

### 5.5 الحماية من الثغرات (OWASP Top 10)

| الثغرة | الحماية |
|--------|---------|
| **SQL Injection** | ORM + Prepared/Parameterized Statements حصراً؛ لا استعلامات نصية مباشرة |
| **XSS** | ترميز المخرجات (Output Encoding) + Content-Security-Policy + تعقيم المدخلات (خاصة محرّرات النص الغني) |
| **CSRF** | CSRF Tokens + SameSite Cookies + التحقق من Origin/Referer للطلبات المغيّرة |
| **Broken Access Control** | RBAC/ABAC في الخادم، فحص الملكية، منع IDOR (التحقق من عائدية كل معرّف) |
| **Auth Failures** | MFA، قفل الحساب، Rate Limiting على الدخول، Refresh Rotation |
| **Sensitive Data Exposure** | تشفير، Signed URLs، إخفاء الحقول، تقييد السري |
| **Security Misconfiguration** | تعطيل Debug في الإنتاج، رؤوس أمان (HSTS, X-Frame-Options), أقل امتياز |
| **SSRF/Injection عام** | التحقق من كل مدخل (Validation) + Allowlists |
| **Vulnerable Components** | مسح تبعيات دوري (Dependabot/Snyk) وتحديثات أمنية |
| **Insufficient Logging** | Audit شامل + مراقبة (Sentry) + تنبيهات على الأنماط المشبوهة |

### 5.6 حماية الطبقة الشبكية
- **Rate Limiting / Throttling** على الـ API (خاصة الدخول والتصدير).
- **WAF** (Cloudflare/ModSecurity) أمام Nginx.
- رؤوس أمان: `HSTS`, `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `CSP`.
- عزل شبكي لأجهزة البصمة وقاعدة البيانات (لا تُعرَّض للإنترنت مباشرة).

### 5.7 الخصوصية والامتثال
- **سرية العميل-المحامي:** المستندات السرية خلف صلاحية `documents.view_confidential`.
- **الاحتفاظ بالبيانات (Retention):** سياسات أرشفة/حذف حسب القانون.
- **إخفاء الهوية (Masking):** بيئة الاختبار تستخدم بيانات مُقنّعة.
- **مبدأ أقل امتياز (Least Privilege)** و**فصل المهام (SoD)** في تصميم الأدوار.

---

## 6. ملخص طبقات الدفاع (Defense in Depth)

```
[WAF/Cloudflare] → [Nginx: TLS + Rate Limit + Security Headers]
   → [API: JWT + MFA + RBAC Middleware]
      → [Policy: ABAC ownership/branch/confidential]
         → [ORM: Parameterized Queries + Validation]
            → [DB: Encryption at Rest + RLS اختياري + Audit Triggers]
[شامل: Audit Logs · Monitoring/Sentry · Backups · Least Privilege]
```
