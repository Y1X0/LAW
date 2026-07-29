# وحدة Attendance

**الملكية (Owns):** attendance_logs, attendance_records, work_shifts, biometric_devices
**تُتيح (Exposes):** AttendanceService, BiometricAdapter

> المرجع: [docs/module-boundaries.md](../../../docs/module-boundaries.md)

**القاعدة الذهبية:** لا تصل هذه الوحدة إلى جداول وحدة أخرى مباشرةً — التواصل عبر الخدمات/الأحداث فقط.

الحالة: هيكل جاهز (Scaffold). التنفيذ في Issue #15/#16 ضمن MVP Phase 1.
