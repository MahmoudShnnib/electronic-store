<?php

declare(strict_types=1);

/*
 * Arabic labels for backed enums. Keys must mirror lang/en/enums.php exactly —
 * a missing key surfaces to the user as the raw dotted key.
 */
return [
    'coupon_type' => [
        'fixed' => 'مبلغ ثابت',
        'percentage' => 'نسبة مئوية',
    ],

    'item_condition' => [
        'resellable' => 'قابل لإعادة البيع',
        'damaged' => 'تالف',
    ],

    'movement_type' => [
        'initial' => 'رصيد افتتاحي',
        'purchase_receipt' => 'استلام مشتريات',
        'customer_order' => 'طلب عميل',
        'order_cancellation' => 'إلغاء طلب',
        'customer_return' => 'مرتجع عميل',
        'supplier_return' => 'مرتجع إلى مورّد',
        'manual_increase' => 'زيادة يدوية',
        'manual_decrease' => 'خصم يدوي',
        'damaged' => 'تالف',
        'transfer_out' => 'تحويل صادر',
        'transfer_in' => 'تحويل وارد',
        'correction' => 'تصحيح',
        'purchase_incoming' => 'إرسال أمر شراء — حجز وارد',
        'purchase_cancelled' => 'إلغاء أمر شراء — تحرير وارد',
    ],

    'order_status' => [
        'pending' => 'قيد الانتظار',
        'confirmed' => 'مؤكد',
        'processing' => 'قيد التجهيز',
        'ready_for_shipping' => 'جاهز للشحن',
        'shipped' => 'تم الشحن',
        'delivered' => 'تم التسليم',
        'completed' => 'مكتمل',
        'cancelled' => 'ملغى',
        'returned' => 'مُرتجع',
        'refunded' => 'مسترد',
    ],

    'payment_method' => [
        'cod' => 'الدفع عند الاستلام',
        'bank_transfer' => 'تحويل بنكي',
    ],

    'payment_status' => [
        'unpaid' => 'غير مدفوع',
        'pending' => 'قيد الانتظار',
        'partially_paid' => 'مدفوع جزئيًا',
        'paid' => 'مدفوع',
        'failed' => 'فشل',
        'partially_refunded' => 'مسترد جزئيًا',
        'refunded' => 'مسترد',
    ],

    'price_type' => [
        'regular' => 'عادي',
        'sale' => 'عرض',
        'cost' => 'تكلفة',
    ],

    'product_status' => [
        'draft' => 'مسودة',
        'active' => 'نشط',
        'inactive' => 'غير نشط',
        'out_of_stock' => 'نفد من المخزون',
        'discontinued' => 'موقوف',
    ],

    'purchase_order_status' => [
        'draft' => 'مسودة',
        'sent' => 'مُرسل',
        'partially_received' => 'مستلم جزئيًا',
        'received' => 'مستلم',
        'cancelled' => 'ملغى',
    ],

    'refund_method' => [
        'cash' => 'نقدًا',
        'bank_transfer' => 'تحويل بنكي',
        'store_credit' => 'رصيد المتجر',
        'original' => 'طريقة الدفع الأصلية',
    ],

    'return_status' => [
        'requested' => 'مطلوب',
        'under_review' => 'قيد المراجعة',
        'approved' => 'معتمد',
        'rejected' => 'مرفوض',
        'item_received' => 'تم استلام الصنف',
        'refunded' => 'مسترد',
        'closed' => 'مغلق',
    ],

    'review_status' => [
        'pending' => 'قيد المراجعة',
        'approved' => 'معتمد',
        'rejected' => 'مرفوض',
    ],

    'stock_count_status' => [
        'draft' => 'مسودة',
        'in_progress' => 'قيد التنفيذ',
        'completed' => 'مكتمل',
        'cancelled' => 'ملغى',
    ],

    'transfer_status' => [
        'pending' => 'قيد الانتظار',
        'in_transit' => 'قيد النقل',
        'completed' => 'مكتمل',
        'cancelled' => 'ملغى',
    ],
];
