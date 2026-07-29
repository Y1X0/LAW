# وحدة Core (النواة)

**الملكية (Owns):** users, roles, permissions, sessions, branches, settings, audit_logs.
**تُتيح (Exposes):** AuthService, PermissionService, SettingsService, AuditService.
**ملاحظة:** وحدة عرضية (Cross-cutting) يعتمد عليها الجميع للمصادقة والصلاحيات والتدقيق.

> المرجع: [docs/module-boundaries.md](../../../docs/module-boundaries.md) · [ADR-001](../../../docs/adr/001-modular-monolith.md)

الحالة: النواة جاهزة (Health endpoint). المصادقة والصلاحيات تُنفَّذ في Issues #11 و #12.
