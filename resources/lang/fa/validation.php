<?php

return [
    'required' => ':attribute الزامی است.',
    'unique' => ':attribute باید منحصر به فرد باشد.',
    'numeric' => ':attribute باید عدد باشد.',
    'min' => [
        'numeric' => ':attribute باید حداقل :min باشد.',
        'string' => ':attribute باید حداقل :min کاراکتر باشد.',
    ],
    'max' => [
        'numeric' => ':attribute نباید بیشتر از :max باشد.',
        'string' => ':attribute نباید بیشتر از :max کاراکتر باشد.',
    ],
    'email' => 'فرمت :attribute نامعتبر است.',
    'confirmed' => 'تاییدیه :attribute مطابقت ندارد.',
    'in' => ':attribute انتخاب شده نامعتبر است.',
    'file' => ':attribute باید یک فایل باشد.',
    'mimes' => ':attribute باید یک فایل از نوع: :values باشد.',
    'exists' => ':attribute انتخاب شده نامعتبر است.',
    'custom' => [
        'name' => [
            'required' => 'نام الزامی است.',
        ],
        'username' => [
            'required' => 'نام کاربری الزامی است.',
        ],
        'mobile' => [
            'required' => 'شماره موبایل الزامی است.',
        ],
        'password' => [
            'required' => 'رمز عبور الزامی است.',
        ],
        'user_id' => [
            'required' => 'کاربر الزامی است.',
        ],
        'product_part_id' => [
            'required' => 'زیر محصول الزامی است.',
        ],
        'fabric_id' => [
            'required' => 'پارچه الزامی است.',
        ],
        'color_id' => [
            'required' => 'رنگ الزامی است.',
        ],
        'production_date' => [
            'required' => 'تاریخ تولید الزامی است.',
            'before_or_equal' => 'تاریخ تولید نمی تواند برای آینده باشد.',
        ],
        'bunch_count' => [
            'required' => 'تعداد الزامی است.',
        ]
    ],
    'attributes' => [
        'firstname' => 'نام',
        'username' => 'نام کاربری',
        'mobile' => 'شماره موبایل',
        'color_id' => 'رنگ',
        'description' => 'توضیحات',
        'production_date' => 'تاریخ تولید',
        'image' => 'تصویر',
        'images' => 'تصاویر',
        'user_id' => 'کاربر',
        'fabric_id' => 'پارچه',
        'bunch_count' => 'تعداد دسته',
        'product_part_id' => 'زیرمحصول',
        'items' => 'موارد حواله',
        'transfer_date' => 'تاریخ حواله',
        'to_user_id' => 'کاربر مقصد',
        'from_user_id' => 'کاربر مبدأ',
        'from_inventory_type' => 'نوع انبار مبدأ',
        'to_inventory_type' => 'نوع انبار مقصد',
    ],
];
