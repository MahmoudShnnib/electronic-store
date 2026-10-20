<?php

/*
 * Arabic validation messages for the rules the storefront actually uses.
 * Rules not listed here fall back to Laravel's English defaults, so add a
 * message whenever a new rule becomes user-facing.
 */
return [
    'required' => 'حقل :attribute مطلوب.',
    'email' => 'يجب أن يكون :attribute بريداً إلكترونياً صالحاً.',
    'string' => 'يجب أن يكون :attribute نصاً.',
    'numeric' => 'يجب أن يكون :attribute رقماً.',
    'integer' => 'يجب أن يكون :attribute رقماً صحيحاً.',
    'boolean' => 'قيمة :attribute غير صالحة.',
    'in' => 'القيمة المحددة في :attribute غير صالحة.',
    'enum' => 'القيمة المحددة في :attribute غير صالحة.',
    'date' => 'يجب أن يكون :attribute تاريخاً صالحاً.',
    'max' => [
        'numeric' => 'يجب ألا يتجاوز :attribute القيمة :max.',
        'string' => 'يجب ألا يتجاوز :attribute :max حرفاً.',
    ],
    'min' => [
        'numeric' => 'يجب ألا يقل :attribute عن :min.',
        'string' => 'يجب ألا يقل :attribute عن :min أحرف.',
    ],
    'size' => [
        'string' => 'يجب أن يكون طول :attribute :size أحرف.',
    ],
    'unique' => ':attribute مستخدم من قبل.',
    'exists' => 'القيمة المحددة في :attribute غير موجودة.',
    'regex' => 'صيغة :attribute غير صحيحة.',
    'confirmed' => 'تأكيد :attribute غير مطابق.',
    'current_password' => 'كلمة المرور الحالية غير صحيحة.',
    'password' => [
        'letters' => 'يجب أن تحتوي :attribute على حرف واحد على الأقل.',
        'mixed' => 'يجب أن تحتوي :attribute على حرف كبير وحرف صغير على الأقل.',
        'numbers' => 'يجب أن تحتوي :attribute على رقم واحد على الأقل.',
        'symbols' => 'يجب أن تحتوي :attribute على رمز واحد على الأقل.',
        'uncompromised' => 'ظهرت :attribute في تسريب بيانات. اختر كلمة مرور أخرى.',
    ],
    'phone_ly' => 'أدخل رقم هاتف ليبي صالح، مثل 0912345678 أو ‎+218912345678.',

    'attributes' => [
        'customer.name' => 'الاسم الكامل',
        'customer.phone' => 'رقم الهاتف',
        'customer.email' => 'البريد الإلكتروني',
        'shipping_address.city' => 'المدينة',
        'shipping_address.area' => 'العنوان تحديدا',
        'shipping_address.country' => 'الدولة',
        'payment_method' => 'طريقة الدفع',
        'customer_note' => 'الملاحظة',
        'name' => 'الاسم',
        'phone' => 'رقم الهاتف',
        'email' => 'البريد الإلكتروني',
        'city' => 'المدينة',
        'area' => 'العنوان تحديدا',
        'country' => 'الدولة',
        'label' => 'التسمية',
        'notes' => 'الملاحظات',
        'quantity' => 'الكمية',
        'rating' => 'التقييم',
        'password' => 'كلمة المرور',
        'current_password' => 'كلمة المرور الحالية',
        'token' => 'رابط إعادة التعيين',
    ],
];
