# وحدة Core (النواة)

**الملكية (Owns):** users, roles, permissions, sessions, branches, settings, audit_logs.
**تُتيح (Exposes):** AuthService, PermissionService, SettingsService, AuditService.
**ملاحظة:** وحدة عرضية (Cross-cutting) يعتمد عليها الجميع للمصادقة والصلاحيات والتدقيق.

> المرجع: [docs/module-boundaries.md](../../../docs/module-boundaries.md) · [ADR-001](../../../docs/adr/001-modular-monolith.md)

## أساس قاعدة البيانات (Issue #10)
ترحيلات النواة التأسيسية في `database/migrations/` وفق [docs/02](../../../docs/02-database-design.md):
`branches` · `departments` · `roles` · `permissions` · `role_permission` · `user_role` · `settings` · `audit_logs` · `activity_logs` (+ أعمدة الأساس على `users`).
النماذج الأساسية وعلاقاتها في `Models/`. تُحمَّل الترحيلات تلقائياً عبر `ModuleServiceProvider`.

الحالة: النواة جاهزة (Health endpoint + أساس المخطط). منطق المصادقة والصلاحيات يُنفَّذ في Issues #11 و #12.
