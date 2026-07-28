# نظام إدارة مكتب المحاماة المتكامل — LawFirm ERP/HR

> مستند تحليل وتصميم نظام (System Analysis & Design Document — SAD)
> نظام ويب واحد لإدارة مكتب محاماة بالكامل: الموارد البشرية، المالية، القضايا، العملاء، العقود، التسويق، المهام، والأرشفة الإلكترونية.

**الإصدار:** 1.0
**التاريخ:** 2026-07-28
**الحالة:** جاهز للتنفيذ من قبل فريق تطوير كامل
**نوع المشروع:** ERP / HRMS / Legal Practice Management (Web Application)

---

## نظرة عامة سريعة

نظام ويب احترافي وحديث مبني على معمارية متعددة الطبقات (Multi-tier)، يخدم حالياً **10 موظفين** ويتوسع أفقياً وعمودياً إلى **أي عدد** من الموظفين والفروع دون إعادة تصميم. النظام مبني على مبدأ **Multi-tenant-ready** (جاهزية تعدد المكاتب/الفروع) ونموذج صلاحيات **RBAC + ABAC** دقيق على مستوى الحقل.

هذا المستند لا يحتوي على شيفرة برمجية — بل تحليل وظيفي وتقني كامل: قاعدة البيانات، العلاقات، الصلاحيات، الشاشات، العمليات، والتقارير.

---

## فهرس المستند

| # | الملف | المحتوى |
|---|-------|---------|
| 00 | [الملخص التنفيذي](docs/00-executive-summary.md) | الرؤية، الأهداف، النطاق، الوحدات، معايير النجاح، المخاطر |
| 01 | [المعمارية والتقنيات](docs/01-architecture-and-stack.md) | البنية التقنية، الـ Stack المقترح، الطبقات، النشر، البيئات |
| 02 | [تصميم قاعدة البيانات](docs/02-database-design.md) | جميع الجداول، العلاقات، المفاتيح، الفهارس، ER Diagram، وصف كل جدول |
| 03 | [الوحدات الوظيفية](docs/03-functional-modules.md) | HR، القضايا، العملاء، العقود، المالية، التسويق، المهام بالتفصيل |
| 04 | [الحضور والربط مع أجهزة البصمة](docs/04-attendance-biometrics.md) | ZKTeco / Hikvision / Suprema / Anviz — API/SDK، المزامنة، التخزين |
| 05 | [الصلاحيات والأمان](docs/05-roles-permissions-security.md) | RBAC/ABAC، الأدوار، MFA، Audit Logs، الحماية OWASP |
| 06 | [شاشات وواجهات النظام](docs/06-screens-ui.md) | كل شاشة: الهدف، الحقول، الأزرار، العمليات، صلاحيات الوصول |
| 07 | [لوحة التحكم والتقارير](docs/07-dashboard-and-reports.md) | Dashboard، KPIs، جميع التقارير، التصدير PDF/Excel/Print |
| 08 | [الإشعارات والأتمتة](docs/08-notifications-automation.md) | محرك الإشعارات، القنوات، القواعد، المهام المجدولة |
| 09 | [واجهة برمجة التطبيقات وخارطة الطريق](docs/09-api-and-roadmap.md) | تصميم REST/WebSocket API، المراحل، التقديرات، التحسينات |

---

## الوحدات الرئيسية (Modules)

```
┌─────────────────────────────────────────────────────────────────┐
│                     LawFirm ERP/HR Platform                      │
├───────────┬───────────┬───────────┬───────────┬─────────────────┤
│    HR      │  القضايا   │  العملاء   │  المالية   │    التسويق      │
│  الموارد   │   Cases    │  Clients   │  Finance   │  Marketing/CRM  │
│  البشرية   │            │  العقود    │            │                 │
├───────────┼───────────┼───────────┼───────────┼─────────────────┤
│  الحضور    │  المهام    │ الإشعارات  │  التقارير  │   الأرشفة        │
│والانصراف   │  Tasks     │Notifications│  Reports  │  الإلكترونية     │
├───────────┴───────────┴───────────┴───────────┴─────────────────┤
│         الإدارة والإعدادات · الصلاحيات · الأمان · Audit           │
└─────────────────────────────────────────────────────────────────┘
```

---

## الـ Stack التقني المعتمد (ملخص)

| الطبقة | التقنية المعتمدة | البديل |
|--------|------------------|--------|
| Frontend | **React 18 + TypeScript + Vite** | Flutter Web |
| UI Kit | Ant Design / MUI + TailwindCSS | — |
| Backend | **Laravel 11 (PHP 8.3)** | ASP.NET Core 8 / NestJS |
| Database | **PostgreSQL 16** | MySQL 8 |
| Cache/Queue | **Redis 7** | — |
| Search | Meilisearch / OpenSearch | — |
| Storage | **S3-Compatible (MinIO)** | AWS S3 |
| Auth | **JWT + Refresh Token + MFA (TOTP)** | — |
| Realtime | **WebSocket (Laravel Reverb / Socket.io)** | Pusher |
| Reports | Puppeteer/wkhtmltopdf (PDF) + Maatwebsite (Excel) | — |
| Deployment | **Docker + Nginx + Linux (Ubuntu LTS)** | Kubernetes |

> التفاصيل الكاملة والمبررات في [ملف المعمارية](docs/01-architecture-and-stack.md).

---

## كيفية قراءة هذا المستند

- **الإدارة والمالك:** ابدأ بـ [الملخص التنفيذي](docs/00-executive-summary.md).
- **فريق الـ Backend / DBA:** [قاعدة البيانات](docs/02-database-design.md) + [API](docs/09-api-and-roadmap.md).
- **فريق الـ Frontend / UX:** [الشاشات](docs/06-screens-ui.md) + [لوحة التحكم](docs/07-dashboard-and-reports.md).
- **DevOps / الأمان:** [المعمارية](docs/01-architecture-and-stack.md) + [الأمان](docs/05-roles-permissions-security.md).
- **فريق التكامل (Integrations):** [أجهزة البصمة](docs/04-attendance-biometrics.md).
