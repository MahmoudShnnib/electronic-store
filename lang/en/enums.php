<?php

declare(strict_types=1);

/*
 * Human labels for backed enums. Keyed by snake_case enum name, then case value.
 * Consumed by each enum's label() method, so these strings reach Inertia props,
 * filter dropdowns, invoices and mail alike.
 */
return [
    'coupon_type' => [
        'fixed' => 'Fixed',
        'percentage' => 'Percentage',
        'free_shipping' => 'Free shipping',
    ],

    'item_condition' => [
        'resellable' => 'Resellable',
        'damaged' => 'Damaged',
    ],

    'movement_type' => [
        'initial' => 'Initial',
        'purchase_receipt' => 'Purchase receipt',
        'customer_order' => 'Customer order',
        'order_cancellation' => 'Order cancellation',
        'customer_return' => 'Customer return',
        'supplier_return' => 'Supplier return',
        'manual_increase' => 'Manual increase',
        'manual_decrease' => 'Manual decrease',
        'damaged' => 'Damaged',
        'transfer_out' => 'Transfer out',
        'transfer_in' => 'Transfer in',
        'correction' => 'Correction',
    ],

    'order_status' => [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'processing' => 'Processing',
        'ready_for_shipping' => 'Ready for shipping',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'returned' => 'Returned',
        'refunded' => 'Refunded',
    ],

    'payment_method' => [
        'cod' => 'Cash on delivery',
        'bank_transfer' => 'Bank transfer',
    ],

    'payment_status' => [
        'unpaid' => 'Unpaid',
        'pending' => 'Pending',
        'partially_paid' => 'Partially paid',
        'paid' => 'Paid',
        'failed' => 'Failed',
        'partially_refunded' => 'Partially refunded',
        'refunded' => 'Refunded',
    ],

    'price_type' => [
        'regular' => 'Regular',
        'sale' => 'Sale',
        'cost' => 'Cost',
    ],

    'product_status' => [
        'draft' => 'Draft',
        'active' => 'Active',
        'inactive' => 'Inactive',
        'out_of_stock' => 'Out of stock',
        'discontinued' => 'Discontinued',
    ],

    'purchase_order_status' => [
        'draft' => 'Draft',
        'sent' => 'Sent',
        'partially_received' => 'Partially received',
        'received' => 'Received',
        'cancelled' => 'Cancelled',
    ],

    'refund_method' => [
        'cash' => 'Cash',
        'bank_transfer' => 'Bank transfer',
        'store_credit' => 'Store credit',
        'original' => 'Original method',
    ],

    'return_status' => [
        'requested' => 'Requested',
        'under_review' => 'Under review',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'item_received' => 'Item received',
        'refunded' => 'Refunded',
        'closed' => 'Closed',
    ],

    'review_status' => [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ],

    'stock_count_status' => [
        'draft' => 'Draft',
        'in_progress' => 'In progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ],

    'transfer_status' => [
        'pending' => 'Pending',
        'in_transit' => 'In transit',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ],
];
