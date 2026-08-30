<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The audit trail of an order's movements. Statuses are kept as plain strings,
 * not cast to OrderStatus: rows written before the pipeline was collapsed hold
 * retired values ("shipped", "delivered"), and casting those would throw. What
 * happened, happened — the history renders it verbatim.
 */
final class OrderStatusHistory extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = ['order_id', 'from_status', 'to_status', 'comment', 'user_id'];

    /** Label for a stored status, falling back to retired ones by key. */
    public static function labelFor(?string $status): ?string
    {
        if ($status === null) {
            return null;
        }

        return OrderStatus::tryFrom($status)?->label() ?? __('enums.order_status.'.$status);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
