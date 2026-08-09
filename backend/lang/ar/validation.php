<?php

/*
|--------------------------------------------------------------------------
| رسائل التحقّق (Validation) بالعربية
|--------------------------------------------------------------------------
| ترجمة رسائل Laravel للتحقّق إلى العربية، لتظهر للمستخدم بسبب واضح على
| مستوى الحقل بدل الرسائل الإنجليزية الافتراضية. لا تغيّر أي قاعدة تحقّق —
| شكل الرسائل فقط. أسماء الحقول العربية في مصفوفة attributes بالأسفل.
*/

return [
    'accepted' => 'يجب قبول :attribute.',
    'accepted_if' => 'يجب قبول :attribute عندما تكون :other هي :value.',
    'active_url' => ':attribute ليس رابطاً صحيحاً.',
    'after' => 'يجب أن يكون :attribute تاريخاً بعد :date.',
    'after_or_equal' => 'يجب أن يكون :attribute تاريخاً بعد أو يساوي :date.',
    'alpha' => 'يجب أن يحتوي :attribute على حروف فقط.',
    'alpha_dash' => 'يجب أن يحتوي :attribute على حروف وأرقام وشرطات فقط.',
    'alpha_num' => 'يجب أن يحتوي :attribute على حروف وأرقام فقط.',
    'array' => 'يجب أن يكون :attribute مصفوفة.',
    'ascii' => 'يجب أن يحتوي :attribute على حروف وأرقام ورموز أحادية البايت فقط.',
    'before' => 'يجب أن يكون :attribute تاريخاً قبل :date.',
    'before_or_equal' => 'يجب أن يكون :attribute تاريخاً قبل أو يساوي :date.',
    'between' => [
        'array' => 'يجب أن يحتوي :attribute بين :min و :max عنصراً.',
        'file' => 'يجب أن يكون :attribute بين :min و :max كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة :attribute بين :min و :max.',
        'string' => 'يجب أن يكون :attribute بين :min و :max حرفاً.',
    ],
    'boolean' => 'يجب أن تكون قيمة :attribute إمّا صحيحة أو خاطئة.',
    'can' => ':attribute يحتوي على قيمة غير مصرّح بها.',
    'confirmed' => 'حقل تأكيد :attribute غير متطابق.',
    'contains' => ':attribute يفتقد إلى قيمة مطلوبة.',
    'current_password' => 'كلمة المرور غير صحيحة.',
    'date' => ':attribute ليس تاريخاً صحيحاً.',
    'date_equals' => 'يجب أن يكون :attribute تاريخاً مساوياً لـ :date.',
    'date_format' => 'لا يتوافق :attribute مع النمط :format.',
    'decimal' => 'يجب أن يحتوي :attribute على :decimal منزلة عشرية.',
    'declined' => 'يجب رفض :attribute.',
    'declined_if' => 'يجب رفض :attribute عندما تكون :other هي :value.',
    'different' => 'يجب أن يكون :attribute و :other مختلفين.',
    'digits' => 'يجب أن يتكوّن :attribute من :digits رقماً.',
    'digits_between' => 'يجب أن يكون :attribute بين :min و :max رقماً.',
    'dimensions' => 'أبعاد صورة :attribute غير صحيحة.',
    'distinct' => 'قيمة :attribute مكرّرة.',
    'doesnt_end_with' => 'يجب ألا ينتهي :attribute بأحد التالي: :values.',
    'doesnt_start_with' => 'يجب ألا يبدأ :attribute بأحد التالي: :values.',
    'email' => 'يجب أن يكون :attribute بريداً إلكترونياً صحيحاً.',
    'ends_with' => 'يجب أن ينتهي :attribute بأحد التالي: :values.',
    'enum' => 'القيمة المحدّدة لـ :attribute غير صالحة.',
    'exists' => 'القيمة المحدّدة لـ :attribute غير موجودة.',
    'extensions' => 'يجب أن يكون امتداد ملف :attribute أحد التالي: :values.',
    'file' => 'يجب أن يكون :attribute ملفاً.',
    'filled' => 'يجب تعبئة حقل :attribute.',
    'gt' => [
        'array' => 'يجب أن يحتوي :attribute على أكثر من :value عنصراً.',
        'file' => 'يجب أن يكون :attribute أكبر من :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة :attribute أكبر من :value.',
        'string' => 'يجب أن يكون :attribute أطول من :value حرفاً.',
    ],
    'gte' => [
        'array' => 'يجب أن يحتوي :attribute على :value عنصراً أو أكثر.',
        'file' => 'يجب أن يكون :attribute أكبر من أو يساوي :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة :attribute أكبر من أو تساوي :value.',
        'string' => 'يجب أن يكون :attribute أطول من أو يساوي :value حرفاً.',
    ],
    'hex_color' => 'يجب أن يكون :attribute لوناً سداسياً عشرياً صحيحاً.',
    'image' => 'يجب أن يكون :attribute صورة.',
    'in' => 'القيمة المحدّدة لـ :attribute غير صالحة.',
    'in_array' => 'قيمة :attribute غير موجودة في :other.',
    'integer' => 'يجب أن يكون :attribute عدداً صحيحاً.',
    'ip' => 'يجب أن يكون :attribute عنوان IP صحيحاً.',
    'ipv4' => 'يجب أن يكون :attribute عنوان IPv4 صحيحاً.',
    'ipv6' => 'يجب أن يكون :attribute عنوان IPv6 صحيحاً.',
    'json' => 'يجب أن يكون :attribute نصّ JSON صحيحاً.',
    'lowercase' => 'يجب أن يكون :attribute بحروف صغيرة.',
    'lt' => [
        'array' => 'يجب أن يحتوي :attribute على أقل من :value عنصراً.',
        'file' => 'يجب أن يكون :attribute أصغر من :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة :attribute أصغر من :value.',
        'string' => 'يجب أن يكون :attribute أقصر من :value حرفاً.',
    ],
    'lte' => [
        'array' => 'يجب ألا يحتوي :attribute على أكثر من :value عنصراً.',
        'file' => 'يجب أن يكون :attribute أصغر من أو يساوي :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة :attribute أصغر من أو تساوي :value.',
        'string' => 'يجب أن يكون :attribute أقصر من أو يساوي :value حرفاً.',
    ],
    'mac_address' => 'يجب أن يكون :attribute عنوان MAC صحيحاً.',
    'max' => [
        'array' => 'يجب ألا يحتوي :attribute على أكثر من :max عنصراً.',
        'file' => 'يجب ألا يكون :attribute أكبر من :max كيلوبايت.',
        'numeric' => 'يجب ألا تكون قيمة :attribute أكبر من :max.',
        'string' => 'يجب ألا يكون :attribute أطول من :max حرفاً.',
    ],
    'max_digits' => 'يجب ألا يحتوي :attribute على أكثر من :max رقماً.',
    'mimes' => 'يجب أن يكون :attribute ملفاً من نوع: :values.',
    'mimetypes' => 'يجب أن يكون :attribute ملفاً من نوع: :values.',
    'min' => [
        'array' => 'يجب أن يحتوي :attribute على :min عنصراً على الأقل.',
        'file' => 'يجب أن يكون :attribute :min كيلوبايت على الأقل.',
        'numeric' => 'يجب ألا تقلّ قيمة :attribute عن :min.',
        'string' => 'يجب أن يكون :attribute :min أحرف على الأقل.',
    ],
    'min_digits' => 'يجب أن يحتوي :attribute على :min رقماً على الأقل.',
    'missing' => 'يجب أن يكون حقل :attribute غير موجود.',
    'missing_if' => 'يجب أن يكون حقل :attribute غير موجود عندما تكون :other هي :value.',
    'missing_unless' => 'يجب أن يكون حقل :attribute غير موجود ما لم تكن :other هي :value.',
    'missing_with' => 'يجب أن يكون حقل :attribute غير موجود عند وجود :values.',
    'missing_with_all' => 'يجب أن يكون حقل :attribute غير موجود عند وجود :values.',
    'multiple_of' => 'يجب أن تكون قيمة :attribute من مضاعفات :value.',
    'not_in' => 'القيمة المحدّدة لـ :attribute غير صالحة.',
    'not_regex' => 'صيغة :attribute غير صحيحة.',
    'numeric' => 'يجب أن يكون :attribute رقماً.',
    'password' => [
        'letters' => 'يجب أن تحتوي :attribute على حرف واحد على الأقل.',
        'mixed' => 'يجب أن تحتوي :attribute على حرف كبير وحرف صغير على الأقل.',
        'numbers' => 'يجب أن تحتوي :attribute على رقم واحد على الأقل.',
        'symbols' => 'يجب أن تحتوي :attribute على رمز واحد على الأقل.',
        'uncompromised' => 'ظهرت :attribute في تسريب بيانات. يرجى اختيار قيمة مختلفة.',
    ],
    'present' => 'يجب أن يكون حقل :attribute موجوداً.',
    'present_if' => 'يجب أن يكون حقل :attribute موجوداً عندما تكون :other هي :value.',
    'present_unless' => 'يجب أن يكون حقل :attribute موجوداً ما لم تكن :other هي :value.',
    'present_with' => 'يجب أن يكون حقل :attribute موجوداً عند وجود :values.',
    'present_with_all' => 'يجب أن يكون حقل :attribute موجوداً عند وجود :values.',
    'prohibited' => 'حقل :attribute محظور.',
    'prohibited_if' => 'حقل :attribute محظور عندما تكون :other هي :value.',
    'prohibited_unless' => 'حقل :attribute محظور ما لم تكن :other ضمن :values.',
    'prohibits' => 'حقل :attribute يمنع وجود :other.',
    'regex' => 'صيغة :attribute غير صحيحة.',
    'required' => 'حقل :attribute مطلوب.',
    'required_array_keys' => 'يجب أن يحتوي حقل :attribute على مدخلات لـ: :values.',
    'required_if' => 'حقل :attribute مطلوب عندما تكون :other هي :value.',
    'required_if_accepted' => 'حقل :attribute مطلوب عند قبول :other.',
    'required_if_declined' => 'حقل :attribute مطلوب عند رفض :other.',
    'required_unless' => 'حقل :attribute مطلوب ما لم تكن :other ضمن :values.',
    'required_with' => 'حقل :attribute مطلوب عند وجود :values.',
    'required_with_all' => 'حقل :attribute مطلوب عند وجود :values.',
    'required_without' => 'حقل :attribute مطلوب عند عدم وجود :values.',
    'required_without_all' => 'حقل :attribute مطلوب عند عدم وجود أيّ من :values.',
    'same' => 'يجب أن يتطابق :attribute مع :other.',
    'size' => [
        'array' => 'يجب أن يحتوي :attribute على :size عنصراً.',
        'file' => 'يجب أن يكون :attribute :size كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة :attribute :size.',
        'string' => 'يجب أن يكون :attribute :size حرفاً.',
    ],
    'starts_with' => 'يجب أن يبدأ :attribute بأحد التالي: :values.',
    'string' => 'يجب أن يكون :attribute نصّاً.',
    'timezone' => 'يجب أن يكون :attribute منطقة زمنية صحيحة.',
    'unique' => 'قيمة :attribute مستخدمة مسبقاً.',
    'uploaded' => 'فشل رفع :attribute.',
    'uppercase' => 'يجب أن يكون :attribute بحروف كبيرة.',
    'url' => 'يجب أن يكون :attribute رابطاً صحيحاً.',
    'ulid' => 'يجب أن يكون :attribute ULID صحيحاً.',
    'uuid' => 'يجب أن يكون :attribute UUID صحيحاً.',

    /*
    |--------------------------------------------------------------------------
    | رسائل تحقّق مخصّصة (Custom)
    |--------------------------------------------------------------------------
    */
    'custom' => [
        'attribute-name' => [
            'rule-name' => 'رسالة مخصّصة',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | أسماء الحقول العربية (Attributes)
    |--------------------------------------------------------------------------
    | تُستبدل بها :attribute في الرسائل أعلاه، فتظهر بأسماء عربية مفهومة بدل
    | أسماء الأعمدة التقنية (email, client_id …).
    */
    'attributes' => [
        // مصادقة ومستخدمون
        'email' => 'البريد الإلكتروني',
        'password' => 'كلمة المرور',
        'password_confirmation' => 'تأكيد كلمة المرور',
        'current_password' => 'كلمة المرور الحالية',
        'username' => 'اسم المستخدم',
        'name' => 'الاسم',
        'token' => 'رمز التحقّق',
        'refresh_token' => 'رمز التجديد',
        'role_ids' => 'الأدوار',
        'role_ids.*' => 'الدور',
        'status' => 'الحالة',

        // موظفون / موارد بشرية
        'employee_id' => 'الموظف',
        'employee_no' => 'الرقم الوظيفي',
        'national_id' => 'الرقم الوطني',
        'department_id' => 'القسم',
        'position_id' => 'المسمّى الوظيفي',
        'hire_date' => 'تاريخ التعيين',
        'phone' => 'رقم الهاتف',
        'mobile' => 'رقم الجوّال',
        'address' => 'العنوان',
        'salary' => 'الراتب',
        'basic_salary' => 'الراتب الأساسي',

        // قضايا / قانوني
        'internal_number' => 'الرقم الداخلي',
        'case_id' => 'القضية',
        'client_id' => 'العميل',
        'responsible_lawyer_id' => 'المحامي المسؤول',
        'assigned_to' => 'المُسنَد إليه',
        'court_name' => 'المحكمة',
        'case_type' => 'نوع القضية',
        'title' => 'العنوان',
        'description' => 'الوصف',
        'notes' => 'الملاحظات',
        'due_date' => 'تاريخ الاستحقاق',
        'session_date' => 'تاريخ الجلسة',
        'priority' => 'الأولوية',

        // مالية
        'amount' => 'المبلغ',
        'invoice_id' => 'الفاتورة',
        'payment_method' => 'طريقة الدفع',
        'account_id' => 'الحساب',
        'debit' => 'المدين',
        'credit' => 'الدائن',
        'currency' => 'العملة',
        'date' => 'التاريخ',
        'reference' => 'المرجع',

        // عملاء
        'company_name' => 'اسم الشركة',
        'contact_person' => 'الشخص المسؤول',
        'client_type' => 'نوع العميل',
    ],
];
