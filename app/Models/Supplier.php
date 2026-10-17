<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

final class Supplier extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'company_name', 'contact_person', 'email', 'phone', 'address',
        'tax_number', 'status', 'notes',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /** The supplier's account: goods received, payments, hand-written debts and credits. */
    public function transactions(): HasMany
    {
        return $this->hasMany(SupplierTransaction::class);
    }

    /** What the shop owes this supplier now; negative means the supplier owes the shop. */
    public function balanceCents(): int
    {
        return (int) $this->transactions()->sum('amount_cents');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['company_name', 'status'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
