# وحدة Dashboard

**الملكية (Owns):** لا تملك جداول (تقرأ عبر خدمات الوحدات)
**تُتيح (Exposes):** DashboardService (aggregation)

> المرجع: [docs/module-boundaries.md](../../../docs/module-boundaries.md)

**القاعدة الذهبية:** لا تصل هذه الوحدة إلى جداول وحدة أخرى مباشرةً — التواصل عبر الخدمات/الأحداث فقط.

الحالة: هيكل جاهز (Scaffold). التنفيذ في Issue #18 ضمن MVP Phase 1.
