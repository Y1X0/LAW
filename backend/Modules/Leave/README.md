# وحدة Leave

**الملكية (Owns):** leave_types, leave_balances, leave_requests
**تُتيح (Exposes):** LeaveService

> المرجع: [docs/module-boundaries.md](../../../docs/module-boundaries.md)

**القاعدة الذهبية:** لا تصل هذه الوحدة إلى جداول وحدة أخرى مباشرةً — التواصل عبر الخدمات/الأحداث فقط.

الحالة: هيكل جاهز (Scaffold). التنفيذ في Issue #17 ضمن MVP Phase 1.
