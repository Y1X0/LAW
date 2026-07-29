# وحدة Core (النواة)

**الملكية (Owns):** users, roles, permissions, sessions, branches, settings, audit_logs.
**تُتيح (Exposes):** AuthService, PermissionService, SettingsService, AuditService.
**ملاحظة:** وحدة عرضية (Cross-cutting) يعتمد عليها الجميع للمصادقة والصلاحيات والتدقيق.

> المرجع: [docs/module-boundaries.md](../../../docs/module-boundaries.md) · [ADR-001](../../../docs/adr/001-modular-monolith.md)

## أساس قاعدة البيانات (Issue #10)
ترحيلات النواة التأسيسية في `database/migrations/` وفق [docs/02](../../../docs/02-database-design.md):
`branches` · `departments` · `roles` · `permissions` · `role_permission` · `user_role` · `settings` · `audit_logs` · `activity_logs` (+ أعمدة الأساس على `users`).
النماذج الأساسية وعلاقاتها في `Models/`. تُحمَّل الترحيلات تلقائياً عبر `ModuleServiceProvider`.

## المصادقة (Issue #11)
- `Services/AuthService` · `Http/Middleware/AuthenticateToken` (alias: `auth.token`) · `Http/Controllers/Auth/*` · `Models/AuthToken`.
- نقاط النهاية (تحت `/api/auth`): `login` · `refresh` (تدوير) · `logout` · `me` · `forgot-password` · `reset-password`.
- توكن Access (15د) + Refresh (14ي) مخزّنان **مجزّأين** (SHA-256) · قفل الحساب بعد 5 محاولات · أحداث Audit للمصادقة · إبطال الجلسات عند تغيير كلمة المرور.

الحالة: النواة جاهزة (Health + أساس المخطط + **المصادقة**). صلاحيات RBAC الكاملة تُنفَّذ في Issue #12.
