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
        'lost' => 'Lost / stolen',
        'transfer_out' => 'Transfer out',
        'transfer_in' => 'Transfer in',
        'correction' => 'Correction',
        'variant_split' => 'Moved to variants',
        'purchase_incoming' => 'PO sent — incoming booked',
        'purchase_cancelled' => 'PO cancelled — incoming released',
    ],

    'order_status' => [
        'in_progress' => 'In progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'refunded' => 'Refunded',

        // Retired states — kept so old timeline entries still read properly.
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'processing' => 'Processing',
        'ready_for_shipping' => 'Ready for shipping',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
        'returned' => 'Returned',
    ],

    'payment_method' => [
        'cod' => 'Cash on delivery',
        'bank_transfer' => 'Bank transfer',
        'cash' => 'Cash',
    ],

    'cashier_shift_status' => [
        'open' => 'Open',
        'closed' => 'Closed',
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

    'supplier_transaction_type' => [
        'purchase' => 'Goods received',
        'payment' => 'Payment',
        'adjustment' => 'Adjustment',
    ],

    'treasury_account' => [
        'cash' => 'Cash',
        'bank' => 'Bank',
    ],

    'treasury_direction' => [
        'in' => 'In',
        'out' => 'Out',
    ],

    'refund_method' => [
        'cash' => 'Cash',
        'bank_transfer' => 'Bank transfer',
        'store_credit' => 'Store credit',
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
