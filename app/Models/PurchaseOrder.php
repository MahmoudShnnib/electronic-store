<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

final class PurchaseOrder extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'po_number', 'supplier_id', 'warehouse_id', 'status', 'subtotal_cents',
        'discount_cents', 'shipping_cents', 'total_cents',
        'expected_at', 'received_at', 'sent_at', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'subtotal_cents' => 'integer',
            'discount_cents' => 'integer',
            'shipping_cents' => 'integer',
            'total_cents' => 'integer',
            'expected_at' => 'immutable_date',
            'received_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['po_number', 'status', 'total_cents'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
