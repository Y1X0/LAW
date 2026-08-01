<?php

/*
| إعداد CORS. الافتراض '*' (لا تغيير للسلوك القائم — المصادقة عبر توكن Bearer في
| الترويسة، لا كوكيز، فالنجمة آمنة). في الإنتاج يُفضَّل حصر الأصل بضبط FRONTEND_URL
| ليقبل نطاق الواجهة فقط. تصلّب اختياري — إضافي بالكامل.
*/

$frontend = array_filter([env('FRONTEND_URL')]);

return [
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    // أصل الواجهة إن ضُبط، وإلّا '*' (السلوك الافتراضي القائم).
    'allowed_origins' => $frontend ?: ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // مصادقة توكن (لا كوكيز) → لا حاجة لاعتماد بيانات الاعتماد؛ يبقي '*' صالحاً.
    'supports_credentials' => false,
];
